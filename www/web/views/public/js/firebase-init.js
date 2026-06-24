/**
 * public/js/firebase-init.js
 * Initialisation de Firebase côté client
 */

// Configuration Firebase (vos valeurs)
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

// État de l'initialisation
let isInitialized = false;

/**
 * Demander la permission de notification et obtenir le token
 */
async function requestNotificationPermission() {
    try {
        console.log('Demande de permission de notification...');

        // Demander la permission
        const permission = await Notification.requestPermission();

        if (permission === 'granted') {
            console.log('✅ Permission de notification accordée');

            // Obtenir le token FCM
            const token = await messaging.getToken({
                vapidKey: 'VOTRE_VAPID_PUBLIC_KEY' // À remplacer
            });

            console.log('📱 Token FCM:', token);

            // Envoyer le token au serveur
            const saved = await enregistrerToken(token);

            if (saved) {
                showNotificationStatus('Notifications activées', 'success');
            }

            return token;
        } else {
            console.log('❌ Permission de notification refusée');
            showNotificationStatus('Notifications désactivées', 'error');
            return null;
        }
    } catch (error) {
        console.error('Erreur lors de la demande de permission:', error);
        showNotificationStatus('Erreur: ' + error.message, 'error');
        return null;
    }
}

/**
 * Envoyer le token au serveur
 */
async function enregistrerToken(token) {
    try {
        const response = await fetch('index.php?action=save_fcm_token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                token: token,
                user_agent: navigator.userAgent
            })
        });

        const result = await response.json();

        if (result.success) {
            console.log('✅ Token enregistré sur le serveur');
            return true;
        } else {
            console.error('❌ Erreur serveur:', result.message);
            return false;
        }
    } catch (error) {
        console.error('Erreur lors de l\'enregistrement:', error);
        return false;
    }
}

/**
 * Afficher le statut des notifications dans l'interface
 */
function showNotificationStatus(message, type) {
    const statusDiv = document.getElementById('notificationStatus');
    if (!statusDiv) return;

    statusDiv.innerHTML = message;
    statusDiv.className = `notification-status ${type}`;
    statusDiv.style.display = 'block';

    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}

/**
 * Vérifier si les notifications sont déjà activées
 */
async function checkNotificationStatus() {
    const permission = Notification.permission;
    const statusDiv = document.getElementById('notificationStatus');

    if (permission === 'granted') {
        // Vérifier si le token existe encore
        try {
            const token = await messaging.getToken();
            if (token) {
                if (statusDiv) {
                    statusDiv.innerHTML = '🔔 Notifications activées';
                    statusDiv.className = 'notification-status success';
                    statusDiv.style.display = 'block';
                }
            }
        } catch (e) {
            console.log('Token non disponible');
        }
    } else if (permission === 'denied') {
        if (statusDiv) {
            statusDiv.innerHTML = '🔕 Notifications bloquées. Veuillez autoriser dans les paramètres du navigateur.';
            statusDiv.className = 'notification-status error';
            statusDiv.style.display = 'block';
        }
    }
}

/**
 * Écouter les messages au premier plan
 */
messaging.onMessage((payload) => {
    console.log('📨 Message reçu au premier plan:', payload);

    // Afficher une notification même au premier plan
    if (Notification.permission === 'granted') {
        const notification = new Notification(payload.notification.title, {
            body: payload.notification.body,
            icon: '/public/images/logo.png',
            badge: '/public/images/badge.png',
            data: payload.data,
            requireInteraction: true
        });

        // Gérer le clic sur la notification
        notification.onclick = () => {
            window.focus();
            if (payload.data && payload.data.url) {
                window.location.href = payload.data.url;
            }
            notification.close();
        };
    }

    // Déclencher un événement personnalisé pour l'interface
    const event = new CustomEvent('notification-received', {
        detail: {
            title: payload.notification.title,
            body: payload.notification.body,
            data: payload.data
        }
    });
    document.dispatchEvent(event);

    // Mettre à jour le compteur de notifications
    updateNotificationBadge();
});

/**
 * Mettre à jour le badge de notification
 */
function updateNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;

    let count = parseInt(badge.dataset.count || '0');
    count++;
    badge.dataset.count = count;
    badge.textContent = count;
    badge.style.display = count > 0 ? 'flex' : 'none';
}

/**
 * Réinitialiser le badge de notification
 */
function resetNotificationBadge() {
    const badge = document.getElementById('notificationBadge');
    if (badge) {
        badge.dataset.count = '0';
        badge.textContent = '0';
        badge.style.display = 'none';
    }
}

/**
 * Initialisation des notifications
 */
async function initNotifications() {
    if (isInitialized) return;

    // Vérifier si les notifications sont supportées
    if (!('Notification' in window)) {
        console.warn('Ce navigateur ne supporte pas les notifications');
        return;
    }

    if (!('serviceWorker' in navigator)) {
        console.warn('Service Worker non supporté');
        return;
    }

    try {
        // Enregistrer le service worker
        const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
        console.log('✅ Service Worker enregistré:', registration);

        // Vérifier le statut actuel
        await checkNotificationStatus();

        isInitialized = true;
    } catch (error) {
        console.error('Erreur lors de l\'initialisation:', error);
    }
}

// Écouter le chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    initNotifications();

    // Bouton d'activation des notifications
    const enableBtn = document.getElementById('enableNotifications');
    if (enableBtn) {
        enableBtn.addEventListener('click', (e) => {
            e.preventDefault();
            requestNotificationPermission();
        });
    }

    // Bouton pour tester les notifications
    const testBtn = document.getElementById('testNotification');
    if (testBtn) {
        testBtn.addEventListener('click', async() => {
            const token = await messaging.getToken();
            if (token) {
                testNotification(token);
            } else {
                alert('Veuillez d\'abord activer les notifications');
            }
        });
    }
});

/**
 * Tester l'envoi d'une notification
 */
async function testNotification(token) {
    try {
        const response = await fetch('index.php?action=test_notification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ token: token })
        });

        const result = await response.json();

        if (result.success) {
            alert('✅ Notification envoyée !');
        } else {
            alert('❌ Erreur: ' + result.message);
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'envoi');
    }
}