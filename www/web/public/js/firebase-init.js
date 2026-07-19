/**
 * public/js/firebase-init.js
 * Initialisation Firebase côté patient – gestion complète des notifications push.
 *
 * Améliorations :
 *  - Affichage en temps réel d'une bannière contextuelle selon le type de notif
 *  - Badge de compteur mis à jour
 *  - Son discret sur les alertes importantes
 *  - Rechargement automatique des données de file d'attente si la page est ouverte
 */

const firebaseConfig = {
    apiKey: "AIzaSyDSWyPMr_cak10gNUYNU94khLZe7Mrge6s",
    authDomain: "smartqueue-37328.firebaseapp.com",
    projectId: "smartqueue-37328",
    storageBucket: "smartqueue-37328.firebasestorage.app",
    messagingSenderId: "87084283644",
    appId: "1:87084283644:web:0b7160836195a66a050979"
};

/* ──────────────────────────────────────────────────────────
   Constantes de configuration
────────────────────────────────────────────────────────── */

// Types qui déclenchent un rechargement automatique des données de file
const TYPES_RELOAD_FILE = [
    'AVANCEMENT', 'RANG_SUIVANT', 'APPEL_IMMEDIAT',
    'CLOTURE_ABSENT', 'ANNULATION', 'DECALAGE', 'URGENCE'
];

// Types qui affichent une bannière persistante (ne disparaît pas automatiquement)
const TYPES_PERSISTANTS = ['APPEL_IMMEDIAT', 'URGENCE', 'CLOTURE_ABSENT'];

// Correspondances type → emoji et couleur
const TYPE_CONFIG = {
    APPEL_IMMEDIAT: { emoji: '🔔', classe: 'notif-urgent', son: true },
    RANG_SUIVANT: { emoji: '🔜', classe: 'notif-warning', son: true },
    AVANCEMENT: { emoji: '📊', classe: 'notif-info', son: false },
    RAPPEL_30MIN: { emoji: '⏳', classe: 'notif-warning', son: true },
    CLOTURE_ABSENT: { emoji: '⚠️', classe: 'notif-danger', son: true },
    ANNULATION: { emoji: '❌', classe: 'notif-danger', son: true },
    CONFIRMATION: { emoji: '✅', classe: 'notif-success', son: false },
    DECALAGE: { emoji: '⚠️', classe: 'notif-warning', son: false },
    URGENCE: { emoji: '🚨', classe: 'notif-danger', son: true },
    MISE_EN_PAUSE: { emoji: '⏸️', classe: 'notif-warning', son: false },
    RETOUR_EXAMEN: { emoji: '▶️', classe: 'notif-urgent', son: true },
    default: { emoji: '📱', classe: 'notif-info', son: false }
};

/* ──────────────────────────────────────────────────────────
   Initialisation Firebase
────────────────────────────────────────────────────────── */

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();
let isInitialized = false;
let notificationCount = 0;
let notificationAudioContext = null;
let notificationAudioUnlocked = false;

function prepareNotificationAudio() {
    if (notificationAudioContext || !window.AudioContext) return;
    try {
        notificationAudioContext = new(window.AudioContext || window.webkitAudioContext)();
    } catch (_) {
        notificationAudioContext = null;
    }
}

async function unlockNotificationAudio() {
    if (notificationAudioUnlocked) return;
    prepareNotificationAudio();
    if (!notificationAudioContext) return;
    try {
        if (notificationAudioContext.state === 'suspended') {
            await notificationAudioContext.resume();
        }
        notificationAudioUnlocked = true;
    } catch (_) {}
}

/* ──────────────────────────────────────────────────────────
   Permission & Token
────────────────────────────────────────────────────────── */

async function requestNotificationPermission() {
    try {
        await unlockNotificationAudio();
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            showBanniere('🔕 Notifications désactivées par le navigateur.', 'notif-danger', false);
            return null;
        }

        const token = await messaging.getToken({
            vapidKey: window.VAPID_PUBLIC_KEY || ''
        });

        if (!token) {
            console.warn('[FCM] Aucun token obtenu.');
            return null;
        }

        await enregistrerToken(token);
        showBanniere('🔔 Notifications activées — vous serez averti(e) en temps réel.', 'notif-success');
        return token;

    } catch (err) {
        console.error('[FCM] Erreur permission:', err);
        showBanniere('Erreur lors de l\'activation des notifications : ' + err.message, 'notif-danger');
        return null;
    }
}

