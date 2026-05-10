<?php
// includes/pwa_head.php — PWA meta tags, asset links, and offline JS stack.
// Include inside every page's <head>, after <title>.
// Usage: <?php require_once 'includes/pwa_head.php'; ?>
?>
  <!-- PWA manifest -->
  <link rel="manifest" href="/manifest.json">

  <!-- Theme colour (browser chrome on Android) -->
  <meta name="theme-color" content="#2d2d30">

  <!-- iOS home-screen icon and standalone behaviour -->
  <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="FarmApp">

  <!-- Prevent phone-number auto-detection on iOS -->
  <meta name="format-detection" content="telephone=no">

  <!-- Offline JS stack (load order matters — defer preserves it) -->
  <script src="/js/db.js"   defer></script>  <!-- IndexedDB wrapper -->
  <script src="/js/sync.js" defer></script>  <!-- JWT + sync manager -->
  <script src="/js/app.js"  defer></script>  <!-- Page-specific offline UI -->

  <!-- Service Worker registration + online/offline banner (last) -->
  <script src="/js/pwa.js"  defer></script>
