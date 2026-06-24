<?php
// views/service/liste.php
$model = new ServiceModel();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Services Hospitaliers — QueueCare</title>
  <link rel="preconnect" href="https://fonts.bunny.net">
  <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700,400i,700i|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="public/css/style.css">
  <style>
    body { background:#f0f4fa; min-height:100vh; font-family:var(--font-body); }
    .page-header { background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%); padding:28px 40px; display:flex; align-items:center; justify-content:space-between; gap:24px; }
    .page-header-left { display:flex; align-items:center; gap:16px; }
    .page-logo { display:flex; align-items:center; gap:10px; font-family:var(--font-display); font-size:1.4rem; font-weight:700; color:#fff; }
    .page-logo-icon { width:38px; height:38px; border-radius:10px; background:rgba(255,255,255,.18); display:flex; align-items:center; justify-content:center; font-size:1rem; color:#fff; }
    .page-title { font-family:var(--font-display); font-size:1.6rem; font-weight:700; color:#fff; margin:0; }
    .page-subtitle { color:rgba(255,255,255,.6); font-size:.875rem; margin-top:2px; }
    .page-actions { display:flex; gap:10px; }
    .btn-white { display:inline-flex; align-items:center; gap:8px; padding:10px 22px; border-radius:8px; background:rgba(255,255,255,.12); border:1.5px solid rgba(255,255,255,.25); color:#fff; font-size:.875rem; font-weight:600; cursor:pointer; text-decoration:none; transition:all .2s; }
    .btn-white:hover { background:rgba(255,255,255,.22); }
    .btn-create { display:inline-flex; align-items:center; gap:8px; padding:10px 22px; border-radius:8px; background:#ea580c; color:#fff; font-size:.875rem; font-weight:600; cursor:pointer; text-decoration:none; border:none; transition:all .2s; box-shadow:0 4px 14px rgba(234,88,12,.4); }
    .btn-create:hover { background:#c2410c; transform:translateY(-1px); }
    .container { max-width:1100px; margin:0 auto; padding:36px 24px; }
    .flash { display:flex; align-items:flex-start; gap:12px; padding:14px 18px; border-radius:8px; margin-bottom:24px; font-size:.88rem; font-weight:500; animation:slideIn .3s ease both; }
    @keyframes slideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:none} }
    .flash-success { background:#d1fae5; border:1px solid #6ee7b7; color:#065f46; }
    .flash-info    { background:#dbeafe; border:1px solid #93c5fd; color:#1e3a8a; }
    .flash-error   { background:#fee2e2; border:1px solid #fca5a5; color:#7f1d1d; }

    /* Grille */
    .services-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:24px; }

    /* Carte service */
    .service-card { background:#fff; border-radius:16px; border:1.5px solid #e2ecf8; overflow:hidden; transition:box-shadow .2s,transform .2s,border-color .2s; }
    .service-card:hover { box-shadow:0 10px 32px rgba(13,45,107,.12); transform:translateY(-2px); border-color:#93c5fd; }
    .card-stripe { height:4px; }
    .statut-actif   .card-stripe { background:linear-gradient(90deg,#1a8a52,#22a863); }
    .statut-inactif .card-stripe { background:linear-gradient(90deg,#dc2626,#f87171); }
    .card-top-inner { padding:20px 20px 0; }
    .card-row-top { display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:12px; }
    .card-icon { width:48px; height:48px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; flex-shrink:0; }
    .statut-actif   .card-icon { background:#d1fae5; color:#065f46; }
    .statut-inactif .card-icon { background:#fee2e2; color:#7f1d1d; }
    .card-name { font-family:var(--font-display); font-size:1.05rem; font-weight:700; color:#0d2d6b; margin-bottom:3px; }
    .card-address { font-size:.77rem; color:#6b83a8; display:flex; align-items:flex-start; gap:4px; }
    .statut-badge { display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:10px; font-size:.68rem; font-weight:700; white-space:nowrap; flex-shrink:0; }
    .badge-actif   { background:#d1fae5; color:#065f46; }
    .badge-inactif { background:#fee2e2; color:#7f1d1d; }
    .card-meta { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:10px; }
    .meta-chip { display:flex; align-items:center; gap:5px; padding:4px 9px; background:#f0f4fa; border-radius:7px; font-size:.73rem; color:#6b83a8; }
    .meta-chip i { color:#1a4db5; }
    .card-horaires { font-size:.77rem; color:#6b83a8; display:flex; align-items:center; gap:6px; background:#f8faff; padding:6px 10px; border-radius:7px; margin-bottom:10px; }
    .card-service-actions { display:flex; gap:8px; padding:4px 20px 14px; flex-wrap:wrap; }
    .btn-sm { display:inline-flex; align-items:center; gap:5px; padding:6px 12px; border-radius:7px; font-size:.77rem; font-weight:600; border:none; cursor:pointer; text-decoration:none; transition:all .2s; }
    .btn-sm-edit { background:#eff6ff; color:#1a4db5; }
    .btn-sm-edit:hover { background:#dbeafe; }
    .btn-sm-toggle { background:#f0fdf4; color:#166534; }
    .btn-sm-toggle:hover { background:#dcfce7; }
    .btn-sm-toggle.is-inactif { background:#fff7ed; color:#9a3412; }
    .btn-sm-toggle.is-inactif:hover { background:#fed7aa; }

    /* Section sous-services */
    .ss-section { border-top:1.5px solid #e8f0fb; }
    .ss-toggle-btn { width:100%; display:flex; align-items:center; justify-content:space-between; padding:12px 20px; background:none; border:none; cursor:pointer; font-family:var(--font-body); font-size:.84rem; font-weight:600; color:#0d2d6b; transition:background .2s; text-align:left; }
    .ss-toggle-btn:hover { background:#f0f4fa; }
    .ss-count-badge { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#1a4db5; color:#fff; font-size:.68rem; font-weight:700; margin-left:7px; }
    .chevron { transition:transform .25s; font-size:.73rem; color:#6b83a8; }
    .ss-toggle-btn.open .chevron { transform:rotate(180deg); }
    .ss-body { display:none; padding:0 20px 18px; }
    .ss-body.open { display:block; }

    /* Items sous-services */
    .ss-list { display:flex; flex-direction:column; gap:9px; margin-bottom:14px; }
    .ss-item { border:1.5px solid #e2ecf8; border-radius:9px; background:#f8faff; overflow:hidden; }
    .ss-item:hover { border-color:#93c5fd; }
    .ss-item-header { display:flex; align-items:center; justify-content:space-between; padding:9px 12px; gap:8px; }
    .ss-item-left { display:flex; align-items:center; gap:9px; flex:1; min-width:0; }
    .ss-icon { width:30px; height:30px; border-radius:7px; display:flex; align-items:center; justify-content:center; font-size:.75rem; flex-shrink:0; }
    .ss-actif   .ss-icon { background:#d1fae5; color:#065f46; }
    .ss-inactif .ss-icon { background:#fee2e2; color:#7f1d1d; }
    .ss-name { font-size:.84rem; font-weight:600; color:#0d2d6b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .ss-chips { display:flex; gap:5px; margin-top:2px; flex-wrap:wrap; }
    .ss-chip { font-size:.66rem; color:#6b83a8; background:#e8f0fb; padding:2px 6px; border-radius:5px; }
    .ss-badge { font-size:.63rem; font-weight:700; padding:2px 7px; border-radius:7px; }
    .ss-badge-actif   { background:#d1fae5; color:#065f46; }
    .ss-badge-inactif { background:#fee2e2; color:#7f1d1d; }
    .ss-item-actions { display:flex; gap:5px; flex-shrink:0; }
    .btn-ss { display:inline-flex; align-items:center; gap:4px; padding:5px 9px; border-radius:6px; font-size:.71rem; font-weight:600; border:none; cursor:pointer; text-decoration:none; transition:all .2s; }
    .btn-ss-edit { background:#eff6ff; color:#1a4db5; }
    .btn-ss-edit:hover { background:#dbeafe; }
    .btn-ss-tog { background:#f0fdf4; color:#166534; }
    .btn-ss-tog:hover { background:#dcfce7; }
    .btn-ss-tog.inactif { background:#fff7ed; color:#9a3412; }

    /* Formulaire inline edit */
    .ss-edit-form { display:none; padding:11px 12px; border-top:1px solid #e2ecf8; background:#fff; }
    .ss-edit-form.open { display:block; animation:fadeIn .2s ease; }
    @keyframes fadeIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }
    .ss-fg { display:grid; grid-template-columns:1fr 1fr; gap:9px; margin-bottom:9px; }
    .ss-field { display:flex; flex-direction:column; gap:3px; }
    .ss-label { font-size:.69rem; font-weight:700; color:#0d2d6b; text-transform:uppercase; letter-spacing:.05em; }
    .ss-input { font-size:.81rem; padding:6px 9px; border:1.5px solid #c8dff2; border-radius:6px; font-family:var(--font-body); color:#1e293b; background:#f8faff; transition:border-color .2s; }
    .ss-input:focus { outline:none; border-color:#1a4db5; background:#fff; }
    .ss-sel { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%234a6fa5' d='M5 7L0 2h10z'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 8px center; padding-right:24px; }
    .ss-fa { display:flex; gap:7px; justify-content:flex-end; margin-top:6px; }
    .btn-ss-save { background:#1a4db5; color:#fff; padding:6px 14px; border-radius:6px; font-size:.76rem; font-weight:600; border:none; cursor:pointer; }
    .btn-ss-save:hover { background:#0d2d6b; }
    .btn-ss-cancel { background:#f0f4fa; color:#6b83a8; padding:6px 12px; border-radius:6px; font-size:.76rem; font-weight:600; border:none; cursor:pointer; }

    /* Ajouter sous-service */
    .ss-add-section { border-top:1px dashed #c8dff2; padding-top:12px; margin-top:2px; }
    .ss-add-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 14px; border-radius:7px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border:1.5px solid #93c5fd; color:#1a4db5; font-size:.78rem; font-weight:600; cursor:pointer; transition:all .2s; }
    .ss-add-btn:hover { background:linear-gradient(135deg,#dbeafe,#bfdbfe); border-color:#1a4db5; }
    .ss-add-form { display:none; margin-top:11px; background:#f0f4fa; border-radius:9px; padding:14px; border:1px solid #c8dff2; }
    .ss-add-form.open { display:block; animation:fadeIn .2s ease; }
    .ss-add-form h4 { font-size:.83rem; font-weight:700; color:#0d2d6b; margin-bottom:11px; }
    .btn-ss-create { background:linear-gradient(135deg,#1a4db5,#2563eb); color:#fff; padding:7px 16px; border-radius:7px; font-size:.8rem; font-weight:600; border:none; cursor:pointer; display:inline-flex; align-items:center; gap:6px; }
    .btn-ss-create:hover { background:linear-gradient(135deg,#0d2d6b,#1a4db5); }

    /* Empty */
    .ss-empty { text-align:center; padding:18px; color:#6b83a8; font-size:.8rem; }
    .ss-empty i { font-size:1.4rem; color:#c8dff2; display:block; margin-bottom:7px; }

    /* Next steps */
    .empty-state { text-align:center; padding:72px 24px; background:#fff; border-radius:16px; border:2px dashed #c8dff2; }
    .empty-icon { width:80px; height:80px; border-radius:24px; background:linear-gradient(135deg,#eff6ff,#dbeafe); display:flex; align-items:center; justify-content:center; font-size:2rem; color:#1a4db5; margin:0 auto 24px; }
    .empty-title { font-family:var(--font-display); font-size:1.5rem; font-weight:700; color:#0d2d6b; margin-bottom:12px; }
    .empty-sub { color:#6b83a8; font-size:.95rem; max-width:420px; margin:0 auto 28px; line-height:1.65; }
    .next-steps { margin-top:36px; background:#fff; border-radius:16px; border:1.5px solid #e2ecf8; padding:28px 32px; }
    .next-steps-title { font-family:var(--font-display); font-size:1.1rem; font-weight:700; color:#0d2d6b; margin-bottom:18px; }
    .steps-row { display:flex; gap:16px; flex-wrap:wrap; }
    .step-item { flex:1; min-width:200px; display:flex; gap:14px; align-items:flex-start; padding:18px; border-radius:12px; background:#f8faff; border:1.5px solid #e2ecf8; text-decoration:none; transition:all .2s; }
    .step-item:hover { border-color:#1a4db5; background:#eff6ff; transform:translateY(-2px); }
    .step-num { width:36px; height:36px; border-radius:10px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:.85rem; font-weight:700; color:#fff; }
    .step-num-2 { background:linear-gradient(135deg,#1a4db5,#2563eb); }
    .step-num-3 { background:linear-gradient(135deg,#1a8a52,#22a863); }
    .step-label { font-size:.88rem; font-weight:600; color:#0d2d6b; margin-bottom:3px; }
    .step-desc  { font-size:.78rem; color:#6b83a8; line-height:1.4; }
    @media(max-width:640px) {
      .page-header { padding:20px; flex-direction:column; align-items:flex-start; }
      .container { padding:20px 16px; }
      .ss-fg { grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

<header class="page-header">
  <div class="page-header-left">
    <div class="page-logo">
      <div class="page-logo-icon"><i class="fa-solid fa-list-check"></i></div>
      QueueCare
    </div>
    <div>
      <div class="page-title">Services Hospitaliers</div>
      <div class="page-subtitle">Gérez les établissements et leurs sous-services</div>
    </div>
  </div>
  <div class="page-actions">
    <a href="accueil.php" class="btn-white"><i class="fa-solid fa-house"></i> Accueil</a>
    <a href="service.php?action=creer" class="btn-create"><i class="fa-solid fa-plus"></i> Nouveau service</a>
  </div>
</header>

<div class="container">

  <?php if (!empty($messageAction)): ?>
  <div class="flash flash-<?= htmlspecialchars($typeMessage) ?>">
    <i class="fa-solid <?= $typeMessage === 'success' ? 'fa-circle-check' : ($typeMessage === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-info') ?>"></i>
    <?= htmlspecialchars($messageAction) ?>
  </div>
  <?php endif; ?>

  <?php if (empty($services)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fa-solid fa-hospital"></i></div>
    <h2 class="empty-title">Aucun service enregistré</h2>
    <p class="empty-sub">Pour commencer à utiliser QueueCare, créez d'abord au moins un service hospitalier.</p>
    <a href="service.php?action=creer" class="btn-create" style="display:inline-flex;"><i class="fa-solid fa-plus"></i> Créer le premier service</a>
  </div>

  <?php else: ?>
  <div class="services-grid">
    <?php foreach ($services as $svc):
      $svcId    = (int)$svc['id'];
      $ssList   = $model->getSousServicesParService($svcId);
      $nbSS     = count($ssList);
    ?>
    <div class="service-card statut-<?= $svc['statut'] ?>" id="svc-<?= $svcId ?>">
      <div class="card-stripe"></div>
      <div class="card-top-inner">

        <div class="card-row-top">
          <div class="card-icon"><i class="fa-solid fa-hospital"></i></div>
          <div style="flex:1;min-width:0;">
            <div class="card-name"><?= htmlspecialchars($svc['nom']) ?></div>
            <div class="card-address">
              <i class="fa-solid fa-location-dot" style="color:#1a4db5;flex-shrink:0;font-size:.65rem;margin-top:1px;"></i>
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($svc['adresse']) ?></span>
            </div>
          </div>
          <span class="statut-badge badge-<?= $svc['statut'] ?>">
            <i class="fa-solid fa-circle" style="font-size:.38rem;"></i>
            <?= $svc['statut'] === 'actif' ? 'Actif' : 'Inactif' ?>
          </span>
        </div>

        <?php if (!empty($svc['horaires'])): ?>
        <div class="card-horaires"><i class="fa-solid fa-clock"></i> <?= htmlspecialchars($svc['horaires']) ?></div>
        <?php endif; ?>

        <div class="card-meta">
          <div class="meta-chip"><i class="fa-solid fa-layer-group"></i> <?= $nbSS ?> sous-service<?= $nbSS !== 1 ? 's' : '' ?></div>
          <div class="meta-chip"><i class="fa-solid fa-user-doctor"></i> <?= (int)$svc['nb_medecins'] ?> médecin<?= $svc['nb_medecins'] != 1 ? 's' : '' ?></div>
          <div class="meta-chip"><i class="fa-solid fa-user-tie"></i> <?= (int)$svc['nb_gestionnaires'] ?> gestionnaire<?= $svc['nb_gestionnaires'] != 1 ? 's' : '' ?></div>
        </div>

      </div><!-- /.card-top-inner -->

      <div class="card-service-actions">
        <a href="service.php?action=modifier&id=<?= $svcId ?>" class="btn-sm btn-sm-edit">
          <i class="fa-solid fa-pen"></i> Modifier
        </a>
        <a href="service.php?action=basculer_statut&id=<?= $svcId ?>"
           class="btn-sm btn-sm-toggle <?= $svc['statut'] === 'inactif' ? 'is-inactif' : '' ?>"
           onclick="return confirm('Confirmer le changement de statut ?');">
          <?= $svc['statut'] === 'actif'
              ? '<i class="fa-solid fa-toggle-on"></i> Désactiver'
              : '<i class="fa-solid fa-toggle-off"></i> Activer' ?>
        </a>
      </div>

      <!-- ── Sous-services ── -->
      <div class="ss-section">
        <button type="button"
                class="ss-toggle-btn <?= $nbSS > 0 ? 'open' : '' ?>"
                onclick="toggleSS(this,'ss-body-<?= $svcId ?>')">
          <span>
            <i class="fa-solid fa-layer-group" style="color:#1a4db5;margin-right:5px;"></i>
            Sous-services
            <span class="ss-count-badge"><?= $nbSS ?></span>
          </span>
          <i class="fa-solid fa-chevron-down chevron"></i>
        </button>

        <div class="ss-body <?= $nbSS > 0 ? 'open' : '' ?>" id="ss-body-<?= $svcId ?>">

          <div class="ss-list">
            <?php if (empty($ssList)): ?>
            <div class="ss-empty">
              <i class="fa-solid fa-layer-group"></i>
              Aucun sous-service encore — ajoutez-en un ci-dessous.
            </div>
            <?php else: ?>
            <?php foreach ($ssList as $ss):
              $ssId    = (int)$ss['id'];
              $dureMin = round((int)($ss['duree_rdv_defaut'] ?? $ss['duree_estimee'] ?? 1800) / 60);
            ?>
            <div class="ss-item ss-<?= $ss['statut'] ?>" id="ss-item-<?= $ssId ?>">
              <div class="ss-item-header">
                <div class="ss-item-left">
                  <div class="ss-icon"><i class="fa-solid fa-notes-medical"></i></div>
                  <div style="min-width:0;">
                    <div class="ss-name"><?= htmlspecialchars($ss['nom']) ?></div>
                    <div class="ss-chips">
                      <span class="ss-chip"><i class="fa-solid fa-clock" style="font-size:.58rem;"></i> <?= $dureMin ?>min</span>
                      <span class="ss-chip"><i class="fa-solid fa-users" style="font-size:.58rem;"></i> <?= (int)$ss['capacite_horaire'] ?>/h</span>
                      <span class="ss-badge ss-badge-<?= $ss['statut'] ?>"><?= $ss['statut'] === 'actif' ? 'Actif' : 'Inactif' ?></span>
                    </div>
                  </div>
                </div>
                <div class="ss-item-actions">
                  <button type="button" class="btn-ss btn-ss-edit" onclick="toggleEditSS(<?= $ssId ?>)">
                    <i class="fa-solid fa-pen"></i>
                  </button>
                  <a href="service.php?action=basculer_statut_ss&ss_id=<?= $ssId ?>&service_id=<?= $svcId ?>"
                     class="btn-ss btn-ss-tog <?= $ss['statut'] === 'inactif' ? 'inactif' : '' ?>"
                     onclick="return confirm('Changer le statut de ce sous-service ?');">
                    <i class="fa-solid <?= $ss['statut'] === 'actif' ? 'fa-toggle-on' : 'fa-toggle-off' ?>"></i>
                  </a>
                  <a href="service.php?action=supprimer_ss&ss_id=<?= $ssId ?>&service_id=<?= $svcId ?>"
                     class="btn-ss"
                     style="background:#fee2e2;color:#991b1b;"
                     onclick="return confirm('Supprimer définitivement ce sous-service ? Cette action est irréversible.');"
                     title="Supprimer">
                    <i class="fa-solid fa-trash"></i>
                  </a>
                </div>
              </div>

              <!-- Formulaire inline modification -->
              <div class="ss-edit-form" id="ss-edit-<?= $ssId ?>">
                <form method="POST" action="service.php?action=modifier_ss">
                  <input type="hidden" name="ss_id"      value="<?= $ssId ?>">
                  <input type="hidden" name="service_id" value="<?= $svcId ?>">
                  <div class="ss-fg">
                    <div class="ss-field" style="grid-column:1/-1;">
                      <label class="ss-label">Nom *</label>
                      <input type="text" name="ss_nom" class="ss-input" value="<?= htmlspecialchars($ss['nom']) ?>" required>
                    </div>
                    <div class="ss-field">
                      <label class="ss-label">Durée RDV (sec.)</label>
                      <input type="number" name="ss_duree" class="ss-input" value="<?= (int)($ss['duree_rdv_defaut'] ?? 1800) ?>" min="60" step="60">
                    </div>
                    <div class="ss-field">
                      <label class="ss-label">Capacité /h</label>
                      <input type="number" name="ss_capacite" class="ss-input" value="<?= (int)$ss['capacite_horaire'] ?>" min="1">
                    </div>
                    <div class="ss-field" style="grid-column:1/-1;">
                      <label class="ss-label">Description</label>
                      <input type="text" name="ss_description" class="ss-input" value="<?= htmlspecialchars($ss['description'] ?? '') ?>">
                    </div>
                    <div class="ss-field">
                      <label class="ss-label">Statut</label>
                      <select name="ss_statut" class="ss-input ss-sel">
                        <option value="actif"   <?= $ss['statut'] === 'actif'   ? 'selected':'' ?>>Actif</option>
                        <option value="inactif" <?= $ss['statut'] === 'inactif' ? 'selected':'' ?>>Inactif</option>
                      </select>
                    </div>
                  </div>
                  <div class="ss-fa">
                    <button type="button" class="btn-ss-cancel" onclick="toggleEditSS(<?= $ssId ?>)">Annuler</button>
                    <button type="submit" class="btn-ss-save"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button>
                  </div>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div><!-- /.ss-list -->

          <!-- Ajouter un sous-service -->
          <div class="ss-add-section">
            <button type="button" class="ss-add-btn" onclick="toggleAddSS(<?= $svcId ?>)">
              <i class="fa-solid fa-plus"></i> Ajouter un sous-service
            </button>
            <div class="ss-add-form" id="ss-add-<?= $svcId ?>">
              <h4><i class="fa-solid fa-plus" style="color:#1a4db5;margin-right:5px;"></i>Nouveau sous-service — <?= htmlspecialchars($svc['nom']) ?></h4>
              <form method="POST" action="service.php?action=creer_ss">
                <input type="hidden" name="service_id" value="<?= $svcId ?>">
                <div class="ss-fg">
                  <div class="ss-field" style="grid-column:1/-1;">
                    <label class="ss-label">Nom *</label>
                    <input type="text" name="ss_nom" class="ss-input" placeholder="ex : Cardiologie, Pédiatrie, Urgences…" required>
                  </div>
                  <div class="ss-field">
                    <label class="ss-label">Durée RDV (sec.)</label>
                    <input type="number" name="ss_duree" class="ss-input" value="1800" min="60" step="60" title="1800 = 30 minutes">
                  </div>
                  <div class="ss-field">
                    <label class="ss-label">Capacité /h</label>
                    <input type="number" name="ss_capacite" class="ss-input" value="10" min="1">
                  </div>
                  <div class="ss-field" style="grid-column:1/-1;">
                    <label class="ss-label">Description (optionnel)</label>
                    <input type="text" name="ss_description" class="ss-input" placeholder="Description courte…">
                  </div>
                </div>
                <div class="ss-fa">
                  <button type="button" class="btn-ss-cancel" onclick="toggleAddSS(<?= $svcId ?>)">Annuler</button>
                  <button type="submit" class="btn-ss-create"><i class="fa-solid fa-plus"></i> Créer</button>
                </div>
              </form>
            </div>
          </div>

        </div><!-- /.ss-body -->
      </div><!-- /.ss-section -->
    </div><!-- /.service-card -->
    <?php endforeach; ?>
  </div><!-- /.services-grid -->
  <?php endif; ?>

  <?php if (!empty($services)): ?>
  <div class="next-steps">
    <div class="next-steps-title"><i class="fa-solid fa-arrow-right" style="color:#1a4db5;margin-right:8px;"></i>Prochaines étapes</div>
    <div class="steps-row">
      <a href="medecin.php?action=inscription" class="step-item">
        <div class="step-num step-num-2"><i class="fa-solid fa-user-doctor"></i></div>
        <div><div class="step-label">Inscrire les médecins</div><div class="step-desc">Affectés à un sous-service de l'hôpital.</div></div>
      </a>
      <a href="gestionnaire.php?action=inscription" class="step-item">
        <div class="step-num step-num-3"><i class="fa-solid fa-user-tie"></i></div>
        <div><div class="step-label">Inscrire les gestionnaires</div><div class="step-desc">Gèrent les files d'attente des sous-services.</div></div>
      </a>
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
function toggleSS(btn, bodyId) {
  btn.classList.toggle('open');
  document.getElementById(bodyId).classList.toggle('open');
}
function toggleEditSS(id) {
  document.getElementById('ss-edit-' + id).classList.toggle('open');
}
function toggleAddSS(id) {
  document.getElementById('ss-add-' + id).classList.toggle('open');
}
document.addEventListener('DOMContentLoaded', () => {
  const hash = window.location.hash;
  if (hash && hash.startsWith('#svc-')) {
    const card = document.querySelector(hash);
    if (card) {
      setTimeout(() => card.scrollIntoView({ behavior:'smooth', block:'start' }), 100);
      const id   = hash.replace('#svc-', '');
      const body = document.getElementById('ss-body-' + id);
      const btn  = body?.previousElementSibling;
      if (body && !body.classList.contains('open')) {
        body.classList.add('open');
        if (btn) btn.classList.add('open');
      }
    }
  }
});
</script>
</body>
</html>
