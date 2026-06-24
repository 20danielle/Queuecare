/**
 * public/firebase-messaging-sw.js
 * Service Worker pour les notifications Firebase
 */

// Importer Firebase
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.22.0/firebase-messaging-compat.js');

// Configuration Firebase (identique à firebase-init.js)
const firebaseConfig = {
    apiKey: "AIzaSyDSWyPMr_cak10gNUYNU94khLZe7Mrge6s",
    authDomain: "smartqueue-37328.firebaseapp.com",
    projectId: "smartqueue-37328",
    storageBucket: "smartqueue-37328.firebasestorage.app",
    messagingSenderId: "87084283644",
    appId: "1:87084283644:web:0b7160836195a66a050979"
};

// Initialiser Firebase
firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Cache pour les assets (optionnel)
const CACHE_NAME = 'queuecare-cache-v1';
const urlsToCache = [
    '/',
    '/public/css/medecin.css',
    '/public/css/gestionnaire.css',
    '/public/images/logo.png',
    '/public/images/badge.png'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
        .then((cache) => cache.addAll(urlsToCache))
    );
});

self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
        .then((response) => response || fetch(event.request))
    );
});

/**
 * Gérer les messages en arrière-plan
 */
messaging.onBackgroundMessage((payload) => {
    console.log('[Service Worker] Message reçu:', payload);

    const notificationTitle = payload.notification?.title || 'QueueCare';
    const notificationOptions = {
        body: payload.notification?.body || 'Nouvelle notification',
        icon: '/public/images/logo.png',
        badge: '/public/images/badge.png',
        vibrate: [200, 100, 200],
        data: payload.data || {},
        actions: payload.data?.actions || [{
                action: 'open',
                title: 'Voir'
            },
            {
                action: 'close',
                title: 'Fermer'
            }
        ]
    };

    // Ajouter l'image si présente
    if (payload.notification?.image) {
        notificationOptions.image = payload.notification.image;
    }

    return self.registration.showNotification(notificationTitle, notificationOptions);
});

/**
 * Gérer le clic sur une notification
 */
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const urlToOpen = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then((windowClients) => {
            // Vérifier si une fenêtre est déjà ouverte
            for (let client of windowClients) {
                if (client.url === urlToOpen && 'focus' in client) {
                    return client.focus();
                }
            }
            // Sinon ouvrir une nouvelle fenêtre
            if (clients.openWindow) {
                return clients.openWindow(urlToOpen);
            }
        })
    );
});

/**
 * Gérer la fermeture de notification
 */
self.addEventListener('notificationclose', (event) => {
    console.log('Notification fermée:', event.notification);
});
