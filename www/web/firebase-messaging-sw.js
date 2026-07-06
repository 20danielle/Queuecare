/**
 * firebase-messaging-sw.js
 * Service Worker Firebase Cloud Messaging — QueueCare Hôpital
 *
 * Gère les notifications en arrière-plan avec des actions et des
 * comportements différenciés selon le type d'événement de file d'attente.
 */

importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

const firebaseConfig = {
    apiKey:            "AIzaSyDSWyPMr_cak10gNUYNU94khLZe7Mrge6s",
    authDomain:        "smartqueue-37328.firebaseapp.com",
    projectId:         "smartqueue-37328",
    storageBucket:     "smartqueue-37328.firebasestorage.app",
    messagingSenderId: "87084283644",
    appId:             "1:87084283644:web:0b7160836195a66a050979"
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

/* ──────────────────────────────────────────────────────────
   Configuration par type de notification
────────────────────────────────────────────────────────── */

const NOTIF_CONFIG = {
    APPEL_IMMEDIAT: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [300, 100, 300, 100, 300],
        requireInteraction: true,
        tag:                'appel-immediat',
        renotify:           true,
        actions: [
            { action: 'arrive',  title: 'J\'arrive !' },
            { action: 'retard',  title: 'Légèrement en retard' }
        ]
    },
    RANG_SUIVANT: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [200, 100, 200],
        requireInteraction: true,
        tag:                'rang-suivant',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Voir ma position' },
            { action: 'close',  title: 'OK' }
        ]
    },
    RAPPEL_30MIN: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [200, 100, 200],
        requireInteraction: true,
        tag:                'rappel-30min',
        renotify:           false,
        actions: [
            { action: 'open',   title: 'Voir ma file' },
            { action: 'close',  title: 'Compris' }
        ]
    },
    AVANCEMENT: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [100],
        requireInteraction: false,
        tag:                'avancement-file',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Voir ma position' }
        ]
    },
    CLOTURE_ABSENT: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [300, 100, 300],
        requireInteraction: true,
        tag:                'absent',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Contacter l\'accueil' },
            { action: 'close',  title: 'Fermer' }
        ]
    },
    ANNULATION: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [200, 100, 200],
        requireInteraction: true,
        tag:                'annulation',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Plus d\'infos' },
            { action: 'close',  title: 'Fermer' }
        ]
    },
    CONFIRMATION: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [100],
        requireInteraction: false,
        tag:                'confirmation',
        actions: [
            { action: 'open',   title: 'Voir mon ticket' }
        ]
    },
    DECALAGE: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [200],
        requireInteraction: false,
        tag:                'decalage-horaire',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Voir les nouveaux horaires' }
        ]
    },
    REAFFECTATION_MEDECIN: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [300, 100, 300],
        requireInteraction: true,
        tag:                'reaffectation-medecin',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Voir ma nouvelle position' },
            { action: 'close',  title: 'Fermer' }
        ]
    },
    URGENCE: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [500, 100, 500, 100, 500],
        requireInteraction: true,
        tag:                'urgence',
        renotify:           true,
        actions: [
            { action: 'open',   title: 'Voir l\'information' },
            { action: 'close',  title: 'Fermer' }
        ]
    },
    MISE_EN_PAUSE: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [100, 50, 100],
        requireInteraction: false,
        tag:                'pause',
        actions: [
            { action: 'open',   title: 'Voir ma consultation' }
        ]
    },
    RETOUR_EXAMEN: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [300, 100, 300],
        requireInteraction: true,
        tag:                'retour-examen',
        renotify:           true,
        actions: [
            { action: 'arrive', title: 'J\'arrive !' },
            { action: 'close',  title: 'Fermer' }
        ]
    },
    default: {
        icon:               '/public/images/logo.png',
        badge:              '/public/images/badge.png',
        vibrate:            [200, 100, 200],
        requireInteraction: false,
        tag:                'default',
        actions: [
            { action: 'open',   title: 'Voir' },
            { action: 'close',  title: 'Fermer' }
        ]
    }
};

/* ──────────────────────────────────────────────────────────
   Cache assets statiques
────────────────────────────────────────────────────────── */

const CACHE_NAME   = 'queuecare-v2';
const CACHE_ASSETS = [
    '/public/images/logo.png',
    '/public/images/badge.png',
];

self.addEventListener('install', (event) => {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache =>
            cache.addAll(CACHE_ASSETS.filter(Boolean))
        ).catch(() => {/* Images optionnelles — ne pas bloquer l'install */})
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            )
        ).then(() => clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    // Ne mettre en cache que les assets statiques connus
    if (CACHE_ASSETS.some(a => event.request.url.endsWith(a))) {
        event.respondWith(
            caches.match(event.request).then(r => r || fetch(event.request))
        );
    }
});

/* ──────────────────────────────────────────────────────────
   Réception des messages en arrière-plan
────────────────────────────────────────────────────────── */

messaging.onBackgroundMessage((payload) => {
    console.log('[SW] Message arrière-plan reçu:', payload);

    const data   = payload.data || {};
    const notif  = payload.notification || {};
    const type   = data.type || 'default';
    const config = NOTIF_CONFIG[type] || NOTIF_CONFIG.default;

    const title   = notif.title || 'QueueCare';
    const options = {
        body:               notif.body || 'Nouvelle notification',
        ...config,
        data: {
            ...data,
            url: data.url || '/patient/dashboard.php',
            type
        }
    };

    return self.registration.showNotification(title, options);
});

/* ──────────────────────────────────────────────────────────
   Clic sur la notification / actions
────────────────────────────────────────────────────────── */

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data   = event.notification.data || {};
    const action = event.action;
    const type   = data.type || 'default';

    // Action "close" → juste fermer
    if (action === 'close') return;

    // Toutes les autres actions → ouvrir/focuser l'application
    let url = data.url || '/patient/dashboard.php';

    // Actions contextuelles
    if (action === 'arrive' || action === 'open') {
        url = data.url || '/patient/dashboard.php';
    }
    if (action === 'retard') {
        // Pourrait déclencher une API pour signaler le retard
        url = data.url || '/patient/dashboard.php';
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((windowClients) => {
                // Focus sur une fenêtre existante de l'app
                for (const client of windowClients) {
                    if ('focus' in client) {
                        // Envoyer un message à la page pour déclencher une action
                        client.postMessage({
                            type:            'NOTIFICATION_ACTION',
                            notifType:       type,
                            action:          action,
                            consultationId:  data.consultation_id,
                            rang:            data.rang,
                            url
                        });
                        return client.focus();
                    }
                }
                // Pas de fenêtre ouverte → en ouvrir une
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

/* ──────────────────────────────────────────────────────────
   Fermeture de notification (analytics optionnel)
────────────────────────────────────────────────────────── */

self.addEventListener('notificationclose', (event) => {
    const data = event.notification.data || {};
    console.log('[SW] Notification fermée:', data.type, data.consultation_id);
    // Ici on pourrait appeler une API pour tracker les dismissals
});

/* ──────────────────────────────────────────────────────────
   Messages depuis la page principale (postMessage)
────────────────────────────────────────────────────────── */

self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});