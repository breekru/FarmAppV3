// js/sync.js — FarmApp sync manager
// Depends on: js/db.js (must load first)
//
// Responsibilities:
//   1. Acquire a JWT by exchanging the PHP session cookie (no credentials in JS)
//   2. Silently refresh the token before it expires
//   3. Provide an authenticated fetch helper for all API calls
//   4. Pull fresh animal data from the server into IndexedDB
//   5. Flush the offline sync queue to api/sync.php when back online

window.farmSync = (() => {

  // ─── Token management ──────────────────────────────────────────────────────

  /**
   * Return a valid JWT, refreshing if it expires within 24 h.
   * Returns null if no token is available (user not logged in / offline).
   */
  async function getToken() {
    const [token, exp] = await Promise.all([
      FarmDB.auth.get('jwt_token'),
      FarmDB.auth.get('jwt_exp'),
    ]);

    if (!token) return null;

    const now = Math.floor(Date.now() / 1000);

    // Refresh if expiring within 24 hours (and we're online)
    if (exp && (exp - now) < 86400 && navigator.onLine) {
      return _refreshToken(token);
    }

    return token;
  }

  /**
   * Exchange the current PHP session cookie for a JWT.
   * Safe to call on every page load — fails silently if not logged in.
   */
  async function fetchToken() {
    try {
      const resp = await fetch('/api/session_token.php', {
        credentials: 'include',  // sends the FARMSESSID cookie
      });
      if (!resp.ok) return null;

      const json = await resp.json();
      if (json.success && json.data?.token) {
        await _storeToken(json.data.token);
        return json.data.token;
      }
    } catch (e) {
      // Network error or not logged in — not an error worth surfacing
    }
    return null;
  }

  /** Renew a token that is close to expiry. */
  async function _refreshToken(oldToken) {
    try {
      const resp = await fetch('/api/auth.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ refresh_token: oldToken }),
      });
      if (!resp.ok) return oldToken;

      const json = await resp.json();
      if (json.success && json.data?.token) {
        await _storeToken(json.data.token);
        return json.data.token;
      }
    } catch (e) {
      // Keep old token on network failure
    }
    return oldToken;
  }

  /** Decode the JWT payload and persist token + expiry to IndexedDB. */
  async function _storeToken(token) {
    try {
      const parts   = token.split('.');
      const payload = JSON.parse(atob(
        parts[1].replace(/-/g, '+').replace(/_/g, '/')
      ));
      await FarmDB.auth.set('jwt_token', token);
      await FarmDB.auth.set('jwt_exp',   payload.exp);
      await FarmDB.auth.set('user_id',   payload.sub);
      await FarmDB.auth.set('username',  payload.username);
    } catch (e) {
      console.error('[Sync] Could not parse/store token:', e);
    }
  }

  // ─── Authenticated fetch helper ────────────────────────────────────────────

  /**
   * Make an authenticated JSON API call.
   * Attaches Authorization: Bearer <token> and sends session cookie.
   */
  async function apiCall(path, method = 'GET', body = null) {
    const token = await getToken();
    const opts  = {
      method,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    };
    if (body !== null) opts.body = JSON.stringify(body);

    const resp = await fetch(path, opts);
    return resp.json();
  }

  // ─── Data refresh ──────────────────────────────────────────────────────────

  /**
   * Fetch all animals from the server and replace the IndexedDB copy.
   * Pending (unsynced) local records are always preserved.
   */
  async function refreshAnimals() {
    try {
      const json = await apiCall('/api/animals.php');
      if (json.success && Array.isArray(json.data)) {
        await FarmDB.animals.replaceAll(json.data);
        console.log('[Sync] Animals refreshed —', json.data.length, 'records');
        return true;
      }
    } catch (e) {
      console.warn('[Sync] refreshAnimals failed:', e);
    }
    return false;
  }

  // ─── Offline queue flush ───────────────────────────────────────────────────

  /**
   * Send all queued offline operations to api/sync.php.
   * Called automatically when the browser comes back online (wired up in pwa.js).
   */
  async function flushQueue() {
    const queue = await FarmDB.syncQueue.getAll();
    if (queue.length === 0) return;

    if (!navigator.onLine) {
      console.log('[Sync] Offline — queue held (', queue.length, 'items)');
      return;
    }

    console.log('[Sync] Flushing', queue.length, 'queued operation(s)…');

    // Build the changes array expected by api/sync.php
    const changes = queue.map(op => {
      if (op.action === 'create') {
        return {
          action:   'create',
          local_id: String(op.animal_id),  // local IDB id as the correlation key
          data:     op.data,
        };
      }
      return {
        action:    'update',
        server_id: op.server_id,
        data:      op.data,
      };
    });

    let result;
    try {
      result = await apiCall('/api/sync.php', 'POST', { changes });
    } catch (e) {
      console.error('[Sync] Network error during flush:', e);
      return;
    }

    if (!result?.success) {
      console.error('[Sync] Sync endpoint returned error:', result?.error);
      return;
    }

    const { applied, skipped, results } = result.data;
    console.log(`[Sync] Applied: ${applied}, Skipped: ${skipped}`);

    // Update local IDs with the server IDs returned for 'create' operations
    for (const item of results) {
      if (item.status === 'created' && item.local_id != null) {
        const localIdbId = parseInt(item.local_id, 10);
        const animal     = await FarmDB.animals.get(localIdbId);
        if (animal) {
          animal.server_id    = item.server_id;
          animal.pending_sync = 0;
          await FarmDB.animals.put(animal);
        }
      } else if (item.status === 'updated' && item.server_id != null) {
        const matches = await FarmDB.animals.getByServerId(item.server_id);
        for (const a of matches) {
          a.pending_sync = 0;
          await FarmDB.animals.put(a);
        }
      }
    }

    // Remove each processed queue entry individually
    for (const op of queue) {
      await FarmDB.syncQueue.remove(op.id);
    }

    // Pull fresh canonical data from the server
    await refreshAnimals();

    // Notify any open page to re-render if it's showing animals
    if (typeof window._farmOnSyncComplete === 'function') {
      window._farmOnSyncComplete();
    }
  }

  // Public interface
  return { getToken, fetchToken, apiCall, refreshAnimals, flushQueue };

})();
