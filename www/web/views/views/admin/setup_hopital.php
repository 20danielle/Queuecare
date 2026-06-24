<?php
/**
 * views/admin/setup_hopital.php
 * Formulaire de configuration de l'hôpital
 */
$joursLabels   = ['0'=>'Dimanche','1'=>'Lundi','2'=>'Mardi','3'=>'Mercredi','4'=>'Jeudi','5'=>'Vendredi','6'=>'Samedi'];
$joursFermes   = explode(',', $anciens['jours_fermeture'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration hôpital — QueueCare</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="stylesheet" href="public/css/dashboard.css">
    <style>
        .setup-wrap  { max-width:720px; margin:40px auto; padding:0 20px 60px; }
        .setup-card  { background:var(--white); border-radius:var(--radius); border:1px solid var(--border); box-shadow:var(--shadow-sm); padding:36px; }
        .setup-title { font-family:var(--font-display); font-size:1.6rem; font-weight:700; color:var(--blue-dark); margin-bottom:4px; }
        .setup-sub   { font-size:.875rem; color:var(--text-muted); margin-bottom:32px; }
        .days-grid   { display:flex; flex-wrap:wrap; gap:10px; }
        .day-toggle  { display:flex; align-items:center; gap:7px; padding:8px 16px;
                       border:1.8px solid var(--border); border-radius:var(--radius-sm);
                       cursor:pointer; font-size:.85rem; color:var(--text-muted);
                       transition:all var(--ease); user-select:none; }
        .day-toggle input { display:none; }
        .day-toggle.checked { border-color:var(--green); background:var(--green-pale); color:var(--green-dark); font-weight:600; }
        .hours-row   { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    </style>
</head>
<body class="dash-body">

<header class="topbar">
    <a href="admin.php?action=dashboard" class="topbar-logo">
        <div class="topbar-logo-icon"><i class="fa-solid fa-hospital"></i></div>
        QueueCare
    </a>
    <div class="topbar-sep"></div>
    <a href="index.php?action=logout" class="topbar-logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Déconnexion
    </a>
</header>

<div class="setup-wrap">

    <?php if (isset($nouveau)): ?>
    <div class="alert alert-success anim-fade-up" style="margin-bottom:20px">
        <i class="fa-solid fa-circle-check"></i>
        Compte directeur créé ! Complétez maintenant les informations de votre hôpital.
    </div>
    <?php endif; ?>

    <div class="setup-card anim-fade-up">
        <p class="setup-title"><i class="fa-solid fa-hospital" style="color:var(--green);margin-right:10px"></i>Configuration de l'hôpital</p>
        <p class="setup-sub">Ces informations seront visibles par les patients et le personnel.</p>

        <form method="POST" action="admin.php?action=sauver_hopital" style="display:flex;flex-direction:column;gap:22px" novalidate>

            <!-- Nom -->
            <div class="field">
                <label class="field-label" for="nom">Nom de l'établissement <span style="color:#ef4444">*</span></label>
                <div class="field-wrap">
                    <i class="fa-solid fa-hospital field-icon"></i>
                    <input id="nom" type="text" name="nom" class="field-input <?= !empty($erreurs['nom']) ? 'field--error' : '' ?>"
                        placeholder="ex : Hôpital Central de Bafoussam"
                        value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>" required>
                </div>
                <?php if (!empty($erreurs['nom'])): ?>
                <span class="field-error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($erreurs['nom']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Adresse -->
            <div class="field">
                <label class="field-label" for="adresse">Adresse</label>
                <div class="field-wrap">
                    <i class="fa-solid fa-location-dot field-icon"></i>
                    <input id="adresse" type="text" name="adresse" class="field-input"
                        placeholder="Quartier, Ville"
                        value="<?= htmlspecialchars($anciens['adresse'] ?? '') ?>">
                </div>
            </div>

            <!-- Téléphone + Email -->
            <div class="hours-row">
                <div class="field">
                    <label class="field-label" for="telephone">Téléphone</label>
                    <div class="field-wrap">
                        <i class="fa-solid fa-phone field-icon"></i>
                        <input id="telephone" type="text" name="telephone" class="field-input"
                            placeholder="+237 6xx xxx xxx"
                            value="<?= htmlspecialchars($anciens['telephone'] ?? '') ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="email_contact">Email contact</label>
                    <div class="field-wrap">
                        <i class="fa-regular fa-envelope field-icon"></i>
                        <input id="email_contact" type="email" name="email_contact" class="field-input"
                            placeholder="contact@hopital.cm"
                            value="<?= htmlspecialchars($anciens['email_contact'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Horaires -->
            <div class="hours-row">
                <div class="field">
                    <label class="field-label" for="horaires_ouverture">Heure d'ouverture</label>
                    <div class="field-wrap">
                        <i class="fa-regular fa-clock field-icon"></i>
                        <input id="horaires_ouverture" type="time" name="horaires_ouverture" class="field-input"
                            value="<?= htmlspecialchars($anciens['horaires_ouverture'] ?? '08:00') ?>">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="horaires_fermeture">Heure de fermeture</label>
                    <div class="field-wrap">
                        <i class="fa-regular fa-clock field-icon"></i>
                        <input id="horaires_fermeture" type="time" name="horaires_fermeture" class="field-input"
                            value="<?= htmlspecialchars($anciens['horaires_fermeture'] ?? '18:00') ?>">
                    </div>
                </div>
            </div>

            <!-- Jours de fermeture -->
            <div class="field">
                <label class="field-label">Jours de fermeture</label>
                <div class="days-grid" id="days-grid">
                    <?php foreach ($joursLabels as $num => $label): ?>
                    <?php $checked = in_array($num, $joursFermes); ?>
                    <label class="day-toggle <?= $checked ? 'checked' : '' ?>">
                        <input type="checkbox" name="jours_fermeture[]" value="<?= $num ?>"
                               <?= $checked ? 'checked' : '' ?> onchange="toggleDay(this)">
                        <?= $label ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg" style="align-self:flex-start;padding-left:40px;padding-right:40px">
                <i class="fa-solid fa-floppy-disk"></i> Enregistrer la configuration
            </button>

        </form>
    </div>
</div>

<script>
function toggleDay(cb) {
    cb.closest('.day-toggle').classList.toggle('checked', cb.checked);
}
</script>
</body>
</html>
