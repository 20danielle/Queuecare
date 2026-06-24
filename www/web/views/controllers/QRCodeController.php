<?php
/**
 * controllers/QRCodeController.php
 * Contrôleur pour la gestion des QR codes - Version avec ticket virtuel automatique
 */

require_once __DIR__ . '/../models/QRCodeModel.php';
require_once __DIR__ . '/../models/TicketModel.php';
require_once __DIR__ . '/../models/PatientModel.php';
require_once __DIR__ . '/../models/ConsultationModel.php';
require_once __DIR__ . '/../helpers/TicketHelper.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';

// Inclure la librairie PHP QR Code
require_once __DIR__ . '/../vendor/phpqrcode/qrlib.php';


class QRCodeController
{
    private QRCodeModel $model;
    private TicketModel $ticketModel;
    private PatientModel $patientModel;
    private ConsultationModel $consultationModel;
    private ?string $qrcodeDir = null;
    private ?string $initError = null;

    public function __construct()
    {
        try {
            $this->model = new QRCodeModel();
            $this->ticketModel = new TicketModel();
            $this->patientModel = new PatientModel();
            $this->consultationModel = new ConsultationModel();
        } catch (\Throwable $e) {
            // On capture l'erreur au lieu de laisser un die()/exception casser le JSON
            error_log('[QRCode] Erreur initialisation: ' . $e->getMessage());
            $this->initError = 'Erreur d\'initialisation (base de données ?) : ' . $e->getMessage();
            return;
        }
        $this->qrcodeDir = __DIR__ . '/../public/qrcodes/';
        
        // Créer le dossier s'il n'existe pas
        if (!file_exists($this->qrcodeDir)) {
            mkdir($this->qrcodeDir, 0777, true);
        }
    }

    /**
     * Vérifie si le gestionnaire est connecté
     */
    private function checkAuth(): bool
    {
        return isset($_SESSION['gestionnaire_id']);
    }

