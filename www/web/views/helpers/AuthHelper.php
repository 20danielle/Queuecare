<?php
/**
 * AuthHelper.php - Gestion de l'authentification et des rôles
 *
 * Rôles définis :
 * - medecin      : accès uniquement à l'espace médecin
 * - gestionnaire : accès uniquement à l'espace gestionnaire
 * - admin        : accès à l'espace admin (admin.php)
 */

class AuthHelper {

    public static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', 28800);
            ini_set('session.cookie_lifetime', 28800);
            session_start();
        }
    }

    public static function connecterMedecin($medecinId, $nom, $email): void {
        self::initSession();
        $_SESSION['user_id']       = $medecinId;
        $_SESSION['user_nom']      = $nom;
        $_SESSION['user_email']    = $email;
        $_SESSION['medecin_id']    = $medecinId;
        $_SESSION['role']          = 'medecin';
        $_SESSION['last_activity'] = time();
    }

    public static function connecterGestionnaire($gestionnaireId, $nom, $email): void {
        self::initSession();
        $_SESSION['user_id']          = $gestionnaireId;
        $_SESSION['user_nom']         = $nom;
        $_SESSION['user_email']       = $email;
        $_SESSION['gestionnaire_id']  = $gestionnaireId;
        $_SESSION['role']             = 'gestionnaire';
        $_SESSION['last_activity']    = time();
    }

    public static function connecterAdmin($userId, $nom, $email, $medecinId = null): void {
        self::initSession();
        $_SESSION['user_id']       = $userId;
        $_SESSION['user_nom']      = $nom;
        $_SESSION['user_email']    = $email;
        $_SESSION['role']          = 'admin';
        $_SESSION['last_activity'] = time();
        if ($medecinId) {
            $_SESSION['medecin_id'] = $medecinId;
        }
    }

    public static function estConnecte(): bool {
        self::initSession();
        return isset($_SESSION['user_id'], $_SESSION['role']);
    }

    public static function estMedecin(): bool {
        self::initSession();
        return ($_SESSION['role'] ?? '') === 'medecin';
    }

    public static function estGestionnaire(): bool {
        self::initSession();
        return ($_SESSION['role'] ?? '') === 'gestionnaire';
    }

    public static function estAdmin(): bool {
        self::initSession();
        return ($_SESSION['role'] ?? '') === 'admin';
    }

    /**
     * Un medecin OU un admin-medecin (admin avec medecin_id) peut acceder a l'espace medecin.
     */
    public static function peutAccederEspaceMedecin(): bool {
        if (!self::estConnecte()) return false;
        if (self::estMedecin()) return true;
        // Admin-medecin : admin qui possede aussi un profil medecin
        if (self::estAdmin() && !empty($_SESSION['medecin_id'])) return true;
        return false;
    }

    /**
     * Indique si l'utilisateur connecte est un admin jouant aussi le role de medecin.
     */
    public static function estAdminMedecin(): bool {
        return self::estAdmin() && !empty($_SESSION['medecin_id']);
    }

    public static function peutAccederEspaceGestionnaire(): bool {
        return self::estConnecte() && self::estGestionnaire();
    }

    public static function peutAccederEspaceAdmin(): bool {
        return self::estConnecte() && self::estAdmin();
    }

    public static function getRole(): ?string {
        self::initSession();
        return $_SESSION['role'] ?? null;
    }

    public static function getMedecinId(): ?int {
        self::initSession();
        return isset($_SESSION['medecin_id']) ? (int) $_SESSION['medecin_id'] : null;
    }

    public static function getGestionnaireId(): ?int {
        self::initSession();
        return isset($_SESSION['gestionnaire_id']) ? (int) $_SESSION['gestionnaire_id'] : null;
    }

    /**
     * Retourne l'ID utilisateur de l'admin connecté
     */
    public static function getAdminId(): ?int {
        self::initSession();
        if (!self::estAdmin()) return null;
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function getUserNom(): string {
        self::initSession();
        return $_SESSION['user_nom'] ?? 'Utilisateur';
    }

    /**
     * Vérifie le timeout de session (défaut : SESSION_TIMEOUT ou 900 s)
     */
    public static function verifierSession(?string $redirectUrl = null): bool {
        self::initSession();
        $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 900;

        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > $timeout) {
                self::deconnecter();
                if ($redirectUrl) {
                    header('Location: ' . $redirectUrl . '?timeout=1');
                    exit;
                }
                return false;
            }
        }
        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function deconnecter(): void {
        self::initSession();
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }

    public static function exigerAuthentification(): void {
        if (!self::estConnecte()) {
            header('Location: index.php?action=login');
            exit;
        }
    }

    /**
     * Exige un rôle précis ; redirige sinon
     */
    public static function exigerRole(string $role): void {
        self::exigerAuthentification();

        $autorise = match ($role) {
            'medecin'      => self::peutAccederEspaceMedecin(),
            'gestionnaire' => self::peutAccederEspaceGestionnaire(),
            'admin'        => self::peutAccederEspaceAdmin(),
            default        => false,
        };

        if (!$autorise) {
            header('Location: index.php?action=login&error=acces_refuse');
            exit;
        }
    }
}
