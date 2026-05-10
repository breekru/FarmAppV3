// js/app.js — FarmApp offline UI layer
// Depends on: js/db.js, js/sync.js (both must load first via defer)
//
// Detects the current page and applies the right offline behaviour:
//   dashboard      → pre-fetch animals into IndexedDB; acquire JWT
//   view_animals   → render from IndexedDB when offline; show pending-sync badge
//   add_animal     → intercept form submit when offline; save to IndexedDB + queue
//   edit_animal    → same, for updates

(async function init() {
  const path = window.location.pathname;

  const isAuth = !/login|register|forgot_password|reset_password|offline/.test(path);

  if (isAuth) {
    // Inject bottom navigation bar
    injectBottomNav(path);

    // Acquire / refresh JWT in the background
    farmSync.fetchToken().catch(() => {});

    // Flush any queued offline changes if we're online
    if (navigator.onLine) {
      farmSync.flushQueue().catch(() => {});
    }

    // Re-render animals table after a successful sync
    window._farmOnSyncComplete = () => {
      if (path.includes('view_animals')) renderAnimalsFromDB();
    };
  }

  if (path.includes('dashboard') || path === '/' || path.endsWith('index.php')) {
    initDashboard();
  } else if (path.includes('view_animals')) {
    await initViewAnimals();
  } else if (path.includes('add_animal')) {
    initAddAnimal();
  } else if (path.includes('edit_animal')) {
    initEditAnimal();
  }
})();

// ─────────────────────────────────────────────────────────────────────────────
// Dashboard — silently pre-fetch animals so offline cache is warm
// ─────────────────────────────────────────────────────────────────────────────
function initDashboard() {
  if (!navigator.onLine) return;
  farmSync.fetchToken()
    .then(() => farmSync.refreshAnimals())
    .catch(() => {});
}

// ─────────────────────────────────────────────────────────────────────────────
// View Animals
// ─────────────────────────────────────────────────────────────────────────────
async function initViewAnimals() {
  // Show success toast if redirected here after an offline save
  const params = new URLSearchParams(window.location.search);
  if (params.get('offline_saved') === '1') {
    showToast('✓ Saved offline — will sync when reconnected', 'success');
    history.replaceState({}, '', window.location.pathname);
  }

  if (navigator.onLine) {
    // Refresh the IndexedDB cache from the server (background — don't await)
    farmSync.fetchToken()
      .then(() => farmSync.refreshAnimals())
      .catch(() => {});
  } else {
    // Offline: replace the PHP-rendered table with IndexedDB data
    await renderAnimalsFromDB();
  }

  // Show pending-sync badge if the queue has items
  const queue = await FarmDB.syncQueue.getAll();
  if (queue.length > 0) {
    showPendingBadge(queue.length);
  }
}

/**
 * Re-render the animals table entirely from IndexedDB.
 * Called when offline, and after a successful sync.
 */
