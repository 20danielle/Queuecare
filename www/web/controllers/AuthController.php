<?php
/**
 * AuthController.php - Gestion de l'authentification web
 */

require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../models/UtilisateurModel.php';
require_once __DIR__ . '/../models/HopitalModel.php';
require_once __DIR__ . '/../models/MedecinModel.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/LangHelper.php';

class AuthController {

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm() {
        if (AuthHelper::estConnecte()) {
            $this->_redirecterSelonRole(AuthHelper::getRole());
        }
        include __DIR__ . '/../views/auth/connexion.php';
    }

    /**
     * Traite la tentative de connexion
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=login');
            exit;
        }

        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            header('Location: index.php?action=login&error=champs_vides');
            exit;
        }

        $utilisateurModel = new UtilisateurModel();
        $user = $utilisateurModel->authentifier($email, $password);

        if ($user) {
            $sessionToken = $utilisateurModel->ouvrirSessionUnique((int)$user['id']);
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['user_nom']      = $user['nom'] ?? $user['email'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();
            $_SESSION['active_session_token'] = $sessionToken;

            if (!empty($user['medecin_id'])) {
                $_SESSION['medecin_id'] = $user['medecin_id'];
                // Se reconnecter = de nouveau opérationnel : on annule
                // toute urgence en attente et on repasse "disponible".
                (new MedecinModel())->reactiverApresConnexion((int)$user['medecin_id']);
            }
            if (!empty($user['gestionnaire_id'])) {
                $_SESSION['gestionnaire_id'] = $user['gestionnaire_id'];
            }

            $utilisateurModel->updateDerniereConnexion($user['id']);
            // Restaurer la langue préférée de l'utilisateur
            LangHelper::setLang($user['langue'] ?? 'fr');
            $this->_redirecterSelonRole($user['role']);
        } else {
            header('Location: index.php?action=login&error=identifiants_invalides');
            exit;
        }
    }

    /**
     * Affiche le formulaire d'inscription de l'administrateur
     */
    public function showRegisterAdmin() {
        $utilisateurModel = new UtilisateurModel();

        // Si un admin existe déjà, bloquer l'accès
        if ($utilisateurModel->adminExiste()) {
            header('Location: index.php?action=login&error=admin_existe');
            exit;
        }

        include __DIR__ . '/../views/auth/inscription_admin.php';
    }