async function enregistrerToken(token) {
    try {
        const resp = await fetch('index.php?action=save_fcm_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, user_agent: navigator.userAgent })
        });
        const result = await resp.json();
        if (!result.success) console.warn('[FCM] Serveur:', result.message);
    } catch (err) {
        console.error('[FCM] Erreur enregistrement token:', err);
    }
}

/* ──────────────────────────────────────────────────────────
   Gestion des messages au 1er plan
────────────────────────────────────────────────────────── */

messaging.onMessage((payload) => {
    console.log('[FCM] Message au 1er plan:', payload);

    const data = payload.data || {};
    const notif = payload.notification || {};
    const type = data.type || 'default';
    const cfg = TYPE_CONFIG[type] || TYPE_CONFIG.default;

    // 1 — Notification navigateur native (pour les onglets en arrière-plan dans le même navigateur)
    if (Notification.permission === 'granted') {
        const n = new Notification(notif.title || 'QueueCare', {
            body: notif.body || '',
            icon: '/public/images/logo.png',
            badge: '/public/images/badge.png',
            requireInteraction: TYPES_PERSISTANTS.includes(type),
            data
        });
        n.onclick = () => {
            window.focus();
            if (data.url) window.location.href = data.url;
            n.close();
        };
    }

    // 2 — Bannière en ligne sur la page
    const message = `${cfg.emoji} ${notif.title || ''} — ${notif.body || ''}`;
    showBanniere(message, cfg.classe, !TYPES_PERSISTANTS.includes(type));

    // 3 — Son discret
    if (cfg.son) {
        unlockNotificationAudio().finally(() => jouerSon());
    }

    // 4 — Badge
    incrementerBadge();

    // 5 — Rechargement des données de file si pertinent
    if (TYPES_RELOAD_FILE.includes(type)) {
        rechargerDonneesFile(data);
    }

    // 6 — Événement personnalisé (pour les modules JS qui l'écoutent)
    document.dispatchEvent(new CustomEvent('queuecare:notification', {
        detail: { type, title: notif.title, body: notif.body, data }
    }));
});

/* ──────────────────────────────────────────────────────────
   Bannière in-page
────────────────────────────────────────────────────────── */

function showBanniere(message, classe = 'notif-info', autoHide = true) {
    // Créer le conteneur de bannières s'il n'existe pas
    let conteneur = document.getElementById('fcm-banniere-conteneur');
    if (!conteneur) {
        conteneur = document.createElement('div');
        conteneur.id = 'fcm-banniere-conteneur';
        conteneur.style.cssText = `
            position: fixed; top: 16px; right: 16px; z-index: 99999;
            display: flex; flex-direction: column; gap: 8px;
            max-width: min(400px, calc(100vw - 32px));
        `;
        document.body.appendChild(conteneur);
    }

    const banniere = document.createElement('div');
    banniere.className = `fcm-banniere ${classe}`;
    banniere.innerHTML = `
        <span class="fcm-banniere-msg">${message}</span>
        <button class="fcm-banniere-close" onclick="this.parentElement.remove()">✕</button>
    `;
    banniere.style.cssText = `
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 16px; border-radius: 10px;
        font-size: 14px; line-height: 1.4;
        box-shadow: 0 4px 20px rgba(0,0,0,0.18);
        animation: fcm-slide-in 0.3s ease;
        cursor: default;
    `;

    // Couleurs selon la classe
    const couleurs = {
        'notif-urgent': ['#fff3cd', '#856404', '#ffc107'],
        'notif-warning': ['#fff3cd', '#664d03', '#ffc107'],
        'notif-danger': ['#f8d7da', '#842029', '#f5c2c7'],
        'notif-success': ['#d1e7dd', '#0f5132', '#a3cfbb'],
        'notif-info': ['#cff4fc', '#055160', '#9eeaf9'],
    };
    const [bg, color, border] = couleurs[classe] || couleurs['notif-info'];
    banniere.style.backgroundColor = bg;
    banniere.style.color = color;
    banniere.style.border = `1px solid ${border}`;

    const closeBtn = banniere.querySelector('.fcm-banniere-close');
    closeBtn.style.cssText = `
        background: none; border: none; cursor: pointer;
        font-size: 16px; color: ${color}; flex-shrink: 0; padding: 0;
    `;

    conteneur.prepend(banniere);

    if (autoHide) {
        setTimeout(() => {
            banniere.style.animation = 'fcm-slide-out 0.3s ease forwards';
            setTimeout(() => banniere.remove(), 300);
        }, 6000);
    }
}

