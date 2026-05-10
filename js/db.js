// js/db.js — FarmApp IndexedDB wrapper
// No external dependencies. Promise-based API.
//
// Stores:
//   animals   — keyPath: 'id' (autoIncrement). Fields: server_id, type, name,
//               dob, weight, notes, created_at, updated_at, pending_sync (0|1)
//   syncQueue — keyPath: 'id' (autoIncrement). Fields: action, animal_id,
//               server_id, data, created_at
//   auth      — keyPath: 'key'. Simple key/value for JWT + user info.

const FarmDB = (() => {
  const DB_NAME    = 'farmapp_db';
  const DB_VERSION = 1;
  let _db = null;

  // ─── Open / upgrade ────────────────────────────────────────────────────────
  function open() {
    if (_db) return Promise.resolve(_db);

    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);

      req.onupgradeneeded = (evt) => {
        const db = evt.target.result;

        // animals store
        if (!db.objectStoreNames.contains('animals')) {
          const s = db.createObjectStore('animals', { keyPath: 'id', autoIncrement: true });
          s.createIndex('by_server_id',   'server_id',   { unique: false });
          s.createIndex('by_pending',     'pending_sync', { unique: false });
        }

        // syncQueue store
        if (!db.objectStoreNames.contains('syncQueue')) {
          const q = db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
          q.createIndex('by_animal_id', 'animal_id', { unique: false });
        }

        // auth / config key-value store
        if (!db.objectStoreNames.contains('auth')) {
          db.createObjectStore('auth', { keyPath: 'key' });
        }
      };

      req.onsuccess = (evt) => { _db = evt.target.result; resolve(_db); };
      req.onerror   = (evt) => reject(evt.target.error);
    });
  }

  // ─── Low-level helpers ─────────────────────────────────────────────────────
  function tx(storeName, mode, fn) {
    return open().then(db => new Promise((resolve, reject) => {
      const transaction = db.transaction(storeName, mode);
      const store       = transaction.objectStore(storeName);
      const req         = fn(store, transaction);

      if (req && typeof req.onsuccess !== 'undefined') {
        req.onsuccess = () => resolve(req.result);
        req.onerror   = () => reject(req.error);
      } else {
        transaction.oncomplete = () => resolve();
        transaction.onerror    = () => reject(transaction.error);
      }
    }));
  }

  // ─── Generic store operations ──────────────────────────────────────────────
  const ops = {
    getAll(store) {
      return tx(store, 'readonly', s => s.getAll());
    },
    get(store, key) {
      return tx(store, 'readonly', s => s.get(key))
        .then(r => r ?? null);
    },
    getByIndex(store, indexName, value) {
      return tx(store, 'readonly', (s) => s.index(indexName).getAll(value));
    },
    put(store, data) {
      return tx(store, 'readwrite', s => s.put(data));
    },
    delete(store, key) {
      return tx(store, 'readwrite', s => s.delete(key));
    },
    clear(store) {
      return tx(store, 'readwrite', s => s.clear());
    },
  };

  // ─── animals ───────────────────────────────────────────────────────────────
  const animals = {
    getAll() {
      return ops.getAll('animals');
    },
    get(id) {
      return ops.get('animals', id);
    },
    getByServerId(serverId) {
      return ops.getByIndex('animals', 'by_server_id', serverId);
    },
    put(animal) {
      // Normalise pending_sync to integer for IDB index compatibility
      animal.pending_sync = animal.pending_sync ? 1 : 0;
      return ops.put('animals', animal);
    },
    delete(id) {
      return ops.delete('animals', id);
    },
    clear() {
      return ops.clear('animals');
    },

    /**
     * Replace all synced animals with fresh data from the server.
     * Pending (unsynced) animals are preserved.
     */
    replaceAll(serverAnimals) {
      return open().then(db => new Promise((resolve, reject) => {
        const transaction = db.transaction('animals', 'readwrite');
        const store       = transaction.objectStore('animals');
        const now         = new Date().toISOString();

        // 1. Fetch all currently pending records so we can keep them
        const pendingReq = store.index('by_pending').getAll(1);

        pendingReq.onsuccess = () => {
          const pending = pendingReq.result;

          // 2. Clear everything
          store.clear();

          // 3. Re-add pending records (preserve their local IDs intact)
          pending.forEach(a => store.put(a));

          // 4. Add fresh server records (server_id as the canonical reference)
          serverAnimals.forEach(a => {
            store.put({
              // id omitted — autoIncrement assigns a new local ID
              server_id:    a.id,
              type:         a.type,
              name:         a.name,
              dob:          a.dob          ?? null,
              weight:       a.weight       ?? null,
              notes:        a.notes        ?? '',
              created_at:   a.created_at   ?? now,
              updated_at:   a.updated_at   ?? now,
              pending_sync: 0,
            });
          });
        };

        transaction.oncomplete = () => resolve();
        transaction.onerror    = () => reject(transaction.error);
      }));
    },
  };

  // ─── syncQueue ─────────────────────────────────────────────────────────────
  const syncQueue = {
    getAll()        { return ops.getAll('syncQueue'); },
    push(op)        { return ops.put('syncQueue', op); },
    remove(id)      { return ops.delete('syncQueue', id); },
    clear()         { return ops.clear('syncQueue'); },
  };

  // ─── auth (key-value) ──────────────────────────────────────────────────────
  const auth = {
    get(key) {
      return ops.get('auth', key).then(rec => (rec ? rec.value : null));
    },
    set(key, value) {
      return ops.put('auth', { key, value });
    },
    remove(key) {
      return ops.delete('auth', key);
    },
  };

  return { open, animals, syncQueue, auth };
})();
