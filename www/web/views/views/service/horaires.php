<?php
// views/service/horaires.php
// Version mono-service - Configuration des horaires du service unique
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horaires du service - QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f5f7fa; color: #1a2a3a; }
        .container { max-width: 1000px; margin: 0 auto; padding: 32px 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 700; color: #0052a0; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; color: #0052a0; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .btn-back:hover { background: #f0f4f8; border-color: #0052a0; }
        .card { background: white; border-radius: 24px; padding: 32px; margin-bottom: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-title { font-size: 1.3rem; font-weight: 700; color: #0052a0; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; gap: 12px; }
        .service-info { background: #f0f4f8; padding: 20px; border-radius: 16px; margin-bottom: 28px; }
        .service-name { font-size: 1.2rem; font-weight: 700; color: #0052a0; margin-bottom: 8px; }
        .service-address { color: #64748b; font-size: 0.9rem; }
        .form-group { margin-bottom: 24px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .form-label { display: block; font-weight: 600; margin-bottom: 8px; color: #1a2a3a; }
        .form-label i { width: 24px; color: #0052a0; }
        .form-input { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 0.95rem; transition: all 0.2s; }
        .form-input:focus { outline: none; border-color: #0052a0; box-shadow: 0 0 0 3px rgba(0,82,160,0.1); }
        .jours-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px; margin-top: 8px; }
        .jour-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #f8fafc; border-radius: 10px; cursor: pointer; border: 1px solid #e2e8f0; }
        .jour-item:hover { background: #eef2ff; border-color: #0052a0; }
        .jour-item input { width: 18px; height: 18px; cursor: pointer; accent-color: #0052a0; }
        .jour-item label { flex: 1; cursor: pointer; font-weight: 500; }
        .form-actions { display: flex; gap: 16px; margin-top: 32px; padding-top: 24px; border-top: 1px solid #e2e8f0; }
        .btn-save { background: linear-gradient(135deg, #0052a0, #003d7a); color: white; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; flex: 1; }
        .btn-save:hover { background: linear-gradient(135deg, #003d7a, #002a5a); }
        .btn-reset { background: #f1f5f9; color: #475569; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; }
        .btn-reset:hover { background: #e2e8f0; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-danger { background: #fee2e2; color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .info-note { background: #eef2ff; padding: 14px 18px; border-radius: 12px; margin-top: 24px; font-size: 0.85rem; color: #1e40af; display: flex; align-items: center; gap: 12px; }
        .separator { margin: 0 8px; color: #cbd5e1; }
        small { font-size: 0.7rem; color: #64748b; display: block; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-clock"></i> Horaires du service</h1>
        <a href="accueil.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Retour à l'accueil</a>
    </div>

    <?php if (!empty($messageAction)): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($messageAction) ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-hospital"></i>
            Configuration des horaires
        </div>

        <?php if (!$service): ?>
        <div class="alert-danger" style="padding: 20px; text-align: center;">
            <i class="fa-solid fa-triangle-exclamation"></i> Aucun service trouvé.
        </div>
        <?php else: ?>

        <div class="service-info">
            <div class="service-name">
                <i class="fa-solid fa-building"></i> <?= htmlspecialchars($service['nom']) ?>
            </div>
            <div class="service-address">
                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($service['adresse']) ?>
            </div>
        </div>

        <form method="POST" action="service.php?action=maj_horaires">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-sun"></i> Heure d'ouverture</label>
                    <input type="time" name="horaires_ouverture" class="form-input" 
                           value="<?= substr(htmlspecialchars($service['horaires_ouverture'] ?? '08:00:00'), 0, 5) ?>" required>
                    <small>Heure à laquelle le service ouvre ses portes</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-moon"></i> Heure de fermeture</label>
                    <input type="time" name="horaires_fermeture" class="form-input" 
                           value="<?= substr(htmlspecialchars($service['horaires_fermeture'] ?? '18:00:00'), 0, 5) ?>" required>
                    <small>Heure à laquelle le service ferme</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-utensils"></i> Début de pause</label>
                    <input type="time" name="pause_debut" class="form-input" 
                           value="<?= $service['pause_debut'] ? substr(htmlspecialchars($service['pause_debut']), 0, 5) : '' ?>">
                    <small>Optionnel - Heure de début de la pause déjeuner</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fa-solid fa-utensils"></i> Fin de pause</label>
                    <input type="time" name="pause_fin" class="form-input" 
                           value="<?= $service['pause_fin'] ? substr(htmlspecialchars($service['pause_fin']), 0, 5) : '' ?>">
                    <small>Optionnel - Heure de fin de la pause déjeuner</small>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-calendar-xmark"></i> Jours de fermeture</label>
                <div class="jours-grid">
                    <?php 
                    $joursFermeture = !empty($service['jours_fermeture']) ? explode(',', $service['jours_fermeture']) : [];
                    $joursFermeture = array_map('intval', $joursFermeture);
                    $joursSemaine = [
                        1 => 'Lundi',
                        2 => 'Mardi',
                        3 => 'Mercredi',
                        4 => 'Jeudi',
                        5 => 'Vendredi',
                        6 => 'Samedi',
                        7 => 'Dimanche'
                    ];
                    foreach ($joursSemaine as $num => $nom): 
                    ?>
                    <div class="jour-item">
                        <input type="checkbox" name="jours_fermeture[]" value="<?= $num ?>" id="jour_<?= $num ?>"
                            <?= in_array($num, $joursFermeture) ? 'checked' : '' ?>>
                        <label for="jour_<?= $num ?>"><?= $nom ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small>Cochez les jours où le service est complètement fermé</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer les horaires
                </button>
                <button type="reset" class="btn-reset" onclick="return confirm('Réinitialiser le formulaire ?')">
                    <i class="fa-solid fa-undo"></i> Réinitialiser
                </button>
            </div>
        </form>

        <div class="info-note">
            <i class="fa-solid fa-circle-info" style="font-size: 1.2rem;"></i>
            <div>
                <strong>Informations importantes :</strong><br>
                • Les horaires définis ici s'appliquent à tous les sous-services.<br>
                • Les jours de fermeture annulent tous les rendez-vous pour ce jour.<br>
                • La pause déjeuner est facultative et s'applique à tous les médecins.
            </div>
        </div>

        <?php endif; ?>
    </div>
</div>
</body>
</html>