<?php // views/service/modifier.php ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Modifier un Service — QueueCare</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700,400i,700i|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="public/css/style.css">
  <link rel="stylesheet" href="public/css/auth.css">
  <style>
    .svc-brand {
      width:44%; min-width:340px;
      background: linear-gradient(155deg, #0f172a 0%, #1e3a5f 55%, #0c4a6e 100%);
      display:flex; align-items:stretch; position:relative; overflow:hidden;
    }
    .svc-brand-inner { padding:56px 52px; display:flex; flex-direction:column; gap:0; width:100%; position:relative; z-index:2; }
    .svc-logo { display:flex; align-items:center; gap:13px; margin-bottom:60px; }
    .svc-logo-icon { width:46px; height:46px; background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.3); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; color:#fff; }
    .svc-logo-name { font-family:var(--font-display); font-size:1.6rem; font-weight:700; color:#fff; }
    .svc-brand-title { font-family:var(--font-display); font-size:2.5rem; font-weight:700; line-height:1.15; color:#fff; margin-bottom:18px; }
    .svc-brand-title em { font-style:italic; color:rgba(255,255,255,.7); }
    .svc-brand-sub { font-size:.95rem; color:rgba(255,255,255,.7); line-height:1.75; max-width:320px; margin-bottom:44px; }
    .svc-features { list-style:none; display:flex; flex-direction:column; gap:14px; }
    .svc-features li { display:flex; align-items:flex-start; gap:12px; font-size:.875rem; color:rgba(255,255,255,.8); }
    .svc-features li i { color:#67e8f9; margin-top:3px; }
    .svc-deco { position:absolute; inset:0; z-index:1; pointer-events:none; }
    .svc-deco-ring { position:absolute; border-radius:50%; border:1.5px solid rgba(255,255,255,.08); }
    .svc-deco-ring-1 { width:380px; height:380px; bottom:-90px; right:-90px; }
    .svc-deco-ring-2 { width:220px; height:220px; bottom:-20px; right:-20px; }
    .badge-orange { display:inline-flex; align-items:center; gap:6px; padding:5px 13px; border-radius:20px; background:#fff7ed; color:#c2410c; font-size:.73rem; font-weight:700; border:1px solid #fed7aa; margin-bottom:14px; }
    .svc-form-title { font-family:var(--font-display); font-size:2rem; font-weight:700; color:var(--blue-dark); margin:4px 0 8px; }
    .btn-orange { background:linear-gradient(135deg,#c2410c,#ea580c); color:#fff; border:none; box-shadow:0 4px 14px rgba(194,65,12,.35); }
    .btn-orange:hover { background:linear-gradient(135deg,#9a3412,#c2410c); transform:translateY(-2px); }
    .svc-field-label { font-size:.78rem; font-weight:700; color:var(--blue-dark); text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; display:block; }
    .back-link { display:inline-flex; align-items:center; gap:6px; font-size:.8rem; color:var(--text-muted); margin-bottom:20px; transition:color var(--ease); }
    .back-link:hover { color:var(--blue); }
  </style>
</head>
<body class="auth-body">
<div class="auth-wrapper">

  <aside class="svc-brand">
    <div class="svc-brand-inner">
      <div class="svc-logo anim-fade-up">
        <span class="svc-logo-icon"><i class="fa-solid fa-hospital"></i></span>
        <span class="svc-logo-name">QueueCare</span>
      </div>
      <div class="anim-fade-up-1">
        <h1 class="svc-brand-title">Modifier<br><em>le service.</em></h1>
        <p class="svc-brand-sub">Mettez à jour les informations de l'établissement hospitalier.</p>
      </div>
      <ul class="svc-features anim-fade-up-2">
        <li><i class="fa-solid fa-circle-check"></i> <span>Nom et adresse de l'établissement</span></li>
        <li><i class="fa-solid fa-circle-check"></i> <span>Horaires d'ouverture</span></li>
        <li><i class="fa-solid fa-circle-check"></i> <span>Description et statut</span></li>
        <li><i class="fa-solid fa-circle-check"></i> <span>Activation / désactivation du service</span></li>
      </ul>
      <div class="svc-deco">
        <div class="svc-deco-ring svc-deco-ring-1"></div>
        <div class="svc-deco-ring svc-deco-ring-2"></div>
      </div>
    </div>
  </aside>

  <main class="auth-form-panel">
    <div class="auth-form-inner anim-fade-up">

      <a href="service.php?action=liste" class="back-link">
        <i class="fa-solid fa-arrow-left fa-xs"></i> Retour à la liste
      </a>
      <a href="accueil.php" class="back-link" style="margin-left:16px;">
        <i class="fa-solid fa-house fa-xs"></i> Accueil
      </a>

      <div class="auth-form-header">
        <span class="badge-orange">
          <i class="fa-solid fa-pen"></i>&nbsp; Modification
        </span>
        <h2 class="svc-form-title">Modifier le service</h2>
        <p class="auth-subtitle" style="color:var(--text-muted);">
          ID #<?= (int)$anciens['id'] ?> — <?= htmlspecialchars($anciens['nom'] ?? '') ?>
        </p>
      </div>

      <?php if (!empty($erreurs['global'])): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?= htmlspecialchars($erreurs['global']) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="service.php?action=modifier"
            class="auth-form" id="serviceForm" novalidate>
        <input type="hidden" name="id" value="<?= (int)$anciens['id'] ?>">

        <div class="field <?= isset($erreurs['nom']) ? 'field--error' : '' ?>">
          <label class="svc-field-label" for="nom"><i class="fa-solid fa-hospital fa-xs"></i> Nom *</label>
          <div class="field-wrap">
            <span class="field-icon"><i class="fa-solid fa-hospital"></i></span>
            <input type="text" id="nom" name="nom" class="field-input"
              value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>" required autofocus>
          </div>
          <?php if (isset($erreurs['nom'])): ?>
          <span class="field-error"><i class="fa-solid fa-circle-exclamation fa-xs"></i> <?= htmlspecialchars($erreurs['nom']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field <?= isset($erreurs['adresse']) ? 'field--error' : '' ?>">
          <label class="svc-field-label" for="adresse"><i class="fa-solid fa-location-dot fa-xs"></i> Adresse *</label>
          <div class="field-wrap">
            <span class="field-icon"><i class="fa-solid fa-location-dot"></i></span>
            <input type="text" id="adresse" name="adresse" class="field-input"
              value="<?= htmlspecialchars($anciens['adresse'] ?? '') ?>" required>
          </div>
          <?php if (isset($erreurs['adresse'])): ?>
          <span class="field-error"><i class="fa-solid fa-circle-exclamation fa-xs"></i> <?= htmlspecialchars($erreurs['adresse']) ?></span>
          <?php endif; ?>
        </div>

        <div class="field">
          <label class="svc-field-label" for="horaires"><i class="fa-solid fa-clock fa-xs"></i> Horaires</label>
          <div class="field-wrap">
            <span class="field-icon"><i class="fa-solid fa-clock"></i></span>
            <input type="text" id="horaires" name="horaires" class="field-input"
              placeholder="ex : Lun-Ven 07h00-17h00"
              value="<?= htmlspecialchars($anciens['horaires'] ?? '') ?>">
          </div>
        </div>

        <div class="field">
          <label class="svc-field-label" for="description"><i class="fa-solid fa-align-left fa-xs"></i> Description</label>
          <textarea id="description" name="description" class="field-input" rows="3"
            style="resize:vertical;padding-top:12px;line-height:1.6;"><?= htmlspecialchars($anciens['description'] ?? '') ?></textarea>
        </div>

        <div class="field">
          <label class="svc-field-label"><i class="fa-solid fa-toggle-on fa-xs"></i> Statut</label>
          <div style="display:flex;gap:12px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem;">
              <input type="radio" name="statut" value="actif"
                <?= (($anciens['statut'] ?? 'actif') === 'actif') ? 'checked' : '' ?>
                style="accent-color:#1a8a52;width:16px;height:16px;">
              <span><i class="fa-solid fa-circle" style="color:#1a8a52;font-size:.55rem;"></i> Actif</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem;">
              <input type="radio" name="statut" value="inactif"
                <?= (($anciens['statut'] ?? '') === 'inactif') ? 'checked' : '' ?>
                style="accent-color:#dc2626;width:16px;height:16px;">
              <span><i class="fa-solid fa-circle" style="color:#dc2626;font-size:.55rem;"></i> Inactif</span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-orange btn-full btn-lg" id="submitBtn" style="margin-top:8px;">
          <i class="fa-solid fa-floppy-disk"></i> Enregistrer les modifications
        </button>
      </form>

    </div>
  </main>
</div>
<script>
document.getElementById('serviceForm').addEventListener('submit', () => {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement…';
  btn.disabled = true;
});
</script>
</body>
</html>
