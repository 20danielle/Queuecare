<?php 
// views/service/sous_services.php
require_once __DIR__ . '/../../helpers/AuthHelper.php';
$estAdmin = AuthHelper::estAdmin();
$userNom = AuthHelper::getUserNom();
$role = AuthHelper::getRole();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sous-services — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display|outfit" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">
    <style>
        body { background: #f0f4fa; font-family: 'Outfit', sans-serif; }
        .page-header { background: linear-gradient(135deg, #0052a0, #003d7a); padding: 24px 32px; color: white; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .admin-badge { background: #f59e0b; color: #1e293b; padding: 6px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
        .card { background: white; border-radius: 20px; margin-bottom: 24px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .card-header { background: #f8fafc; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 700; color: #0052a0; font-size: 1.1rem; }
        .card-body { padding: 20px; }
        .form-inline { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 150px; }
        .form-group label { font-size: 0.7rem; font-weight: 700; color: #1e293b; display: block; margin-bottom: 5px; text-transform: uppercase; }
        .form-group input, .form-group select { width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .btn-create { background: #0052a0; color: white; border: none; padding: 8px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; }
        .btn-create:hover { background: #003d7a; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; color: #1e293b; font-size: 0.8rem; }
        .btn-sm { padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; text-decoration: none; margin: 0 2px; display: inline-block; }
        .btn-edit { background: #e0e7ff; color: #1e40af; }
        .btn-delete { background: #fee2e2; color: #991b1b; }
        .badge-actif { background: #d1fae5; color: #065f46; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; }
        .badge-inactif { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; }
        .flash { background: #d1fae5; color: #065f46; padding: 12px 20px; border-radius: 12px; margin-bottom: 24px; }
        .user-info { display: flex; align-items: center; gap: 16px; }
        .user-name { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.15); padding: 6px 12px; border-radius: 30px; }
        .btn-logout { background: rgba(255,255,255,0.15); border: none; padding: 6px 12px; border-radius: 30px; color: white; cursor: pointer; text-decoration: none; font-size: 0.8rem; }
        .btn-logout:hover { background: rgba(255,255,255,0.25); }
        .nav-links { display: flex; gap: 16px; margin-top: 16px; flex-wrap: wrap; }
        .nav-link { color: white; text-decoration: none; padding: 8px 16px; border-radius: 30px; background: rgba(255,255,255,0.1); transition: all 0.2s; }
        .nav-link:hover { background: rgba(255,255,255,0.2); }
        .nav-link-medecin { background: #10b981; }
        .nav-link-medecin:hover { background: #059669; }
    </style>
</head>
<body>
<header class="page-header">
    <div>
        <h1><i class="fa-solid fa-sitemap"></i> Sous-services</h1>
        <p>Gérer les spécialités / départements de l'établissement</p>
    </div>
    <div class="user-info">
        <div class="user-name">
            <i class="fa-solid fa-crown"></i>
            <span><?= htmlspecialchars($userNom) ?></span>
            <span class="admin-badge">Directeur</span>
        </div>
        <a href="index.php?action=logout" class="btn-logout"><i class="fa-solid fa-right-from-bracket"></i> Déconnexion</a>
    </div>
</header>

<div class="nav-links" style="max-width: 1200px; margin: 16px auto 0; padding: 0 24px;">
    <a href="service.php?action=sous_services" class="nav-link" style="background: #0052a0;"><i class="fa-solid fa-sitemap"></i> Sous-services</a>
    <a href="service.php?action=horaires" class="nav-link"><i class="fa-solid fa-clock"></i> Horaires</a>
    <a href="service.php?action=medecins" class="nav-link"><i class="fa-solid fa-user-md"></i> Médecins</a>
    <a href="service.php?action=jours_travail" class="nav-link"><i class="fa-solid fa-calendar-week"></i> Jours de travail</a>
    <a href="medecin.php" class="nav-link nav-link-medecin"><i class="fa-solid fa-stethoscope"></i> Mon espace médecin</a>
</div>

<div class="container">
    <?php if (!empty($messageAction)): ?>
    <div class="flash"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($messageAction) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="fa-solid fa-plus"></i> Créer un sous-service</div>
        <div class="card-body">
            <form method="POST" action="service.php?action=creer_ss" class="form-inline">
                <div class="form-group">
                    <label>Service *</label>
                    <select name="service_id" required>
                        <option value="">Choisir un service</option>
                        <?php foreach ($services as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="ss_nom" placeholder="ex: Cardiologie" required>
                </div>
                <div class="form-group">
                    <label>Durée RDV (sec.)</label>
                    <input type="number" name="ss_duree" value="1800" step="60">
                </div>
                <div class="form-group">
                    <label>Capacité /h</label>
                    <input type="number" name="ss_capacite" value="10" min="1">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="ss_description" placeholder="Optionnel">
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-create"><i class="fa-solid fa-plus"></i> Créer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><i class="fa-solid fa-list"></i> Liste des sous-services</div>
        <div class="card-body">
            <table>
                <thead>
                    <tr><th>Service</th><th>Nom</th><th>Durée</th><th>Capacité/h</th><th>Statut</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($sousServices as $ss): ?>
                <tr>
                    <td><?= htmlspecialchars($ss['service_nom']) ?></td>
                    <td><?= htmlspecialchars($ss['nom']) ?></td>
                    <td><?= round($ss['duree_rdv_defaut']/60) ?> min</td>
                    <td><?= $ss['capacite_horaire'] ?></td>
                    <td><span class="badge-<?= $ss['statut'] ?>"><?= $ss['statut'] === 'actif' ? 'Actif' : 'Inactif' ?></span></td>
                    <td>
                        <a href="service.php?action=modifier_ss&id=<?= $ss['id'] ?>" class="btn-sm btn-edit"><i class="fa-solid fa-pen"></i> Modifier</a>
                        <a href="service.php?action=basculer_statut_ss&ss_id=<?= $ss['id'] ?>&service_id=<?= $ss['service_id'] ?>" class="btn-sm btn-edit" onclick="return confirm('Changer le statut ?')"><i class="fa-solid fa-toggle-on"></i> Toggle</a>
                        <a href="service.php?action=supprimer_ss&ss_id=<?= $ss['id'] ?>&service_id=<?= $ss['service_id'] ?>" class="btn-sm btn-delete" onclick="return confirm('Supprimer ce sous-service ?')"><i class="fa-solid fa-trash"></i> Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>