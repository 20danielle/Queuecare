<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/PatientModel.php';
require_once __DIR__ . '/../models/ConsultationModel.php';
require_once __DIR__ . '/../models/MedecinModel.php';
require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/../models/PasswordResetModel.php';
require_once __DIR__ . '/../helpers/MailHelper.php';

class MobileApiController
{
    private PDO $db;
    private PatientModel $patientModel;
    private ConsultationModel $consultationModel;
    private MedecinModel $medecinModel;
    private NotificationModel $notificationModel;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->patientModel = new PatientModel();
        $this->consultationModel = new ConsultationModel();
        $this->medecinModel = new MedecinModel();
        $this->notificationModel = new NotificationModel();
    }

    public function dispatch(string $method, string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $path = '/' . trim($path, '/');

        if ($method === 'POST' && $path === '/api/auth/login/patient') {
            $this->loginPatient();
        } elseif ($method === 'POST' && $path === '/api/auth/register/patient') {
            $this->registerPatient();
        } elseif ($method === 'POST' && $path === '/api/auth/login/google/patient') {
            $this->loginGooglePatient();
        } elseif ($method === 'POST' && $path === '/api/auth/password/forgot') {
            $this->forgotPassword();
        } elseif ($method === 'POST' && $path === '/api/auth/password/reset') {
            $this->resetPassword();
        } elseif ($method === 'POST' && $path === '/api/auth/fcm/update') {
            $this->updateFcmToken();
        } elseif ($method === 'GET' && $path === '/api/services') {
            $this->services();
        } elseif ($method === 'GET' && preg_match('#^/api/services/(\d+)/sous-services$#', $path, $m)) {
            $this->subServices((int)$m[1]);
        } elseif ($method === 'GET' && preg_match('#^/api/consultations/creneaux-disponibles/(\d+)$#', $path, $m)) {
            $this->availableSlots((int)$m[1]);
        } elseif ($method === 'POST' && $path === '/api/consultations/rdv-distance') {
            $this->createRemoteAppointment();
        } elseif ($method === 'GET' && preg_match('#^/api/notifications/historique/(\d+)$#', $path, $m)) {
            $this->notificationHistory((int)$m[1]);
        } else {
            $this->json(false, 'Endpoint mobile introuvable.', null, 404);
        }
    }

    private function input(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }

    private function json(bool $success, string $message = '', mixed $data = null, int $httpCode = 200, array $extra = []): void
    {
        http_response_code($httpCode);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $extra));
        exit;
    }

    private function bearerToken(): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        return preg_match('/Bearer\s+(\S+)/', $header, $m) ? $m[1] : null;
    }

    private function authenticatedPatientId(): ?int
    {
        $token = $this->bearerToken();
        if (!$token) return null;

        $stmt = $this->db->prepare('SELECT patient_id FROM patient_api_tokens WHERE token = :token AND expire_at > NOW() LIMIT 1');
        $stmt->execute([':token' => $token]);
        $patientId = $stmt->fetchColumn();
        return $patientId ? (int)$patientId : null;
    }

    private function createApiToken(int $patientId): array
    {
        $apiToken = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->db->prepare('DELETE FROM patient_api_tokens WHERE patient_id = :pid')->execute([':pid' => $patientId]);
        $stmt = $this->db->prepare('INSERT INTO patient_api_tokens (patient_id, token, expire_at) VALUES (:pid, :token, :expire_at)');
        $stmt->execute([':pid' => $patientId, ':token' => $apiToken, ':expire_at' => $expireAt]);

        return [$apiToken, $expireAt];
    }

    private function patientPayload(array $patient): array
    {
        return [
            'id' => (int)$patient['id'],
            'nom' => $patient['nom'] ?? '',
            'prenom' => $patient['prenom'] ?? '',
            'telephone' => $patient['telephone'] ?? '',
            'email' => $patient['email'] ?? '',
            'statut' => $patient['statut'] ?? 'actif',
        ];
    }

    private function loginPatient(): void
    {
        $input = $this->input();
        $email = strtolower(trim($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $this->json(false, 'Email valide et mot de passe requis.', null, 400);
        }

        $patient = $this->patientModel->verifierConnexion($email, $password);
        if (!$patient) {
            $this->json(false, 'Email ou mot de passe incorrect.', null, 401);
        }

        if (!empty($input['token_fcm'])) {
            $this->patientModel->mettreAJourTokenFCM((int)$patient['id'], trim($input['token_fcm']));
            $patient = $this->patientModel->trouverParId((int)$patient['id']);
        }

        [$token] = $this->createApiToken((int)$patient['id']);
        $this->json(true, 'Connexion reussie.', [
            'token' => $token,
            'patient' => $this->patientPayload($patient),
        ]);
    }

    private function registerPatient(): void
    {
        $input = $this->input();
        $email = strtolower(trim($input['email'] ?? ''));
        $telephone = trim($input['telephone'] ?? '');
        $password = $input['password'] ?? '';

        $errors = [];
        if (mb_strlen(trim($input['nom'] ?? '')) < 2) $errors['nom'] = 'Nom trop court.';
        if (mb_strlen(trim($input['prenom'] ?? '')) < 2) $errors['prenom'] = 'Prenom trop court.';
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) $errors['telephone'] = 'Telephone invalide.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Adresse email invalide.';
        if ($this->patientModel->emailExiste($email)) $errors['email'] = 'Cette adresse email est deja utilisee.';
        if ($this->patientModel->telephoneExiste($telephone)) $errors['telephone'] = 'Ce telephone est deja utilise.';
        if (strlen($password) < 6) $errors['password'] = 'Mot de passe trop court.';

        if ($errors) {
            $this->json(false, 'Erreurs de validation.', null, 400, ['error' => ['message' => implode(' ', $errors)], 'errors' => $errors]);
        }

        $patientId = $this->patientModel->creer([
            'nom' => $input['nom'],
            'prenom' => $input['prenom'],
            'telephone' => $telephone,
            'email' => $email,
            'password' => $password,
            'token_fcm' => trim($input['token_fcm'] ?? '') ?: null,
        ]);
        if (!$patientId) {
            $this->json(false, 'Inscription impossible.', null, 500);
        }

        $patient = $this->patientModel->trouverParId((int)$patientId);
        [$token] = $this->createApiToken((int)$patientId);
        $this->json(true, 'Inscription reussie.', ['token' => $token, 'patient' => $this->patientPayload($patient)]);
    }

    private function loginGooglePatient(): void
    {
        $input = $this->input();
        $email = strtolower(trim($input['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(false, 'Adresse email Google invalide.', null, 400);
        }

        $patient = $this->patientModel->trouverParEmail($email);
        if (!$patient) {
            $patientId = $this->patientModel->creer([
                'nom' => trim($input['nom'] ?? 'Patient'),
                'prenom' => trim($input['prenom'] ?? ''),
                'telephone' => 'google-' . substr(sha1($email), 0, 12),
                'email' => $email,
                'password' => bin2hex(random_bytes(12)),
                'token_fcm' => trim($input['token_fcm'] ?? '') ?: null,
            ]);
            $patient = $this->patientModel->trouverParId((int)$patientId);
        } elseif (!empty($input['token_fcm'])) {
            $this->patientModel->mettreAJourTokenFCM((int)$patient['id'], trim($input['token_fcm']));
            $patient = $this->patientModel->trouverParId((int)$patient['id']);
        }

        [$token] = $this->createApiToken((int)$patient['id']);
        $this->json(true, 'Connexion Google reussie.', ['token' => $token, 'patient' => $this->patientPayload($patient)]);
    }

    private function forgotPassword(): void
    {
        $input = $this->input();
        $email = strtolower(trim($input['contact'] ?? $input['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(false, 'Adresse email valide requise pour envoyer le code OTP.', null, 400);
        }

        $patient = $this->patientModel->trouverParEmail($email);
        if ($patient && ($patient['statut'] ?? '') === 'actif') {
            $code = MailHelper::genererCode(defined('RESET_CODE_LONGUEUR') ? RESET_CODE_LONGUEUR : 6);
            (new PasswordResetModel())->creerCode($email, $code);
            MailHelper::envoyerCodeReset($email, trim(($patient['prenom'] ?? '') . ' ' . ($patient['nom'] ?? '')), $code);
        }

        $this->json(true, 'Si cet email est enregistre, un code OTP a ete envoye.');
    }

    private function resetPassword(): void
    {
        $input = $this->input();
        $email = strtolower(trim($input['contact'] ?? ''));
        $otp = trim($input['otp_code'] ?? '');
        $password = $input['new_password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(false, 'Adresse email valide requise.', null, 400);
        }
        if (strlen($otp) < 4 || strlen($password) < 6) {
            $this->json(false, 'Code OTP ou mot de passe invalide.', null, 400);
        }

        $patient = $this->patientModel->trouverParEmail($email);
        if (!$patient || !(new PasswordResetModel())->verifierEtConsommerCode($email, $otp)) {
            $this->json(false, 'Code OTP invalide ou expire.', null, 400);
        }

        $this->patientModel->mettreAJourMotDePasse((int)$patient['id'], $password);
        $this->db->prepare('DELETE FROM patient_api_tokens WHERE patient_id = :pid')->execute([':pid' => $patient['id']]);
        $this->json(true, 'Mot de passe mis a jour.');
    }

    private function updateFcmToken(): void
    {
        $patientId = $this->authenticatedPatientId();
        if (!$patientId) $this->json(false, 'Non authentifie.', null, 401);

        $input = $this->input();
        $token = trim($input['token_fcm'] ?? '');
        if ($token === '') $this->json(false, 'Token FCM requis.', null, 400);

        $this->patientModel->mettreAJourTokenFCM($patientId, $token);
        $this->json(true, 'Token FCM mis a jour.');
    }

    private function services(): void
    {
        $stmt = $this->db->query('SELECT id, nom FROM services WHERE statut = "actif" ORDER BY nom');
        $this->json(true, 'Services recuperes.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function subServices(int $serviceId): void
    {
        $stmt = $this->db->prepare('SELECT id, nom, COALESCE(duree_rdv_defaut, duree_estimee) AS duree_rdv FROM sous_services WHERE service_id = :sid AND statut = "actif" ORDER BY nom');
        $stmt->execute([':sid' => $serviceId]);
        $this->json(true, 'Sous-services recuperes.', $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function doctorsForSubService(int $subServiceId): array
    {
        return $this->consultationModel->getTousMedecins($subServiceId);
    }

    private function availableSlots(int $subServiceId): void
    {
        $date = trim($_GET['date'] ?? date('Y-m-d', strtotime('+1 day')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date <= date('Y-m-d')) {
            $this->json(true, 'La date doit etre dans le futur.', []);
        }

        $byHour = [];
        foreach ($this->doctorsForSubService($subServiceId) as $doctor) {
            foreach ($this->medecinModel->getCreneauxDisponibles((int)$doctor['id'], $date) as $slot) {
                $hour = $slot['heure'] ?? null;
                if (!$hour) continue;
                if (!isset($byHour[$hour])) {
                    $byHour[$hour] = ['heure' => $hour, 'date' => $date, 'disponible' => true];
                }
            }
        }

        ksort($byHour);
        $this->json(true, 'Creneaux recuperes.', array_values($byHour));
    }

    private function createRemoteAppointment(): void
    {
        $patientId = $this->authenticatedPatientId();
        if (!$patientId) $this->json(false, 'Non authentifie.', null, 401);

        $input = $this->input();
        $subServiceId = (int)($input['ss_id'] ?? 0);
        $date = trim($input['date_rdv'] ?? '');
        $hour = trim($input['heure_rdv'] ?? '');
        if (!$subServiceId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $hour)) {
            $this->json(false, 'Sous-service, date et heure requis.', null, 400);
        }

        foreach ($this->doctorsForSubService($subServiceId) as $doctor) {
            foreach ($this->medecinModel->getCreneauxDisponibles((int)$doctor['id'], $date) as $slot) {
                if (($slot['heure'] ?? '') === $hour) {
                    $result = $this->medecinModel->reserverRdvPatient($patientId, (int)$doctor['id'], $date, $hour, 'Rendez-vous en ligne');
                    if ($result['success']) {
                        $this->json(true, $result['message'], [
                            'id' => (int)$result['rdv_id'],
                            'statut' => 'confirme',
                            'message' => $result['message'],
                        ]);
                    }
                    $this->json(false, $result['message'], null, 409);
                }
            }
        }

        $this->json(false, 'Ce creneau n\'est plus disponible.', null, 409);
    }

    private function notificationHistory(int $patientIdFromPath): void
    {
        $patientId = $this->authenticatedPatientId();
        if (!$patientId || $patientId !== $patientIdFromPath) {
            $this->json(false, 'Non authentifie.', null, 401);
        }

        $this->json(true, 'Notifications recuperees.', $this->notificationModel->getNotificationsPatient($patientId, 50, 0));
    }
}