async function renderAnimalsFromDB() {
  const animals = await FarmDB.animals.getAll();

  // Sort by created_at descending (newest first)
  animals.sort((a, b) => (b.created_at ?? '').localeCompare(a.created_at ?? ''));

  const container = document.querySelector('.form-container');
  if (!container) return;

  const existingTable = container.querySelector('table');
  const existingMsg   = container.querySelector('p:not(.error):not(.success)');

  if (animals.length === 0) {
    const msg = existingTable ?? existingMsg;
    if (msg) msg.outerHTML = '<p>You haven\'t added any animals yet (offline data).</p>';
    return;
  }

  const offlineBanner = navigator.onLine
    ? ''
    : '<p class="offline-note">📴 Offline — showing locally cached data</p>';

  const rows = animals.map(a => `
    <tr>
      <td data-label="Type">${esc(a.type)}</td>
      <td data-label="Name">${esc(a.name)}</td>
      <td data-label="DOB">${esc(a.dob ?? '—')}</td>
      <td data-label="Weight">${a.weight != null ? esc(String(a.weight)) + ' lbs' : '—'}</td>
      <td data-label="Notes">${esc(a.notes ?? '')}</td>
      <td data-label="Status" class="sync-status">
        ${a.pending_sync ? '⏳ Pending sync' : (a.server_id ? '✓' : '—')}
      </td>
    </tr>
  `).join('');

  const tableHTML = `
    ${offlineBanner}
    <table>
      <thead>
        <tr>
          <th>Type</th><th>Name</th><th>DOB</th>
          <th>Weight</th><th>Notes</th><th>Status</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;

  if (existingTable) {
    existingTable.outerHTML = tableHTML;
  } else if (existingMsg) {
    existingMsg.outerHTML = tableHTML;
  } else {
    container.insertAdjacentHTML('afterbegin', tableHTML);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Add Animal — intercept form when offline
// ─────────────────────────────────────────────────────────────────────────────
function initAddAnimal() {
  const form = document.querySelector('form');
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    // Online: let the PHP form submit handle everything normally
    if (navigator.onLine) return;

    e.preventDefault();

    const data = collectFormData(form);
    if (!data) return; // validation failed, error shown inside collectFormData

    const now = new Date().toISOString();

    // Save to local IndexedDB
    const localId = await FarmDB.animals.put({
      server_id:    null,
      type:         data.type,
      name:         data.name,
      dob:          data.dob,
      weight:       data.weight,
      notes:        data.notes,
      created_at:   now,
      updated_at:   now,
      pending_sync: 1,
    });

    // Add to sync queue
    await FarmDB.syncQueue.push({
      action:     'create',
      animal_id:  localId,
      server_id:  null,
      data,
      created_at: now,
    });

    window.location.href = 'view_animals.php?offline_saved=1';
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Edit Animal — intercept form when offline
// ─────────────────────────────────────────────────────────────────────────────
function initEditAnimal() {
  const form = document.querySelector('form');
  if (!form) return;

  const serverId = parseInt(new URLSearchParams(window.location.search).get('id') ?? '0', 10);

  form.addEventListener('submit', async (e) => {
    if (navigator.onLine) return;

    e.preventDefault();

    const data = collectFormData(form);
    if (!data) return;

    const now = new Date().toISOString();

    // Update the local IndexedDB copy if we have one
    if (serverId) {
      const matches = await FarmDB.animals.getByServerId(serverId);
      if (matches.length > 0) {
        const animal = { ...matches[0], ...data, updated_at: now, pending_sync: 1 };
        await FarmDB.animals.put(animal);
      }
    }

    // Queue the update
    await FarmDB.syncQueue.push({
      action:     'update',
      animal_id:  null,
      server_id:  serverId,
      data,
      created_at: now,
    });

    window.location.href = 'view_animals.php?offline_saved=1';
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Shared helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Read, validate, and return form fields as a plain object. */
function collectFormData(form) {
  const type   = form.querySelector('[name="type"]')?.value.trim()   ?? '';
  const name   = form.querySelector('[name="name"]')?.value.trim()   ?? '';
  const dob    = form.querySelector('[name="dob"]')?.value           ?? '';
  const weight = form.querySelector('[name="weight"]')?.value        ?? '';
  const notes  = form.querySelector('[name="notes"]')?.value.trim()  ?? '';

  if (!type || !name) {
    showFormError('Type and Name are required.');
    return null;
  }

  return {
    type,
    name,
    dob:    dob    || null,
    weight: weight ? parseFloat(weight) : null,
    notes,
  };
}

/** HTML-escape a string for safe table insertion. */
function esc(str) {
  const d = document.createElement('div');
  d.textContent = String(str ?? '');
  return d.innerHTML;
}

/** Show an inline form error above the submit button. */
function showFormError(msg) {
  let el = document.querySelector('.js-form-error');
  if (!el) {
    el = document.createElement('p');
    el.className = 'error js-form-error';
    const btn = document.querySelector('button[type="submit"]');
    if (btn) btn.before(el);
  }
  el.textContent = msg;
}

/**
 * Show a temporary toast notification.
 * @param {string} message
 * @param {'success'|'error'} type
 */
function showToast(message, type = 'success') {
  const toast = document.createElement('p');
  toast.className = type;
  toast.textContent = message;
  toast.style.cssText = 'margin-bottom:1rem;';

  const container = document.querySelector('.form-container');
  const heading   = container?.querySelector('h2');
  if (heading) {
    heading.after(toast);
  } else if (container) {
    container.prepend(toast);
  }

  // Auto-dismiss after 6 seconds
  setTimeout(() => toast.remove(), 6000);
}

/** Show a "N changes pending sync" notice above the form-links. */
function showPendingBadge(count) {
  const links = document.querySelector('.form-links');
  if (!links) return;

  const badge = document.createElement('p');
  badge.style.cssText = 'color:#e67e22;font-size:.85rem;margin-bottom:.75rem;text-align:center;';
  badge.textContent   = `⏳ ${count} change${count !== 1 ? 's' : ''} pending sync`;
  links.before(badge);
}

// ─────────────────────────────────────────────────────────────────────────────
// Bottom navigation bar — injected on all authenticated pages
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Inject the fixed bottom nav into the DOM and add body padding.
 * @param {string} path - window.location.pathname
 */
function injectBottomNav(path) {
  if (document.getElementById('bottom-nav')) return; // already present

  const items = [
    { href: 'dashboard.php',      icon: '🏠', label: 'Home'    },
    { href: 'view_animals.php',   icon: '📋', label: 'Animals' },
    { href: 'add_animal.php',     icon: '➕', label: 'Add'     },
    { href: 'change_password.php',icon: '🔐', label: 'Account' },
    { href: 'logout.php',         icon: '🚪', label: 'Logout'  },
  ];

  const nav = document.createElement('nav');
  nav.id = 'bottom-nav';
  nav.setAttribute('aria-label', 'Main navigation');

  nav.innerHTML = items.map(({ href, icon, label }) => {
    const isActive = path.includes(href.replace('.php', ''));
    return `
      <a href="${href}" ${isActive ? 'class="nav-active" aria-current="page"' : ''}>
        <span class="nav-icon">${icon}</span>
        <span class="nav-label">${label}</span>
      </a>`;
  }).join('');

  document.body.appendChild(nav);
  document.body.classList.add('has-bottom-nav');
}

// ─────────────────────────────────────────────────────────────────────────────
// Loading state helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Show a centered loading spinner inside .form-container.
 * Removes any existing spinner first.
 */
function showLoading() {
  hideLoading();
  const container = document.querySelector('.form-container');
  if (!container) return;
  const el = document.createElement('div');
  el.className = 'farm-loading';
  el.id = 'farm-spinner';
  container.appendChild(el);
}

/** Remove the loading spinner if present. */
function hideLoading() {
  document.getElementById('farm-spinner')?.remove();
}

// ─────────────────────────────────────────────────────────────────────────────
// Offline-note style (injected once at runtime — keeps pwa_head.php clean)
// ─────────────────────────────────────────────────────────────────────────────
(function injectStyles() {
  if (document.getElementById('farm-app-styles')) return;
  const style = document.createElement('style');
  style.id = 'farm-app-styles';
  style.textContent = `
    .offline-note {
      color: #c0392b;
      font-size: .85rem;
      margin-bottom: .5rem;
    }
    .sync-status {
      font-size: .8rem;
      color: #666;
      white-space: nowrap;
    }
  `;
  document.head.appendChild(style);
})();