/* ──────────────────────────────────────────────────────────
   Badge de compteur
────────────────────────────────────────────────────────── */

function incrementerBadge() {
    notificationCount++;
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.textContent = notificationCount;
        badge.style.display = 'flex';
        badge.dataset.count = notificationCount;
    }
    // Titre de la page
    document.title = `(${notificationCount}) ${document.title.replace(/^\(\d+\)\s*/, '')}`;
}

function reinitialiserBadge() {
    notificationCount = 0;
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.textContent = '0';
        badge.style.display = 'none';
        badge.dataset.count = '0';
    }
    document.title = document.title.replace(/^\(\d+\)\s*/, '');
}

/* ──────────────────────────────────────────────────────────
   Son discret
────────────────────────────────────────────────────────── */

function jouerSon() {
    try {
        prepareNotificationAudio();
        if (!notificationAudioContext) return;
        if (!notificationAudioUnlocked && notificationAudioContext.state === 'suspended') return;

        const ctx = notificationAudioContext;
        const now = ctx.currentTime;
        const freqs = [880, 988];
        freqs.forEach((freq, index) => {
            const osc = ctx.createOscillator();
            const gn = ctx.createGain();
            osc.connect(gn);
            gn.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.value = freq;
            gn.gain.setValueAtTime(0.0001, now + index * 0.18);
            gn.gain.exponentialRampToValueAtTime(0.12, now + index * 0.18 + 0.02);
            gn.gain.exponentialRampToValueAtTime(0.0001, now + index * 0.18 + 0.16);
            osc.start(now + index * 0.18);
            osc.stop(now + index * 0.18 + 0.18);
        });
    } catch (_) { /* Navigateurs sans Web Audio API */ }
}

/* ──────────────────────────────────────────────────────────
   Rechargement des données de file
────────────────────────────────────────────────────────── */

function rechargerDonneesFile(data) {
    // Déclencher un événement custom que les pages peuvent écouter
    document.dispatchEvent(new CustomEvent('queuecare:recharger-file', { detail: data }));

    // Si la page expose une fonction de refresh, l'appeler
    if (typeof window.refreshFileAttente === 'function') {
        setTimeout(() => window.refreshFileAttente(), 1000);
    }

    // Si une card de rang est présente, animer la mise à jour
    const rangCard = document.querySelector('[data-rang-patient]');
    if (rangCard && data.rang) {
        const nouveauRang = parseInt(data.rang, 10);
        rangCard.classList.add('rang-update');
        setTimeout(() => {
            rangCard.dataset.rangPatient = nouveauRang;
            const rangDisplay = rangCard.querySelector('.rang-valeur');
            if (rangDisplay) {
                rangDisplay.textContent = nouveauRang;
                rangDisplay.classList.add('rang-updated');
                setTimeout(() => rangDisplay.classList.remove('rang-updated'), 2000);
            }
            rangCard.classList.remove('rang-update');
        }, 300);
    }
}

/* ──────────────────────────────────────────────────────────
   Vérification du statut des notifications
────────────────────────────────────────────────────────── */

