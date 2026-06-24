<?php
/**
 * config/firebase.php
 * Configuration Firebase pour les notifications push.
 */

function firebaseEnv(string $key, string $default = ''): string
{
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

// Configuration Firebase publique.
define('FIREBASE_API_KEY', firebaseEnv('FIREBASE_API_KEY', 'AIzaSyDSWyPMr_cak10gNUYNU94khLZe7Mrge6s'));
define('FIREBASE_AUTH_DOMAIN', firebaseEnv('FIREBASE_AUTH_DOMAIN', 'smartqueue-37328.firebaseapp.com'));
define('FIREBASE_PROJECT_ID', firebaseEnv('FIREBASE_PROJECT_ID', 'smartqueue-37328'));
define('FIREBASE_STORAGE_BUCKET', firebaseEnv('FIREBASE_STORAGE_BUCKET', 'smartqueue-37328.firebasestorage.app'));
define('FIREBASE_MESSAGING_SENDER_ID', firebaseEnv('FIREBASE_MESSAGING_SENDER_ID', '87084283644'));
define('FIREBASE_APP_ID', firebaseEnv('FIREBASE_APP_ID', '1:87084283644:web:0b7160836195a66a050979'));

// FCM HTTP v1: coller le JSON du compte de service dans cette variable Railway.
define('FIREBASE_SERVICE_ACCOUNT_JSON', firebaseEnv('FIREBASE_SERVICE_ACCOUNT_JSON'));

// Web push: cle publique visible dans Firebase > Cloud Messaging > Certificats Web push.
define('VAPID_PUBLIC_KEY', firebaseEnv('VAPID_PUBLIC_KEY'));

// Repli ancienne API si elle est active sur un autre projet.
define('FCM_SERVER_KEY', firebaseEnv('FCM_SERVER_KEY'));
define('FCM_API_URL', 'https://fcm.googleapis.com/fcm/send');
