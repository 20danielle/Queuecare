<?php // views/service/creer.php ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Créer un Service Hospitalier — QueueCare</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700,400i,700i|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="public/css/style.css">
  <link rel="stylesheet" href="public/css/auth.css">
  <style>
    /* ── Overrides thème orange/teal pour les services ── */
    :root {
      --svc-dark:  #7c2d12;
      --svc:       #c2410c;
      --svc-mid:   #ea580c;
      --svc-light: #fed7aa;
      --svc-pale:  #fff7ed;
    }

    .svc-brand {
      width:44%; min-width:340px;
      background: linear-gradient(155deg, #0f172a 0%, #1e3a5f 55%, #0c4a6e 100%);
      display:flex; align-items:stretch; position:relative; overflow:hidden;
    }
    .svc-brand-inner {
      padding:56px 52px; display:flex; flex-direction:column;
      gap:0; width:100%; position:relative; z-index:2;
    }

    .svc-logo { display:flex; align-items:center; gap:13px; margin-bottom:60px; }
    .svc-logo-icon {
      width:46px; height:46px;
      background:rgba(255,255,255,.18); border:1.5px solid rgba(255,255,255,.3);
      border-radius:14px; display:flex; align-items:center; justify-content:center;
      font-size:1.15rem; color:#fff; backdrop-filter:blur(8px);
    }
    .svc-logo-name {
      font-family:var(--font-display); font-size:1.6rem; font-weight:700;
      color:#fff; letter-spacing:-.015em;
    }
    .svc-brand-title {
      font-family:var(--font-display); font-size:2.7rem; font-weight:700;
      line-height:1.15; color:#fff; margin-bottom:18px; letter-spacing:-.025em;
    }
    .svc-brand-title em { font-style:italic; color:rgba(255,255,255,.7); }
    .svc-brand-sub { font-size:.95rem; color:rgba(255,255,255,.7); line-height:1.75; max-width:320px; margin-bottom:44px; }

    .svc-features { list-style:none; display:flex; flex-direction:column; gap:14px; }
    .svc-features li { display:flex; align-items:flex-start; gap:12px; font-size:.875rem; color:rgba(255,255,255,.8); line-height:1.5; }
    .svc-features li i { color:#67e8f9; font-size:.85rem; flex-shrink:0; margin-top:3px; }

    .svc-deco { position:absolute; inset:0; z-index:1; pointer-events:none; }
    .svc-deco-ring { position:absolute; border-radius:50%; border:1.5px solid rgba(255,255,255,.08); }
    .svc-deco-ring-1 { width:380px; height:380px; bottom:-90px; right:-90px; }
    .svc-deco-ring-2 { width:220px; height:220px; bottom:-20px; right:-20px; }
    .svc-deco-dot { position:absolute; border-radius:50%; background:rgba(255,255,255,.12); }
    .svc-deco-dot-1 { width:14px; height:14px; top:25%; left:12%; }
    .svc-deco-dot-2 { width:8px; height:8px; top:45%; left:7%; }

    /* Badge orange */
    .badge-orange {
      display:inline-flex; align-items:center; gap:6px;
      padding:5px 13px; border-radius:20px;
      background:var(--svc-pale); color:var(--svc);
      font-size:.73rem; font-weight:700; letter-spacing:.04em;
      border:1px solid var(--svc-light); margin-bottom:14px;
    }

    /* Titre orange */
    .svc-form-title {
      font-family:var(--font-display); font-size:2rem; font-weight:700;
      color:var(--blue-dark); margin:4px 0 8px; letter-spacing:-.02em;
    }

    .btn-orange {
      background: linear-gradient(135deg, #c2410c, #ea580c);
      color:#fff; border:none;
      box-shadow:0 4px 14px rgba(194,65,12,.35);
    }
    .btn-orange:hover { background: linear-gradient(135deg, #9a3412, #c2410c); transform:translateY(-2px); box-shadow:0 6px 20px rgba(194,65,12,.4); }

    /* Champ label orange */
    .svc-field-label {
      font-size:.78rem; font-weight:700; color:var(--blue-dark);
      text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px;
      display:block;
    }

    /* Step counter */
    .step-hint {
      display:flex; align-items:center; gap:10px; padding:14px 18px;
      background:linear-gradient(135deg, #eff6ff, #dbeafe);
      border:1px solid #93c5fd; border-radius:var(--radius-sm);
      margin-bottom:24px; font-size:.84rem; color:var(--blue-dark);
    }
    .step-hint i { color:var(--blue); font-size:1rem; }

    .back-link {
      display:inline-flex; align-items:center; gap:6px;
      font-size:.8rem; color:var(--text-muted); margin-bottom:20px;
      transition:color var(--ease);
    }
    .back-link:hover { color:var(--blue); }
  </style>
</head>
<body class="auth-body">
<div class="auth-wrapper">

  <!-- Panneau gauche bleu foncé -->
  <aside class="svc-brand">
    <div class="svc-brand-inner">
      <div class="svc-logo anim-fade-up">
        <span class="svc-logo-icon"><i class="fa-solid fa-hospital"></i></span>
        <span class="svc-logo-name">QueueCare</span>
      </div>
      <div class="anim-fade-up-1">
        <h1 class="svc-brand-title">Votre hôpital,<br><em>étape par étape.</em></h1>
        <p class="svc-brand-sub">Avant toute inscription, vous devez d'abord enregistrer votre établissement de santé dans le système.</p>
      </div>
      <ul class="svc-features anim-fade-up-2">
        <li><i class="fa-solid fa-circle-1"></i> <span><strong>Créez le service hospitalier</strong> — Enregistrez l'hôpital ou l'établissement.</span></li>
        <li><i class="fa-solid fa-circle-2"></i> <span><strong>Inscrivez les médecins</strong> — Affectez-les aux sous-services créés.</span></li>
        <li><i class="fa-solid fa-circle-3"></i> <span><strong>Inscrivez les médecins ordinaires</strong> — Ils rejoignent un sous-service existant.</span></li>
        <li><i class="fa-solid fa-circle-4"></i> <span><strong>Inscrivez les gestionnaires</strong> — Affectés aux sous-services pour la file.</span></li>
      </ul>
      <div class="svc-deco">
        <div class="svc-deco-ring svc-deco-ring-1"></div>
        <div class="svc-deco-ring svc-deco-ring-2"></div>
        <div class="svc-deco-dot svc-deco-dot-1"></div>
        <div class="svc-deco-dot svc-deco-dot-2"></div>
      </div>
    </div>
  </aside>

  <!-- Panneau droit : formulaire -->
  <main class="auth-form-panel">
    <div class="auth-form-inner anim-fade-up">

      <a href="accueil.php" class="back-link">
        <i class="fa-solid fa-arrow-left fa-xs"></i> Retour à l'accueil
      </a>

      <div class="auth-form-header">
        <span class="badge-orange">
          <i class="fa-solid fa-hospital"></i>&nbsp; Étape 1 — Service Hospitalier
        </span>
        <h2 class="svc-form-title">Créer un service</h2>
        <p class="auth-subtitle">Enregistrez un hôpital ou un établissement de santé.</p>
      </div>

      <div class="step-hint">
        <i class="fa-solid fa-circle-info"></i>
        <span>Cette étape est <strong>obligatoire</strong> avant toute inscription. Créez les sous-services, puis inscrivez les médecins et gestionnaires.</span>
      </div>

      <?php if (!empty($erreurs['global'])): ?>
      <div class="alert alert-error">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?= htmlspecialchars($erreurs['global']) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="service.php?action=creer"
            class="auth-form" id="serviceForm" novalidate>

        <!-- Nom du service -->
        <div class="field <?= isset($erreurs['nom']) ? 'field--error' : '' ?>">
          <label class="svc-field-label" for="nom">
            <i class="fa-solid fa-hospital fa-xs"></i> Nom de l'établissement *
          </label>
          <div class="field-wrap">
            <span class="field-icon"><i class="fa-solid fa-hospital"></i></span>
            <input type="text" id="nom" name="nom" class="field-input"
              placeholder="ex : Hôpital Régional de Douala"
              value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>"
              required autofocus>
          </div>
          <?php if (isset($erreurs['nom'])): ?>
          <span class="field-error"><i class="fa-solid fa-circle-exclamation fa-xs"></i> <?= htmlspecialchars($erreurs['nom']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Adresse -->
        <div class="field <?= isset($erreurs['adresse']) ? 'field--error' : '' ?>">
          <label class="svc-field-label" for="adresse">
            <i class="fa-solid fa-location-dot fa-xs"></i> Adresse *
          </label>
          <div class="field-wrap">
            <span class="field-icon"><i class="fa-solid fa-location-dot"></i></span>
            <input type="text" id="adresse" name="adresse" class="field-input"
              placeholder="ex : Avenue de la Liberté, Douala, Cameroun"
              value="<?= htmlspecialchars($anciens['adresse'] ?? '') ?>"
              required>
          </div>
          <?php if (isset($erreurs['adresse'])): ?>
          <span class="field-error"><i class="fa-solid fa-circle-exclamation fa-xs"></i> <?= htmlspecialchars($erreurs['adresse']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Horaires -->
        <div class="field">
          <label class="svc-field-label" for="horaires">
            <i class="fa-solid fa-clock fa-xs"></i> Horaires d'ouverture
          </label>
          <div class="field-wrap">
            <span class="field-icon"><i class="fa-solid fa-clock"></i></span>
            <input type="text" id="horaires" name="horaires" class="field-input"
              placeholder="ex : Lun-Ven 07h00-17h00 | Sam 07h00-13h00"
              value="<?= htmlspecialchars($anciens['horaires'] ?? '') ?>">
          </div>
        </div>

        <!-- Description -->
        <div class="field">
          <label class="svc-field-label" for="description">
            <i class="fa-solid fa-align-left fa-xs"></i> Description (optionnel)
          </label>
          <textarea id="description" name="description"
            class="field-input" rows="3"
            placeholder="Décrivez brièvement l'établissement, ses spécialités principales…"
            style="resize:vertical;padding-top:12px;line-height:1.6;"><?= htmlspecialchars($anciens['description'] ?? '') ?></textarea>
        </div>

        <!-- Statut -->
        <div class="field">
          <label class="svc-field-label">
            <i class="fa-solid fa-toggle-on fa-xs"></i> Statut initial
          </label>
          <div style="display:flex;gap:12px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem;color:var(--text-main);">
              <input type="radio" name="statut" value="actif"
                <?= (($anciens['statut'] ?? 'actif') === 'actif') ? 'checked' : '' ?>
                style="accent-color:#1a8a52;width:16px;height:16px;">
              <span><i class="fa-solid fa-circle" style="color:#1a8a52;font-size:.55rem;"></i> Actif</span>
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem;color:var(--text-main);">
              <input type="radio" name="statut" value="inactif"
                <?= (($anciens['statut'] ?? '') === 'inactif') ? 'checked' : '' ?>
                style="accent-color:#dc2626;width:16px;height:16px;">
              <span><i class="fa-solid fa-circle" style="color:#dc2626;font-size:.55rem;"></i> Inactif</span>
            </label>
          </div>
        </div>

        <button type="submit" class="btn btn-orange btn-full btn-lg" id="submitBtn" style="margin-top:8px;">
          <i class="fa-solid fa-plus"></i> Créer le service hospitalier
        </button>

        <a href="service.php?action=liste"
           style="display:flex;align-items:center;justify-content:center;gap:8px;padding:11px;font-size:.875rem;color:var(--text-muted);border-radius:var(--radius-sm);border:1.5px solid var(--border);text-decoration:none;transition:all var(--ease);"
           onmouseover="this.style.borderColor='var(--blue)';this.style.color='var(--blue)'"
           onmouseout="this.style.borderColor='var(--border)';this.style.color='var(--text-muted)'">
          <i class="fa-solid fa-list"></i> Voir les services existants
        </a>

      </form>

    </div>
  </main>
</div>

<script>
document.getElementById('serviceForm').addEventListener('submit', () => {
  const btn = document.getElementById('submitBtn');
  btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Création en cours…';
  btn.disabled = true;
});
</script>
</body>
</html>
