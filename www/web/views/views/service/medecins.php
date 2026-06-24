<?php
// views/service/medecins.php
// Version mono-service - Plus de sélection de service
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des médecins - QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Outfit', sans-serif; background: #f5f7fa; color: #1a2a3a; }
        .container { max-width: 1400px; margin: 0 auto; padding: 32px 24px; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.8rem; font-weight: 700; color: #0052a0; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; color: #0052a0; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .btn-back:hover { background: #f0f4f8; border-color: #0052a0; }
        .card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-title { font-size: 1.2rem; font-weight: 700; color: #0052a0; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; gap: 10px; }
        .med-table { width: 100%; border-collapse: collapse; }
        .med-table th { text-align: left; padding: 14px 12px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; color: #475569; border-bottom: 1px solid #e2e8f0; }
        .med-table td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.9rem; vertical-align: middle; }
        .med-table tr:hover { background: #f8fafc; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fed7aa; color: #9a3412; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .btn-sm { padding: 6px 12px; border: none; border-radius: 8px; cursor: pointer; font-size: 0.75rem; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-sm-info { background: #3b82f6; color: white; }
        .btn-sm-info:hover { background: #2563eb; }
        .jours-work { display: flex; gap: 4px; flex-wrap: wrap; }
        .jour-badge { background: #e2e8f0; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-danger { background: #fee2e2; color: #991b1b; padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .empty-state { text-align: center; padding: 60px; color: #94a3b8; }
        .subtitle { color: #64748b; margin-bottom: 24px; }
    </style>
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-user-md"></i> Gestion des médecins</h1>
        <a href="accueil.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Retour à l'accueil</a>
    </div>

    <?php if (!empty($messageAction)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($messageAction) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">
            <i class="fa-solid fa-list"></i>
            Liste des médecins
        </div>
        
        <?php if (empty($medecins)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-user-md" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
            Aucun médecin inscrit pour le moment.
        </div>
        <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="med-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom complet</th>
                        <th>Spécialité</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Jours de travail</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medecins as $med): ?>
                    <tr>
                        <td><?= $med['id'] ?></td>
                        <td><strong><?= htmlspecialchars($med['prenom'] . ' ' . $med['nom']) ?></strong></td>
                        <td><?= htmlspecialchars($med['specialite'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($med['telephone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($med['email'] ?? '—') ?></td>
                        <td>
                            <div class="jours-work">
                                <?php 
                                $joursTravail = $med['jours_travail'] ?? [];
                                $joursSemaine = [
                                    1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 
                                    5 => 'Ven', 6 => 'Sam', 7 => 'Dim'
                                ];
                                if (empty($joursTravail)): ?>
                                <span class="jour-badge" style="background:#fee2e2; color:#991b1b;">Aucun</span>
                                <?php else: ?>
                                    <?php foreach ($joursTravail as $jour): ?>
                                    <span class="jour-badge"><?= $joursSemaine[$jour] ?? $jour ?></span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php 
                            $statut = $med['statut'] ?? 'disponible';
                            $badgeClass = $statut === 'disponible' ? 'badge-success' : ($statut === 'conge' ? 'badge-warning' : 'badge-secondary');
                            $statutLabel = $statut === 'disponible' ? 'Disponible' : ($statut === 'conge' ? 'Congé' : 'Indisponible');
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= $statutLabel ?></span>
                        </td>
                        <td>
                            <a href="service.php?action=jours_travail&medecin_id=<?= $med['id'] ?>" class="btn-sm btn-sm-info">
                                <i class="fa-solid fa-calendar-week"></i> Jours de travail
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <div style="margin-top: 24px; padding: 16px; background: #eef2ff; border-radius: 12px; text-align: center;">
        <i class="fa-solid fa-info-circle" style="color: #0052a0;"></i>
        Les médecins s'inscrivent directement via le formulaire d'inscription.
    </div>
</div>
</body>
</html>