    private function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }

    /* ═══════════════════════════════════════════════════════════
       GESTION DES QR CODES (PARTIE ADMIN)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Affiche l'interface de génération de QR code
     */
    public function afficherGenerateur(): void
    {
        if (!$this->checkAuth()) {
            if ($this->isAjaxRequest()) {
                echo json_encode(['error' => 'Non authentifié']);
                exit;
            }
            header('Location: gestionnaire.php?action=connexion');
            exit;
        }
        
        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
        
        if (!$sousService) {
            die('Aucun sous-service trouvé pour ce gestionnaire.');
        }
        
        require __DIR__ . '/../views/qrcode/generate.php';
    }

    /**
     * Génère un nouveau QR code (via AJAX)
     */
    public function genererQRCode(): void
    {
        header('Content-Type: application/json');

        if ($this->initError !== null) {
            echo json_encode(['error' => $this->initError]);
            exit;
        }
        
        if (!$this->checkAuth()) {
            echo json_encode(['error' => 'Non authentifié', 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $sousServiceId = $_POST['sous_service_id'] ?? $_SESSION['sous_service_id'] ?? null;
        
        if (!$sousServiceId) {
            $gestionnaireId = $_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
            $sousServiceId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $sousServiceId;
        }
        
        if (!$sousServiceId) {
            echo json_encode(['error' => 'Sous-service non spécifié']);
            exit;
        }
        
        // Vérifier que l'extension GD est disponible (requise pour générer l'image PNG)
        if (!extension_loaded('gd')) {
            echo json_encode(['error' => "L'extension PHP GD n'est pas activée sur le serveur. Activez-la dans php.ini (extension=gd) puis redémarrez le serveur."]);
            exit;
        }

        // Vérifier que le dossier de sortie existe et est accessible en écriture
        if (!is_dir($this->qrcodeDir)) {
            @mkdir($this->qrcodeDir, 0777, true);
        }
        if (!is_dir($this->qrcodeDir) || !is_writable($this->qrcodeDir)) {
            echo json_encode(['error' => 'Le dossier public/qrcodes/ est introuvable ou non accessible en écriture sur le serveur.']);
            exit;
        }

        // Générer un token unique
        $token = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+20 minutes'));
        
        // Créer l'URL du QR code
        $baseUrl    = $this->getBaseUrl();
        $qrContent  = $baseUrl . '/scan_ticket.php?token=' . $token;
        
        // Générer l'image QR code
        $filename = 'qrcode_' . $sousServiceId . '_' . time() . '.png';
        $filepath = $this->qrcodeDir . $filename;
        
        try {
            // Utiliser la librairie PHP QR Code
            \QRcode::png($qrContent, $filepath, QR_ECLEVEL_L, 10);
        } catch (\Throwable $e) {
            error_log('[QRCode] Erreur génération image: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur lors de la génération de l\'image QR code : ' . $e->getMessage()]);
            exit;
        }

        if (!file_exists($filepath)) {
            echo json_encode(['error' => "L'image QR code n'a pas pu être créée sur le disque."]);
            exit;
        }
        
        // Sauvegarder en base de données
        try {
            $qrCodeId = $this->model->saveQRCode([
                'sous_service_id' => $sousServiceId,
                'token' => $token,
                'qr_code_path' => 'public/qrcodes/' . $filename,
                'expire_at' => $expireAt,
                'content' => $qrContent,
                'created_by' => $_SESSION['gestionnaire_id']
            ]);
        } catch (\Throwable $e) {
            error_log('[QRCode] Erreur saveQRCode: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur base de données lors de la sauvegarde : ' . $e->getMessage()]);
            exit;
        }
        
        if ($qrCodeId) {
            // Nettoyer les anciens fichiers
            $this->cleanOldQRCodeFiles($sousServiceId);
            
            echo json_encode([
                'success' => true,
                'qr_code_path' => 'public/qrcodes/' . $filename,
                'token' => $token,
                'expire_at' => $expireAt,
                'content' => $qrContent
            ]);
        } else {
            echo json_encode(['error' => 'Erreur lors de la sauvegarde']);
        }
    }

    private function cleanOldQRCodeFiles(int $sousServiceId): void
    {
        $files = glob($this->qrcodeDir . 'qrcode_' . $sousServiceId . '_*.png');
        if (count($files) > 10) {
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $toDelete = array_slice($files, 10);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Récupère le QR code actif (via AJAX)
     */
    public function getQRCodeActif(): void
    {
        header('Content-Type: application/json');

        if ($this->initError !== null) {
            echo json_encode(['error' => $this->initError]);
            exit;
        }
        
        if (!$this->checkAuth()) {
            echo json_encode(['error' => 'Non authentifié', 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $sousServiceId = $_GET['sous_service_id'] ?? $_SESSION['sous_service_id'] ?? null;
        
        if (!$sousServiceId) {
            $gestionnaireId = $_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
            $sousServiceId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $sousServiceId;
        }
        
        if (!$sousServiceId) {
            echo json_encode(['error' => 'Sous-service non spécifié']);
            exit;
        }

        try {
            $qrCodeInfo = $this->model->getActiveQRCode($sousServiceId);
        } catch (\Throwable $e) {
            error_log('[QRCode] Erreur getActiveQRCode: ' . $e->getMessage());
            echo json_encode(['error' => 'Erreur base de données : ' . $e->getMessage()]);
            exit;
        }
        
        if ($qrCodeInfo) {
            echo json_encode([
                'success' => true,
                'qr_code_path' => $qrCodeInfo['qr_code_path'],
                'token' => $qrCodeInfo['token'],
                'expire_at' => $qrCodeInfo['expire_at']
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    /**
     * Télécharge le QR code
     */
    public function telechargerQRCode(): void
    {
        if (!$this->checkAuth()) {
            header('Location: gestionnaire.php?action=connexion');
            exit;
        }
        
        $path = $_GET['path'] ?? '';
        if (!$path) {
            die('Chemin non spécifié');
        }
        
        $fullPath = __DIR__ . '/../' . $path;
        if (!file_exists($fullPath)) {
            die('Fichier non trouvé');
        }
        
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="qrcode_consultation.png"');
        readfile($fullPath);
    }

    /* ═══════════════════════════════════════════════════════════
       SCAN DU QR CODE - CRÉATION AUTOMATIQUE DU TICKET VIRTUEL
    ═══════════════════════════════════════════════════════════ */

    /**
     * Point d'entrée après scan du QR code par le patient
     * URL: index.php?action=scanner_qr&token=XXX
     */
    public function scannerQRCode(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->showErrorPage('Token QR code manquant.');
            return;
        }
        
        // Vérifier le QR code
        $qrCode = $this->model->findByToken($token);
        
        if (!$qrCode) {
            $this->showErrorPage('QR code invalide.');
            return;
        }
        
        if ($qrCode['expire_at'] < date('Y-m-d H:i:s')) {
            $this->showErrorPage('Ce QR code a expiré. Veuillez utiliser un QR code plus récent.');
            return;
        }
        
        if ($qrCode['statut'] !== 'actif') {
            $this->showErrorPage('Ce QR code n\'est plus actif.');
            return;
        }
        
        // Incrémenter le compteur de scans
        $this->model->incrementScanCount($qrCode['id']);
        
        // Vérifier si l'utilisateur est connecté ou doit s'identifier
        if (!isset($_SESSION['patient_id'])) {
            // Stocker le token pour la redirection après connexion
            $_SESSION['pending_qr_token'] = $token;
            header('Location: patient.php?action=connexion&redirect=qr');
            exit;
        }
        
        $patientId = $_SESSION['patient_id'];
        
        // Créer le ticket virtuel
        $ticketResult = TicketHelper::creerTicketDepuisQR($qrCode['id'], $patientId);
        
        if (!$ticketResult || !$ticketResult['ticket_id']) {
            $this->showErrorPage('Erreur lors de la création du ticket. Veuillez réessayer.');
            return;
        }
        
        // Rediriger vers l'affichage du ticket
        header('Location: index.php?action=afficher_ticket&id=' . $ticketResult['ticket_id']);
        exit;
    }

    /**
     * Affiche le ticket après scan (ou consultation)
     */
    public function afficherTicket(): void
    {
        $ticketId = (int)($_GET['id'] ?? 0);
        
        if ($ticketId <= 0) {
            $this->showErrorPage('Ticket invalide.');
            return;
        }
        
        $ticket = $this->ticketModel->obtenirParId($ticketId);
        
        if (!$ticket) {
            $this->showErrorPage('Ticket introuvable.');
            return;
        }
        
        require __DIR__ . '/../views/tickets/ticket.php';
    }

    /**
     * Rafraîchit le statut du ticket (AJAX)
     */
    public function rafraichirTicketAjax(): void
    {
        header('Content-Type: application/json');
        
        $ticketId = (int)($_GET['id'] ?? 0);
        
        if ($ticketId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Ticket invalide']);
            exit;
        }
        
        $ticket = $this->ticketModel->obtenirParId($ticketId);
        
        if (!$ticket) {
            echo json_encode(['success' => false, 'message' => 'Ticket introuvable']);
            exit;
        }
        
        // Si le ticket a une consultation associée, récupérer les infos à jour
        $consultation = null;
        if ($ticket['consultation_id']) {
            $consultation = $this->consultationModel->findById($ticket['consultation_id']);
        }
        
        echo json_encode([
            'success' => true,
            'statut' => $ticket['statut'],
            'rang' => $ticket['rang'],
            'temps_attente_minutes' => $ticket['temps_attente_minutes'],
            'consultation_statut' => $consultation ? $consultation['statut'] : null
        ]);
        exit;
    }

    /**
     * Point d'entrée pour la prise de rendez-vous (ancienne méthode - conservée pour compatibilité)
     */
    public function priseRdv(): void
    {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            $this->showErrorPage('Token QR code manquant.');
            return;
        }
        
        // Rediriger vers le nouveau scanner
        header('Location: index.php?action=scanner_qr&token=' . urlencode($token));
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       MÉTHODES UTILITAIRES
    ═══════════════════════════════════════════════════════════ */

    private function showErrorPage(string $message): void
    {
        $errorMessage = $message;
        require __DIR__ . '/../views/errors/qr_error.php';
        exit;
    }

    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . ($script != '/' ? $script : '');
    }
}