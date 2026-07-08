<?php
// views/medecin/dashboard.php

// ── Timeout inactivité 15 min ──
const SESSION_TIMEOUT = 900;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: medecin.php?action=connexion&timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

// ============================================================
// GESTION DES VARIABLES AVEC VALEURS PAR DÉFAUT
// ============================================================

if (!isset($medecin) || !$medecin) {
    $medecin = [];
}

$medecinNom = isset($medecin['nom']) && isset($medecin['prenom']) 
    ? $medecin['prenom'] . ' ' . $medecin['nom']
    : (isset($_SESSION['medecin_nom']) ? $_SESSION['medecin_nom'] : 'Médecin');

$initiale = mb_strtoupper(mb_substr($medecinNom, 0, 1));
$dureeMin = isset($affectation['duree_estimee']) ? round($affectation['duree_estimee'] / 60) : 30;
$hasService = isset($affectation) && $affectation && !empty($affectation['ss_id']);
$serviceNom = isset($affectation['service_nom']) ? $affectation['service_nom'] : '';
$ssNom = isset($affectation['ss_nom']) ? $affectation['ss_nom'] : '';

// ============================================================
// HORAIRES DU SERVICE POUR LE PLANNING
// ============================================================
$serviceHoraires = [];
if (isset($service) && $service) {
    $serviceHoraires = [
        'ouverture' => $service['horaires_ouverture'] ?? '08:00:00',
        'fermeture' => $service['horaires_fermeture'] ?? '18:00:00',
        'pause_debut' => $service['pause_debut'] ?? null,
        'pause_fin' => $service['pause_fin'] ?? null
    ];
} else {
    $serviceHoraires = [
        'ouverture' => '08:00:00',
        'fermeture' => '18:00:00',
        'pause_debut' => null,
        'pause_fin' => null
    ];
}

// ============================================================
// GESTION DE LA PHOTO DE PROFIL
// ============================================================

$photoUrl = 'public/images/default-avatar.png';
$photoValide = false;

if (isset($medecin) && !empty($medecin['photo']) && $medecin['photo'] !== 'null') {
    $cheminPhysique = $_SERVER['DOCUMENT_ROOT'] . '/fil-attente2/' . $medecin['photo'];
    if (!file_exists($cheminPhysique)) {
        $cheminPhysique = __DIR__ . '/../' . $medecin['photo'];
    }
    if (file_exists($cheminPhysique)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . $host . '/fil-attente2/';
        $photoUrl = $baseUrl . $medecin['photo'];
        $photoValide = true;
    }
}

if (!$photoValide && isset($medecin) && !empty($medecin['photo']) && $medecin['photo'] !== 'null') {
    $testPhysique = __DIR__ . '/../' . $medecin['photo'];
    if (file_exists($testPhysique)) {
        $photoUrl = $medecin['photo'];
        $photoValide = true;
    }
}

// ============================================================
// STATISTIQUES ET CONSULTATIONS AVEC VALEURS PAR DÉFAUT
// ============================================================

if (!isset($stats) || !$stats) {
    $stats = ['total' => 0, 'traitees' => 0, 'en_attente' => 0, 'absentes' => 0, 'annulees' => 0, 'duree_moy_sec' => 0];
}

if (!isset($consultations) || !$consultations) {
    $consultations = [];
}

if (!isset($planning) || !$planning) {
    $planning = [];
}

// Jours de travail du médecin
$medecinJoursTravail = isset($medecin['jours_travail']) && is_array($medecin['jours_travail']) 
    ? $medecin['jours_travail'] 
    : [1, 2, 3, 4, 5];

$messageAction = $_SESSION['message_action'] ?? '';
$erreurAction = $_SESSION['erreur_action'] ?? '';
unset($_SESSION['message_action']);
unset($_SESSION['erreur_action']);

