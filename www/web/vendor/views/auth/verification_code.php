<?php
/**
 * views/auth/verification_code.php — Saisie du code reçu par email
 */

$error = $_GET['error'] ?? '';
$envoye = isset($_GET['envoye']);
$errorMessages = [
    'code_vide'     => 'Veuillez saisir le code reçu par email.',
    'code_invalide' => 'Ce code est invalide ou a expiré. Veuillez réessayer.',
];
$errorMsg = $errorMessages[$error] ?? '';
$emailMasque = '';
if (!empty($_SESSION['reset_email'])) {
    $parts = explode('@', $_SESSION['reset_email']);
    if (count($parts) === 2) {
        $local = $parts[0];
        $emailMasque = substr($local, 0, 2) . str_repeat('•', max(strlen($local) - 2, 1)) . '@' . $parts[1];
    }
}
$dureeMin = defined('RESET_CODE_DUREE_MIN') ? RESET_CODE_DUREE_MIN : 15;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification du code — QueueCare</title>
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
        .auth-desc { color: #64748b; font-size: .87rem; margin-bottom: 1.5rem; text-align: center; }
        .auth-desc strong { color: #1e293b; }
        .code-input {
            width: 100%; text-align: center; font-size: 1.6rem; font-weight: 700;
            letter-spacing: .6rem; padding: .65rem .5rem;
        }
        .btn-login { width: 100%; padding: .75rem; font-weight: 600; }
        .link-back { text-align: center; margin-top: 1.2rem; font-size: .88rem; color: #64748b; }
        .link-back a { color: #0d6efd; text-decoration: none; }
        .link-resend { text-align: center; margin-top: .9rem; font-size: .85rem; }
        .link-resend a { color: #0d6efd; text-decoration: none; font-weight: 500; }
        .link-resend a.disabled { color: #94a3b8; pointer-events: none; text-decoration: none; }
        .brand-tag { display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 1.4rem; font-size: .8rem; color: #94a3b8; }
        .brand-tag img { height: 16px; }
        @media (max-width: 480px) {
            body { padding: 16px; align-items: flex-start; padding-top: 32px; }
            .auth-card { padding: 1.75rem 1.4rem; }
        }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <i class="fas fa-shield-halved"></i>
        <h1>Vérification du code</h1>
    </div>

    <p class="auth-desc">
        Un code à <?= (int) (defined('RESET_CODE_LONGUEUR') ? RESET_CODE_LONGUEUR : 6) ?> chiffres a été
        envoyé à <strong><?= htmlspecialchars($emailMasque) ?></strong>. Il est valable
        <?= (int) $dureeMin ?> minutes.
    </p>

    <?php if ($envoye && !$errorMsg): ?>
        <div class="alert alert-success py-2">
            <i class="fas fa-check-circle me-1"></i> Code envoyé. Vérifiez votre boîte de réception.
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="alert alert-danger py-2">
            <i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?action=verifier_code" novalidate>
        <div class="mb-4">
            <label for="code" class="form-label fw-semibold">Code reçu</label>
            <input type="text" class="form-control code-input" id="code" name="code"
                   inputmode="numeric" pattern="[0-9]*" maxlength="<?= (int) (defined('RESET_CODE_LONGUEUR') ? RESET_CODE_LONGUEUR : 6) ?>"
                   placeholder="••••••" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary btn-login">
            <i class="fas fa-unlock me-2"></i>Valider et se connecter
        </button>
    </form>

    <div class="link-resend">
        <a href="#" id="resendLink" onclick="renvoyerCode(event)">Renvoyer le code</a>
        <span id="resendTimer" style="display:none;color:#94a3b8;"></span>
    </div>

    <div class="link-back">
        <a href="index.php?action=mot_de_passe_oublie"><i class="fas fa-arrow-left me-1"></i>Changer d'adresse email</a>
    </div>

    <div class="brand-tag"><img src="public/img/logo-queuecare-icon.png" alt=""> QueueCare</div>
</div>

<script>
let cooldown = 0;
let timerInterval = null;

function renvoyerCode(e) {
    e.preventDefault();
    if (cooldown > 0) return;

    const link = document.getElementById('resendLink');
    link.classList.add('disabled');
    link.textContent = 'Envoi en cours…';

    fetch('index.php?action=renvoyer_code', { method: 'GET' })
        .then(() => { window.location.href = 'index.php?action=verifier_code&envoye=1'; })
        .catch(() => { window.location.href = 'index.php?action=verifier_code&envoye=1'; });
}

function demarrerCooldown(secondes) {
    cooldown = secondes;
    const link  = document.getElementById('resendLink');
    const timer = document.getElementById('resendTimer');
    link.classList.add('disabled');
    timer.style.display = 'inline';

    timerInterval = setInterval(() => {
        cooldown--;
        timer.textContent = `(disponible dans ${cooldown}s)`;
        if (cooldown <= 0) {
            clearInterval(timerInterval);
            link.classList.remove('disabled');
            link.textContent = 'Renvoyer le code';
            timer.style.display = 'none';
        }
    }, 1000);
}

demarrerCooldown(60);
</script>
</body>
</html>