<?php
/**
 * AuthController.php - Gestion de l'authentification web
 */

require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../models/UtilisateurModel.php';
require_once __DIR__ . '/../models/HopitalModel.php';
require_once __DIR__ . '/../config/app.php';

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
            $_SESSION['user_id']       = $user['id'];
            $_SESSION['user_email']    = $user['email'];
            $_SESSION['user_nom']      = $user['nom'] ?? $user['email'];
            $_SESSION['role']          = $user['role'];
            $_SESSION['last_activity'] = time();

            if (!empty($user['medecin_id'])) {
                $_SESSION['medecin_id'] = $user['medecin_id'];
            }
            if (!empty($user['gestionnaire_id'])) {
                $_SESSION['gestionnaire_id'] = $user['gestionnaire_id'];
            }

            $utilisateurModel->updateDerniereConnexion($user['id']);
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
        $_SESSION['user_id']       = $adminId;
        $_SESSION['user_email']    = $email;
        $_SESSION['user_nom']      = $nom;
        $_SESSION['role']          = 'admin';
        $_SESSION['last_activity'] = time();

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
