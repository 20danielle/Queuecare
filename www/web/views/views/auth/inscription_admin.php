<?php
/**
 * views/auth/inscription_admin.php — Setup initial : création du directeur + nom hôpital
 */

$errors  = $_SESSION['register_errors'] ?? [];
$old     = $_SESSION['register_old']    ?? [];
unset($_SESSION['register_errors'], $_SESSION['register_old']);

$globalError = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration initiale — QueueCare</title>
    <link rel="stylesheet" href="vendor/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="public/css/auth.css">
    <style>
        body { background: #f0f4f8; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem 0; }
        .auth-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 2.5rem; width: 100%; max-width: 480px; }
        .setup-badge { background: #e0f2fe; color: #0369a1; border-radius: 20px; padding: .3rem .9rem; font-size: .8rem; font-weight: 600; display: inline-block; margin-bottom: 1rem; }
        .section-divider { border-top: 1px solid #e2e8f0; margin: 1.5rem 0; padding-top: 1.5rem; }
        .section-title { font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: 1rem; }
        .btn-submit { width: 100%; padding: .75rem; font-weight: 600; }
        .strength-bar { height: 4px; border-radius: 2px; transition: all .3s; }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="text-center mb-3">
        <span class="setup-badge"><i class="fas fa-cog me-1"></i>Configuration initiale</span>
        <h1 class="h4 fw-bold text-dark">Bienvenue sur QueueCare</h1>
        <p class="text-muted small">Créez le compte administrateur et nommez votre hôpital.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger py-2 small">
            <ul class="mb-0 ps-3">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($globalError === 'creation_echouee'): ?>
        <div class="alert alert-danger py-2 small">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Une erreur est survenue lors de la création. Réessayez.
        </div>
    <?php endif; ?>

    <form method="POST" action="index.php?action=register_admin" novalidate>

        <!-- Hôpital -->
        <div class="section-title"><i class="fas fa-hospital me-1"></i>Informations hôpital</div>

        <div class="mb-3">
            <label for="nom_hopital" class="form-label fw-semibold">Nom de l'hôpital <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-building"></i></span>
                <input type="text" class="form-control" id="nom_hopital" name="nom_hopital"
                       placeholder="Ex: Hôpital Central de Yaoundé"
                       value="<?= htmlspecialchars($old['nomHopital'] ?? '') ?>"
                       required>
            </div>
        </div>

        <!-- Administrateur -->
        <div class="section-divider">
            <div class="section-title"><i class="fas fa-user-shield me-1"></i>Compte administrateur (directeur)</div>
        </div>

        <div class="mb-3">
            <label for="nom" class="form-label fw-semibold">Nom complet <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-user"></i></span>
                <input type="text" class="form-control" id="nom" name="nom"
                       placeholder="Dr. Jean Dupont"
                       value="<?= htmlspecialchars($old['nom'] ?? '') ?>"
                       required>
            </div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="directeur@hopital.cm"
                       value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                       required>
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label fw-semibold">Mot de passe <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password"
                       placeholder="8 caractères minimum" required
                       oninput="evalForce(this.value)">
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('password', 'eye1')">
                    <i class="fas fa-eye" id="eye1"></i>
                </button>
            </div>
            <div class="strength-bar mt-1 bg-secondary" id="strengthBar" style="width:0"></div>
            <div class="small text-muted mt-1" id="strengthLabel"></div>
        </div>

        <div class="mb-4">
            <label for="password_confirm" class="form-label fw-semibold">Confirmer le mot de passe <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                       placeholder="••••••••" required>
                <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('password_confirm', 'eye2')">
                    <i class="fas fa-eye" id="eye2"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-submit">
            <i class="fas fa-check-circle me-2"></i>Créer et accéder au tableau de bord
        </button>
    </form>

    <div class="text-center mt-3 small text-muted">
        Déjà configuré ? <a href="index.php?action=login">Se connecter</a>
    </div>
</div>

<script>
function togglePwd(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

function evalForce(val) {
    const bar   = document.getElementById('strengthBar');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 8)  score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [
        { pct: '0',    cls: 'bg-secondary', txt: '' },
        { pct: '25%',  cls: 'bg-danger',    txt: 'Faible' },
        { pct: '50%',  cls: 'bg-warning',   txt: 'Moyen' },
        { pct: '75%',  cls: 'bg-info',      txt: 'Fort' },
        { pct: '100%', cls: 'bg-success',   txt: 'Très fort' },
    ];
    const l = levels[score];
    bar.style.width = l.pct;
    bar.className   = `strength-bar mt-1 ${l.cls}`;
    label.textContent = l.txt;
}
</script>
</body>
</html>
