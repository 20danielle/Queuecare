<?php
/**
 * views/admin/liste_utilisateurs.php
 * Liste des gestionnaires ou médecins créés par l'admin
 */
$role       = $role ?? 'gestionnaire';
$isMedecin  = ($role === 'medecin');
$titre      = $isMedecin ? 'Médecins' : 'Gestionnaires';
$icone      = $isMedecin ? 'fa-user-doctor' : 'fa-user-tie';
$autreRole  = $isMedecin ? 'gestionnaire' : 'medecin';
$autreTitre = $isMedecin ? 'Gestionnaires' : 'Médecins';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titre ?> — QueueCare</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="stylesheet" href="public/css/dashboard.css">
    <style>
        .list-table     { width:100%; border-collapse:collapse; }
        .list-table th  { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em;
                          color:var(--text-muted); padding:10px 16px; border-bottom:2px solid var(--border);
                          text-align:left; }
        .list-table td  { padding:14px 16px; border-bottom:1px solid var(--border); font-size:.875rem; color:var(--text-main); vertical-align:middle; }
        .list-table tr:last-child td { border-bottom:none; }
        .list-table tr:hover td      { background:var(--off-white); }
        .avatar         { width:34px; height:34px; border-radius:50%; background:var(--blue-light);
                          display:inline-flex; align-items:center; justify-content:center;
                          font-weight:700; font-size:.8rem; color:var(--blue-dark); }
        .empty-state    { text-align:center; padding:60px 20px; color:var(--text-muted); }
        .empty-state i  { font-size:3rem; color:var(--border); margin-bottom:16px; display:block; }

        /* ── Responsive : tableau empilé sur mobile ── */
        @media (max-width:768px) {
            .dash-header { flex-direction:column; align-items:flex-start; gap:12px; }
            .list-table thead { display:none; }
            .list-table, .list-table tbody, .list-table tr, .list-table td { display:block; width:100%; box-sizing:border-box; }
            .list-table tr { background:var(--white); border:1px solid var(--border); border-radius:var(--radius-sm); padding:.75rem; margin-bottom:.6rem; }
            .list-table td { border-bottom:none; padding:.3rem 0; display:flex; justify-content:space-between; align-items:center; gap:10px; font-size:.83rem; }
            .list-table td::before { content:attr(data-label); font-weight:700; font-size:.72rem; text-transform:uppercase; color:var(--text-muted); flex-shrink:0; }
        }
    </style>
</head>
<body class="dash-body">

<header class="topbar">
    <a href="admin.php?action=dashboard" class="topbar-logo">
        <div class="topbar-logo-icon"><i class="fa-solid fa-hospital"></i></div>
        QueueCare
    </a>
    <div class="topbar-sep"></div>
    <div class="topbar-user">
        <div class="topbar-avatar"><?= strtoupper(mb_substr($adminNom, 0, 1)) ?></div>
        <span><?= htmlspecialchars($adminNom) ?></span>
    </div>
    <a href="index.php?action=logout" class="topbar-logout">
        <i class="fa-solid fa-arrow-right-from-bracket"></i> Déconnexion
    </a>
</header>

<main class="dash-main">
    <div class="dash-header">
        <div>
            <div class="dash-welcome">
                <i class="fa-solid <?= $icone ?>" style="color:var(--green);margin-right:10px"></i><?= $titre ?>
            </div>
            <div class="dash-date"><?= count($utilisateurs) ?> compte(s) enregistré(s)</div>
        </div>
        <div style="display:flex;gap:10px">
            <a href="admin.php?action=lister_utilisateurs&role=<?= $autreRole ?>" class="btn btn-secondary">
                <i class="fa-solid fa-<?= $isMedecin ? 'user-tie' : 'user-doctor' ?>"></i> Voir les <?= $autreTitre ?>
            </a>
            <a href="admin.php?action=creer_utilisateur&role=<?= $role ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Ajouter
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="action-msg">
        <?php if ($_GET['msg'] === 'cree'): ?>
            <i class="fa-solid fa-circle-check"></i> Compte créé avec succès.
        <?php elseif ($_GET['msg'] === 'desactive'): ?>
            <i class="fa-solid fa-circle-check"></i> Compte désactivé.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <?php if (empty($utilisateurs)): ?>
        <div class="empty-state">
            <i class="fa-solid <?= $icone ?>"></i>
            <p>Aucun <?= strtolower(rtrim($titre, 's')) ?> enregistré pour le moment.</p>
            <a href="admin.php?action=creer_utilisateur&role=<?= $role ?>" class="btn btn-primary" style="margin-top:16px">
                <i class="fa-solid fa-plus"></i> Créer le premier
            </a>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="list-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Statut</th>
                    <th>Créé le</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($utilisateurs as $u): ?>
            <tr>
                <td data-label="Nom">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="avatar"><?= strtoupper(mb_substr($u['nom'] ?? '?', 0, 1)) ?></div>
                        <?= htmlspecialchars($u['nom'] ?? '—') ?>
                    </div>
                </td>
                <td data-label="Email"><?= htmlspecialchars($u['email']) ?></td>
                <td data-label="Statut">
                    <?php if ($u['statut'] === 'actif'): ?>
                        <span class="badge badge-green"><i class="fa-solid fa-circle" style="font-size:.5rem"></i> Actif</span>
                    <?php else: ?>
                        <span class="badge badge-grey">Inactif</span>
                    <?php endif; ?>
                </td>
                <td data-label="Créé le"><?= htmlspecialchars(date('d/m/Y', strtotime($u['created_at']))) ?></td>
                <td data-label="Action">
                    <?php if ($u['statut'] === 'actif'): ?>
                    <form method="POST" action="admin.php?action=desactiver_utilisateur"
                          onsubmit="return confirm('Désactiver ce compte ?')"
                          style="display:inline">
                        <input type="hidden" name="id"   value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
                        <button type="submit" class="btn btn-danger" style="padding:6px 14px;font-size:.8rem">
                            <i class="fa-solid fa-ban"></i> Désactiver
                        </button>
                    </form>
                    <?php else: ?>
                    <span style="color:var(--text-light);font-size:.8rem">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.querySelectorAll('.action-msg').forEach(el => {
    setTimeout(() => {
        el.classList.add('dismissing');
        setTimeout(() => el.remove(), 600);
    }, 5000);
});
</script>
</body>
</html>