// Formatage des horaires pour affichage
$heureOuverture = substr($serviceHoraires['ouverture'], 0, 5);
$heureFermeture = substr($serviceHoraires['fermeture'], 0, 5);
$pauseDebut = $serviceHoraires['pause_debut'] ? substr($serviceHoraires['pause_debut'], 0, 5) : null;
$pauseFin = $serviceHoraires['pause_fin'] ? substr($serviceHoraires['pause_fin'], 0, 5) : null;
?>
<!DOCTYPE html>
<html lang="<?= \LangHelper::getLang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= __('page_title_doctor') ?></title>
    <link rel="icon" type="image/png" href="public/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="64x64" href="public/img/favicon-64.png">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/medecin.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
    <style>
        /* ===== PLANNING TABLE STYLES ===== */
        /* ══ CALENDRIER STYLE GOOGLE CALENDAR ══ */
        .gcal-wrapper { display:flex; overflow-x:auto; border-radius:14px; border:1px solid #e2e8f0; background:#fff; box-shadow:0 2px 16px rgba(0,82,160,.07); min-width:720px; }
        .gcal-time-col { width:64px; flex-shrink:0; border-right:1px solid #e8edf5; background:#fafbfc; }
        .gcal-time-col .gcal-header-cell { border-bottom:1px solid #e8edf5; height:52px; }
        .gcal-time-label { height:56px; display:flex; align-items:flex-start; justify-content:flex-end; padding:4px 10px 0 0; font-size:.7rem; font-weight:600; color:#94a3b8; user-select:none; }
        .gcal-time-label.pause-time { color:#d97706; }
        .gcal-days { display:flex; flex:1; min-width:0; }
        .gcal-day-col { flex:1; min-width:90px; border-right:1px solid #f1f5f9; display:flex; flex-direction:column; }
        .gcal-day-col:last-child { border-right:none; }
        .gcal-header-cell { height:52px; display:flex; flex-direction:column; align-items:center; justify-content:center; border-bottom:1px solid #e8edf5; padding:4px 2px; position:sticky; top:0; z-index:5; background:#fff; gap:2px; }
        .gcal-header-cell.today-col { background:linear-gradient(135deg,#eff6ff,#dbeafe); }
        .gcal-header-cell.off-col { background:#f8fafc; opacity:.8; }
        .gcal-day-name { font-size:.72rem; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
        .gcal-day-num { font-size:1.15rem; font-weight:700; color:#1e293b; line-height:1; }
        .gcal-day-num.today-num { background:#0052a0; color:#fff; border-radius:50%; width:30px; height:30px; display:flex; align-items:center; justify-content:center; font-size:1rem; }
        .gcal-slot { height:56px; border-bottom:1px solid #f1f5f9; position:relative; padding:1px 3px; box-sizing:border-box; }
        .gcal-slot.pause-slot { background:repeating-linear-gradient(45deg,#fef9c3,#fef9c3 4px,#fefce8 4px,#fefce8 10px); }
        .gcal-slot.off-slot { background:repeating-linear-gradient(45deg,#f8fafc,#f8fafc 4px,#f1f5f9 4px,#f1f5f9 10px); }
        .gcal-event { border-radius:5px; padding:3px 6px; font-size:.68rem; line-height:1.3; overflow:hidden; height:calc(100% - 2px); box-sizing:border-box; display:flex; flex-direction:column; justify-content:flex-start; cursor:default; }
        .gcal-event.ev-attente { background:#bfdbfe; border-left:3px solid #3b82f6; color:#1e3a8a; }
        .gcal-event.ev-cours { background:#bbf7d0; border-left:3px solid #10b981; color:#064e3b; }
        .gcal-event.ev-traite { background:#e2e8f0; border-left:3px solid #94a3b8; color:#475569; opacity:.85; }
        .gcal-event.ev-pause { background:#fde68a; border-left:3px solid #f59e0b; color:#78350f; }
        .gcal-event.ev-absent { background:#fecaca; border-left:3px solid #ef4444; color:#7f1d1d; opacity:.9; }
        .gcal-event-time { font-weight:700; font-size:.62rem; white-space:nowrap; }
        .gcal-event-name { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gcal-event-badge { display:inline-block; font-size:.58rem; font-weight:700; padding:1px 5px; border-radius:8px; margin-top:2px; background:rgba(255,255,255,.55); }
        .gcal-slot-empty { display:flex; align-items:center; justify-content:center; height:100%; }
        
        /* ===== AUTRES STYLES ===== */
        .refresh-indicator { position: fixed; bottom: 20px; right: 20px; background: #0052a0; color: white; padding: 8px 16px; border-radius: 30px; font-size: 0.75rem; display: none; align-items: center; gap: 8px; z-index: 1000; }
        .refresh-indicator.show { display: flex; }
        .refresh-indicator i { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        
        .semaine-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .semaine-label { background: #dbeafe; color: #1e40af; padding: 6px 16px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .btn-icon { padding: 8px 16px; background: #f0f4f8; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-left: 8px; font-size: 0.8rem; }
        .btn-icon:hover { background: #e2e8f0; }
        
        .info-banner {
            background: #eef2ff;
            padding: 10px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: #1e40af;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            padding: 10px 16px;
            background: #f8fafc;
            border-radius: 10px;
            flex-wrap: wrap;
            font-size: 0.7rem;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }
        
        /* Photos et sidebar */
        .photo-preview { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin: 0 auto 10px; display: block; border: 3px solid #0052a0; background: #f0f4f8; }
        .photo-upload-area { text-align: center; margin-bottom: 20px; }
        .photo-upload-label { display: inline-block; padding: 6px 12px; background: #0052a0; color: white; border-radius: 6px; cursor: pointer; font-size: 0.75rem; }
        .sidebar-avatar-img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
        .sidebar-avatar { width: 48px; height: 48px; background: #00a86b; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; color: #0052a0; overflow: hidden; }
        .profil-form { max-width: 600px; margin: 0 auto; }
        .profil-form .form-group { margin-bottom: 20px; }
        .profil-form .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #1a2a3a; }
        .profil-form .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .profil-form .form-group input:focus { outline: none; border-color: #0052a0; }
        .profil-separator { border-top: 1px solid #e2e8f0; margin: 25px 0; }
        .profil-title { font-size: 1.2rem; font-weight: 700; color: #0052a0; margin-bottom: 20px; }
        .btn-save { background: linear-gradient(135deg, #0052a0, #003d7a); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; }
        .profil-success { background: #e6f7f0; color: #00a86b; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .profil-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .rang-badge { width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .rang-badge.current { background: #10b981; color: white; animation: pulse 1s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        .med-table { width: 100%; border-collapse: collapse; }
        .med-table th { text-align: left; padding: 12px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; }
        .med-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 16px; padding: 16px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .stat-icon { font-size: 1.8rem; color: #0052a0; margin-bottom: 6px; }
        .stat-value { font-size: 1.6rem; font-weight: 700; color: #0052a0; }
        .stat-label { font-size: 0.7rem; color: #64748b; }
        .empty-state { text-align: center; padding: 40px; color: #64748b; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-warning { background: #fed7aa; color: #9a3412; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
        .btn-sm { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.7rem; font-weight: 600; margin: 0 2px; }
        .btn-sm-success { background: #10b981; color: white; }
        .btn-sm-info { background: #3b82f6; color: white; }
        .btn-sm-warning { background: #f59e0b; color: white; }
        .btn-sm-danger { background: #ef4444; color: white; }
        .alert-success { background: #d1fae5; color: #065f46; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; }
        .alert-danger { background: #fee2e2; color: #991b1b; padding: 14px 18px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; }
        .section { background: white; border-radius: 20px; padding: 20px; margin-bottom: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 2px solid #e2e8f0; }

        /* ── Search bar ── */
        .search-bar-wrap { padding: 0 0 16px 0; }
        .search-bar { display: flex; align-items: center; background: #1e293b; border: 1.5px solid #334155; border-radius: 12px; padding: 4px 4px 4px 14px; gap: 10px; transition: border-color .2s; }
        .search-bar:focus-within { border-color: #6d5ce7; }
        .search-icon { color: #94a3b8; font-size: .9rem; flex-shrink: 0; }
        .search-bar input { flex: 1; border: none; background: transparent; font-family: inherit; font-size: .9rem; color: #e2e8f0; outline: none; min-width: 0; }
        .search-bar input::placeholder { color: #64748b; }
        .search-bar input:-webkit-autofill,
        .search-bar input:-webkit-autofill:hover,
        .search-bar input:-webkit-autofill:focus,
        .search-bar input:-webkit-autofill:active {
            -webkit-text-fill-color: #e2e8f0 !important;
            -webkit-box-shadow: 0 0 0 30px #1e293b inset !important;
            box-shadow: 0 0 0 30px #1e293b inset !important;
            caret-color: #e2e8f0;
            transition: background-color 5000s ease-in-out 0s;
        }
        .search-clear { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: .85rem; padding: 2px 4px; border-radius: 4px; }
        .search-clear:hover { color: #ef4444; }
        .search-filter-btn { display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg,#6d5ce7,#5b4bd4); color: #fff; border: none; border-radius: 9px; padding: 10px 18px; font-size: .85rem; font-weight: 600; cursor: pointer; flex-shrink: 0; white-space: nowrap; transition: opacity .2s; }
        .search-filter-btn:hover { opacity: .9; }
        .search-no-result { text-align: center; padding: 32px; color: #94a3b8; font-size: .9rem; }
        .statut-pills { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:12px; }
        .statut-pill { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; font-size:.75rem; font-weight:600; cursor:pointer; transition:all .15s; white-space:nowrap; }
        .statut-pill:hover { border-color:#6d5ce7; color:#6d5ce7; background:#f5f3ff; }
        .statut-pill.active { border-color:#6d5ce7; background:#6d5ce7; color:#fff; }
        .statut-pill .pill-count { background:rgba(255,255,255,.25); border-radius:10px; padding:0 5px; font-size:.7rem; }
        .statut-pill:not(.active) .pill-count { background:#e2e8f0; color:#475569; }


        /* ── Profil lock screen inside dashboard ── */
        .profil-lock { max-width: 400px; margin: 40px auto; background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,.08); padding: 40px 32px; text-align: center; border: 1px solid #e2e8f0; }
        .profil-lock-icon { width: 72px; height: 72px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; color: #0052a0; }
        .profil-lock-title { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .profil-lock-sub { color: #64748b; font-size: .875rem; margin-bottom: 20px; }
        .profil-lock-error { color: #ef4444; font-size: .8rem; margin-bottom: 10px; background: #fee2e2; padding: 8px 12px; border-radius: 8px; }
        .profil-lock-input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: .9rem; margin-bottom: 14px; outline: none; }
        .profil-lock-input:focus { border-color: #0052a0; }
        .profil-lock-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #0052a0, #003d7a); color: white; border: none; border-radius: 10px; font-family: inherit; font-size: .95rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .profil-lock-btn:hover { opacity: .9; }

        /* ── Responsive improvements ── */
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
            .dashboard-content { padding: 16px; }
            .section { padding: 16px; border-radius: 14px; }
            .topbar { padding: 12px 16px; }
            .page-title { font-size: 1.1rem; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
            .stat-card { padding: 16px 12px; border-radius: 14px; }
            .stat-value { font-size: 1.5rem; }
            .med-table th, .med-table td { padding: 8px 6px; font-size: .75rem; }
            .search-bar input { font-size: .8rem; }
            .profil-lock { padding: 28px 16px; margin: 16px auto; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .section-header { flex-wrap: wrap; gap: 8px; }
            .topbar { flex-wrap: wrap; gap: 8px; }
        }
        /* ── Boutons période stats ── */
        .btn-periode {
            padding: 6px 14px; border: 1.5px solid #e2e8f0; border-radius: 20px;
            background: white; cursor: pointer; font-size: .78rem; font-weight: 600;
            color: #64748b; font-family: inherit; transition: all .15s;
        }
        .btn-periode:hover { border-color: #0052a0; color: #0052a0; }
        .btn-periode.active { background: #0052a0; border-color: #0052a0; color: white; }
        /* ── Charts responsive ── */
        .charts-double-col { grid-template-columns: 1fr 1fr; }
        @media (max-width: 700px) { .charts-double-col { grid-template-columns: 1fr; } }
        .section-title { font-size: 1rem; font-weight: 700; color: #0052a0; }
        .topbar { background: white; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 99; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.3rem; font-weight: 700; color: #0052a0; }
        .dashboard-content { padding: 24px; }
        .app-container { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: linear-gradient(180deg, #0a2540 0%, #0f2c3f 100%); color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; overflow-y: auto; transition: transform 0.3s ease; z-index: 200; }
        .sidebar-header { padding: 20px 16px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { display: flex; align-items: center; gap: 10px; font-size: 1.3rem; font-weight: 700; margin-bottom: 24px; }
        .sidebar-logo-icon { width: 32px; height: 32px; background: #fff; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
        .sidebar-logo-icon img { width: 100%; height: 100%; object-fit: contain; padding: 3px; }
        .sidebar-logo i { color: #00a86b; font-size: 1.5rem; }
        .sidebar-user { display: flex; align-items: center; gap: 12px; }
        .sidebar-user-info { flex: 1; min-width: 0; }
        .sidebar-user-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-service { font-size: 0.7rem; opacity: 0.7; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; margin: 4px 12px; border-radius: 12px; cursor: pointer; transition: all 0.2s; color: rgba(255,255,255,0.7); }
        .nav-item i { width: 20px; font-size: 1rem; flex-shrink: 0; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid rgba(255,255,255,0.1); }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 16px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px; transition: all 0.2s; }
        .logout-btn:hover { background: rgba(255,255,255,0.1); color: white; }
        .lang-toggle-wrap { display:flex; align-items:center; gap:0; background:rgba(255,255,255,.08); border-radius:10px; padding:4px; margin-bottom:12px; }
        .lang-toggle-wrap .lang-label { font-size:.65rem; font-weight:600; color:rgba(255,255,255,.45); text-transform:uppercase; letter-spacing:.06em; padding:0 6px 0 4px; flex-shrink:0; }
        .lang-toggle-btn { flex:1; padding:6px 0; border:none; border-radius:7px; background:transparent; color:rgba(255,255,255,.55); font-size:.8rem; cursor:pointer; font-family:inherit; font-weight:500; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:4px; }
        .lang-toggle-btn.active { background:#00a86b; color:#fff; font-weight:700; box-shadow:0 2px 8px rgba(0,168,107,.35); }
        .main-content { flex: 1; margin-left: 260px; background: #f5f7fa; min-height: 100vh; }

        /* Hamburger */
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 8px; border-radius: 8px; color: #0a2540; }
        .hamburger span { display: block; width: 22px; height: 2px; background: currentColor; margin: 5px 0; transition: all 0.3s; border-radius: 2px; }
        .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .hamburger.open span:nth-child(2) { opacity: 0; }
        .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 150; }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
            .hamburger { display: block; }
            .topbar { padding: 12px 16px; }
            .page-title { font-size: 1.1rem; }
            .dashboard-content { padding: 16px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 12px; }
            .stat-card { padding: 16px 12px; }
            .section { padding: 16px; margin-bottom: 20px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr !important; gap: 8px; }
            .stat-card { padding: 12px 8px; }
            #modalPause > div, #modalRdv > div { padding: 20px 18px !important; }
        }
    </style>
</head>
<body>
<?php if (!empty($_SESSION['must_reset_password'])): include __DIR__ . '/../partials/modal_reset_password.php'; endif; ?>
<div class="app-container">

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div id="refreshIndicator" class="refresh-indicator">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span><?= __('refreshing') ?></span>
    </div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <div class="sidebar-logo-icon"><img src="public/img/logo-queuecare-icon.png" alt="QueueCare"></div>
                <span>QueueCare</span>
            </div>
            <div class="sidebar-user">
                <?php if ($photoValide): ?>
                <img src="<?= htmlspecialchars($photoUrl) ?>" class="sidebar-avatar-img" alt="Photo de profil">
                <?php else: ?>
                <div class="sidebar-avatar"><?= htmlspecialchars($initiale) ?></div>
                <?php endif; ?>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">Dr. <?= htmlspecialchars($medecinNom) ?></div>
                    <?php if ($hasService && !empty($ssNom)): ?>
                    <div class="sidebar-user-service"><?= htmlspecialchars($ssNom) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item active" data-section="consultations" onclick="showSection('consultations')">
                <i class="fa-solid fa-calendar-day"></i><span><?= __('today_consultations') ?></span>
            </div>
            <div class="nav-item" data-section="planning" onclick="showSection('planning')">
                <i class="fa-solid fa-calendar-week"></i><span><?= __('my_schedule') ?></span>
            </div>
            <div class="nav-item" data-section="stats" onclick="showSection('stats')">
                <i class="fa-solid fa-chart-line"></i><span><?= __('my_stats') ?></span>
            </div>
            <div class="nav-item" data-section="historique" onclick="showSection('historique')">
                <i class="fa-solid fa-clock-rotate-left"></i><span><?= __('history') ?></span>
            </div>
            <div class="nav-item" data-section="profil" onclick="showSection('profil')">
                <i class="fa-solid fa-user"></i><span><?= __('my_profile') ?></span>
            </div>
        </nav>
        
        <div class="sidebar-footer">
            <!-- Sélecteur de langue -->
            <?php $currentLang = \LangHelper::getLang(); ?>
            <div class="lang-toggle-wrap">
                <span class="lang-label"><i class="fa-solid fa-language"></i></span>
                <button type="button" class="lang-toggle-btn <?= $currentLang==='fr'?'active':'' ?>"
                        onclick="dashChangerLangue('fr','medecin')">
                    🇫🇷 <?= __('lang_fr_full') ?>
                </button>
                <button type="button" class="lang-toggle-btn <?= $currentLang==='en'?'active':'' ?>"
                        onclick="dashChangerLangue('en','medecin')">
                    🇬🇧 <?= __('lang_en_full') ?>
                </button>
            </div>
            <a href="accueil.php" class="logout-btn" style="margin-bottom: 6px;"><i class="fa-solid fa-house"></i><span><?= __('sidebar_home') ?></span></a>
            <a href="medecin.php?action=deconnexion" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i><span><?= __('sidebar_logout') ?></span></a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:12px">
                <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
                <h1 class="page-title"><?= __('dashboard') ?></h1>
            </div>
            <div>
                <button class="btn-icon" onclick="window.location.reload()"><i class="fa-solid fa-rotate-right"></i> <?= __('refresh') ?></button>
            </div>
        </div>
        
        <div class="dashboard-content">
            
            <div id="timeoutBanner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;background:#c2410c;color:#fff;padding:12px 24px;align-items:center;justify-content:space-between;gap:16px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.3);">
                <span><i class="fa-solid fa-triangle-exclamation" style="margin-right:8px;"></i><?= __('session_expiring') ?></span>
                <button onclick="document.getElementById('timeoutBanner').style.display='none';resetIdleTimer();"><?= __('stay_connected') ?></button>
            </div>
            
            <div id="messageContainer">
                <?php if (!empty($messageAction)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($messageAction) ?></div>
                <?php endif; ?>
                <?php if (!empty($erreurAction)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($erreurAction) ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Section Consultations du jour -->
            <div id="section-consultations" class="section">
                <div class="section-header">
                    <span class="section-title"><i class="fa-solid fa-calendar-day"></i> <?= __('today_consultations') ?></span>
                    <div>
                        <?php if (!empty($consultations)): ?>
                        <button type="button" class="btn-sm btn-sm-danger" onclick="annulerToutesConsultations()" title="<?= __('postpone_tomorrow_title') ?>"><i class="fa-solid fa-calendar-plus"></i> <?= __('postpone_tomorrow') ?></button>
                        <?php endif; ?>
                        <span id="consultationsCount" class="badge badge-info"><?= count($consultations) ?> <?= __('consultation_count') ?></span>
                    </div>
                </div>
                <div id="medConsultsPills" class="statut-pills"></div>
                <div id="consultationsContent">
                    <?php
                    $statutOrdreMed = ['en_cours'=>0,'en_pause'=>1,'confirme'=>2,'en_attente'=>3,'traite'=>4,'absent'=>5,'annule'=>6];
                    usort($consultations, function($a,$b) use($statutOrdreMed){
                        $oa = $statutOrdreMed[$a['statut']] ?? 99;
                        $ob = $statutOrdreMed[$b['statut']] ?? 99;
                        return $oa !== $ob ? $oa - $ob : (int)$a['rang'] - (int)$b['rang'];
                    });
                    ?>
                    <?php if (empty($consultations)): ?>
                    <div class="empty-state"><?= __('no_consultation_today') ?></div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="med-table">
                            <thead>
                                <tr><th>#</th><th><?= __('patient') ?></th><th><?= __('phone') ?></th><th><?= __('expected_time') ?></th><th><?= __('start_time') ?></th><th><?= __('end_time') ?></th><th><?= __('status') ?></th><th><?= __('action') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consultations as $c): ?>
                                <tr data-id="<?= $c['id'] ?>">
                                    <td><div class="rang-badge <?= $c['statut'] === 'en_cours' ? 'current' : '' ?>"><?= (int)$c['rang'] ?></div></td>
                                    <td><strong><?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?></strong></td>
                                    <td><?= htmlspecialchars($c['telephone']) ?></td>
                                    <td><?= isset($c['heure_passage_estimee']) && $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td>
                                    <td><?= isset($c['heure_debut_reelle']) && $c['heure_debut_reelle'] ? date('H:i', strtotime($c['heure_debut_reelle'])) : '—' ?></td>
                                    <td><?= isset($c['heure_fin_reelle']) && $c['heure_fin_reelle'] ? date('H:i', strtotime($c['heure_fin_reelle'])) : '—' ?></td>
                                    <td><?php 
                                        $bc = match($c['statut']) { 
                                            'traite' => 'badge-success', 
                                            'en_cours' => 'badge-info', 
                                            'annule' => 'badge-secondary', 
                                            'absent' => 'badge-danger', 
                                            default => 'badge-secondary' 
                                        }; 
                                        $lb = match($c['statut']) { 
                                            'traite' => __('status_treated'), 
                                            'en_cours' => __('status_in_progress'), 
                                            'annule' => __('status_cancelled'), 
                                            'absent' => __('status_absent'), 
                                            'en_attente' => __('status_waiting'), 
                                            'confirme' => __('status_confirmed'), 
                                            default => $c['statut'] 
                                        }; 
                                        ?><span class="badge <?= $bc ?>"><?= $lb ?></span></td>
                                    <td><div style="display:flex; gap:5px;">
                                        <?php if ($c['statut'] === 'en_cours'): ?>
                                        <button class="btn-sm btn-sm-success" onclick="terminerConsultation(<?= $c['id'] ?>)"><i class="fa-solid fa-check"></i> <?= __('finish') ?></button>
                                        <button class="btn-sm btn-sm-primary" onclick="ouvrirModalRdv(<?= $c['id'] ?>, '<?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?>')"><i class="fa-solid fa-calendar-plus"></i> <?= __('next_appointment') ?></button>
                                        <?php elseif (in_array($c['statut'], ['en_attente', 'confirme'])): ?>
                                        <button class="btn-sm btn-sm-info" onclick="demarrerConsultation(<?= $c['id'] ?>)"><i class="fa-solid fa-play"></i> <?= __('start') ?></button>
                                        <?php endif; ?>
                                        <?php if (!in_array($c['statut'], ['traite', 'annule', 'absent'])): ?>
                                        <button class="btn-sm btn-sm-warning" onclick="marquerAbsent(<?= $c['id'] ?>)"><i class="fa-solid fa-user-slash"></i> <?= __('absent_btn') ?></button>
                                        <?php endif; ?>
                                        </div></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section Planning -->
            <div id="section-planning" class="section" style="display:none;">
                <div class="section-header">
                    <span class="section-title"><i class="fa-solid fa-calendar-week"></i> <?= __('my_timetable') ?></span>
                    <button class="btn-icon" onclick="chargerPlanning()"><i class="fa-solid fa-rotate-right"></i> <?= __('refresh') ?></button>
                </div>
                
                <div class="info-banner">
                    <i class="fa-solid fa-clock"></i>
                    <span><?= __('hours_label') ?> : <strong><?= $heureOuverture ?> - <?= $heureFermeture ?></strong></span>
                    <?php if ($pauseDebut && $pauseFin): ?>
                    <span>• <?= __('break_label') ?> : <strong><?= $pauseDebut ?> - <?= $pauseFin ?></strong></span>
                    <?php endif; ?>
                </div>
                
                <div class="legend">
                    <div class="legend-item"><div class="legend-color" style="background:#bfdbfe;border-left:3px solid #3b82f6;border-radius:3px;"></div><span><?= __('legend_scheduled') ?></span></div>
                    <div class="legend-item"><div class="legend-color" style="background:#bbf7d0;border-left:3px solid #10b981;border-radius:3px;"></div><span><?= __('legend_in_progress') ?></span></div>
                    <div class="legend-item"><div class="legend-color" style="background:#e2e8f0;border-left:3px solid #94a3b8;border-radius:3px;"></div><span><?= __('legend_treated') ?></span></div>
                    <div class="legend-item"><div class="legend-color" style="background:#fde68a;border-left:3px solid #f59e0b;border-radius:3px;"></div><span><?= __('legend_paused') ?></span></div>
                    <div class="legend-item"><div class="legend-color" style="background:repeating-linear-gradient(45deg,#f8fafc,#f8fafc 4px,#f1f5f9 4px,#f1f5f9 9px);border:1px solid #e2e8f0;border-radius:3px;"></div><span><?= __('legend_unavailable') ?></span></div>
                </div>
                
                <div class="semaine-nav">
                    <div style="display:flex;gap:8px;">
                        <button class="btn-icon" onclick="changerSemaine(-1)"><i class="fa-solid fa-chevron-left"></i> <?= __('prev_week') ?></button>
                        <button class="btn-icon" onclick="changerSemaine(0)"><i class="fa-solid fa-calendar-day"></i> <?= __('this_week') ?></button>
                        <button class="btn-icon" onclick="changerSemaine(1)"><?= __('next_week') ?> <i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div id="semaineLabel" class="semaine-label"><?= __('loading') ?></div>
                </div>
                
                <div id="planningContainer">
                    <div id="planningLoading" style="text-align:center;padding:40px;">
                        <i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem;color:#0052a0;"></i>
                        <p style="margin-top:10px;"><?= __('loading') ?></p>
                    </div>
                    <div id="planningTable" style="display:none;"></div>
                </div>
            </div>
            
            <!-- Section Statistiques -->
            <div id="section-stats" style="display:none;">
                <!-- Filtres période -->
                <div class="section" style="margin-bottom:16px;padding:16px 20px;">
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <span style="font-weight:600;font-size:.9rem;color:#0052a0;"><i class="fa-solid fa-calendar-range"></i> <?= __('period_label') ?></span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button class="btn-periode" data-jours="0" onclick="changerPeriode(this,0)"><?= __('today_btn') ?></button>
                            <button class="btn-periode active" data-jours="7" onclick="changerPeriode(this,7)"><?= __('days_7') ?></button>
                            <button class="btn-periode" data-jours="30" onclick="changerPeriode(this,30)"><?= __('days_30') ?></button>
                            <button class="btn-periode" data-jours="90" onclick="changerPeriode(this,90)"><?= __('months_3') ?></button>
                            <button class="btn-periode" data-jours="365" onclick="changerPeriode(this,365)"><?= __('year_1') ?></button>
                        </div>
                    </div>
                </div>

                <!-- Cards résumé -->
                <div class="stats-grid" id="statsGridMed">
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-users"></i></div><div class="stat-value" id="statTotal"><?= (int)($stats['total'] ?? 0) ?></div><div class="stat-label"><?= __('total') ?></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div><div class="stat-value" id="statTraitees"><?= (int)($stats['traitees'] ?? 0) ?></div><div class="stat-label"><?= __('treated') ?></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="stat-value" id="statEnAttente"><?= (int)($stats['en_attente'] ?? 0) ?></div><div class="stat-label"><?= __('waiting') ?></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-user-slash"></i></div><div class="stat-value" id="statAbsentes"><?= (int)($stats['absentes'] ?? 0) ?></div><div class="stat-label"><?= __('absents') ?></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-ban"></i></div><div class="stat-value" id="statAnnulees"><?= (int)($stats['annulees'] ?? 0) ?></div><div class="stat-label"><?= __('cancelled_pl') ?></div></div>
                    <div class="stat-card"><div class="stat-icon"><i class="fa-solid fa-clock"></i></div><div class="stat-value" id="statDureeMoy"><?php $dm = (int)($stats['duree_moy_sec'] ?? 0); echo $dm > 0 ? round($dm / 60) . 'min' : '—'; ?></div><div class="stat-label"><?= __('avg_duration') ?></div></div>
                </div>

                <!-- Graphiques -->
                <div class="section" style="margin-bottom:20px;">
                    <div class="section-header">
                        <span class="section-title"><i class="fa-solid fa-chart-line"></i> <?= __('consultations_evolution') ?></span>
                        <div id="statsChargement" style="font-size:.8rem;color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> <?= __('loading') ?></div>
                    </div>
                    <canvas id="chartEvolution" style="max-height:300px;"></canvas>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;" class="charts-double-col">
                    <div class="section">
                        <div class="section-header"><span class="section-title"><i class="fa-solid fa-chart-pie"></i> <?= __('status_breakdown') ?></span></div>
                        <canvas id="chartDonut" style="max-height:260px;"></canvas>
                    </div>
                    <div class="section">
                        <div class="section-header"><span class="section-title"><i class="fa-solid fa-chart-bar"></i> <?= __('consultations_per_weekday') ?></span></div>
                        <canvas id="chartBarJour" style="max-height:260px;"></canvas>
                    </div>
                </div>

                <!-- Section Temps d'attente moyen -->
                <div class="section" style="margin-bottom:20px;">
                    <div class="section-header">
                        <span class="section-title"><i class="fa-solid fa-hourglass-half" style="color:#f59e0b;"></i> <?= __('avg_wait_time_title') ?></span>
                        <div id="attenteChargement" style="font-size:.8rem;color:#64748b;"><i class="fa-solid fa-spinner fa-spin"></i> <?= __('loading') ?></div>
                    </div>
                    <!-- Cards résumé attente -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;" id="attenteCardsGrid">
                        <div class="stat-card" style="border-top:3px solid #f59e0b;">
                            <div class="stat-icon" style="color:#f59e0b;"><i class="fa-solid fa-clock"></i></div>
                            <div class="stat-value" id="attenteGlobal">—</div>
                            <div class="stat-label"><?= __('avg_wait_global') ?></div>
                        </div>
                        <div class="stat-card" style="border-top:3px solid #3b82f6;">
                            <div class="stat-icon" style="color:#3b82f6;"><i class="fa-solid fa-calendar-week"></i></div>
                            <div class="stat-value" id="attente7j">—</div>
                            <div class="stat-label"><?= __('avg_wait_7d') ?></div>
                        </div>
                        <div class="stat-card" style="border-top:3px solid #10b981;">
                            <div class="stat-icon" style="color:#10b981;"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="stat-value" id="attente30j">—</div>
                            <div class="stat-label"><?= __('avg_wait_30d') ?></div>
                        </div>
                        <div class="stat-card" style="border-top:3px solid #6366f1;">
                            <div class="stat-icon" style="color:#6366f1;"><i class="fa-solid fa-stethoscope"></i></div>
                            <div class="stat-value" id="attenteNbMesures">—</div>
                            <div class="stat-label"><?= __('measured_consultations') ?></div>
                        </div>
                    </div>
                    <!-- Tendance (indicateur comparaison) -->
                    <div id="attenteTendance" style="background:#f8fafc;border-radius:10px;padding:12px 16px;margin-bottom:18px;font-size:.85rem;color:#475569;display:none;">
                    </div>
                    <!-- Graphique évolution -->
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
                        <span style="font-size:.82rem;font-weight:600;color:#475569;"><?= __('chart_period') ?></span>
                        <button class="btn-periode active" data-att="30" onclick="changerPeriodeAttente(this,30)"><?= __('days_30') ?></button>
                        <button class="btn-periode" data-att="90" onclick="changerPeriodeAttente(this,90)"><?= __('months_3') ?></button>
                        <button class="btn-periode" data-att="180" onclick="changerPeriodeAttente(this,180)"><?= __('months_6') ?></button>
                        <button class="btn-periode" data-att="365" onclick="changerPeriodeAttente(this,365)"><?= __('year_1') ?></button>
                        <button class="btn-periode" data-att="9999" onclick="changerPeriodeAttente(this,9999)"><?= __('since_beginning') ?></button>
                    </div>
                    <canvas id="chartTempsAttente" style="max-height:280px;"></canvas>
                </div>
            </div>
            
            <!-- Section Profil -->
            <div id="section-historique" class="section" style="display:none;">
                <div class="section-header">
                    <span class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> <?= __('consultation_history') ?></span>
                    <span id="historiqueTotal" class="badge badge-secondary" style="background:#f1f5f9;color:#475569;">—</span>
                </div>
                <!-- Filtres -->
                <div id="histPillsMed" class="statut-pills"></div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;padding:14px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
                    <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px;">
                        <label style="font-size:.72rem;font-weight:600;color:#475569;"><?= __('from') ?></label>
                        <input type="date" id="histDateDebut" onchange="chargerHistorique(1)" style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px;">
                        <label style="font-size:.72rem;font-weight:600;color:#475569;"><?= __('to') ?></label>
                        <input type="date" id="histDateFin" onchange="chargerHistorique(1)" style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    </div>
                    <div style="display:flex;align-items:flex-end;">
                        <button onclick="reinitFiltresHist()" style="padding:8px 14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:.82rem;color:#64748b;">
                            <i class="fa-solid fa-rotate-left"></i> <?= __('reset_filters') ?>
                        </button>
                    </div>
                </div>
                <div id="historiqueContent">
                    <div class="empty-state"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem;color:#0052a0;"></i><p><?= __('loading') ?></p></div>
                </div>
                <div id="historiquePagination" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap;"></div>
            </div>

            <div id="section-profil" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-user"></i> <?= __('my_profile') ?></span></div>
                <!-- Lock screen profil -->
                <div id="profilLockScreen" class="profil-lock">
                    <div class="profil-lock-icon"><i class="fa-solid fa-lock"></i></div>
                    <div class="profil-lock-title"><?= __('profile_locked') ?></div>
                    <div class="profil-lock-sub"><?= __('profile_locked_sub') ?></div>
                    <div class="profil-lock-error" id="profilLockError" style="display:none;"><?= __('wrong_password') ?></div>
                    <input type="password" class="profil-lock-input" id="profilLockPassword" placeholder="<?= __('profile_lock_pw_input') ?>" autocomplete="current-password">
                    <button class="profil-lock-btn" onclick="verifierMdpProfil()"><i class="fa-solid fa-unlock"></i> <?= __('access_profile') ?></button>
                </div>
                <div id="profilContent" style="display:none;"><div class="empty-state"><?= __('loading') ?></div></div>
            </div>
        </div>
    </main>
</div>

<script>
    // ── Internationalisation : dictionnaire de la langue courante + helper t() ──
    const I18N = <?= \LangHelper::toJson() ?>;
    function t(key) { return I18N[key] || key; }
    const LOCALE = '<?= \LangHelper::getLang() === "en" ? "en-US" : "fr-FR" ?>';

    let currentSection = 'consultations', isRefreshing = false, isRefreshingStats = false, planningOffset = 0;
    let _medConsultStatutFilter = null; // null = tous
    let _histStatutFilterMed = ''; // '' = tous
    
    const serviceHoraires = {
        ouverture: '<?= $heureOuverture ?>',
        fermeture: '<?= $heureFermeture ?>',
        pause_debut: '<?= $pauseDebut ?>',
        pause_fin: '<?= $pauseFin ?>'
    };
    
    const medecinJoursTravail = <?= json_encode($medecinJoursTravail) ?>;
    
    function afficherMessage(msg, type) {
        const c = document.getElementById('messageContainer');
        if (!c) return;
        const d = document.createElement('div');
        d.className = type === 'error' ? 'alert alert-danger' : 'alert alert-success';
        d.innerHTML = msg;
        c.innerHTML = '';
        c.appendChild(d);
        setTimeout(() => d.remove(), 4000);
    }
    
    function estJourTravaille(jourSemaine) {
        return medecinJoursTravail.includes(jourSemaine);
    }
    
    function rafraichirConsultations(auto) {
        if (isRefreshing) return;
        isRefreshing = true;
        fetch('medecin.php?action=get_consultations_data')
            .then(r => r.json()).then(d => {
                // Le gestionnaire a déclenché une urgence (aucune consultation
                // n'était en cours au moment du clic) : on avertit le médecin
                // puis on le déconnecte automatiquement de son dashboard.
                if (d.force_logout) {
                    alert(d.message || 'Vous avez été placé en indisponibilité par le gestionnaire (urgence).');
                    window.location.href = d.redirect || 'medecin.php?action=connexion&urgence=1';
                    return;
                }
                // DEBUG TEMPORAIRE : journalise la réponse brute du serveur pour diagnostiquer
                // les cas où la liste de consultations se vide après une action.
                console.log('[DEBUG rafraichirConsultations]', d);
                // Ne mettre à jour QUE si le serveur confirme le succès avec des données
                if (d.success && Array.isArray(d.consultations)) {
                    if (d.consultations.length === 0 && _consultationsDataMed.length > 0) {
                        console.warn('[DEBUG] Le serveur renvoie une liste VIDE alors que des consultations étaient affichées juste avant. Réponse complète ci-dessus.');
                    }
                    mettreAJourConsultations(d.consultations, !!auto);
                    mettreAJourStatistiques(d.stats);
                } else if (d.redirect) {
                    window.location.href = d.redirect;
                }
                // Si échec auth ou autre erreur : on garde l'affichage existant intact
            })
            .catch(e => console.error('Erreur rafraichissement:', e))
            .finally(() => { isRefreshing = false; });
    }
    
    function rafraichirStatistiques() {
        if (isRefreshingStats) return;
        isRefreshingStats = true;
        fetch('medecin.php?action=get_stats_data')
            .then(r => r.json()).then(d => { if (d.success) mettreAJourStatistiques(d.stats); })
            .catch(e => console.error(e))
            .finally(() => { isRefreshingStats = false; });
    }
    
    function mettreAJourConsultations(cons, preservePage) {
        const statutOrdreMed = {en_cours:0,en_pause:1,confirme:2,en_attente:3,traite:4,absent:5,annule:6};
        (cons||[]).sort((a,b)=>{
            const oa = statutOrdreMed[a.statut]??99, ob = statutOrdreMed[b.statut]??99;
            return oa!==ob ? oa-ob : (a.rang||0)-(b.rang||0);
        });
        _consultationsDataMed = cons || [];
        const cs = document.getElementById('consultationsCount');
        if(cs) cs.innerHTML = _consultationsDataMed.length + ' consultation(s)';
        renderMedConsultsPills();
        if(!preservePage) _consultPageMed = 1;
        afficherConsultationsMed(_getMedConsultsFiltrees(), false);
    }

    function renderMedConsultsPills() {
        const bar = document.getElementById('medConsultsPills'); if(!bar) return;
        const labels = {en_cours:t('status_in_progress'),en_pause:t('status_paused'),confirme:t('status_confirmed'),en_attente:t('status_waiting'),traite:t('status_treated'),absent:t('status_absent'),annule:t('status_cancelled')};
        const colors = {en_cours:'#3b82f6',en_pause:'#f59e0b',confirme:'#6d5ce7',en_attente:'#94a3b8',traite:'#22c55e',absent:'#f97316',annule:'#ef4444'};
        const counts = {};
        for(const c of _consultationsDataMed) counts[c.statut] = (counts[c.statut]||0)+1;
        const total = _consultationsDataMed.length;
        let h = `<button class="statut-pill${_medConsultStatutFilter===null?' active':''}" onclick="setMedConsultStatutFilter(null)">${t('all')} <span class="pill-count">${total}</span></button>`;
        for(const [st, label] of Object.entries(labels)) {
            if(!counts[st]) continue;
            const isActive = _medConsultStatutFilter === st;
            const col = isActive ? '' : `color:${colors[st]};border-color:${colors[st]}40;`;
            h += `<button class="statut-pill${isActive?' active':''}" style="${col}" onclick="setMedConsultStatutFilter('${st}')">${label} <span class="pill-count">${counts[st]}</span></button>`;
        }
        bar.innerHTML = h;
    }

    function setMedConsultStatutFilter(st) {
        _medConsultStatutFilter = st;
        renderMedConsultsPills();
        _consultPageMed = 1;
        afficherConsultationsMed(_getMedConsultsFiltrees(), false);
    }

    function _getMedConsultsFiltrees() {
        if(_medConsultStatutFilter === null) return _consultationsDataMed;
        return _consultationsDataMed.filter(c => c.statut === _medConsultStatutFilter);
    }

    let _consultPageMed = 1;
    const _PER_PAGE_MED = 10;

    function afficherConsultationsMed(cons, filtered) {
        const c = document.getElementById('consultationsContent');
        if (!c) return;
        if (!cons || cons.length === 0) { c.innerHTML = `<div class="search-no-result"><i class="fa-solid fa-search"></i><br>${filtered?t('no_result_search'):t('no_consultation_scheduled')}</div>`; return; }
        const totalPages = Math.max(1, Math.ceil(cons.length / _PER_PAGE_MED));
        if(_consultPageMed > totalPages) _consultPageMed = totalPages;
        const debut = (_consultPageMed - 1) * _PER_PAGE_MED;
        const page = cons.slice(debut, debut + _PER_PAGE_MED);
        // Bandeau d'alerte si un patient en_pause avec priorité attend
        const patientsPrioritaires = cons.filter(v => v.statut === 'en_pause' && v.priorite_retour);
        const hasEnCours = cons.some(v => v.statut === 'en_cours');
        let bandeauPrioritaire = '';
        if(patientsPrioritaires.length > 0 && !hasEnCours) {
            const noms = patientsPrioritaires.map(v => `${escapeHtml(v.patient_prenom)} ${escapeHtml(v.patient_nom)}`).join(', ');
            bandeauPrioritaire = `<div style="background:#fef3c7;border:1.5px solid #f59e0b;border-radius:10px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#d97706;font-size:1.1rem;"></i>
                <span style="font-size:.85rem;font-weight:600;color:#92400e;">${t('priority_patients_banner')} <em>${noms}</em> — ${t('call_asap')}</span>
            </div>`;
        }

        let h = bandeauPrioritaire + `<div style="overflow-x:auto;"><table class="med-table"><thead><tr><th>#</th><th>${t('patient')}</th><th>${t('phone')}</th><th>${t('expected_time')}</th><th>${t('start_time')}</th><th>${t('end_time')}</th><th>${t('status')}</th><th>${t('action')}</th></tr></thead><tbody>`;
        for(const v of page) {
            const isPauseRetour = v.statut === 'en_pause' && v.priorite_retour;
            const bc = v.statut === 'traite' ? 'badge-success' : (v.statut === 'en_cours' ? 'badge-info' : (v.statut === 'annule' ? 'badge-secondary' : (v.statut === 'absent' ? 'badge-danger' : (v.statut === 'en_pause' ? 'badge-pause' : 'badge-secondary'))));
            const lb = v.statut === 'traite' ? 'Traitée' : (v.statut === 'en_cours' ? 'En cours' : (v.statut === 'annule' ? 'Annulée' : (v.statut === 'absent' ? 'Absent' : (v.statut === 'en_pause' ? '⏸ En pause' : (v.statut === 'en_attente' ? 'En attente' : 'Confirmé')))));
            // Sous-info pause
            let pauseDetail = '';
            if(v.statut === 'en_pause' && v.secondes_en_pause > 0) {
                const mins = Math.floor(v.secondes_en_pause/60);
                const secs = v.secondes_en_pause % 60;
                pauseDetail = `<br><small style="color:#b45309;font-size:.7rem;"><i class="fa-solid fa-clock"></i> ${t('left_since')} ${mins}min ${secs}s${v.motif_pause?' — '+escapeHtml(v.motif_pause):''}</small>`;
            }
            if(isPauseRetour) {
                pauseDetail += `<br><span style="display:inline-flex;align-items:center;gap:4px;background:#0052a0;color:white;font-size:.68rem;font-weight:700;border-radius:20px;padding:2px 8px;margin-top:3px;"><i class="fa-solid fa-star"></i> ${t('return_priority_badge')}</span>`;
            }
            const rowStyle = isPauseRetour ? 'style="background:#eff6ff;border-left:3px solid #0052a0;"' : (v.statut==='en_pause'?'style="background:#fffbeb;"':'');
            h += `<tr ${rowStyle}>
                <td><div class="rang-badge ${v.statut === 'en_cours' ? 'current' : ''}">${v.rang}</div></td>
                <td><strong>${escapeHtml(v.patient_nom)} ${escapeHtml(v.patient_prenom)}</strong></td>
                <td>${escapeHtml(v.telephone)}</td>
                <td>${v.heure_passage_estimee || '—'}</td>
                <td>${v.heure_debut_reelle || '—'}</td>
                <td>${v.heure_fin_reelle || '—'}</td>
                <td><span class="badge ${bc}">${lb}</span>${pauseDetail}</td>
                <td><div style="display:flex; gap:5px; flex-wrap:wrap;">`;
            if(v.statut === 'en_cours') {
                h += `<button class="btn-sm btn-sm-success" onclick="terminerConsultation(${v.id})"><i class="fa-solid fa-check"></i> ${t('finish')}</button>`;
                h += `<button class="btn-sm btn-sm-primary" onclick="ouvrirModalRdv(${v.id}, '${(v.patient_nom||'')} ${(v.patient_prenom||'')}')"><i class="fa-solid fa-calendar-plus"></i> ${t('next_appointment')}</button>`;
                h += `<button class="btn-sm btn-sm-pause" onclick="ouvrirModalPause(${v.id})"><i class="fa-solid fa-pause"></i> ${t('pause_exam')}</button>`;
            }
            else if(v.statut === 'en_pause') {
                h += `<button class="btn-sm btn-sm-resume" style="${isPauseRetour?'font-weight:700;border:2px solid #0052a0;':''}" onclick="reprendreConsultation(${v.id})"><i class="fa-solid fa-play"></i> ${isPauseRetour?t('resume_priority'):t('resume')}</button>`;
                h += `<button class="btn-sm btn-sm-success" onclick="terminerConsultation(${v.id})"><i class="fa-solid fa-check"></i> ${t('finish')}</button>`;
            }
            else if(v.statut === 'en_attente' || v.statut === 'confirme') h += `<button class="btn-sm btn-sm-info" onclick="demarrerConsultation(${v.id})"><i class="fa-solid fa-play"></i> ${t('start')}</button>`;
            if(!['traite','annule','absent','en_pause'].includes(v.statut)) h += `<button class="btn-sm btn-sm-warning" onclick="marquerAbsent(${v.id})"><i class="fa-solid fa-user-slash"></i> ${t('absent_btn')}</button>`;
            h += `</div></td></tr>`;
        }
        h += '</tbody></table></div>';
        h += renderPaginationMed(cons, _consultPageMed, totalPages);
        c.innerHTML = h;
    }

    function renderPaginationMed(cons, page, totalPages) {
        if(totalPages <= 1) return '';
        const btnS = 'padding:5px 10px;border-radius:7px;border:1.5px solid #e2e8f0;background:white;cursor:pointer;font-size:.75rem;font-family:inherit;';
        const actS = 'padding:5px 10px;border-radius:7px;border:1.5px solid #0052a0;background:#0052a0;color:white;cursor:pointer;font-size:.75rem;font-family:inherit;font-weight:700;';
        let h = `<div style="display:flex;justify-content:center;align-items:center;gap:5px;margin-top:14px;flex-wrap:wrap;">`;
        h += `<button style="${btnS}" onclick="_goPageMed(${page-1},event)" ${page<=1?'disabled':''}><i class="fa-solid fa-angle-left"></i></button>`;
        for(let i=1; i<=totalPages; i++) h += `<button style="${i===page?actS:btnS}" onclick="_goPageMed(${i},event)">${i}</button>`;
        h += `<button style="${btnS}" onclick="_goPageMed(${page+1},event)" ${page>=totalPages?'disabled':''}><i class="fa-solid fa-angle-right"></i></button>`;
        h += `<span style="font-size:.72rem;color:#64748b;margin-left:4px;">${(page-1)*_PER_PAGE_MED+1}–${Math.min(page*_PER_PAGE_MED,cons.length)} / ${cons.length}</span>`;
        h += '</div>';
        return h;
    }

    function _goPageMed(p, e) {
        e && e.stopPropagation();
        _consultPageMed = p;
        afficherConsultationsMed(_getMedConsultsFiltrees(), false);
    }
    
    function mettreAJourStatistiques(stats) {
        const st = document.getElementById('statTotal'), stt = document.getElementById('statTraitees'), sea = document.getElementById('statEnAttente'), sa = document.getElementById('statAbsentes'), san = document.getElementById('statAnnulees'), sdm = document.getElementById('statDureeMoy');
        if(st) st.innerHTML = stats.total || 0;
        if(stt) stt.innerHTML = stats.traitees || 0;
        if(sea) sea.innerHTML = stats.en_attente || 0;
        if(sa) sa.innerHTML = stats.absentes || 0;
        if(san) san.innerHTML = stats.annulees || 0;
        if(sdm) sdm.innerHTML = (stats.duree_moy_sec || 0) > 0 ? Math.round((stats.duree_moy_sec || 0) / 60) + 'min' : '—';
    }
    
    function escapeHtml(t) { if(!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    
    function _handleAjaxResponse(d, successMsg) {
        if (d.redirect) { window.location.href = d.redirect; return; }
        if (d.success) { afficherMessage(successMsg, 'success'); rafraichirConsultations(); }
        else afficherMessage(d.message || t('generic_error'), 'error');
    }

    function demarrerConsultation(id) {
        fetch('medecin.php?action=demarrer_consultation_ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'consultation_id=' + id
        })
        .then(r => r.json()).then(d => {
            if (d.redirect) { window.location.href = d.redirect; return; }
            if (d.success) {
                afficherMessage(t('consultation_started'), 'success');
                rafraichirConsultations();
            } else {
                afficherMessage(d.message || t('cant_start'), 'error');
            }
        })
        .catch(e => { afficherMessage(t('network_error_short'), 'error'); console.error(e); });
    }

    function terminerConsultation(id) {
        fetch('medecin.php?action=terminer_consultation_ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'consultation_id=' + id
        })
        .then(r => r.json()).then(d => {
            if (d.redirect) { if (d.message) alert(d.message); window.location.href = d.redirect; return; }
            if (d.success) {
                afficherMessage(t('consultation_finished'), 'success');
                rafraichirConsultations();
            } else {
                afficherMessage(d.message || t('cant_finish'), 'error');
                console.error('Debug:', d.debug || d);
            }
        })
        .catch(e => { afficherMessage(t('network_error_short'), 'error'); console.error(e); });
    }

    function marquerAbsent(id) {
        fetch('medecin.php?action=marquer_absent_ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'consultation_id=' + id
        })
        .then(r => r.json()).then(d => {
            if (d.redirect) { if (d.message) alert(d.message); window.location.href = d.redirect; return; }
            if (d.success) {
                afficherMessage(t('patient_marked_absent'), 'success');
                rafraichirConsultations();
            } else {
                afficherMessage(d.message || t('generic_error'), 'error');
                console.error('Debug:', d.debug || d);
            }
        })
        .catch(e => { afficherMessage(t('network_error_short'), 'error'); console.error(e); });
    }

    // ── Pause examen ──
    let _pauseConsultationId = null;

    function ouvrirModalPause(id) {
        _pauseConsultationId = id;
        const modal = document.getElementById('modalPause');
        const motifInput = document.getElementById('pauseMotif');
        if(motifInput) motifInput.value = '';
        if(modal) {
            modal.style.display = 'flex';
            setTimeout(() => motifInput && motifInput.focus(), 100);
        }
    }

    function fermerModalPause() {
        const modal = document.getElementById('modalPause');
        if(modal) modal.style.display = 'none';
        _pauseConsultationId = null;
    }

    function confirmerPause() {
        const id    = _pauseConsultationId;
        const motif = document.getElementById('pauseMotif')?.value.trim() || t('default_exam_reason');
        if(!id) return;
        fermerModalPause();
        fetch('medecin.php?action=mettre_en_pause_ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `consultation_id=${id}&motif=${encodeURIComponent(motif)}`
        })
        .then(r => r.json()).then(d => {
            if(d.success) {
                afficherMessage(t('consultation_paused_with') + ' ' + motif, 'success');
                setTimeout(rafraichirConsultations, 600);
            } else {
                afficherMessage(d.message || t('generic_error'), 'error');
            }
        })
        .catch(() => afficherMessage(t('network_error_short'), 'error'));
    }

    function reprendreConsultation(id) {
        if(!confirm(t('patient_returned_confirm'))) return;
        fetch('medecin.php?action=reprendre_consultation_ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'consultation_id=' + id
        })
        .then(r => r.json()).then(d => {
            if(d.success) {
                afficherMessage(t('consultation_resumed_ex'), 'success');
                setTimeout(rafraichirConsultations, 600);
            } else {
                afficherMessage(d.message || t('generic_error'), 'error');
            }
        })
        .catch(() => afficherMessage(t('network_error_short'), 'error'));
    }

    // Fermer le modal pause avec Escape
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') fermerModalPause();
    });
    
    function annulerToutesConsultations() {
        if(confirm(t('postpone_all_confirm'))) {
            fetch('medecin.php?action=annuler_toutes_ajax', { method: 'POST' })
                .then(r => r.json()).then(d => _handleAjaxResponse(d, t('postponed_with_priority')))
                .catch(() => afficherMessage(t('retry_please'), 'error'));
        }
    }


    
    function toggleSidebar(){
        const sb=document.getElementById('sidebar'), ov=document.getElementById('sidebarOverlay'), hb=document.getElementById('hamburgerBtn');
        if(!sb) return;
        sb.classList.toggle('open');
        if(ov) ov.classList.toggle('active');
        if(hb) hb.classList.toggle('open');
    }

    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if(window.innerWidth <= 900){
                    const sb=document.getElementById('sidebar'), ov=document.getElementById('sidebarOverlay'), hb=document.getElementById('hamburgerBtn');
                    if(sb) sb.classList.remove('open');
                    if(ov) ov.classList.remove('active');
                    if(hb) hb.classList.remove('open');
                }
            });
        });
    });

    let _histPage = 1;

    function showSection(section) {
        currentSection = section;
        // Sauvegarder dans localStorage pour restaurer l'onglet après un rechargement
        // (ex: changement de langue), afin que l'interface ne "saute" pas ailleurs.
        localStorage.setItem('medecin_active_section', section);
        document.getElementById('section-consultations').style.display = 'none';
        document.getElementById('section-planning').style.display = 'none';
        document.getElementById('section-stats').style.display = 'none';
        document.getElementById('section-historique').style.display = 'none';
        document.getElementById('section-profil').style.display = 'none';
        if(section === 'consultations') {
            document.getElementById('section-consultations').style.display = 'block';
            if (_initialLoad) { _initialLoad = false; }
        }
        else if(section === 'planning') { document.getElementById('section-planning').style.display = 'block'; chargerPlanning(); }
        else if(section === 'stats') { document.getElementById('section-stats').style.display = 'block'; rafraichirStatistiques(); chargerStatsEvolution(); chargerTempsAttente(); }
        else if(section === 'historique') { document.getElementById('section-historique').style.display = 'block'; chargerHistorique(1); }
        else if(section === 'profil') {
            document.getElementById('section-profil').style.display = 'block';
            const ls=document.getElementById('profilLockScreen'), pc=document.getElementById('profilContent'), lp=document.getElementById('profilLockPassword');
            if(ls) ls.style.display='block'; if(pc) pc.style.display='none';
            if(lp) lp.value=''; _profilPasswordMed='';
            const le=document.getElementById('profilLockError'); if(le) le.style.display='none';
        }
        document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
        const activeNav = document.querySelector(`.nav-item[data-section="${section}"]`);
        if(activeNav) activeNav.classList.add('active');
    }

    // ── Historique médecin ──
    function chargerHistorique(page, silent) {
        _histPage = page || 1;
        const statut    = _histStatutFilterMed;
        const dateDebut = document.getElementById('histDateDebut')?.value || '';
        const dateFin   = document.getElementById('histDateFin')?.value || '';
        const content   = document.getElementById('historiqueContent');
        const pagination = document.getElementById('historiquePagination');
        if(!silent){
            if(content) content.innerHTML = '<div class="empty-state"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.4rem;color:#0052a0;"></i></div>';
            if(pagination) pagination.innerHTML = '';
        }

        const params = new URLSearchParams({ page: _histPage, statut, date_debut: dateDebut, date_fin: dateFin });
        fetch('medecin.php?action=get_historique&' + params.toString())
            .then(r => r.json())
            .then(d => {
                if(d.redirect) { window.location.href = d.redirect; return; }
                if(!d.success) { if(content && !silent) content.innerHTML = `<div class="empty-state">${d.message||'Erreur'}</div>`; return; }
                const tot = document.getElementById('historiqueTotal');
                if(tot) tot.textContent = d.total + ' consultation(s)';
                renderHistPillsMed(d.counts || {}, d.total || 0);
                afficherTableauHistorique(d.data);
                afficherPaginationHist(d.page, d.last_page);
            })
            .catch(() => { if(content && !silent) content.innerHTML = `<div class="empty-state">${t('network_error_short')}</div>`; });
    }

    function renderHistPillsMed(counts, total) {
        const bar = document.getElementById('histPillsMed'); if(!bar) return;
        const labels = {en_attente:t('status_waiting'),confirme:t('status_confirmed'),en_cours:t('status_in_progress'),en_pause:t('status_paused'),traite:t('status_treated'),absent:t('status_absent'),annule:t('status_cancelled')};
        const colors = {en_attente:'#94a3b8',confirme:'#6d5ce7',en_cours:'#3b82f6',en_pause:'#f59e0b',traite:'#22c55e',absent:'#f97316',annule:'#ef4444'};
        let h = `<button class="statut-pill${_histStatutFilterMed===''?' active':''}" onclick="setHistStatutFilterMed('')">${t('all')} <span class="pill-count">${total}</span></button>`;
        for(const [st, label] of Object.entries(labels)) {
            const cnt = counts[st] || 0;
            if(!cnt) continue;
            const isActive = _histStatutFilterMed === st;
            const col = isActive ? '' : `color:${colors[st]};border-color:${colors[st]}40;`;
            h += `<button class="statut-pill${isActive?' active':''}" style="${col}" onclick="setHistStatutFilterMed('${st}')">${label} <span class="pill-count">${cnt}</span></button>`;
        }
        bar.innerHTML = h;
    }

    function setHistStatutFilterMed(st) {
        _histStatutFilterMed = st;
        chargerHistorique(1);
    }

    function afficherTableauHistorique(rows) {
        const c = document.getElementById('historiqueContent');
        if(!c) return;
        if(!rows || rows.length === 0) {
            c.innerHTML = `<div class="empty-state"><i class="fa-solid fa-inbox" style="font-size:2rem;margin-bottom:10px;"></i><br>${t('no_consultation_found')}</div>`;
            return;
        }
        let h = `<div style="overflow-x:auto;"><table class="med-table"><thead><tr><th>${t('date_label')}</th><th>${t('patient')}</th><th>${t('phone')}</th><th>${t('expected_time')}</th><th>${t('start_short')}</th><th>${t('end_time')}</th><th>${t('duration_label')}</th><th>${t('status')}</th><th>${t('mode_label')}</th></tr></thead><tbody>`;
        for(const v of rows) {
            const bcMap = { traite:'badge-success', absent:'badge-warning', annule:'badge-danger', en_cours:'badge-info', en_pause:'badge-secondary', en_attente:'badge-secondary', confirme:'badge-secondary' };
            const lbMap = { traite:t('status_treated'), absent:t('status_absent'), annule:t('status_cancelled'), en_cours:t('status_in_progress'), en_pause:t('status_paused'), en_attente:t('status_waiting'), confirme:t('status_confirmed') };
            const modeMap = { LIGNE:`<span class="badge badge-info" style="font-size:.65rem;">${t('online_mode')}</span>`, PLACE:`<span class="badge badge-secondary" style="font-size:.65rem;">${t('onsite_mode')}</span>`, MANUEL:`<span class="badge badge-secondary" style="font-size:.65rem;">${t('manual_mode')}</span>`, QR_CODE:'<span class="badge badge-secondary" style="font-size:.65rem;">QR Code</span>' };
            h += `<tr>
                <td style="white-space:nowrap;">${escapeHtml(v.date_consultation)}</td>
                <td><strong>${escapeHtml(v.patient_nom)} ${escapeHtml(v.patient_prenom)}</strong></td>
                <td>${escapeHtml(v.telephone)}</td>
                <td>${escapeHtml(v.heure_estimee_fmt)}</td>
                <td>${escapeHtml(v.heure_debut_fmt)}</td>
                <td>${escapeHtml(v.heure_fin_fmt)}</td>
                <td>${escapeHtml(v.duree_fmt)}</td>
                <td><span class="badge ${bcMap[v.statut]||'badge-secondary'}">${lbMap[v.statut]||v.statut}</span></td>
                <td>${modeMap[v.mode_prise]||v.mode_prise}</td>
            </tr>`;
        }
        h += '</tbody></table></div>';
        c.innerHTML = h;
    }

    function afficherPaginationHist(page, lastPage) {
        const p = document.getElementById('historiquePagination');
        if(!p || lastPage <= 1) return;
        let h = '';
        const btnStyle = 'padding:6px 12px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;cursor:pointer;font-size:.8rem;font-family:inherit;';
        const activStyle = 'padding:6px 12px;border-radius:8px;border:1.5px solid #0052a0;background:#0052a0;color:white;cursor:pointer;font-size:.8rem;font-family:inherit;font-weight:700;';
        h += `<button style="${btnStyle}" onclick="chargerHistorique(1)" ${page<=1?'disabled':''}><i class="fa-solid fa-angles-left"></i></button>`;
        h += `<button style="${btnStyle}" onclick="chargerHistorique(${page-1})" ${page<=1?'disabled':''}><i class="fa-solid fa-angle-left"></i></button>`;
        const from = Math.max(1, page - 2), to = Math.min(lastPage, page + 2);
        if(from > 1) h += `<span style="padding:6px 4px;font-size:.8rem;color:#94a3b8;">…</span>`;
        for(let i=from; i<=to; i++) h += `<button style="${i===page?activStyle:btnStyle}" onclick="chargerHistorique(${i})">${i}</button>`;
        if(to < lastPage) h += `<span style="padding:6px 4px;font-size:.8rem;color:#94a3b8;">…</span>`;
        h += `<button style="${btnStyle}" onclick="chargerHistorique(${page+1})" ${page>=lastPage?'disabled':''}><i class="fa-solid fa-angle-right"></i></button>`;
        h += `<button style="${btnStyle}" onclick="chargerHistorique(${lastPage})" ${page>=lastPage?'disabled':''}><i class="fa-solid fa-angles-right"></i></button>`;
        p.innerHTML = h;
    }

    function reinitFiltresHist() {
        const dd = document.getElementById('histDateDebut'), df = document.getElementById('histDateFin');
        if(dd) dd.value=''; if(df) df.value='';
        _histStatutFilterMed = '';
        chargerHistorique(1);
    }
    
    function getLundiSemaine(offset) { offset = offset || 0; const t = new Date(), dow = t.getDay(), diff = dow === 0 ? -6 : -(dow - 1), l = new Date(t); l.setDate(t.getDate() + diff + (offset * 7)); return l; }
    function formatDateISO(d) { return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0'); }
    function formatDateFr(d) { return d.toLocaleDateString(LOCALE,{day:'2-digit',month:'2-digit',year:'numeric'}); }
    function getNomJour(d) { return d.toLocaleDateString(LOCALE,{weekday:'long'}); }
    function getNumJourSemaine(d) { let jour = d.getDay(); return jour === 0 ? 7 : jour; }
    function changerSemaine(delta) { if(delta===0) planningOffset=0; else planningOffset+=delta; chargerPlanning(); }
    
    function chargerPlanning(silent) {
        const l = getLundiSemaine(planningOffset), dd = formatDateISO(l);
        const dim = new Date(l);
        dim.setDate(l.getDate()+6);
        document.getElementById('semaineLabel').innerHTML = `${formatDateFr(l)} - ${formatDateFr(dim)}`;
        if(!silent){
            document.getElementById('planningLoading').style.display = 'block';
            document.getElementById('planningTable').style.display = 'none';
        }
        fetch(`medecin.php?action=get_planning_medecin&date=${dd}`)
            .then(r => r.json()).then(d => {
                document.getElementById('planningLoading').style.display = 'none';
                if(d.error) { if(!silent){ document.getElementById('planningTable').innerHTML = `<div class="empty-state">${d.error}</div>`; document.getElementById('planningTable').style.display = 'block'; } return; }
                afficherPlanning(d);
            }).catch(() => { document.getElementById('planningLoading').style.display = 'none'; if(!silent){ document.getElementById('planningTable').innerHTML = `<div class="empty-state">${t('loading_error')}</div>`; document.getElementById('planningTable').style.display = 'block'; } });
    }
    
    function afficherPlanning(data) {
        const l = getLundiSemaine(planningOffset);
        const jours = [];
        for(let i=0;i<7;i++){const j=new Date(l);j.setDate(l.getDate()+i);jours.push(j);}

        const sh = data.service_horaires || {};
        const ouv  = sh.horaires_ouverture ? sh.horaires_ouverture.substring(0,5) : '08:00';
        const fer  = sh.horaires_fermeture ? sh.horaires_fermeture.substring(0,5) : '18:00';
        const pauD = sh.pause_debut ? sh.pause_debut.substring(0,5) : null;
        const pauF = sh.pause_fin  ? sh.pause_fin.substring(0,5)  : null;
        const joursTravail = data.jours_travail || [];

        function toMin(hm){ const [h,m]=(hm||'00:00').split(':').map(Number); return h*60+m; }
        function fromMin(tot){ return String(Math.floor(tot/60)).padStart(2,'0')+':'+String(tot%60).padStart(2,'0'); }
        const ouvMin=toMin(ouv), ferMin=toMin(fer);
        const creneaux=[];
        for(let m=ouvMin;m<ferMin;m+=60) creneaux.push(fromMin(m));

        function estPause(hm){ if(!pauD||!pauF) return false; return hm>=pauD&&hm<pauF; }
        function getNumJour(d){ let n=d.getDay(); return n===0?7:n; }
        function medecinTravaille(numJ){ if(!joursTravail||joursTravail.length===0) return true; return joursTravail.includes(numJ)||joursTravail.includes(String(numJ)); }

        // Index consultations par date + créneau horaire arrondi à l'heure
        const consIndex={};
        (data.consultations||[]).forEach(c=>{
            // Trouver le créneau de l'heure (ex: 08:30 → créneau 08:00)
            const [hh] = (c.heure_debut||'00:00').split(':');
            const hArrondie = hh.padStart(2,'0') + ':00';
            const key = c.date_consultation + '|' + hArrondie;
            if(!consIndex[key]) consIndex[key]=[];
            consIndex[key].push(c);
        });

        const today=formatDateISO(new Date());
        let html='<div class="gcal-wrapper">';

        // Colonne heures
        html+='<div class="gcal-time-col"><div class="gcal-header-cell"></div>';
        creneaux.forEach(h=>{
            const isPause=estPause(h);
            html+=`<div class="gcal-time-label${isPause?' pause-time':''}">${h}</div>`;
        });
        html+='</div>';

        // Colonnes jours
        html+='<div class="gcal-days">';
        jours.forEach(jour=>{
            const dateStr=formatDateISO(jour);
            const numJ=getNumJour(jour);
            const travaille=medecinTravaille(numJ);
            const estAjd=dateStr===today;
            const jourNom=getNomJour(jour).substring(0,3).toUpperCase();
            const jourNum=jour.getDate();

            let hColClass='gcal-header-cell'+(estAjd?' today-col':(!travaille?' off-col':''));
            let numHtml=estAjd
                ? `<span class="gcal-day-num today-num">${jourNum}</span>`
                : `<span class="gcal-day-num">${jourNum}</span>`;

            html+=`<div class="gcal-day-col">`;
            html+=`<div class="${hColClass}"><span class="gcal-day-name">${jourNom}</span>${numHtml}<small style="font-size:.6rem;color:#94a3b8;">${dateStr.substring(5).replace('-','/')}</small></div>`;

            creneaux.forEach(heure=>{
                const isPause=estPause(heure);
                let slotClass='gcal-slot';
                if(!travaille) slotClass+=' off-slot';
                else if(isPause) slotClass+=' pause-slot';

                html+=`<div class="${slotClass}">`;

                const key=dateStr+'|'+heure;
                const cons=(consIndex[key]||[]);

                if(cons.length>0){
                    // Une consultation réellement enregistrée doit toujours être
                    // visible, même si le créneau est par ailleurs marqué "fermé"
                    // ou "pause" (ex: décalage horaire de la pause après coup).
                    cons.forEach(c=>{
                        const evCls=c.statut==='en_cours'?'ev-cours':(c.statut==='traite'?'ev-traite':(c.statut==='en_pause'?'ev-pause':(c.statut==='absent'?'ev-absent':'ev-attente')));
                        const badge=c.statut==='en_cours'?'En cours':(c.statut==='traite'?'Traité':(c.statut==='en_pause'?'En pause':(c.statut==='absent'?'Absent':'Programmé')));
                        const hDebut = c.heure_debut || heure;
                        const hFin   = c.heure_fin   || '';
                        const plage  = hFin ? `${hDebut} – ${hFin}` : hDebut;
                        html+=`<div class="gcal-event ${evCls}" title="${escapeHtml(c.patient_nom)} ${escapeHtml(c.patient_prenom)} | ${plage}">
                            <span class="gcal-event-time">${plage}</span>
                            <span class="gcal-event-name">${escapeHtml(c.patient_nom)} ${escapeHtml((c.patient_prenom||'')[0]||'')}.</span>
                            <span class="gcal-event-badge">${badge}</span>
                        </div>`;
                    });
                } else if(!travaille){
                    html+=`<div class="gcal-slot-empty" style="font-size:.62rem;color:#cbd5e1;"><i class="fa-solid fa-moon"></i></div>`;
                } else if(isPause){
                    html+=`<div class="gcal-event ev-pause"><span class="gcal-event-time"><i class="fa-solid fa-utensils"></i> Pause</span></div>`;
                }
                html+='</div>';
            });
            html+='</div>';
        });
        html+='</div></div>';

        document.getElementById('planningTable').innerHTML=html;
        document.getElementById('planningTable').style.display='block';
    }
    
    // ── Profil – lock screen ──
    let _profilPasswordMed = '';
    function chargerProfil() { /* lock screen gère l'affichage */ }
    function verifierMdpProfil() {
        const pwd = document.getElementById('profilLockPassword').value;
        if(!pwd) return;
        const fd = new FormData(); fd.append('password', pwd);
        fetch('medecin.php?action=verifier_mdp',{method:'POST',body:fd})
            .then(r=>r.json()).then(d=>{
                if(d.success){
                    _profilPasswordMed = pwd;
                    document.getElementById('profilLockScreen').style.display = 'none';
                    document.getElementById('profilContent').style.display = 'block';
                    chargerProfilData();
                } else {
                    const e = document.getElementById('profilLockError');
                    e.style.display = 'block';
                    document.getElementById('profilLockPassword').value = '';
                    setTimeout(()=>e.style.display='none', 3000);
                }
            }).catch(()=>{});
    }
    document.addEventListener('keydown', function(e){
        if(e.key==='Enter' && document.getElementById('profilLockPassword') === document.activeElement) verifierMdpProfil();
    });
    function chargerProfilData() {
        const c = document.getElementById('profilContent');
        c.innerHTML = `<div class="empty-state">${t('loading')}</div>`;
        fetch('medecin.php?action=get_profil_data')
            .then(r=>r.json()).then(d=>{if(d.success)afficherFormulaireProfil(d.profil);else c.innerHTML=`<div class="profil-error">${d.message||t('generic_error')}</div>`;})
            .catch(()=>c.innerHTML=`<div class="profil-error">${t('generic_error')}</div>`);
    }
    
    function afficherFormulaireProfil(profil) {
        const pp = profil.photo && profil.photo !== 'null' && profil.photo !== '' ? profil.photo : 'public/images/default-avatar.png';
        const html = `<div class="profil-form"><form id="profilForm" enctype="multipart/form-data"><div class="photo-upload-area"><img id="photoPreview" class="photo-preview" src="${pp}"><div><label class="photo-upload-label"><i class="fa-solid fa-camera"></i> ${t('change_photo')}<input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/jpg" style="display:none;"></label></div><small>${t('formats_accepted_short')}</small></div><div class="form-group"><label><i class="fa-solid fa-user"></i> ${t('last_name')}</label><input type="text" name="nom" id="profil_nom" value="${escapeHtml(profil.nom)}" required></div><div class="form-group"><label><i class="fa-solid fa-user"></i> ${t('first_name')}</label><input type="text" name="prenom" id="profil_prenom" value="${escapeHtml(profil.prenom)}" required></div><div class="form-group"><label><i class="fa-solid fa-phone"></i> ${t('phone')}</label><input type="tel" name="telephone" id="profil_telephone" value="${escapeHtml(profil.telephone)}" required></div><div class="form-group"><label><i class="fa-solid fa-envelope"></i> ${t('email_label')}</label><input type="email" name="email" id="profil_email" value="${escapeHtml(profil.email)}" required></div><div class="profil-separator"></div><div class="profil-title">${t('change_pw_title')}</div><div class="form-group"><label><i class="fa-solid fa-lock"></i> ${t('current_pw')}</label><input type="password" name="password_actuel" id="profil_password_actuel" placeholder="${t('current_pw')}"></div><div class="form-group"><label><i class="fa-solid fa-key"></i> ${t('new_pw')}</label><input type="password" name="nouveau_password" id="profil_nouveau_password" placeholder="${t('password_hint')}"></div><div class="form-group"><label><i class="fa-solid fa-check"></i> ${t('confirm')}</label><input type="password" name="confirmer_password" id="profil_confirmer_password" placeholder="${t('repeat')}"></div><button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> ${t('save_btn')}</button></form><div id="profilMessage"></div></div>`;
        document.getElementById('profilContent').innerHTML = html;
        // Pré-remplir le mot de passe actuel avec celui du déverrouillage
        if(_profilPasswordMed) document.getElementById('profil_password_actuel').value = _profilPasswordMed;
        const pi = document.getElementById('photoInput'), ppv = document.getElementById('photoPreview');
        if(pi) pi.addEventListener('change',function(e){const f=e.target.files[0];if(f){const r=new FileReader();r.onload=function(ev){ppv.src=ev.target.result};r.readAsDataURL(f);}});
        const pf = document.getElementById('profilForm');
        if(pf) pf.addEventListener('submit',function(e){e.preventDefault();enregistrerProfil();});
    }

    // ══════════════════════════════════════════════════════
//  GRAPHIQUES STATISTIQUES MÉDECIN
// ══════════════════════════════════════════════════════
let _chartEvolution = null, _chartDonut = null, _chartBarJour = null;
let _periodeJours = 7;

function changerPeriode(btn, jours) {
    document.querySelectorAll('.btn-periode').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _periodeJours = jours;
    chargerStatsEvolution();
}

function chargerStatsEvolution() {
    const loading = document.getElementById('statsChargement');
    if (loading) loading.style.display = 'inline';

    fetch('medecin.php?action=get_stats_evolution&jours=' + _periodeJours)
        .then(r => r.json())
        .then(d => {
            if (loading) loading.style.display = 'none';
            if (!d.success) return;
            // Mettre à jour les cards résumé avec les totaux sur la période
            if (d.totaux) {
                const t = d.totaux;
                const st = document.getElementById('statTotal');
                const stt = document.getElementById('statTraitees');
                const sa = document.getElementById('statAbsentes');
                const san = document.getElementById('statAnnulees');
                const sdm = document.getElementById('statDureeMoy');
                if (st) st.textContent = t.total || 0;
                if (stt) stt.textContent = t.traitees || 0;
                if (sa) sa.textContent = t.absentes || 0;
                if (san) san.textContent = t.annulees || 0;
                if (sdm) sdm.textContent = (t.duree_moy_sec || 0) > 0 ? Math.round(t.duree_moy_sec / 60) + 'min' : '—';
            }
            dessinerChartsEvolution(d.evolution || []);
        })
        .catch(() => { if (loading) loading.style.display = 'none'; });
}

function dessinerChartsEvolution(evolution) {
    const labels  = evolution.map(r => formatJourCourt(r.jour));
    const totaux  = evolution.map(r => parseInt(r.total) || 0);
    const traites = evolution.map(r => parseInt(r.traitees) || 0);
    const abs     = evolution.map(r => parseInt(r.absentes) || 0);
    const ann     = evolution.map(r => parseInt(r.annulees) || 0);

    // ── Graphique évolution linéaire ──
    const ctxEv = document.getElementById('chartEvolution')?.getContext('2d');
    if (ctxEv) {
        if (_chartEvolution) _chartEvolution.destroy();
        _chartEvolution = new Chart(ctxEv, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    { label: 'Total', data: totaux, borderColor: '#0052a0', backgroundColor: 'rgba(0,82,160,.08)', fill: true, tension: .4, pointRadius: 4, pointHoverRadius: 6 },
                    { label: 'Traitées', data: traites, borderColor: '#10b981', backgroundColor: 'transparent', tension: .4, pointRadius: 3 },
                    { label: 'Absents', data: abs, borderColor: '#f59e0b', backgroundColor: 'transparent', tension: .4, pointRadius: 3 },
                    { label: 'Annulées', data: ann, borderColor: '#ef4444', backgroundColor: 'transparent', tension: .4, pointRadius: 3 },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 16 } }, tooltip: { mode: 'index', intersect: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } }
            }
        });
    }

    // ── Donut répartition ──
    const sumT = traites.reduce((a,b)=>a+b,0);
    const sumA = abs.reduce((a,b)=>a+b,0);
    const sumN = ann.reduce((a,b)=>a+b,0);
    const ctxDn = document.getElementById('chartDonut')?.getContext('2d');
    if (ctxDn) {
        if (_chartDonut) _chartDonut.destroy();
        _chartDonut = new Chart(ctxDn, {
            type: 'doughnut',
            data: {
                labels: ['Traitées', 'Absents', 'Annulées'],
                datasets: [{ data: [sumT, sumA, sumN], backgroundColor: ['#10b981', '#f59e0b', '#ef4444'], borderWidth: 2 }]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } }, tooltip: { callbacks: { label: ctx => ` ${ctx.label} : ${ctx.raw} (${totaux.reduce((a,b)=>a+b,0) > 0 ? Math.round(ctx.raw * 100 / totaux.reduce((a,b)=>a+b,0)) : 0}%)` } } },
                cutout: '65%'
            }
        });
    }

    // ── Bar par jour de semaine ──
    const joursNoms = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];
    const parJour = [0,0,0,0,0,0,0];
    evolution.forEach(r => {
        const d = new Date(r.jour + 'T00:00:00');
        let dn = d.getDay(); // 0=dim
        dn = dn === 0 ? 6 : dn - 1; // réindexer 0=lun
        parJour[dn] += parseInt(r.total) || 0;
    });
    const ctxBr = document.getElementById('chartBarJour')?.getContext('2d');
    if (ctxBr) {
        if (_chartBarJour) _chartBarJour.destroy();
        _chartBarJour = new Chart(ctxBr, {
            type: 'bar',
            data: {
                labels: joursNoms,
                datasets: [{ label: 'Consultations', data: parJour, backgroundColor: '#0052a0', borderRadius: 6, hoverBackgroundColor: '#003d7a' }]
            },
            options: {
                responsive: true, maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } }
            }
        });
    }
}

function formatJourCourt(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    return d.toLocaleDateString(LOCALE, { day: '2-digit', month: '2-digit' });
}

// ══════════════════════════════════════════════════════
//  TEMPS D'ATTENTE MOYEN — Section médecin
// ══════════════════════════════════════════════════════
let _chartTempsAttente = null;
let _periodeAttenteJours = 30;

function changerPeriodeAttente(btn, jours) {
    document.querySelectorAll('[data-att]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _periodeAttenteJours = jours;
    chargerTempsAttente();
}

function chargerTempsAttente() {
    const loading = document.getElementById('attenteChargement');
    if (loading) loading.style.display = 'inline';
    const joursParam = _periodeAttenteJours >= 9999 ? 3650 : _periodeAttenteJours;
    fetch('medecin.php?action=get_temps_attente_evolution&jours=' + joursParam)
        .then(r => r.json())
        .then(d => {
            if (loading) loading.style.display = 'none';
            if (!d.success) return;
            // Mettre à jour les cards globales
            const g = d.global || {};
            const fmtMin = v => (v === null || v === undefined || isNaN(v)) ? '—' : Math.round(v) + ' min';
            const ag = document.getElementById('attenteGlobal');
            const a7 = document.getElementById('attente7j');
            const a30 = document.getElementById('attente30j');
            const nb = document.getElementById('attenteNbMesures');
            if (ag) ag.textContent = fmtMin(g.attente_moy_min);
            if (a7) a7.textContent = fmtMin(g.attente_7j_min);
            if (a30) a30.textContent = fmtMin(g.attente_30j_min);
            if (nb) nb.textContent = g.nb_mesures || '—';
            // Tendance : comparaison 7j vs 30j
            const tend = document.getElementById('attenteTendance');
            if (tend && g.attente_7j_min != null && g.attente_30j_min != null) {
                const diff = parseFloat(g.attente_7j_min) - parseFloat(g.attente_30j_min);
                const abs = Math.abs(diff).toFixed(1);
                let icon, couleur, msg;
                if (diff < -2) {
                    icon = '📉'; couleur = '#16a34a';
                    msg = t('wait_time_decreased').replace('%s', abs);
                } else if (diff > 2) {
                    icon = '📈'; couleur = '#dc2626';
                    msg = t('wait_time_increased').replace('%s', abs);
                } else {
                    icon = '➡️'; couleur = '#0052a0';
                    msg = t('wait_time_stable');
                }
                if (g.premier_jour) {
                    const premierJour = new Date(g.premier_jour + 'T00:00:00').toLocaleDateString(LOCALE, { day:'2-digit', month:'long', year:'numeric' });
                    msg += ` <span style="color:#94a3b8;font-size:.78rem;">${t('measures_since')} ${premierJour}.</span>`;
                }
                tend.innerHTML = `<span style="font-size:1.2rem;">${icon}</span> <span style="color:${couleur};">${msg}</span>`;
                tend.style.display = 'flex';
                tend.style.gap = '10px';
                tend.style.alignItems = 'flex-start';
            }
            // Dessiner le graphique
            dessinerChartTempsAttente(d.evolution || [], d.sous_service || '');
        })
        .catch(() => { if (loading) loading.style.display = 'none'; });
}

function dessinerChartTempsAttente(evolution, ssNom) {
    const ctx = document.getElementById('chartTempsAttente')?.getContext('2d');
    if (!ctx) return;
    if (_chartTempsAttente) _chartTempsAttente.destroy();
    const labels  = evolution.map(r => formatJourCourt(r.jour));
    const attentes = evolution.map(r => r.attente_moy_min !== null ? parseFloat(r.attente_moy_min) : null);
    const nbMes   = evolution.map(r => parseInt(r.nb_mesures) || 0);
    // Moyenne mobile 7 jours
    const movAvg7 = attentes.map((v, i) => {
        const slice = attentes.slice(Math.max(0, i - 6), i + 1).filter(x => x !== null);
        return slice.length > 0 ? parseFloat((slice.reduce((a,b)=>a+b,0)/slice.length).toFixed(1)) : null;
    });
    _chartTempsAttente = new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Attente moy. (min)',
                    data: attentes,
                    backgroundColor: attentes.map(v => v === null ? 'transparent' : 'rgba(245,158,11,.35)'),
                    borderColor: 'rgba(245,158,11,.7)',
                    borderWidth: 1,
                    borderRadius: 4,
                    type: 'bar',
                    yAxisID: 'y',
                },
                {
                    label: 'Moy. mobile 7j',
                    data: movAvg7,
                    type: 'line',
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    tension: 0.4,
                    pointRadius: 2,
                    borderDash: [5, 3],
                    yAxisID: 'y',
                },
                {
                    label: 'Nb consultations mesurées',
                    data: nbMes,
                    type: 'line',
                    borderColor: '#0052a0',
                    backgroundColor: 'rgba(0,82,160,.06)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 2,
                    borderWidth: 1.5,
                    yAxisID: 'y2',
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: true,
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 14 } },
                tooltip: {
                    mode: 'index', intersect: false,
                    callbacks: {
                        label: ctx => {
                            if (ctx.datasetIndex === 0 || ctx.datasetIndex === 1) {
                                return ` ${ctx.dataset.label}: ${ctx.raw !== null ? ctx.raw + ' min' : 'Aucune donnée'}`;
                            }
                            return ` ${ctx.dataset.label}: ${ctx.raw}`;
                        }
                    }
                },
                title: { display: !!ssNom, text: `Sous-service : ${ssNom}`, font: { size: 11 }, color: '#64748b' }
            },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Minutes', font: { size: 10 } }, grid: { color: 'rgba(0,0,0,.04)' }, position: 'left' },
                y2: { beginAtZero: true, title: { display: true, text: 'Consultations', font: { size: 10 } }, grid: { display: false }, position: 'right' },
                x: { grid: { display: false } }
            }
        }
    });
}

    let _consultationsDataMed = <?= json_encode(array_map(function($c) {
        return [
            'id' => $c['id'],
            'rang' => $c['rang'],
            'statut' => $c['statut'],
            'patient_nom' => $c['patient_nom'],
            'patient_prenom' => $c['patient_prenom'],
            'telephone' => $c['telephone'],
            'heure_passage_estimee' => isset($c['heure_passage_estimee']) && $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : null,
            'heure_debut_reelle' => isset($c['heure_debut_reelle']) && $c['heure_debut_reelle'] ? date('H:i', strtotime($c['heure_debut_reelle'])) : null,
            'heure_fin_reelle' => isset($c['heure_fin_reelle']) && $c['heure_fin_reelle'] ? date('H:i', strtotime($c['heure_fin_reelle'])) : null,
        ];

    // Init pills on page load
    renderMedConsultsPills();
    }, $consultations)) ?>;
    function filtrerConsultationsMed(q) {
        const t = q.toLowerCase().trim();
        if(!t){ afficherConsultationsMed(_consultationsDataMed); return; }
        const f = _consultationsDataMed.filter(c=>(c.patient_nom||'').toLowerCase().includes(t)||(c.patient_prenom||'').toLowerCase().includes(t)||(c.patient_telephone||'').toLowerCase().includes(t)||(c.statut||'').toLowerCase().includes(t));
        afficherConsultationsMed(f, true);
    }
    
    
    function enregistrerProfil() {
        const fd = new FormData();
        fd.append('nom', document.getElementById('profil_nom').value);
        fd.append('prenom', document.getElementById('profil_prenom').value);
        fd.append('telephone', document.getElementById('profil_telephone').value);
        fd.append('email', document.getElementById('profil_email').value);
        fd.append('password_actuel', document.getElementById('profil_password_actuel').value || _profilPasswordMed);
        fd.append('nouveau_password', document.getElementById('profil_nouveau_password').value);
        fd.append('confirmer_password', document.getElementById('profil_confirmer_password').value);
        const pf = document.getElementById('photoInput')?.files[0];
        if(pf) fd.append('photo', pf);
        const btn = document.querySelector('#profilForm button[type="submit"]'), ot = btn ? btn.innerHTML : '';
        if(btn){btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';btn.disabled=true;}
        fetch('medecin.php?action=mettre_a_jour_profil',{method:'POST',body:fd})
            .then(r=>r.json()).then(d=>{
                if(btn){btn.innerHTML=ot;btn.disabled=false;}
                const md=document.getElementById('profilMessage');
                if(d.success){if(md)md.innerHTML=`<div class="profil-success">${d.message}</div>`;setTimeout(()=>window.location.reload(),1500);}
                else{let err='';if(d.errors)for(const[k,v]of Object.entries(d.errors))err+=`<div class="error-msg">- ${v}</div>`;else err=d.message||'Erreur';if(md)md.innerHTML=`<div class="profil-error">${err}</div>`;}
            }).catch(()=>{if(btn){btn.innerHTML=ot;btn.disabled=false;}const md=document.getElementById('profilMessage');if(md)md.innerHTML=`<div class="profil-error">${t('generic_error')}</div>`;});
    }
    
    let idleTimer, warnTimer;
    function resetIdleTimer() {
        if(idleTimer) clearTimeout(idleTimer);
        if(warnTimer) clearTimeout(warnTimer);
        warnTimer = setTimeout(() => { const b = document.getElementById('timeoutBanner'); if(b) b.style.display = 'flex'; }, 14 * 60 * 1000);
        idleTimer = setTimeout(() => { window.location.href = 'medecin.php?action=deconnexion&timeout=1'; }, 15 * 60 * 1000);
    }

    // ══════════════════════════════════════════════════════════════
    // Actualisation automatique de l'interface (en arrière-plan)
    // Ne change jamais de section, ne réinitialise jamais la page/le
    // filtre en cours, et se met en pause si une fenêtre modale est
    // ouverte ou si l'onglet du navigateur n'est pas visible, afin de
    // ne jamais interrompre l'activité de l'utilisateur.
    // ══════════════════════════════════════════════════════════════
    const _AUTO_REFRESH_MS_MED = 8000;
    let _autoRefreshIntervalMed = null;

    function _unModalEstOuvertMed() {
        const mp = document.getElementById('modalPause');
        const mr = document.getElementById('modalRdv');
        return (mp && mp.style.display === 'flex') || (mr && mr.style.display === 'flex');
    }

    function actualisationAutomatiqueMed() {
        if (document.hidden) return;          // onglet en arrière-plan : on attend
        if (_unModalEstOuvertMed()) return;    // une saisie est en cours : on n'y touche pas
        if (currentSection === 'profil') return; // formulaire de profil/mot de passe : on n'y touche pas
        if (currentSection === 'consultations') {
            rafraichirConsultations(true);
        } else if (currentSection === 'historique') {
            chargerHistorique(_histPage, true);
        } else if (currentSection === 'planning') {
            chargerPlanning(true);
        } else if (currentSection === 'stats') {
            rafraichirStatistiques();
        }
    }

    function demarrerActualisationAutoMed() {
        if (_autoRefreshIntervalMed) return;
        _autoRefreshIntervalMed = setInterval(actualisationAutomatiqueMed, _AUTO_REFRESH_MS_MED);
    }

    // Dès que l'utilisateur revient sur l'onglet, on relance une actualisation immédiate
    document.addEventListener('visibilitychange', () => { if (!document.hidden) actualisationAutomatiqueMed(); });

    // Restaurer l'onglet actif après chargement de la page (ex: après un changement de langue)
    function restaurerSectionActiveMed() {
        const savedSection = localStorage.getItem('medecin_active_section');
        const section = (savedSection && ['consultations', 'planning', 'stats', 'historique', 'profil'].includes(savedSection)) ? savedSection : 'consultations';
        // Initialiser l'affichage avec les données PHP avant showSection
        if (_consultationsDataMed.length > 0) {
            const cs = document.getElementById('consultationsCount');
            if(cs) cs.innerHTML = _consultationsDataMed.length + ' consultation(s)';
            afficherConsultationsMed(_consultationsDataMed, false);
        }
        showSection(section);
    }

    window.onload = function() {
        resetIdleTimer();
        restaurerSectionActiveMed();
        demarrerActualisationAutoMed();
    };
    
    document.onmousemove = resetIdleTimer;
    document.onkeydown = resetIdleTimer;
    document.onclick = resetIdleTimer;
    document.onscroll = resetIdleTimer;
</script>
<!-- Modal Pause Examen -->
<div id="modalPause" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);justify-content:center;align-items:center;">
    <div style="background:#fff;border-radius:16px;padding:28px 32px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
            <span style="width:40px;height:40px;background:#fff3cd;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">⏸</span>
            <h3 style="margin:0;font-size:1.1rem;color:#1e293b;"><?= __('pause_modal_title') ?></h3>
        </div>
        <p style="color:#64748b;font-size:.88rem;margin-bottom:12px;line-height:1.5;">
            <?= __('pause_intro_text') ?>
        </p>
        <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:8px 12px;margin-bottom:14px;font-size:.82rem;color:#1e40af;display:flex;align-items:center;gap:8px;">
            <i class="fa-solid fa-star"></i>
            <span><strong><?= __('auto_priority') ?></strong> <?= __('auto_priority_text') ?></span>
        </div>
        <label style="font-size:.8rem;font-weight:600;color:#475569;display:block;margin-bottom:6px;"><?= __('pause_reason') ?></label>
        <input id="pauseMotif" type="text" placeholder="<?= __('pause_reason_ex') ?>"
               style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.88rem;font-family:inherit;box-sizing:border-box;margin-bottom:20px;"
               onkeydown="if(event.key==='Enter') confirmerPause()">
        <p style="font-size:.78rem;color:#94a3b8;margin-bottom:16px;">
            <i class="fa-solid fa-circle-info" style="color:#3b82f6;"></i>
            <?= __('patient_stays_paused') ?>
        </p>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="fermerModalPause()" style="padding:9px 18px;background:#f1f5f9;border:none;border-radius:8px;cursor:pointer;font-size:.85rem;font-family:inherit;color:#475569;"><?= __('cancel') ?></button>
            <button onclick="confirmerPause()" style="padding:9px 18px;background:#f59e0b;border:none;border-radius:8px;cursor:pointer;font-size:.85rem;font-family:inherit;color:#fff;font-weight:600;">
                <i class="fa-solid fa-pause"></i> <?= __('confirm_pause') ?>
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     Modal — Fixer le prochain rendez-vous
══════════════════════════════════════════ -->
<div id="modalRdv" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);justify-content:center;align-items:center;">
    <div style="background:#fff;border-radius:16px;padding:28px 32px;max-width:480px;width:95%;box-shadow:0 20px 60px rgba(0,0,0,.25);">

        <!-- En-tête -->
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
            <span style="width:40px;height:40px;background:#dbeafe;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;">📅</span>
            <h3 style="margin:0;font-size:1.1rem;color:#1e293b;"><?= __('next_appt_modal_title') ?></h3>
        </div>
        <p id="rdvPatientLabel" style="color:#64748b;font-size:.85rem;margin:0 0 18px 50px;"></p>

        <!-- Date -->
        <label style="font-size:.8rem;font-weight:600;color:#475569;display:block;margin-bottom:5px;">
            <i class="fa-solid fa-calendar-day" style="color:#3b82f6;margin-right:4px;"></i> <?= __('rdv_date_label') ?>
        </label>
        <input id="rdvDate" type="date"
               style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.88rem;font-family:inherit;box-sizing:border-box;margin-bottom:14px;"
               oninput="chargerCreneaux()">

        <!-- Créneaux -->
        <label style="font-size:.8rem;font-weight:600;color:#475569;display:block;margin-bottom:5px;">
            <i class="fa-solid fa-clock" style="color:#3b82f6;margin-right:4px;"></i> <?= __('rdv_time_label') ?>
        </label>
        <div id="rdvCreneauxWrap" style="margin-bottom:14px;">
            <p style="color:#94a3b8;font-size:.82rem;font-style:italic;"><?= __('rdv_select_date_first') ?></p>
        </div>

        <!-- Motif -->
        <label style="font-size:.8rem;font-weight:600;color:#475569;display:block;margin-bottom:5px;">
            <i class="fa-solid fa-pen-line" style="color:#3b82f6;margin-right:4px;"></i> <?= __('rdv_reason_label') ?>
        </label>
        <input id="rdvMotif" type="text" placeholder="<?= __('next_appt_reason_ex') ?>"
               style="width:100%;padding:10px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.88rem;font-family:inherit;box-sizing:border-box;margin-bottom:20px;">

        <!-- Confirmation créée -->
        <div id="rdvConfirmation" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:.85rem;color:#166534;"></div>

        <!-- Boutons -->
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="fermerModalRdv()" id="rdvBtnAnnuler"
                    style="padding:9px 18px;background:#f1f5f9;border:none;border-radius:8px;cursor:pointer;font-size:.85rem;font-family:inherit;color:#475569;">
                <?= __('cancel') ?>
            </button>
            <button onclick="confirmerRdv()" id="rdvBtnConfirmer"
                    style="padding:9px 18px;background:#3b82f6;border:none;border-radius:8px;cursor:pointer;font-size:.85rem;font-family:inherit;color:#fff;font-weight:600;">
                <i class="fa-solid fa-calendar-check"></i> <?= __('confirm_rdv') ?>
            </button>
        </div>
    </div>
</div>

<style>
/* Boutons pause / reprise */
.btn-sm-pause  { background:#f59e0b!important;color:#fff!important;border:none; }
.btn-sm-pause:hover  { background:#d97706!important; }
.btn-sm-resume { background:#10b981!important;color:#fff!important;border:none; }
.btn-sm-resume:hover { background:#059669!important; }
/* Badge en_pause */
.badge-pause { background:#fef3c7;color:#92400e;border:1px solid #fde68a; }
/* Bouton prochain RDV */
.btn-sm-primary { background:#3b82f6!important;color:#fff!important;border:none; }
.btn-sm-primary:hover { background:#2563eb!important; }
/* Créneaux horaires */
.creneaux-grid { display:flex;flex-wrap:wrap;gap:8px; }
.creneau-btn {
    padding:7px 14px;border:1.5px solid #bfdbfe;border-radius:8px;
    background:#f8faff;color:#1e40af;font-size:.82rem;font-weight:600;
    cursor:pointer;transition:all .15s;
}
.creneau-btn:hover { background:#dbeafe;border-color:#3b82f6; }
.creneau-btn.selected { background:#3b82f6;border-color:#3b82f6;color:#fff; }
.creneau-btn .dispo { font-size:.7rem;font-weight:400;opacity:.8; }
</style>

<script>
/* ══════════════════════════════════
   Modal Prochain RDV
══════════════════════════════════ */
let _rdvConsultationId = null;
let _rdvHeureSelectionnee = null;

function ouvrirModalRdv(consultationId, patientNom) {
    _rdvConsultationId   = consultationId;
    _rdvHeureSelectionnee = null;

    // Date min = demain
    const demain = new Date();
    demain.setDate(demain.getDate() + 1);
    const demainStr = demain.toISOString().split('T')[0];

    document.getElementById('rdvDate').min   = demainStr;
    document.getElementById('rdvDate').value = '';
    document.getElementById('rdvMotif').value = '';
    document.getElementById('rdvCreneauxWrap').innerHTML =
        `<p style="color:#94a3b8;font-size:.82rem;font-style:italic;">${t('rdv_select_date_first')}</p>`;
    document.getElementById('rdvPatientLabel').textContent = t('patient_colon') + ' ' + patientNom.trim();
    document.getElementById('rdvConfirmation').style.display = 'none';
    document.getElementById('rdvBtnConfirmer').style.display = '';
    document.getElementById('rdvBtnAnnuler').textContent = t('cancel');

    const modal = document.getElementById('modalRdv');
    modal.style.display = 'flex';
    document.getElementById('rdvDate').focus();
}

function fermerModalRdv() {
    document.getElementById('modalRdv').style.display = 'none';
    _rdvConsultationId   = null;
    _rdvHeureSelectionnee = null;
}

function chargerCreneaux() {
    const date = document.getElementById('rdvDate').value;
    _rdvHeureSelectionnee = null;

    if (!date) {
        document.getElementById('rdvCreneauxWrap').innerHTML =
            `<p style="color:#94a3b8;font-size:.82rem;font-style:italic;">${t('rdv_select_date_first')}</p>`;
        return;
    }

    document.getElementById('rdvCreneauxWrap').innerHTML =
        `<p style="color:#94a3b8;font-size:.82rem;"><i class="fa-solid fa-spinner fa-spin"></i> ${t('loading')}</p>`;

    fetch('medecin.php?action=get_creneaux_disponibles&date=' + encodeURIComponent(date))
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                document.getElementById('rdvCreneauxWrap').innerHTML =
                    `<p style="color:#ef4444;font-size:.82rem;">${d.message || 'Erreur.'}</p>`;
                return;
            }
            if (!d.creneaux || d.creneaux.length === 0) {
                document.getElementById('rdvCreneauxWrap').innerHTML =
                    `<p style="color:#f59e0b;font-size:.82rem;"><i class="fa-solid fa-triangle-exclamation"></i> ${t('no_slot_available')}</p>`;
                return;
            }
            let html = '<div class="creneaux-grid">';
            d.creneaux.forEach(c => {
                html += `<button class="creneau-btn" data-heure="${c.heure}" onclick="selectionnerCreneau(this, '${c.heure}')">
                    ${c.heure}
                    <span class="dispo">(${c.disponible} ${t('spots_available')})</span>
                </button>`;
            });
            html += '</div>';
            document.getElementById('rdvCreneauxWrap').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('rdvCreneauxWrap').innerHTML =
                `<p style="color:#ef4444;font-size:.82rem;">${t('network_error')}</p>`;
        });
}

function selectionnerCreneau(btn, heure) {
    // Désélectionner l'ancien
    document.querySelectorAll('.creneau-btn.selected').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    _rdvHeureSelectionnee = heure;
}

function confirmerRdv() {
    if (!_rdvConsultationId) return;

    const date  = document.getElementById('rdvDate').value;
    const motif = document.getElementById('rdvMotif').value.trim();

    if (!date) {
        afficherMessage(t('select_date_please'), 'error');
        return;
    }
    if (!_rdvHeureSelectionnee) {
        afficherMessage(t('select_slot_please'), 'error');
        return;
    }

    // Désactiver le bouton pendant l'envoi
    const btn = document.getElementById('rdvBtnConfirmer');
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${t('saving')}`;

    const body = new URLSearchParams({
        consultation_id: _rdvConsultationId,
        date_rdv:        date,
        heure_rdv:       _rdvHeureSelectionnee,
        motif:           motif
    });

    fetch('medecin.php?action=fixer_prochain_rdv_ajax', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    body.toString()
    })
    .then(r => r.json())
    .then(d => {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-calendar-check"></i> ${t('confirm_rdv')}`;

        if (d.success) {
            // Afficher la confirmation dans la modale
            const dateF = new Date(d.date_rdv + 'T00:00:00').toLocaleDateString('<?= \LangHelper::getLang() === "en" ? "en-US" : "fr-FR" ?>', {
                weekday:'long', day:'2-digit', month:'long', year:'numeric'
            });
            document.getElementById('rdvConfirmation').style.display = 'block';
            document.getElementById('rdvConfirmation').innerHTML =
                `<i class="fa-solid fa-circle-check" style="color:#16a34a;margin-right:6px;"></i>
                 <strong>${t('rdv_confirmed')}</strong> — ${d.patient_nom} le <strong>${dateF}</strong> à <strong>${d.heure_rdv}</strong>.
                 <br><span style="font-size:.78rem;color:#166534;">${t('slot_auto_blocked')}</span>`;
            document.getElementById('rdvBtnConfirmer').style.display = 'none';
            document.getElementById('rdvBtnAnnuler').textContent = t('close_btn');
            afficherMessage(t('rdv_planned'), 'success');
        } else {
            afficherMessage(d.message || t('rdv_failed'), 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = `<i class="fa-solid fa-calendar-check"></i> ${t('confirm_rdv')}`;
        afficherMessage(t('network_error'), 'error');
    });
}

// Fermer la modale RDV en cliquant en dehors
document.getElementById('modalRdv').addEventListener('click', function(e) {
    if (e.target === this) fermerModalRdv();
});

// ── Changement de langue depuis la sidebar ──
async function dashChangerLangue(langue, role) {
    const endpoint = role === 'medecin'
        ? 'medecin.php?action=changer_langue'
        : 'gestionnaire.php?action=changer_langue';
    const fd = new FormData();
    fd.append('langue', langue);
    const res = await fetch(endpoint, {method:'POST', body:fd});
    const d = await res.json();
    if (d.success) window.location.reload();
}
</script>

</body>
</html>