<?php
/**
 * helpers/NotificationHelper.php
 * Helper pour l'envoi de notifications push via Firebase Cloud Messaging.
 */

require_once __DIR__ . '/../config/firebase.php';

class NotificationHelper
{
    private static ?NotificationHelper $instance = null;
    private string $serverKey;
    private string $legacyApiUrl;
    private string $projectId;
    private string $serviceAccountJson;
    private ?string $accessToken = null;
    private int $accessTokenExpiresAt = 0;

    private function __construct()
    {
        $this->serverKey = defined('FCM_SERVER_KEY') ? FCM_SERVER_KEY : '';
        $this->legacyApiUrl = defined('FCM_API_URL') ? FCM_API_URL : 'https://fcm.googleapis.com/fcm/send';
        $this->projectId = defined('FIREBASE_PROJECT_ID') ? FIREBASE_PROJECT_ID : '';
        $this->serviceAccountJson = defined('FIREBASE_SERVICE_ACCOUNT_JSON') ? FIREBASE_SERVICE_ACCOUNT_JSON : '';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function sendToDevice(string $token, string $title, string $body, array $data = []): array
    {
        if (empty($token)) {
            return ['success' => false, 'message' => 'Token FCM manquant'];
        }

        if ($this->canUseHttpV1()) {
            return $this->sendHttpV1([
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $this->stringifyData($data),
                'webpush' => [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                        'icon' => '/public/images/logo.png',
                        'badge' => '/public/images/badge.png',
                    ],
                    'fcm_options' => [
                        'link' => $data['url'] ?? '/',
                    ],
                ],
                'android' => ['priority' => 'high'],
                'apns' => [
                    'payload' => [
                        'aps' => ['sound' => 'default', 'badge' => 1],
                    ],
                ],
            ]);
        }

        return $this->sendLegacy([
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => 1,
                'icon' => '/public/images/logo.png',
            ],
            'data' => array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default',
            ]),
            'priority' => 'high',
        ]);
    }

    public function sendToMultipleDevices(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_filter($tokens));
        if (empty($tokens)) {
            return ['success' => false, 'message' => 'Aucun token fourni'];
        }

        if (!$this->canUseHttpV1()) {
            return $this->sendLegacy([
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => 1,
                    'icon' => '/public/images/logo.png',
                ],
                'data' => array_merge($data, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'sound' => 'default',
                ]),
                'priority' => 'high',
            ]);
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($tokens as $token) {
            $result = $this->sendToDevice($token, $title, $body, $data);
            if ($result['success']) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $result['message'] ?? 'Erreur inconnue';
            }
        }

        return ['success' => $sent > 0, 'sent' => $sent, 'failed' => $failed, 'errors' => $errors];
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if ($this->canUseHttpV1()) {
            return $this->sendHttpV1([
                'topic' => $topic,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $this->stringifyData($data),
            ]);
        }

        return $this->sendLegacy([
            'to' => '/topics/' . $topic,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
                'badge' => 1,
                'icon' => '/public/images/logo.png',
            ],
            'data' => array_merge($data, [
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'sound' => 'default',
            ]),
            'priority' => 'high',
        ]);
    }

    private function canUseHttpV1(): bool
    {
        return $this->projectId !== '' && $this->serviceAccountJson !== '';
    }

    private function sendHttpV1(array $message): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => 'Compte de service Firebase non configure ou invalide',
            ];
        }

        $url = sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($this->projectId));
        $result = $this->postJson($url, ['message' => $message], [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);

        return [
            'success' => $result['http_code'] >= 200 && $result['http_code'] < 300,
            'http_code' => $result['http_code'],
            'response' => $result['response'],
            'message' => $result['error'] ?: ($result['http_code'] < 300 ? 'Notification envoyee' : $this->extractError($result['response'])),
        ];
    }

    private function sendLegacy(array $payload): array
    {
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'message' => 'FCM HTTP v1 non configure et ancienne cle serveur FCM absente',
            ];
        }

        $result = $this->postJson($this->legacyApiUrl, $payload, [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json',
        ]);

        $response = is_array($result['response']) ? $result['response'] : [];

        return [
            'success' => $result['http_code'] === 200 && isset($response['success']) && $response['success'] > 0,
            'http_code' => $result['http_code'],
            'response' => $response,
            'message' => $result['error'] ?: $this->getLegacyResponseMessage($response),
        ];
    }

    private function postJson(string $url, array $payload, array $headers): array
    {
        if (!function_exists('curl_init')) {
            return ['http_code' => 0, 'response' => null, 'error' => 'Extension PHP cURL non activee'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'response' => json_decode((string) $raw, true),
            'error' => $error,
        ];
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken && time() < $this->accessTokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $account = json_decode($this->serviceAccountJson, true);
        if (!is_array($account) || empty($account['client_email']) || empty($account['private_key'])) {
            return null;
        }

        $now = time();
        $jwtHeader = ['alg' => 'RS256', 'typ' => 'JWT'];
        $jwtClaim = [
            'iss' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $unsignedJwt = $this->base64UrlEncode(json_encode($jwtHeader)) . '.' . $this->base64UrlEncode(json_encode($jwtClaim));
        $signature = '';
        if (!openssl_sign($unsignedJwt, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);
        $result = $this->postForm('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if ($result['http_code'] !== 200 || empty($result['response']['access_token'])) {
            return null;
        }

        $this->accessToken = $result['response']['access_token'];
        $this->accessTokenExpiresAt = $now + (int) ($result['response']['expires_in'] ?? 3600);

        return $this->accessToken;
    }

    private function postForm(string $url, array $fields): array
    {
        if (!function_exists('curl_init')) {
            return ['http_code' => 0, 'response' => null, 'error' => 'Extension PHP cURL non activee'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'http_code' => $httpCode,
            'response' => json_decode((string) $raw, true),
            'error' => $error,
        ];
    }

    private function stringifyData(array $data): array
    {
        $data = array_merge($data, [
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'sound' => 'default',
        ]);

        return array_map(static fn($value) => is_scalar($value) ? (string) $value : json_encode($value), $data);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function extractError($response): string
    {
        if (is_array($response) && isset($response['error']['message'])) {
            return 'Erreur FCM: ' . $response['error']['message'];
        }

        return 'Erreur FCM inconnue';
    }

    private function getLegacyResponseMessage(array $response): string
    {
        if (isset($response['success']) && $response['success'] > 0) {
            return 'Notification envoyee avec succes';
        }

        if (isset($response['results'][0]['error'])) {
            return 'Erreur FCM: ' . $response['results'][0]['error'];
        }

        if (isset($response['error'])) {
            return 'Erreur: ' . $response['error'];
        }

        return 'Erreur inconnue';
    }

    public function notifyConsultationReminder(string $token, array $consultation): array
    {
        $patientName = ($consultation['patient_nom'] ?? '') . ' ' . ($consultation['patient_prenom'] ?? '');
        $heure = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        $date = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'now'));

        return $this->sendToDevice($token, 'Rappel de consultation', "Consultation avec {$patientName} le {$date} a {$heure}", [
            'type' => 'consultation_reminder',
            'consultation_id' => $consultation['id'] ?? 0,
            'heure' => $heure,
            'date' => $date,
            'url' => '/patient/dashboard.php',
        ]);
    }

    public function notifyPatientCalled(string $token, array $consultation): array
    {
        $medecinName = $consultation['medecin_nom'] ?? 'le medecin';

        return $this->sendToDevice($token, 'Vous etes appele(e)', "{$medecinName} est pret(e) a vous recevoir.", [
            'type' => 'patient_called',
            'consultation_id' => $consultation['id'] ?? 0,
            'url' => '/patient/consultation.php?id=' . ($consultation['id'] ?? 0),
        ]);
    }

    public function notifyDayBeforeReminder(string $token, array $consultation): array
    {
        $date = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'tomorrow'));
        $heure = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? '09:00'));

        return $this->sendToDevice($token, 'Rappel : consultation demain', "Vous avez une consultation le {$date} a {$heure}.", [
            'type' => 'day_before_reminder',
            'consultation_id' => $consultation['id'] ?? 0,
            'url' => '/patient/dashboard.php',
        ]);
    }

    public function notifyAppointmentConfirmed(string $token, array $consultation): array
    {
        $date = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        $heure = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? '09:00'));
        $sousService = $consultation['sous_service_nom'] ?? 'consultation';

        return $this->sendToDevice($token, 'Rendez-vous confirme', "Votre {$sousService} est confirmee pour le {$date} a {$heure}.", [
            'type' => 'appointment_confirmed',
            'consultation_id' => $consultation['id'] ?? 0,
            'url' => '/patient/dashboard.php',
        ]);
    }

    public function notifyCancellation(string $token, array $consultation, string $reason = ''): array
    {
        $message = 'Votre consultation a ete annulee.';
        if ($reason) {
            $message .= " Motif : {$reason}";
        }

        return $this->sendToDevice($token, 'Consultation annulee', $message, [
            'type' => 'cancellation',
            'consultation_id' => $consultation['id'] ?? 0,
            'url' => '/patient/dashboard.php',
        ]);
    }

    public function notifyAppointmentUpdated(string $token, array $consultation, string $oldDate = '', string $oldTime = ''): array
    {
        $newDate = date('d/m/Y', strtotime($consultation['heure_passage_estimee'] ?? 'now'));
        $newTime = date('H:i', strtotime($consultation['heure_passage_estimee'] ?? '09:00'));
        $message = "Votre consultation a ete modifiee. Nouveau creneau: {$newDate} a {$newTime}.";

        return $this->sendToDevice($token, 'Consultation modifiee', $message, [
            'type' => 'appointment_updated',
            'consultation_id' => $consultation['id'] ?? 0,
            'url' => '/patient/dashboard.php',
        ]);
    }

    public function notifyWelcome(string $token, array $patient): array
    {
        return $this->sendToDevice($token, 'Bienvenue sur QueueCare', "Bonjour {$patient['prenom']} {$patient['nom']}, vos notifications sont activees.", [
            'type' => 'welcome',
            'url' => '/patient/dashboard.php',
        ]);
    }

    public function sendTestNotification(string $token): array
    {
        return $this->sendToDevice($token, 'Test de notification', 'Vos notifications fonctionnent correctement.', [
            'type' => 'test',
            'url' => '/',
        ]);
    }

    public function sendBulkDayBeforeReminders(array $consultations, callable $getTokenCallback): array
    {
        $successCount = 0;
        $failCount = 0;
        $errors = [];

        foreach ($consultations as $consultation) {
            $token = $getTokenCallback($consultation['patient_id']);
            if (!$token) {
                $failCount++;
                $errors[] = ['consultation_id' => $consultation['id'], 'error' => 'Patient non abonne aux notifications'];
                continue;
            }

            $result = $this->notifyDayBeforeReminder($token, $consultation);
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
                $errors[] = ['consultation_id' => $consultation['id'], 'error' => $result['message']];
            }
        }

        return ['success' => true, 'sent' => $successCount, 'failed' => $failCount, 'errors' => $errors];
    }

    public function isConfigured(): bool
    {
        return $this->canUseHttpV1() || !empty($this->serverKey);
    }
}