async function checkNotificationStatus() {
    const permission = Notification.permission;
    const statusEl = document.getElementById('notificationStatus');
    const enableBtn = document.getElementById('enableNotifications');

    if (permission === 'granted') {
        try {
            const token = await messaging.getToken({ vapidKey: window.VAPID_PUBLIC_KEY || '' });
            if (token && statusEl) {
                statusEl.innerHTML = '🔔 Notifications activées';
                statusEl.className = 'notification-status success';
                statusEl.style.display = 'block';
            }
            if (enableBtn) enableBtn.style.display = 'none';
        } catch (e) {
            console.log('[FCM] Token non disponible:', e.message);
        }
    } else if (permission === 'denied') {
        if (statusEl) {
            statusEl.innerHTML = '🔕 Notifications bloquées. Autorisez-les dans les paramètres du navigateur.';
            statusEl.className = 'notification-status error';
            statusEl.style.display = 'block';
        }
    }
}

/* ──────────────────────────────────────────────────────────
   Animations CSS injectées dynamiquement
────────────────────────────────────────────────────────── */

function injecterStylesFCM() {
    if (document.getElementById('fcm-styles')) return;
    const style = document.createElement('style');
    style.id = 'fcm-styles';
    style.textContent = `
        @keyframes fcm-slide-in {
            from { transform: translateX(120%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
        @keyframes fcm-slide-out {
            from { transform: translateX(0);    opacity: 1; }
            to   { transform: translateX(120%); opacity: 0; }
        }
        .fcm-banniere-msg { flex: 1; }
        @keyframes rang-pulse {
            0%   { transform: scale(1);    }
            50%  { transform: scale(1.15); }
            100% { transform: scale(1);    }
        }
        .rang-updated { animation: rang-pulse 0.6s ease; }
        .rang-update  { opacity: 0.5; transition: opacity 0.3s; }
    `;
    document.head.appendChild(style);
}

/* ──────────────────────────────────────────────────────────
   Initialisation principale
────────────────────────────────────────────────────────── */

async function initNotifications() {
    if (isInitialized) return;

    if (!('Notification' in window) || !('serviceWorker' in navigator)) {
        console.warn('[FCM] Notifications ou Service Worker non supportés');
        return;
    }

    injecterStylesFCM();

    try {
        await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        await checkNotificationStatus();
        isInitialized = true;
        console.log('[FCM] Initialisé');
    } catch (err) {
        console.error('[FCM] Erreur init:', err);
    }
}

/* ──────────────────────────────────────────────────────────
   Test de notification (utilisé depuis le panneau patient)
────────────────────────────────────────────────────────── */

async function testNotification(token) {
    try {
        const resp = await fetch('index.php?action=test_notification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token })
        });
        const result = await resp.json();
        if (result.success) {
            showBanniere('✅ Notification test envoyée !', 'notif-success');
        } else {
            showBanniere('❌ Erreur : ' + result.message, 'notif-danger');
        }
    } catch (err) {
        showBanniere('Erreur réseau lors du test.', 'notif-danger');
    }
}

/* ──────────────────────────────────────────────────────────
   Liaison DOM au chargement
────────────────────────────────────────────────────────── */

document.addEventListener('DOMContentLoaded', () => {
    initNotifications();
    prepareNotificationAudio();
    ['click', 'keydown', 'touchstart'].forEach((evt) => {
        document.addEventListener(evt, unlockNotificationAudio, { once: true, passive: true });
    });

    const enableBtn = document.getElementById('enableNotifications');
    if (enableBtn) {
        enableBtn.addEventListener('click', (e) => {
            e.preventDefault();
            unlockNotificationAudio().finally(() => requestNotificationPermission());
        });
    }

    const testBtn = document.getElementById('testNotification');
    if (testBtn) {
        testBtn.addEventListener('click', async() => {
            try {
                const token = await messaging.getToken({ vapidKey: window.VAPID_PUBLIC_KEY || '' });
                if (token) {
                    testNotification(token);
                } else {
                    showBanniere('Veuillez d\'abord activer les notifications.', 'notif-warning');
                }
            } catch (e) {
                showBanniere('Erreur : ' + e.message, 'notif-danger');
            }
        });
    }

    // Réinitialiser le badge quand l'utilisateur ouvre le panneau de notifications
    document.querySelectorAll('[data-open-notifications]').forEach(el => {
        el.addEventListener('click', () => reinitialiserBadge());
    });
});

// Exposer les fonctions utiles globalement
window.fcm = {
    requestPermission: requestNotificationPermission,
    showBanniere,
    reinitialiserBadge,
    incrementerBadge,
};
