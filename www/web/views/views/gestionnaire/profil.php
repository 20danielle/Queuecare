<?php
// views/gestionnaire/profil.php
$gestionnaire = $this->model->trouverParId($_SESSION['gestionnaire_id']);
$initiale = mb_strtoupper(mb_substr($gestionnaire['nom'] ?? 'G', 0, 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--green:#059669;--green-d:#065f46;--red:#ef4444;--bg:#f5f7fa;--border:#e2e8f0}
        body{font-family:'Outfit',sans-serif;background:var(--bg);min-height:100vh;padding:24px 16px 48px}

        .back-btn{display:inline-flex;align-items:center;gap:8px;color:var(--green);text-decoration:none;
            font-weight:600;font-size:.875rem;margin-bottom:20px;padding:8px 14px;background:white;
            border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
        .back-btn:hover{background:#d1fae5}
        .profil-wrap{max-width:560px;margin:0 auto}

        .lock-screen{background:white;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,.10);padding:40px 32px;text-align:center}
        .lock-icon{width:72px;height:72px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;font-size:2rem;color:var(--green)}
        .lock-title{font-size:1.2rem;font-weight:700;color:#1e293b;margin-bottom:8px}
        .lock-sub{color:#64748b;font-size:.875rem;margin-bottom:24px}
        .lock-input{width:100%;padding:12px 14px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.9rem;margin-bottom:12px;outline:none}
        .lock-input:focus{border-color:var(--green)}
        .lock-btn{width:100%;padding:13px;background:linear-gradient(135deg,var(--green),var(--green-d));color:white;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}
        .lock-btn:hover{opacity:.9}
        .lock-error{color:var(--red);font-size:.8rem;margin-bottom:10px;display:none}

        .profil-card{background:white;border-radius:20px;box-shadow:0 8px 32px rgba(0,0,0,.10);overflow:hidden;display:none}
        .profil-header{background:linear-gradient(135deg,var(--green),var(--green-d));padding:28px;color:white;text-align:center}
        .profil-avatar{width:72px;height:72px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:1.8rem;font-weight:700;color:white}
        .profil-header h2{font-size:1.1rem;font-weight:700;margin-bottom:4px}
        .profil-header p{font-size:.8rem;opacity:.8}

        .profil-body{padding:24px}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        @media(max-width:480px){.form-row{grid-template-columns:1fr}}
        .form-group{margin-bottom:16px}
        .form-group label{display:block;font-size:.78rem;font-weight:700;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px}
        .form-group input{width:100%;padding:11px 13px;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.9rem;outline:none;transition:border-color .2s}
        .form-group input:focus{border-color:var(--green)}
        .separator{border-top:1px solid var(--border);margin:20px 0}
        .section-title{font-size:.875rem;font-weight:700;color:#1e293b;margin-bottom:14px;display:flex;align-items:center;gap:8px}
        .btn-save{width:100%;padding:13px;background:linear-gradient(135deg,var(--green),var(--green-d));color:white;border:none;border-radius:10px;font-family:inherit;font-size:.95rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:4px}
        .btn-save:hover{opacity:.9}
        .msg-success{background:#d1fae5;color:#065f46;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.875rem;font-weight:600;display:none}
        .msg-error{background:#fee2e2;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.875rem;display:none}
    </style>
</head>
<body>
<div class="profil-wrap">
    <a href="gestionnaire.php?action=dashboard" class="back-btn">
        <i class="fa-solid fa-arrow-left"></i> Retour au tableau de bord
    </a>

    <div class="lock-screen" id="lockScreen">
        <div class="lock-icon"><i class="fa-solid fa-lock"></i></div>
        <div class="lock-title">Vérification requise</div>
        <div class="lock-sub">Entrez votre mot de passe pour accéder à votre profil.</div>
        <div class="lock-error" id="lockError">Mot de passe incorrect.</div>
        <input type="password" class="lock-input" id="lockPassword" placeholder="Votre mot de passe" autocomplete="current-password">
        <button class="lock-btn" onclick="verifierMdp()">
            <i class="fa-solid fa-unlock"></i> Accéder au profil
        </button>
    </div>

    <div class="profil-card" id="profilCard">
        <div class="profil-header">
            <div class="profil-avatar"><?= $initiale ?></div>
            <h2>Mon profil</h2>
            <p><?= htmlspecialchars($gestionnaire['nom']) ?></p>
        </div>
        <div class="profil-body">
            <div class="msg-success" id="msgSuccess"><i class="fa-solid fa-circle-check"></i> Modifications enregistrées !</div>
            <div class="msg-error"   id="msgError"></div>

            <form id="profilForm">
                <div class="form-group">
                    <label>Nom complet</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($gestionnaire['nom']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Téléphone</label>
                    <input type="tel" name="telephone" value="<?= htmlspecialchars($gestionnaire['telephone'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($gestionnaire['email']) ?>" required>
                </div>

                <div class="separator"></div>
                <div class="section-title"><i class="fa-solid fa-key"></i> Changer le mot de passe</div>

                <div class="form-group">
                    <label>Mot de passe actuel</label>
                    <input type="password" name="password_actuel" id="password_actuel" placeholder="Requis pour changer le mot de passe">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Nouveau mot de passe</label>
                        <input type="password" name="nouveau_password" placeholder="Min 8 car., 1 maj., 1 chiffre">
                    </div>
                    <div class="form-group">
                        <label>Confirmer</label>
                        <input type="password" name="confirmer_password" placeholder="Répétez">
                    </div>
                </div>
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
                </button>
            </form>
        </div>
    </div>
</div>

<script>
async function verifierMdp() {
    const pwd = document.getElementById('lockPassword').value;
    if (!pwd) return;
    const fd = new FormData();
    fd.append('password', pwd);
    const r = await fetch('gestionnaire.php?action=verifier_mdp', {method:'POST', body:fd});
    const d = await r.json();
    if (d.success) {
        document.getElementById('lockScreen').style.display  = 'none';
        document.getElementById('profilCard').style.display  = 'block';
        document.getElementById('password_actuel').value = pwd;
    } else {
        const err = document.getElementById('lockError');
        err.style.display = 'block';
        document.getElementById('lockPassword').value = '';
        setTimeout(() => err.style.display = 'none', 3000);
    }
}
document.getElementById('lockPassword').addEventListener('keydown', e => {
    if (e.key === 'Enter') verifierMdp();
});
document.getElementById('profilForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';
    btn.disabled = true;
    const r = await fetch('gestionnaire.php?action=mettre_a_jour_profil', {method:'POST', body:new FormData(this)});
    const d = await r.json();
    if (d.success) {
        document.getElementById('msgSuccess').style.display = 'flex';
        document.getElementById('msgError').style.display   = 'none';
        setTimeout(() => window.location.href = 'gestionnaire.php?action=dashboard', 1500);
    } else {
        const msgs = Object.values(d.errors || {}).join('<br>') || (d.message || 'Erreur');
        document.getElementById('msgError').innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + msgs;
        document.getElementById('msgError').style.display = 'block';
    }
    btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications';
    btn.disabled = false;
});
</script>
</body>
</html>