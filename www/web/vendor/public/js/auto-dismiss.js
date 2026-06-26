/**
 * auto-dismiss.js
 * Fait disparaître automatiquement tous les messages .alert et .action-msg
 * après 7 secondes avec une animation de fondu.
 */
document.addEventListener('DOMContentLoaded', function () {
    var DELAI_MS = 7000; // 7 secondes

    function configurerDismiss(el) {
        if (!el) return;

        // Barre de progression visuelle
        var barre = document.createElement('div');
        barre.style.cssText = [
            'position:absolute', 'bottom:0', 'left:0',
            'height:3px', 'border-radius:0 0 8px 8px',
            'background:rgba(0,0,0,.15)',
            'width:100%',
            'transform-origin:left center',
            'animation:dismissProgress ' + DELAI_MS + 'ms linear forwards'
        ].join(';');
        el.style.position = 'relative';
        el.style.overflow  = 'hidden';
        el.appendChild(barre);

        // Bouton fermeture
        var btn = document.createElement('button');
        btn.innerHTML = '&times;';
        btn.setAttribute('aria-label', 'Fermer');
        btn.style.cssText = [
            'position:absolute', 'top:6px', 'right:8px',
            'background:none', 'border:none', 'cursor:pointer',
            'font-size:1.1rem', 'line-height:1', 'padding:2px 6px',
            'color:inherit', 'opacity:.6'
        ].join(';');
        btn.addEventListener('click', function () { dismisser(el); });
        el.appendChild(btn);

        // Timer auto-dismiss
        var timer = setTimeout(function () { dismisser(el); }, DELAI_MS);

        // Pause au survol
        el.addEventListener('mouseenter', function () { clearTimeout(timer); });
        el.addEventListener('mouseleave', function () {
            timer = setTimeout(function () { dismisser(el); }, 1500);
        });
    }

    function dismisser(el) {
        el.classList.add('dismissing');
        setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 650);
    }

    // Injecter l'animation CSS keyframe une seule fois
    if (!document.getElementById('dismissKeyframes')) {
        var style = document.createElement('style');
        style.id  = 'dismissKeyframes';
        style.textContent = '@keyframes dismissProgress { from{transform:scaleX(1)} to{transform:scaleX(0)} }';
        document.head.appendChild(style);
    }

    // Appliquer à tous les messages présents
    var selecteurs = '.alert, .action-msg';
    document.querySelectorAll(selecteurs).forEach(configurerDismiss);
});