    /**
     * Traite l'inscription de l'administrateur
     */
    public function registerAdmin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=register_admin');
            exit;
        }

        $utilisateurModel = new UtilisateurModel();
        $hopitalModel     = new HopitalModel();

        // Blocage si admin déjà présent
        if ($utilisateurModel->adminExiste()) {
            header('Location: index.php?action=login&error=admin_existe');
            exit;
        }

        $nom         = trim($_POST['nom']          ?? '');
        $email       = trim($_POST['email']        ?? '');
        $password    = $_POST['password']          ?? '';
        $confirm     = $_POST['password_confirm']  ?? '';
        $nomHopital  = trim($_POST['nom_hopital']  ?? '');
        $langue      = in_array($_POST['langue'] ?? '', ['fr','en']) ? $_POST['langue'] : 'fr';

        // Validations
        $errors = [];
        if (empty($nom))        $errors[] = 'Le nom est requis.';
        if (empty($email))      $errors[] = "L'email est requis.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email est invalide.";
        if (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        if ($password !== $confirm)  $errors[] = 'Les mots de passe ne correspondent pas.';
        if (empty($nomHopital)) $errors[] = "Le nom de l'hôpital est requis.";

        if ($utilisateurModel->emailExiste($email)) {
            $errors[] = 'Cette adresse email est déjà utilisée.';
        }

        if (!empty($errors)) {
            $_SESSION['register_errors'] = $errors;
            $_SESSION['register_old']    = compact('nom', 'email', 'nomHopital');
            header('Location: index.php?action=register_admin&error=validation');
            exit;
        }

        // Création admin
        $adminId = $utilisateurModel->creerAdmin([
            'nom'      => $nom,
            'email'    => $email,
            'password' => $password,
        ]);

        if (!$adminId) {
            header('Location: index.php?action=register_admin&error=creation_echouee');
            exit;
        }

        // Sauvegarde config hôpital
        $hopitalModel->sauvegarder(['nom_hopital' => $nomHopital]);

        // Connexion automatique
        $sessionToken = $utilisateurModel->ouvrirSessionUnique((int)$adminId);
        $_SESSION['user_id']       = $adminId;
        $_SESSION['user_email']    = $email;
        $_SESSION['user_nom']      = $nom;
        $_SESSION['role']          = 'admin';
        $_SESSION['last_activity'] = time();
        $_SESSION['active_session_token'] = $sessionToken;

        // Sauvegarder la langue choisie
        $utilisateurModel->mettreAJourLangue($adminId, $langue);
        LangHelper::setLang($langue);

        header('Location: admin.php?setup=1');
        exit;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout() {
        AuthHelper::deconnecter();
        header('Location: accueil.php');
        exit;
    }

    // ── Mot de passe oublié ──────────────────────────────────────────────────

    /**
     * Affiche le formulaire de demande de réinitialisation (saisie de l'email)
     */
    public function showForgotPassword() {
        include __DIR__ . '/../views/auth/mot_de_passe_oublie.php';
    }

    /**
     * Traite la demande : génère un code et l'envoie par email
     */
    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=mot_de_passe_oublie');
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: index.php?action=mot_de_passe_oublie&error=email_invalide');
            exit;
        }

        $utilisateurModel = new UtilisateurModel();
        $user = $utilisateurModel->findByEmail($email);

        // Pour ne pas révéler si un email existe ou non, on affiche toujours
        // le même message de succès, mais on n'envoie le code que si le
        // compte existe réellement et qu'il est actif.
        if ($user && $user['statut'] === 'actif') {
            require_once __DIR__ . '/../models/PasswordResetModel.php';
            require_once __DIR__ . '/../helpers/MailHelper.php';

            $code = MailHelper::genererCode(defined('RESET_CODE_LONGUEUR') ? RESET_CODE_LONGUEUR : 6);

            $resetModel = new PasswordResetModel();
            $resetModel->creerCode($email, $code);

            MailHelper::envoyerCodeReset($email, $user['nom'], $code);
        }

        $_SESSION['reset_email'] = $email;
        header('Location: index.php?action=verifier_code&envoye=1');
        exit;
    }

    /**
     * Renvoie un nouveau code à l'email déjà enregistré en session
     */
    public function renvoyerCode() {
        $email = $_SESSION['reset_email'] ?? '';

        if (empty($email)) {
            header('Location: index.php?action=mot_de_passe_oublie');
            exit;
        }

        $utilisateurModel = new UtilisateurModel();
        $user = $utilisateurModel->findByEmail($email);

        if ($user && $user['statut'] === 'actif') {
            require_once __DIR__ . '/../models/PasswordResetModel.php';
            require_once __DIR__ . '/../helpers/MailHelper.php';

            $code = MailHelper::genererCode(defined('RESET_CODE_LONGUEUR') ? RESET_CODE_LONGUEUR : 6);

            $resetModel = new PasswordResetModel();
            $resetModel->creerCode($email, $code);

            MailHelper::envoyerCodeReset($email, $user['nom'], $code);
        }

        header('Location: index.php?action=verifier_code&envoye=1');
        exit;
    }

    /**
     * Affiche le formulaire de saisie du code reçu par email
     */
    public function showVerifyCode() {
        if (empty($_SESSION['reset_email'])) {
            header('Location: index.php?action=mot_de_passe_oublie');
            exit;
        }
        include __DIR__ . '/../views/auth/verification_code.php';
    }

    /**
     * Vérifie le code saisi. Si valide, connecte l'utilisateur et exige
     * la définition d'un nouveau mot de passe (modale bloquante côté dashboard).
     */
    public function verifyCode() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: index.php?action=verifier_code');
            exit;
        }

        $email = $_SESSION['reset_email'] ?? '';
        $code  = trim($_POST['code'] ?? '');

        if (empty($email)) {
            header('Location: index.php?action=mot_de_passe_oublie');
            exit;
        }

        if (empty($code)) {
            header('Location: index.php?action=verifier_code&error=code_vide');
            exit;
        }

        require_once __DIR__ . '/../models/PasswordResetModel.php';
        $resetModel = new PasswordResetModel();

        if (!$resetModel->verifierEtConsommerCode($email, $code)) {
            header('Location: index.php?action=verifier_code&error=code_invalide');
            exit;
        }

        $utilisateurModel = new UtilisateurModel();
        $user = $utilisateurModel->findByEmail($email);

        if (!$user || $user['statut'] !== 'actif') {
            header('Location: index.php?action=mot_de_passe_oublie&error=compte_introuvable');
            exit;
        }

        // Connexion de l'utilisateur, comme un login classique
        $sessionToken = $utilisateurModel->ouvrirSessionUnique((int)$user['id']);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_nom']      = $user['nom'] ?? $user['email'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['active_session_token'] = $sessionToken;

        if (!empty($user['medecin_id'])) {
            $_SESSION['medecin_id'] = $user['medecin_id'];
            (new MedecinModel())->reactiverApresConnexion((int)$user['medecin_id']);
        }
        if (!empty($user['gestionnaire_id'])) {
            $_SESSION['gestionnaire_id'] = $user['gestionnaire_id'];
        }

        // Oblige l'utilisateur à définir un nouveau mot de passe avant
        // de pouvoir utiliser le dashboard (modale bloquante)
        $_SESSION['must_reset_password'] = true;

        unset($_SESSION['reset_email']);

        $utilisateurModel->updateDerniereConnexion($user['id']);
        $this->_redirecterSelonRole($user['role']);
    }

    /**
     * Endpoint AJAX appelé depuis la modale du dashboard pour définir
     * le nouveau mot de passe après une connexion par code.
     */
    public function finaliserResetPassword() {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthHelper::estConnecte() || empty($_SESSION['must_reset_password'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Accès non autorisé.']);
            exit;
        }

        $nouveau  = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirme = $_POST['confirmation_mot_de_passe'] ?? '';

        if (strlen($nouveau) < 8) {
            echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.']);
            exit;
        }

        if ($nouveau !== $confirme) {
            echo json_encode(['success' => false, 'message' => 'Les deux mots de passe ne correspondent pas.']);
            exit;
        }

        $utilisateurModel = new UtilisateurModel();
        $ok = $utilisateurModel->modifierMotDePasse($_SESSION['user_id'], $nouveau);

        if (!$ok) {
            echo json_encode(['success' => false, 'message' => 'Une erreur est survenue. Réessayez.']);
            exit;
        }

        unset($_SESSION['must_reset_password']);

        echo json_encode(['success' => true, 'message' => 'Mot de passe mis à jour avec succès.']);
        exit;
    }

    // ── Privé ──────────────────────────────────────────────────────────────

    private function _redirecterSelonRole(string $role): void {
        switch ($role) {
            case 'admin':
                header('Location: admin.php');
                break;
            case 'medecin':
                header('Location: medecin.php');
                break;
            case 'gestionnaire':
                header('Location: gestionnaire.php');
                break;
            default:
                header('Location: accueil.php');
        }
        exit;
    }
}
