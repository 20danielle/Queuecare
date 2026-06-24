<?php
/**
 * views/auth/connexion.php — Formulaire de connexion unifié
 */

$error = $_GET['error'] ?? '';
$errorMessages = [
    'champs_vides'         => 'Veuillez remplir tous les champs.',
    'identifiants_invalides' => 'Email ou mot de passe incorrect.',
    'acces_refuse'         => 'Accès refusé. Vous n\'avez pas les droits nécessaires.',
    'admin_existe'         => 'Un administrateur existe déjà. Connectez-vous.',
];
$errorMsg = $errorMessages[$error] ?? '';
$timeout  = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — QueueCare</title>
    <link rel="stylesheet" href="vendor/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="public/css/auth.css">
    <style>
        body { background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 420px; }
        .auth-logo { text-align: center; margin-bottom: 1.5rem; }
        .auth-logo i { font-size: 2.5rem; color: #0d6efd; }
        .auth-logo h1 { font-size: 1.4rem; font-weight: 700; margin-top: .5rem; color: #1e293b; }
        .auth-logo p  { color: #64748b; font-size: .9rem; }
        .btn-login { width: 100%; padding: .75rem; font-weight: 600; }
        .link-register { text-align: center; margin-top: 1.2rem; font-size: .88rem; color: #64748b; }
        .link-register a { color: #0d6efd; text-decoration: none; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <i class="fas fa-hospital-alt"></i>
        <h1>QueueCare</h1>
        <p>Gestion des files d'attente hospitalières</p>
    </div>

    <?php if ($timeout): ?>
        <div class="alert alert-warning py-2">
            <i class="fas fa-clock me-1"></i> Session expirée. Veuillez vous reconnecter.
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?action=login" novalidate>
        <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Adresse email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="directeur@hopital.cm"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autofocus>
            </div>
        </div>

        <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Mot de passe</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="••••••••" required>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="togglePwd()" title="Voir le mot de passe">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login">
            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
        </button>
    </form>

    <div class="link-register">
        Première utilisation ?
        <a href="index.php?action=register_admin">Configurer l'hôpital</a>
    </div>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
