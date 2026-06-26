<?php
/**
 * views/auth/mot_de_passe_oublie.php — Demande de réinitialisation (saisie email)
 */

$error = $_GET['error'] ?? '';
$errorMessages = [
    'email_invalide' => 'Veuillez saisir une adresse email valide.',
];
$errorMsg = $errorMessages[$error] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié — QueueCare</title>
    <link rel="icon" type="image/png" href="public/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="public/img/favicon-64.png">
    <link rel="stylesheet" href="vendor/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="public/css/auth.css">
    <style>
        body { background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 420px; }
        .auth-logo { text-align: center; margin-bottom: 1rem; }
        .auth-logo i { font-size: 2.5rem; color: #0d6efd; }
        .auth-logo h1 { font-size: 1.4rem; font-weight: 700; margin-top: .5rem; color: #1e293b; }
        .auth-logo p  { color: #64748b; font-size: .9rem; display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 4px; }
        .auth-logo p img { height: 18px; }
        .auth-desc { color: #64748b; font-size: .87rem; margin-bottom: 1.5rem; text-align: center; }
        .btn-login { width: 100%; padding: .75rem; font-weight: 600; }
        .link-back { text-align: center; margin-top: 1.2rem; font-size: .88rem; color: #64748b; }
        .link-back a { color: #0d6efd; text-decoration: none; }
        @media (max-width: 480px) {
            body { padding: 16px; align-items: flex-start; padding-top: 32px; }
            .auth-card { padding: 1.75rem 1.4rem; }
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <i class="fas fa-key"></i>
        <h1>Mot de passe oublié</h1>
        <p><img src="public/img/logo-queuecare-icon.png" alt=""> QueueCare</p>
    </div>

    <p class="auth-desc">
        Saisissez l'adresse email associée à votre compte. Nous vous envoyons
        un code à utiliser pour vous reconnecter.
    </p>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?action=mot_de_passe_oublie" novalidate>
        <div class="mb-4">
            <label for="email" class="form-label fw-semibold">Adresse email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="directeur@hopital.cm" required autofocus>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-login">
            <i class="fas fa-paper-plane me-2"></i>Envoyer le code
        </button>
    </form>

    <div class="link-back">
        <a href="index.php?action=login"><i class="fas fa-arrow-left me-1"></i>Retour à la connexion</a>
    </div>
</div>
</body>
</html>