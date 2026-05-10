// js/pwa.js — Service Worker registration + online/offline UI banner

// ─────────────────────────────────────────────
// Service Worker registration
// ─────────────────────────────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/' })
      .then(reg => {
        console.log('[PWA] Service Worker registered. Scope:', reg.scope);

        // When a new SW is waiting, prompt the user to refresh
        reg.addEventListener('updatefound', () => {
          const newWorker = reg.installing;
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              showUpdateBanner();
            }
          });
        });
      })
      .catch(err => console.warn('[PWA] Service Worker registration failed:', err));

    // When a new SW takes control, reload so the user gets fresh assets
    let refreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!refreshing) {
        refreshing = true;
        window.location.reload();
      }
    });
  });
}

// ─────────────────────────────────────────────
// Online / Offline status banner
// ─────────────────────────────────────────────
(function () {
  let banner = null;

  function ensureBanner() {
    if (banner) return banner;

    banner = document.createElement('div');
    banner.id = 'pwa-status-banner';
    Object.assign(banner.style, {
      position:        'fixed',
      top:             '0',
      left:            '0',
      right:           '0',
      zIndex:          '9999',
      padding:         '10px 16px',
      textAlign:       'center',
      fontSize:        '14px',
      fontWeight:      '600',
      fontFamily:      '-apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif',
      transition:      'transform .3s ease, opacity .3s ease',
      transform:       'translateY(-100%)',
      opacity:         '0',
      pointerEvents:   'none',
      // Account for iOS safe area (notch)
      paddingTop:      'calc(10px + env(safe-area-inset-top, 0px))',
    });
    document.body.appendChild(banner);
    return banner;
  }

  function showBanner(message, bgColor, duration) {
    const b = ensureBanner();
    b.textContent  = message;
    b.style.background = bgColor;
    b.style.color      = '#fff';
    b.style.transform  = 'translateY(0)';
    b.style.opacity    = '1';

    if (duration) {
      setTimeout(hideBanner, duration);
    }
  }

  function hideBanner() {
    if (!banner) return;
    banner.style.transform = 'translateY(-100%)';
    banner.style.opacity   = '0';
  }

  function handleOffline() {
    showBanner('⚠️  You\'re offline — changes will sync when reconnected', '#c0392b', 0);
  }

  function handleOnline() {
    showBanner('✓  Back online', '#27ae60', 3000);
    // Trigger sync if Phase 4 sync module is loaded
    if (typeof window.farmSync !== 'undefined') {
      window.farmSync.flushQueue();
    }
  }

  window.addEventListener('offline', handleOffline);
  window.addEventListener('online',  handleOnline);

  // Set initial state after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (!navigator.onLine) handleOffline();
    });
  } else {
    if (!navigator.onLine) handleOffline();
  }
})();

// ─────────────────────────────────────────────
// Update banner — tells user a new version is ready
// ─────────────────────────────────────────────
function showUpdateBanner() {
  const bar = document.createElement('div');
  Object.assign(bar.style, {
    position:   'fixed',
    bottom:     '0',
    left:       '0',
    right:      '0',
    zIndex:     '9999',
    background: '#2d2d30',
    color:      '#fff',
    padding:    '12px 16px',
    display:    'flex',
    alignItems: 'center',
    justifyContent: 'space-between',
    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif',
    fontSize:   '14px',
    paddingBottom: 'calc(12px + env(safe-area-inset-bottom, 0px))',
  });

  bar.innerHTML = `
    <span>A new version of FarmApp is available.</span>
    <button id="pwa-update-btn" style="
      background:#fff; color:#2d2d30; border:none; border-radius:6px;
      padding:6px 14px; font-size:13px; font-weight:600; cursor:pointer; margin-left:12px;
    ">Refresh</button>
  `;
  document.body.appendChild(bar);

  document.getElementById('pwa-update-btn').addEventListener('click', () => {
    navigator.serviceWorker.ready.then(reg => {
      if (reg.waiting) {
        reg.waiting.postMessage({ type: 'SKIP_WAITING' });
      }
    });
  });
}
