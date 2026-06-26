/**
 * public/js/sidebar.js
 * Toggle sidebar off-canvas sur mobile (espace gestionnaire & médecin)
 * Requiert dans le HTML :
 *   - un <button class="sidebar-toggle"> dans le topbar
 *   - un <div class="sidebar-overlay"> après la sidebar
 *   - la sidebar avec class="sidebar"
 */
(function () {
  'use strict';

  function init() {
    const toggle   = document.querySelector('.sidebar-toggle');
    const sidebar  = document.querySelector('.sidebar');
    const overlay  = document.querySelector('.sidebar-overlay');

    if (!toggle || !sidebar) return;

    // Ouvrir / fermer
    toggle.addEventListener('click', function () {
      const open = sidebar.classList.toggle('open');
      if (overlay) overlay.classList.toggle('open', open);
      toggle.setAttribute('aria-expanded', open);
    });

    // Clic overlay → fermer
    if (overlay) {
      overlay.addEventListener('click', function () {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    }

    // Clic lien nav → fermer sur mobile
    sidebar.querySelectorAll('.nav-item, .logout-btn').forEach(function (el) {
      el.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
          sidebar.classList.remove('open');
          if (overlay) overlay.classList.remove('open');
        }
      });
    });

    // Resize → réinitialiser état
    window.addEventListener('resize', function () {
      if (window.innerWidth > 768) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
