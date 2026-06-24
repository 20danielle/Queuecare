<?php
// views/service/jours_travail.php
// Configuration des jours de travail d'un médecin
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jours de travail - QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f5f7fa; color: #1a2a3a; }
        .container { max-width: 800px; margin: 0 auto; padding: 32px 24px; }
        .card { background: white; border-radius: 24px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-title { font-size: 1.5rem; font-weight: 700; color: #0052a0; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .medecin-info { background: #f0f4f8; padding: 16px 20px; border-radius: 16px; margin-bottom: 32px; }
        .medecin-name { font-size: 1.2rem; font-weight: 700; color: #0052a0; }
        .medecin-specialite { color: #64748b; margin-top: 4px; }
        .jours-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin: 24px 0; }
        .jour-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: #f8fafc; border-radius: 12px; cursor: pointer; transition: all 0.2s; border: 1px solid #e2e8f0; }
        .jour-item:hover { background: #eef2ff; border-color: #0052a0; }
        .jour-item input { width: 20px; height: 20px; cursor: pointer; accent-color: #0052a0; }
        .jour-item label { flex: 1; cursor: pointer; font-weight: 500; }
        .form-actions { display: flex; gap: 16px; margin-top: 32px; }
        .btn-save { background: linear-gradient(135deg, #0052a0, #003d7a); color: white; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; cursor: pointer; flex: 1; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 600; cursor: pointer; text-decoration: none; text-align: center; flex: 1; }
        .btn-save:hover { background: linear-gradient(135deg, #003d7a, #002a5a); }
        .btn-cancel:hover { background: #e2e8f0; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-calendar-week"></i>
            Configuration des jours de travail
        </div>
        
        <div class="medecin-info">
            <div class="medecin-name">
                <i class="fa-solid fa-user-md"></i> 
                Dr. <?= htmlspecialchars($medecin['prenom'] . ' ' . $medecin['nom']) ?>
            </div>
            <div class="medecin-specialite">
                <i class="fa-solid fa-stethoscope"></i> 
                Spécialité : <?= htmlspecialchars($medecin['specialite'] ?? 'Non renseignée') ?>
            </div>
        </div>
        
        <form method="POST" action="service.php?action=traiter_jours_travail">
            <input type="hidden" name="medecin_id" value="<?= $medecin['id'] ?>">
            
            <p style="margin-bottom: 16px; color: #475569;">
                <i class="fa-solid fa-circle-info"></i> 
                Sélectionnez les jours où le médecin travaille habituellement.
            </p>
            
            <div class="jours-grid">
                <?php foreach ($joursSemaine as $num => $nom): ?>
                <div class="jour-item">
                    <input type="checkbox" name="jours[]" value="<?= $num ?>" id="jour_<?= $num ?>"
                        <?= in_array($num, $joursTravail) ? 'checked' : '' ?>>
                    <label for="jour_<?= $num ?>"><?= $nom ?></label>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                </button>
                <a href="service.php?action=medecins" class="btn-cancel">
                    <i class="fa-solid fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>
</body>
</html>