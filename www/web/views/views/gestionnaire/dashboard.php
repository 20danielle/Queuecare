<?php
// views/gestionnaire/dashboard.php

// ── Timeout inactivité 15 min ──
const SESSION_TIMEOUT_G = 900;
if (isset($_SESSION['last_activity'])) {
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT_G) {
        session_unset();
        session_destroy();
        header('Location: gestionnaire.php?action=connexion&timeout=1');
        exit;
    }
}
$_SESSION['last_activity'] = time();

$initiale = mb_strtoupper(mb_substr($gestionnaireNom, 0, 1));
$dureeMin = isset($sousService['duree_estimee']) ? round($sousService['duree_estimee'] / 60) : 30;
$openQR = isset($_GET['open_qr']) ? true : false;

// Récupérer les messages flash
$messageAction = $_SESSION['message_action'] ?? '';
$typeMessage = $_SESSION['type_message'] ?? 'success';
unset($_SESSION['message_action']);
unset($_SESSION['type_message']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Tableau de bord — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/gestionnaire.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        /* ===== CALENDRIER GOOGLE CALENDAR STYLE ===== */
        .gcal-wrapper { display:flex; overflow-x:auto; border-radius:14px; border:1px solid #e2e8f0; background:#fff; box-shadow:0 2px 16px rgba(10,43,94,.07); }
        .gcal-time-col { width:64px; flex-shrink:0; border-right:1px solid #e8edf5; background:#fafbfc; }
        .gcal-time-col .gcal-header-cell { border-bottom:1px solid #e8edf5; height:54px; }
        .gcal-time-label { height:56px; display:flex; align-items:flex-start; justify-content:flex-end; padding:4px 10px 0 0; font-size:.7rem; font-weight:600; color:#94a3b8; }
        .gcal-time-label.pause-time { color:#d97706; }
        .gcal-days { display:flex; flex:1; min-width:0; }
        .gcal-day-col { flex:1; min-width:90px; border-right:1px solid #f1f5f9; display:flex; flex-direction:column; }
        .gcal-day-col:last-child { border-right:none; }
        .gcal-header-cell { height:54px; display:flex; flex-direction:column; align-items:center; justify-content:center; border-bottom:1px solid #e8edf5; padding:4px 2px; position:sticky; top:0; z-index:5; background:#fff; gap:1px; }
        .gcal-header-cell.today-col { background:linear-gradient(135deg,#eff6ff,#dbeafe); }
        .gcal-header-cell.off-col { background:#f8fafc; opacity:.8; }
        .gcal-day-name { font-size:.68rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
        .gcal-day-num { font-size:1.1rem; font-weight:700; color:#1e293b; line-height:1; }
        .gcal-day-num.today-num { background:#0a2b5e; color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-size:.92rem; }
        .gcal-slot { height:56px; border-bottom:1px solid #f1f5f9; position:relative; padding:1px 3px; box-sizing:border-box; }
        .gcal-slot.pause-slot { background:repeating-linear-gradient(45deg,#fef9c3,#fef9c3 5px,#fefce8 5px,#fefce8 11px); }
        .gcal-slot.off-slot { background:repeating-linear-gradient(45deg,#f8fafc,#f8fafc 5px,#f1f5f9 5px,#f1f5f9 11px); }
        .gcal-event { border-radius:5px; padding:3px 6px; font-size:.66rem; line-height:1.3; overflow:hidden; height:calc(100% - 2px); box-sizing:border-box; display:flex; flex-direction:column; justify-content:flex-start; cursor:default; }
        .gcal-event.ev-attente { background:#bfdbfe; border-left:3px solid #3b82f6; color:#1e3a8a; }
        .gcal-event.ev-cours { background:#bbf7d0; border-left:3px solid #10b981; color:#064e3b; }
        .gcal-event.ev-traite { background:#e2e8f0; border-left:3px solid #94a3b8; color:#475569; opacity:.85; }
        .gcal-event.ev-pause { background:#fde68a; border-left:3px solid #f59e0b; color:#78350f; }
        .gcal-event.ev-dispo { background:#f3e8ff; border-left:3px solid #a855f7; color:#581c87; }
        .gcal-event-time { font-weight:700; font-size:.6rem; white-space:nowrap; }
        .gcal-event-name { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .gcal-event-badge { display:inline-block; font-size:.56rem; font-weight:700; padding:1px 4px; border-radius:8px; margin-top:2px; background:rgba(255,255,255,.55); }
        .gcal-multi { display:flex; flex-direction:column; gap:1px; height:100%; overflow:hidden; }
        .gcal-slot-empty { display:flex; align-items:center; justify-content:center; height:100%; }

        /* Bannière médecin sélectionné */
        .planning-medecin-banner { display:flex; align-items:center; gap:12px; padding:12px 16px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border-radius:12px; margin-bottom:14px; border:1px solid #bfdbfe; }
        .planning-medecin-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#0a2b5e,#1a4db5); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:1.1rem; flex-shrink:0; }
        .planning-medecin-name { font-weight:700; color:#0a2b5e; font-size:1rem; }
        .planning-medecin-sub { font-size:.75rem; color:#64748b; margin-top:2px; }

        /* Info bar + légende */
        .planning-info-bar { display:flex; align-items:center; gap:16px; flex-wrap:wrap; padding:10px 14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:14px; font-size:.8rem; color:#475569; }
        .planning-info-bar strong { color:#0a2b5e; }
        .planning-legend { display:flex; gap:16px; flex-wrap:wrap; margin-bottom:14px; font-size:.75rem; color:#475569; }
        .legend-dot { width:12px; height:12px; border-radius:3px; flex-shrink:0; }

        .semaine-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
        .semaine-nav-buttons { display:flex; gap:8px; flex-wrap:wrap; }
        .semaine-label { background:#dbeafe; color:#1e40af; padding:6px 16px; border-radius:20px; font-size:.85rem; font-weight:600; white-space:nowrap; }
        .planning-loading { text-align:center; padding:60px 20px; }
        .planning-loading i { font-size:2rem; color:#1a4db5; animation:spin 1s linear infinite; }
        @keyframes spin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
        .refresh-indicator { position:fixed; bottom:20px; right:20px; background:#1a4db5; color:white; padding:8px 16px; border-radius:30px; font-size:.75rem; display:none; align-items:center; gap:8px; z-index:1000; }
        .refresh-indicator.show { display:flex; }
        .action-msg-success { background:#d1fae5; color:#065f46; }
        .action-msg-error { background:#fee2e2; color:#991b1b; }
        .action-msg { padding:16px 20px; border-radius:12px; margin-bottom:24px; display:flex; align-items:center; gap:12px; }
        .btn-sm { padding:6px 12px; border:none; border-radius:6px; cursor:pointer; font-size:.75rem; font-weight:600; margin:0 2px; }
        .btn-sm-green { background: #10b981; color: white; }
        .btn-sm-orange { background: #f59e0b; color: white; }
        .btn-sm-red { background: #ef4444; color: white; }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-grey { background: #f1f5f9; color: #475569; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .rang-badge { width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .rang-badge.actif { background: #4ade80; color: white; }
        .file-table { width: 100%; border-collapse: collapse; }
        .file-table th { text-align: left; padding: 12px; background: #f8fafc; font-weight: 600; font-size: 0.8rem; color: #475569; }
        .file-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 0.85rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: white; border-radius: 20px; padding: 24px 20px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .stat-icon { font-size: 2.5rem; margin-bottom: 12px; }
        .stat-value { font-size: 2rem; font-weight: 800; color: #0a2b5e; line-height: 1.2; }
        .stat-label { font-size: 0.8rem; color: #64748b; margin-top: 8px; font-weight: 500; }
        .stat-card.total .stat-icon { color: #3b82f6; }
        .stat-card.traitees .stat-icon { color: #10b981; }
        .stat-card.en-attente .stat-icon { color: #f59e0b; }
        .stat-card.absents .stat-icon { color: #ef4444; }
        .stat-card.annulees .stat-icon { color: #8b5cf6; }
        .stat-card.en-ligne .stat-icon { color: #06b6d4; }
        .stat-card.sur-place .stat-icon { color: #84cc16; }
        .section { background: white; border-radius: 20px; padding: 24px; margin-bottom: 32px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
        .section-title { font-size: 1.1rem; font-weight: 700; color: #0a2b5e; }
        .empty-state { text-align: center; padding: 40px; color: #64748b; }
        .btn-icon { padding: 8px 16px; background: #f0f4f8; border: none; border-radius: 8px; cursor: pointer; font-weight: 500; margin-left: 8px; }
        .btn-primary { background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .topbar { background: white; padding: 16px 32px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 99; }
        .page-title { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #0a2b5e; }
        .dashboard-content { padding: 32px; }
        .profil-section { max-width: 600px; margin: 0 auto; }
        .profil-section .form-group { margin-bottom: 20px; }
        .profil-section .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #0a2b5e; }
        .profil-section .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.9rem; }
        .profil-section .btn-save { background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; }
        .profil-section .separator { border-top: 1px solid #e2e8f0; margin: 25px 0; }
        .profil-success { background: #d1fae5; color: #065f46; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .profil-error { background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 20px; }
        .modal-choix, .modal-consultation, .modal-qr { display: none; position: fixed; inset: 0; z-index: 1000; background: rgba(13,29,67,0.55); backdrop-filter: blur(3px); align-items: center; justify-content: center; padding: 16px; box-sizing: border-box; }
        .modal-choix.active, .modal-consultation.active, .modal-qr.active { display: flex; }
        .modal-choix-content, .modal-consultation-content, .modal-qr-content { background: white; border-radius: 20px; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; overflow-x: hidden; box-sizing: border-box; }
        .modal-choix-header, .modal-consultation-header, .modal-qr-header { background: linear-gradient(135deg, #1a4db5, #2563eb); padding: 22px 28px; color: white; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 1; }
        .modal-choix-body, .modal-consultation-body, .modal-qr-body { padding: 28px; }
        .choix-btn { width: 100%; padding: 20px; border: 2px solid #c8dff2; border-radius: 12px; background: #f8faff; cursor: pointer; margin-bottom: 16px; display: flex; align-items: center; gap: 15px; }
        .choix-btn-icon { width: 48px; height: 48px; background: #e0edfc; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; color: #0d2d6b; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 12px; border: 1.5px solid #c8dff2; border-radius: 8px; font-family: inherit; font-size: 0.875rem; }
        .btn-cancel { flex: 1; padding: 12px; border: 1.5px solid #c8dff2; border-radius: 9px; background: #f0f4fa; cursor: pointer; }
        .btn-submit { flex: 2; padding: 12px; border: none; border-radius: 9px; background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; font-weight: 700; cursor: pointer; }
        .qr-image-container { text-align: center; padding: 20px; }
        .qr-image-container img { max-width: 200px; }
        .qr-timer { text-align: center; margin-top: 20px; font-size: 1.2rem; font-weight: bold; color: #1a4db5; }
        .hidden { display: none !important; }

        /* ── Search bar ── */
        .search-bar-wrap { padding: 0 0 16px 0; }
        .search-bar { display: flex; align-items: center; background: #1e293b; border: 1.5px solid #334155; border-radius: 12px; padding: 4px 4px 4px 14px; gap: 10px; transition: border-color .2s; }
        .search-bar:focus-within { border-color: #6d5ce7; }
        .search-icon { color: #94a3b8; font-size: .9rem; flex-shrink: 0; }
        .search-bar input { flex: 1; border: none; background: transparent; font-family: inherit; font-size: .9rem; color: #e2e8f0; outline: none; min-width: 0; }
        .search-bar input::placeholder { color: #64748b; }
        .search-clear { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: .85rem; padding: 2px 4px; border-radius: 4px; }
        .search-clear:hover { color: #ef4444; }
        .search-filter-btn { display: flex; align-items: center; gap: 8px; background: linear-gradient(135deg,#6d5ce7,#5b4bd4); color: #fff; border: none; border-radius: 9px; padding: 10px 18px; font-size: .85rem; font-weight: 600; cursor: pointer; flex-shrink: 0; white-space: nowrap; transition: opacity .2s; }
        .search-filter-btn:hover { opacity: .9; }
        .search-no-result { text-align: center; padding: 32px; color: #94a3b8; font-size: .9rem; }

        /* ── Profil lock screen inside dashboard ── */
        .profil-lock { max-width: 400px; margin: 40px auto; background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,.08); padding: 40px 32px; text-align: center; }
        .profil-lock-icon { width: 72px; height: 72px; background: #dbeafe; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 2rem; color: #1a4db5; }
        .profil-lock-title { font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .profil-lock-sub { color: #64748b; font-size: .875rem; margin-bottom: 20px; }
        .profil-lock-error { color: #ef4444; font-size: .8rem; margin-bottom: 10px; background: #fee2e2; padding: 8px 12px; border-radius: 8px; }
        .profil-lock-input { width: 100%; padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: .9rem; margin-bottom: 14px; outline: none; }
        .profil-lock-input:focus { border-color: #1a4db5; }
        .profil-lock-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #1a4db5, #2563eb); color: white; border: none; border-radius: 10px; font-family: inherit; font-size: .95rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .profil-lock-btn:hover { opacity: .9; }

        @media (max-width: 600px) {
            .search-bar input { font-size: .8rem; }
            .profil-lock { padding: 28px 20px; margin: 20px auto; }
        }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } .semaine-nav { flex-direction: column; } .stats-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); } }
        
        /* Sidebar styles */
        .app-container { display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #0a2b5e 0%, #0f2c3f 100%); color: white; display: flex; flex-direction: column; position: fixed; height: 100vh; overflow: hidden; transition: transform 0.3s ease; z-index: 200; }
        .sidebar-header { padding: 24px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-logo { display: flex; align-items: center; gap: 10px; font-size: 1.3rem; font-weight: 700; margin-bottom: 24px; }
        .sidebar-logo i { color: #4ade80; font-size: 1.5rem; }
        .sidebar-user { display: flex; align-items: center; gap: 12px; }
        .sidebar-avatar { width: 48px; height: 48px; background: #4ade80; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.2rem; color: #0a2b5e; flex-shrink: 0; }
        .sidebar-user-info { flex: 1; min-width: 0; }
        .sidebar-user-name { font-weight: 600; font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-user-service { font-size: 0.7rem; opacity: 0.7; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-nav { flex: 1; padding: 20px 0; }
        .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 20px; margin: 4px 12px; border-radius: 12px; cursor: pointer; transition: all 0.2s; color: rgba(255,255,255,0.7); }
        .nav-item i { width: 20px; font-size: 1rem; flex-shrink: 0; }
        .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-footer { padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 12px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 8px; transition: all 0.2s; }
        .logout-btn:hover { background: rgba(255,255,255,0.1); color: white; }
        .main-content { flex: 1; margin-left: 280px; background: #f5f7fa; min-height: 100vh; }

        /* Hamburger */
        .hamburger { display: none; background: none; border: none; cursor: pointer; padding: 8px; border-radius: 8px; color: #0a2b5e; }
        .hamburger span { display: block; width: 22px; height: 2px; background: currentColor; margin: 5px 0; transition: all 0.3s; border-radius: 2px; }
        .hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
        .hamburger.open span:nth-child(2) { opacity: 0; }
        .hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

        /* Overlay */
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
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 16px 12px; }
            .stat-value { font-size: 1.5rem; }
            .stat-icon { font-size: 1.8rem; margin-bottom: 8px; }
            .section { padding: 16px; margin-bottom: 20px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .topbar > div { display: flex; gap: 6px; flex-wrap: wrap; }
            .btn-primary { font-size: 0.8rem; padding: 8px 12px; }
            .btn-icon { font-size: 0.8rem; padding: 8px 12px; }
            .file-table { font-size: 0.78rem; }
            .file-table th, .file-table td { padding: 8px 6px; }
            .modal-choix-content, .modal-consultation-content, .modal-qr-content { max-width: 100%; margin: 0; border-radius: 16px 16px 0 0; max-height: 85vh; }
            .modal-choix, .modal-consultation, .modal-qr { align-items: flex-end; padding: 0; }
            .modal-choix-header, .modal-consultation-header, .modal-qr-header { padding: 18px 20px; }
            .modal-choix-body, .modal-consultation-body, .modal-qr-body { padding: 18px 20px; }
        }

        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
            .stat-card { padding: 12px 8px; }
            .stat-label { font-size: 0.7rem; }
            .form-row { grid-template-columns: 1fr; }
            .topbar > div .btn-primary span { display: none; }
            .qr-image-container img { max-width: 160px; }
        }
    </style>
</head>
<body>
<div class="app-container">

    <!-- Overlay mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div id="refreshIndicator" class="refresh-indicator">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span>Mise à jour...</span>
    </div>
    
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fa-solid fa-list-check"></i><span>QueueCare</span></div>
            <div class="sidebar-user"><div class="sidebar-avatar"><?= $initiale ?></div><div class="sidebar-user-info"><div class="sidebar-user-name"><?= htmlspecialchars(explode(' ', $gestionnaireNom)[0]) ?></div><div class="sidebar-user-service"><?= htmlspecialchars($sousService['nom']) ?></div></div></div>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-item" data-section="file" onclick="showSection('file')"><i class="fa-solid fa-list-ol"></i><span>File d'attente</span></div>
            <div class="nav-item" data-section="consultations" onclick="showSection('consultations')"><i class="fa-solid fa-calendar-day"></i><span>Consultations du jour</span></div>
            <div class="nav-item" data-section="stats" onclick="showSection('stats')"><i class="fa-solid fa-chart-simple"></i><span>Statistiques</span></div>
            <div class="nav-item" data-section="planning" onclick="showSection('planning')"><i class="fa-solid fa-calendar-week"></i><span>Emploi du temps</span></div>
            <div class="nav-item" data-section="historique" onclick="showSection('historique')"><i class="fa-solid fa-clock-rotate-left"></i><span>Historique</span></div>
            <div class="nav-item" data-section="profil" onclick="showSection('profil')"><i class="fa-solid fa-user"></i><span>Mon profil</span></div>
        </nav>
        <div class="sidebar-footer">
            <a href="accueil.php" class="logout-btn" style="margin-bottom:8px;"><i class="fa-solid fa-house"></i><span>Accueil</span></a>
            <a href="gestionnaire.php?action=deconnexion" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i><span>Déconnexion</span></a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:12px">
                <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Menu">
                    <span></span><span></span><span></span>
                </button>
                <h1 class="page-title">Tableau de bord</h1>
            </div>
            <div><button class="btn-icon" onclick="window.location.reload()"><i class="fa-solid fa-rotate-right"></i> Actualiser</button><button class="btn-primary" onclick="ouvrirModalChoix()"><i class="fa-solid fa-user-plus"></i> <span>Nouvelle consultation</span></button></div>
        </div>
        
        <div class="dashboard-content">
            
            <div id="timeoutBanner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;background:#c2410c;color:#fff;padding:12px 24px;align-items:center;justify-content:space-between;gap:16px;font-size:.9rem;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,.3);">
                <span><i class="fa-solid fa-triangle-exclamation"></i> Votre session expire dans moins d'1 minute.</span>
                <button onclick="document.getElementById('timeoutBanner').style.display='none';resetIdleTimer();">Rester connecté</button>
            </div>
            
            <div id="messageContainer">
                <?php if (!empty($messageAction)): ?>
                <div class="action-msg <?= $typeMessage === 'error' ? 'action-msg-error' : 'action-msg-success' ?>"><i class="fa-solid <?= $typeMessage === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i><?= htmlspecialchars($messageAction) ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Section File d'attente -->
            <div id="section-file" class="section">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-list-ol"></i> File d'attente</span><span id="fileCount" class="badge badge-green"><?= count($file) ?> patient(s)</span></div>
                <div class="search-bar-wrap">
                    <div class="search-bar">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="searchFile" placeholder="Rechercher un patient (nom, prénom, téléphone)…" oninput="filtrerFile(this.value)">
                        <button class="search-clear" onclick="clearSearch('searchFile','fileContent','file')" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
                        <button class="search-filter-btn" onclick="filtrerFile(document.getElementById('searchFile').value)"><i class="fa-solid fa-filter"></i> Filtrer</button>
                    </div>
                </div>
                <!-- Filtre par médecin -->
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
                    <span style="font-size:.82rem;font-weight:600;color:#475569;">Médecin :</span>
                    <button class="btn-filtre-medecin active" data-mid="0" onclick="filtrerFileMedecin(0, this)" style="padding:5px 12px;border-radius:20px;border:1.5px solid #0052a0;background:#0052a0;color:#fff;font-size:.78rem;font-weight:600;cursor:pointer;">Tous</button>
                    <?php foreach ($tousMedecins as $tm): ?>
                    <button class="btn-filtre-medecin" data-mid="<?= (int)$tm['id'] ?>" onclick="filtrerFileMedecin(<?= (int)$tm['id'] ?>, this)" style="padding:5px 12px;border-radius:20px;border:1.5px solid #e2e8f0;background:#fff;color:#0052a0;font-size:.78rem;font-weight:600;cursor:pointer;"><?= htmlspecialchars($tm['prenom'] . ' ' . $tm['nom']) ?></button>
                    <?php endforeach; ?>
                </div>
                <div id="fileContent">
                    <?php if (empty($file)): ?>
                    <div class="empty-state">Aucun patient en attente.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="file-table"><thead><tr><th>Rang</th><th>Patient</th><th>Téléphone</th><th>Heure</th><th>Mode</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
                        <?php foreach ($file as $c): ?>
                        <tr>
                            <td data-label="Rang"><div class="rang-badge <?= $c['statut'] === 'en_cours' ? 'actif' : '' ?>"><?= (int)$c['rang'] ?></div></td>
                            <td data-label="Patient"><strong><?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?></strong></td>
                            <td data-label="Téléphone"><?= htmlspecialchars($c['telephone']) ?></td>
                            <td data-label="Heure"><?= $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td>
                            <td data-label="Mode"><?= $c['mode_prise'] === 'LIGNE' ? '<span class="badge badge-blue">En ligne</span>' : '<span class="badge badge-green">Sur place</span>' ?></td>
                            <td data-label="Statut"><span class="badge badge-grey"><?= ucfirst($c['statut'] ?? 'en attente') ?></span></td>
                            <td data-label="Actions"><form class="status-form" style="display:flex;gap:5px;"><input type="hidden" name="action" value="maj_statut"><input type="hidden" name="consultation_id" value="<?= (int)$c['id'] ?>"><button type="submit" name="statut" value="traite" class="btn-sm btn-sm-green"><i class="fa-solid fa-check"></i> Traité</button><button type="submit" name="statut" value="absent" class="btn-sm btn-sm-orange"><i class="fa-solid fa-user-slash"></i> Absent</button><button type="submit" name="statut" value="annule" class="btn-sm btn-sm-red"><i class="fa-solid fa-xmark"></i> Annuler</button></form></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section Consultations -->
            <div id="section-consultations" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-calendar-day"></i> Consultations du jour</span><span id="consultationsCount" class="badge badge-blue"><?= count($consultations) ?></span></div>
                <div class="search-bar-wrap">
                    <div class="search-bar">
                        <i class="fa-solid fa-magnifying-glass search-icon"></i>
                        <input type="text" id="searchConsultations" placeholder="Rechercher (patient, médecin, statut)…" oninput="filtrerConsultations(this.value)">
                        <button class="search-clear" onclick="clearSearch('searchConsultations','consultationsContent','consultations')" title="Effacer"><i class="fa-solid fa-xmark"></i></button>
                        <button class="search-filter-btn" onclick="filtrerConsultations(document.getElementById('searchConsultations').value)"><i class="fa-solid fa-filter"></i> Filtrer</button>
                    </div>
                </div>
                <div id="consultationsContent">
                    <?php if (empty($consultations)): ?>
                    <div class="empty-state">Aucune consultation.</div>
                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="file-table"><thead><tr><th>#</th><th>Patient</th><th>Médecin</th><th>Heure</th><th>Statut</th></tr></thead><tbody>
                        <?php foreach ($consultations as $c): ?>
                        <tr>
                            <td data-label="#"><?= (int)$c['rang'] ?></td>
                            <td data-label="Patient"><strong><?= htmlspecialchars($c['patient_nom'] . ' ' . $c['patient_prenom']) ?></strong></td>
                            <td data-label="Médecin"><?= $c['medecin_nom'] ? htmlspecialchars($c['medecin_nom']) : '—' ?></td>
                            <td data-label="Heure"><?= $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—' ?></td>
                            <td data-label="Statut"><?php $bc = match($c['statut']) { 'traite' => 'badge-green', 'en_cours' => 'badge-blue', 'annule','absent' => 'badge-red', default => 'badge-grey' }; $lb = match($c['statut']) { 'traite' => 'Traitée', 'en_cours' => 'En cours', 'annule' => 'Annulée', 'absent' => 'Absent', 'en_attente' => 'En attente', 'confirme' => 'Confirmé', default => $c['statut'] }; ?><span class="badge <?= $bc ?>"><?= $lb ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Section Statistiques -->
            <div id="section-stats" class="stats-grid" style="display:none;">
                <div class="stat-card total">
                    <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="stat-value" id="statTotal"><?= (int)($stats['total'] ?? 0) ?></div>
                    <div class="stat-label">Total consultations</div>
                </div>
                <div class="stat-card traitees">
                    <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-value" id="statTraitees"><?= (int)($stats['traitees'] ?? 0) ?></div>
                    <div class="stat-label">Consultations traitées</div>
                </div>
                <div class="stat-card en-attente">
                    <div class="stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="stat-value" id="statEnAttente"><?= (int)($stats['en_attente'] ?? 0) ?></div>
                    <div class="stat-label">En attente</div>
                </div>
                <div class="stat-card absents">
                    <div class="stat-icon"><i class="fa-solid fa-user-slash"></i></div>
                    <div class="stat-value" id="statAbsentes"><?= (int)($stats['absentes'] ?? 0) ?></div>
                    <div class="stat-label">Patients absents</div>
                </div>
                <div class="stat-card annulees">
                    <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
                    <div class="stat-value" id="statAnnulees"><?= (int)($stats['annulees'] ?? 0) ?></div>
                    <div class="stat-label">Consultations annulées</div>
                </div>
                <div class="stat-card en-ligne">
                    <div class="stat-icon"><i class="fa-solid fa-wifi"></i></div>
                    <div class="stat-value" id="statEnLigne"><?= (int)($stats['en_ligne'] ?? 0) ?></div>
                    <div class="stat-label">Prises en ligne</div>
                </div>
                <div class="stat-card sur-place">
                    <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="stat-value" id="statSurPlace"><?= (int)($stats['sur_place'] ?? 0) ?></div>
                    <div class="stat-label">Prises sur place</div>
                </div>
            </div>
            
            <!-- Section Emploi du temps -->
            <div id="section-planning" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-calendar-week"></i> Emploi du temps</span><button class="btn-icon" onclick="chargerEmploiTemps()"><i class="fa-solid fa-rotate-right"></i> Rafraîchir</button></div>

                <!-- Sélecteur de médecin -->
                <div style="margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <label for="selectMedecin" style="font-size:.85rem;font-weight:600;color:#0a2b5e;white-space:nowrap;"><i class="fa-solid fa-user-doctor" style="color:#1a4db5;margin-right:6px;"></i>Médecin :</label>
                    <select id="selectMedecin" onchange="chargerEmploiTemps()" style="padding:8px 14px;border:1.5px solid #c8dff2;border-radius:10px;font-family:inherit;font-size:.875rem;color:#0a2b5e;background:#fff;cursor:pointer;min-width:220px;">
                        <option value="">— Tous les médecins —</option>
                        <?php foreach ($medecins as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['prenom'] . ' ' . $m['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Info bar horaires hôpital -->
                <?php
                $hOuv = isset($serviceHoraires['horaires_ouverture']) ? substr($serviceHoraires['horaires_ouverture'],0,5) : '08:00';
                $hFer = isset($serviceHoraires['horaires_fermeture']) ? substr($serviceHoraires['horaires_fermeture'],0,5) : '18:00';
                $hPauD = !empty($serviceHoraires['pause_debut']) ? substr($serviceHoraires['pause_debut'],0,5) : null;
                $hPauF = !empty($serviceHoraires['pause_fin']) ? substr($serviceHoraires['pause_fin'],0,5) : null;
                ?>
                <div class="planning-info-bar">
                    <span><i class="fa-solid fa-hospital" style="color:#1a4db5;margin-right:5px;"></i>Horaires : <strong><?= $hOuv ?> – <?= $hFer ?></strong></span>
                    <?php if ($hPauD && $hPauF): ?>
                    <span>•</span><span><i class="fa-solid fa-utensils" style="color:#d97706;margin-right:5px;"></i>Pause : <strong><?= $hPauD ?> – <?= $hPauF ?></strong></span>
                    <?php endif; ?>
                </div>

                <!-- Légende -->
                <div class="planning-legend">
                    <div style="display:flex;align-items:center;gap:6px;"><div class="legend-dot" style="background:#bfdbfe;border-left:3px solid #3b82f6;border-radius:3px;"></div><span>Programmée</span></div>
                    <div style="display:flex;align-items:center;gap:6px;"><div class="legend-dot" style="background:#bbf7d0;border-left:3px solid #10b981;border-radius:3px;"></div><span>En cours</span></div>
                    <div style="display:flex;align-items:center;gap:6px;"><div class="legend-dot" style="background:#e2e8f0;border-left:3px solid #94a3b8;border-radius:3px;"></div><span>Traité</span></div>
                    <div style="display:flex;align-items:center;gap:6px;"><div class="legend-dot" style="background:#fde68a;border-left:3px solid #f59e0b;border-radius:3px;"></div><span>Pause</span></div>
                    <div style="display:flex;align-items:center;gap:6px;"><div class="legend-dot" style="background:repeating-linear-gradient(45deg,#f8fafc,#f8fafc 4px,#f1f5f9 4px,#f1f5f9 9px);border:1px solid #e2e8f0;border-radius:3px;"></div><span>Repos/fermé</span></div>
                </div>

                <!-- Bannière médecin sélectionné (injectée par JS) -->
                <div id="planningMedecinBanner" style="display:none;"></div>

                <div class="semaine-nav">
                    <div class="semaine-nav-buttons">
                        <button class="btn-icon" onclick="changerSemaine(-1)"><i class="fa-solid fa-chevron-left"></i> Semaine préc.</button>
                        <button class="btn-icon" onclick="changerSemaine(0)"><i class="fa-solid fa-calendar-day"></i> Aujourd'hui</button>
                        <button class="btn-icon" onclick="changerSemaine(1)">Semaine suiv. <i class="fa-solid fa-chevron-right"></i></button>
                    </div>
                    <div id="semaineLabel" class="semaine-label">Chargement...</div>
                </div>
                <div id="planningContainer"><div id="planningLoading" class="planning-loading"><i class="fa-solid fa-spinner fa-spin"></i><p>Chargement...</p></div><div id="planningTable" style="display:none;"></div></div>
            </div>
            
            <!-- Section Historique -->
            <div id="section-historique" class="section" style="display:none;">
                <div class="section-header">
                    <span class="section-title"><i class="fa-solid fa-clock-rotate-left"></i> Historique des consultations</span>
                    <span id="historiqueTotal" class="badge badge-grey">—</span>
                </div>
                <!-- Filtres -->
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;padding:14px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
                    <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:130px;">
                        <label style="font-size:.72rem;font-weight:600;color:#475569;">Statut</label>
                        <select id="histStatut" onchange="chargerHistoriqueG(1)" style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.82rem;font-family:inherit;">
                            <option value="">Tous</option>
                            <option value="traite">Traité</option>
                            <option value="absent">Absent</option>
                            <option value="annule">Annulé</option>
                        </select>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px;">
                        <label style="font-size:.72rem;font-weight:600;color:#475569;">Du</label>
                        <input type="date" id="histDateDebut" onchange="chargerHistoriqueG(1)" style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    </div>
                    <div style="display:flex;flex-direction:column;gap:4px;flex:1;min-width:140px;">
                        <label style="font-size:.72rem;font-weight:600;color:#475569;">Au</label>
                        <input type="date" id="histDateFin" onchange="chargerHistoriqueG(1)" style="padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:.82rem;font-family:inherit;">
                    </div>
                    <div style="display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap;">
                        <button onclick="reinitFiltresHistG()" style="padding:8px 14px;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:.82rem;color:#64748b;">
                            <i class="fa-solid fa-rotate-left"></i> Réinitialiser
                        </button>
                        <button onclick="exporterHistoriquePDF()" style="padding:8px 14px;background:#dc2626;border:none;border-radius:8px;cursor:pointer;font-size:.82rem;color:#fff;font-family:inherit;font-weight:600;">
                            <i class="fa-solid fa-file-pdf"></i> Télécharger PDF
                        </button>
                        <button onclick="imprimerHistorique()" style="padding:8px 14px;background:#0a2b5e;border:none;border-radius:8px;cursor:pointer;font-size:.82rem;color:#fff;font-family:inherit;font-weight:600;">
                            <i class="fa-solid fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
                <div id="historiqueContent">
                    <div class="empty-state"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.5rem;color:#1a4db5;"></i><p>Chargement...</p></div>
                </div>
                <div id="historiquePagination" style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap;"></div>
            </div>

            <!-- Section Profil -->
            <div id="section-profil" class="section" style="display:none;">
                <div class="section-header"><span class="section-title"><i class="fa-solid fa-user"></i> Mon profil</span></div>
                <!-- Lock screen profil -->
                <div id="profilLockScreen" class="profil-lock">
                    <div class="profil-lock-icon"><i class="fa-solid fa-lock"></i></div>
                    <div class="profil-lock-title">Vérification requise</div>
                    <div class="profil-lock-sub">Entrez votre mot de passe pour accéder à votre profil.</div>
                    <div class="profil-lock-error" id="profilLockError" style="display:none;">Mot de passe incorrect.</div>
                    <input type="password" class="profil-lock-input" id="profilLockPassword" placeholder="Votre mot de passe" autocomplete="current-password">
                    <button class="profil-lock-btn" onclick="verifierMdpProfil()"><i class="fa-solid fa-unlock"></i> Accéder au profil</button>
                </div>
                <div id="profilContent" style="display:none;"><div class="empty-state">Chargement...</div></div>
            </div>
        </div>
    </main>
</div>

<!-- MODALS -->
<div id="modalChoix" class="modal-choix"><div class="modal-choix-content"><div class="modal-choix-header"><h2>Choisir le mode</h2><button onclick="fermerModalChoix()" style="background:rgba(255,255,255,0.15);border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;color:white;">✕</button></div><div class="modal-choix-body"><div class="choix-btn" onclick="choisirMode('manuel')"><div class="choix-btn-icon"><i class="fa-solid fa-pen-to-square" style="font-size:1.4rem;color:#1a4db5;"></i></div><div><div class="choix-btn-title">Saisie manuelle</div><div class="choix-btn-desc">Remplir le formulaire</div></div></div><div class="choix-btn" onclick="choisirMode('qr')"><div class="choix-btn-icon"><i class="fa-solid fa-qrcode" style="font-size:1.4rem;color:#1a4db5;"></i></div><div><div class="choix-btn-title">QR Code</div><div class="choix-btn-desc">Générer un QR code</div></div></div></div></div></div>

<div id="modalConsultation" class="modal-consultation"><div class="modal-consultation-content"><div class="modal-consultation-header"><div><div style="font-weight:700;font-size:1.2rem;">Enregistrer une consultation</div><div style="font-size:0.8rem;opacity:0.7;"><?= htmlspecialchars($sousService['nom']) ?></div></div><button onclick="fermerModalConsultation()" style="background:rgba(255,255,255,0.15);border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;color:white;">✕</button></div><div class="modal-consultation-body"><form id="formConsultManuelle" onsubmit="return false;"><input type="hidden" name="action" value="consultation_manuelle"><div class="form-group"><label>Téléphone *</label><input type="tel" id="m_telephone" name="patient_telephone" placeholder="+237699123456" oninput="rechercherPatient(this.value)"></div><div class="form-row"><div class="form-group"><label>Nom *</label><input type="text" id="m_nom" name="patient_nom" required></div><div class="form-group"><label>Prénom *</label><input type="text" id="m_prenom" name="patient_prenom" required></div></div><div class="form-group"><label>Email</label><input type="email" id="m_email" name="patient_email" placeholder="patient@email.cm"></div><div class="form-group"><label>Statut initial</label><select name="statut"><option value="en_attente">En attente</option><option value="confirme">Confirmé</option></select></div><div class="form-group"><label>Motif</label><textarea name="motif" rows="3"></textarea></div><div style="display:flex;gap:10px;margin-top:20px;"><button type="button" class="btn-cancel" onclick="fermerModalConsultation()">Annuler</button><button type="submit" class="btn-submit" onclick="soumettreConsultationManuelle()">Enregistrer</button></div></form></div></div></div>

<div id="modalQR" class="modal-qr"><div class="modal-qr-content"><div class="modal-qr-header"><h2>QR Code</h2><button onclick="fermerModalQR()" style="background:rgba(255,255,255,0.15);border:none;width:34px;height:34px;border-radius:50%;cursor:pointer;color:white;">✕</button></div><div class="modal-qr-body"><div id="qrLoadingState" class="qr-loading"></div><div id="qrContentState" class="hidden"><div class="qr-image-container"><img id="qrCodeImage" src="" alt="QR Code"></div><div class="qr-timer" id="qrTimer">20:00</div><div id="qrMessage" class="hidden"></div><div class="qr-actions"><button class="btn-sm btn-sm-green" onclick="telechargerQRCode()"><i class="fa-solid fa-download"></i> Télécharger</button><button class="btn-sm btn-sm-blue" onclick="imprimerQRCode()"><i class="fa-solid fa-print"></i> Imprimer</button><button class="btn-primary" onclick="regenererQRCode()">Régénérer</button></div></div></div></div></div>

<script>
    let semaineOffset = 0, currentQRPath = '', currentExpireAt = null, qrTimerInterval = null, autoRegenerateInterval = null, searchTimer, currentSection = 'file', isRefreshing = false;
    
    function escapeHtml(t) { if(!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    
    function afficherMessage(msg, type) {
        const c = document.getElementById('messageContainer'); if(!c) return;
        const m = document.createElement('div');
        m.className = `action-msg ${type === 'error' ? 'action-msg-error' : 'action-msg-success'}`;
        m.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-triangle-exclamation' : 'fa-circle-check'}"></i> ${escapeHtml(msg)}`;
        c.innerHTML = ''; c.appendChild(m);
        setTimeout(() => { if(m) { m.style.opacity = '0'; setTimeout(() => m.remove(), 500); } }, 5000);
    }
    
    function showRefreshIndicator(show) { const ind = document.getElementById('refreshIndicator'); if(ind) show ? ind.classList.add('show') : ind.classList.remove('show'); }
    
    function rafraichirDonnees() {
        if(isRefreshing) return;
        isRefreshing = true;
        fetch('gestionnaire.php?action=get_dashboard_data')
            .then(r=>r.json()).then(d=>{
                console.log('[DEBUG rafraichirDonnees]', d);
                if(d.success){
                    if(currentSection==='file'){ mettreAJourFileAttente(d.file); const fc=document.getElementById('fileCount'); if(fc) fc.innerHTML=`${d.file.length} patient(s)`; }
                    if(currentSection==='consultations'){ mettreAJourConsultations(d.consultations); const cc=document.getElementById('consultationsCount'); if(cc) cc.innerHTML=d.consultations.length; }
                    mettreAJourStatistiques(d.stats);
                } else {
                    console.warn('[DEBUG] get_dashboard_data a renvoyé success=false :', d.message || d);
                }
            }).catch(e=>console.error('Erreur refresh:',e))
            .finally(()=>{ isRefreshing=false; });
    }
    
    function mettreAJourFileAttente(file) {
        _fileData = file;
        const q = document.getElementById('searchFile')?.value||'';
        _filePageG = 1;
        afficherFile(q ? _fileData.filter(p=>(p.patient_nom||'').toLowerCase().includes(q.toLowerCase())||(p.patient_prenom||'').toLowerCase().includes(q.toLowerCase())||(p.patient_telephone||'').toLowerCase().includes(q.toLowerCase())) : _fileData);
    }
    
    function afficherFile(file, filtered) {
        const c = document.getElementById('fileContent'); if(!c) return;
        if(file.length===0){ c.innerHTML=`<div class="search-no-result"><i class="fa-solid fa-search"></i><br>${filtered?'Aucun résultat pour cette recherche.':'Aucun patient en attente.'}</div>`; return; }
        // Compute rang per médecin (based on position in each doctor's sorted sub-queue)
        const rangParMedecin = {};
        const fileSorted = [...file].sort((a,b) => {
            const ea = (a.statut==='en_cours'||a.statut==='en_pause') ? 0 : 1;
            const eb = (b.statut==='en_cours'||b.statut==='en_pause') ? 0 : 1;
            if(ea!==eb) return ea-eb;
            return (a.rang||0) - (b.rang||0);
        });
        fileSorted.forEach(f => {
            const mid = f.medecin_id || 0;
            if(!rangParMedecin[mid]) rangParMedecin[mid] = 0;
            rangParMedecin[mid]++;
            f._rang_medecin = rangParMedecin[mid];
        });
        const totalPages = Math.max(1, Math.ceil(file.length / _PER_PAGE_G));
        if(_filePageG > totalPages) _filePageG = totalPages;
        const debut = (_filePageG - 1) * _PER_PAGE_G;
        const page = file.slice(debut, debut + _PER_PAGE_G);
        // Bandeau d'alerte priorité retour examen (côté gestionnaire)
        const retoursPrioritaires = file.filter(f => f.statut === 'en_pause' && f.priorite_retour);
        let bandeauG = '';
        if(retoursPrioritaires.length > 0) {
            const nomsG = retoursPrioritaires.map(f => `${escapeHtml(f.patient_prenom)} ${escapeHtml(f.patient_nom)}`).join(', ');
            bandeauG = `<div style="background:#eff6ff;border:1.5px solid #0052a0;border-radius:10px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
                <i class="fa-solid fa-star" style="color:#0052a0;font-size:1rem;"></i>
                <span style="font-size:.84rem;font-weight:700;color:#1e3a5f;">Retour prioritaire d'examen : <em style="font-style:normal;">${nomsG}</em> — Cliquez <strong>Revenu (priorité)</strong> dès leur retour</span>
            </div>`;
        }

        let h= bandeauG + '<div style="overflow-x:auto;"><table class="file-table"><thead><tr><th>Rang</th><th>Patient</th><th>Téléphone</th><th>Heure</th><th>Mode</th><th>Statut</th><th>Actions</th></tr></thead><tbody>';
        for(const f of page){
            const isPauseRetourG = f.statut === 'en_pause' && f.priorite_retour;
            // Badge statut
            let badgeSt, labelSt;
            if(f.statut==='en_cours')       { badgeSt='badge-blue';  labelSt='En cours'; }
            else if(f.statut==='en_pause')  { badgeSt='badge-pause'; labelSt='⏸ En pause'; }
            else if(f.statut==='confirme')  { badgeSt='badge-grey';  labelSt='Confirmé'; }
            else                             { badgeSt='badge-grey';  labelSt='En attente'; }

            // Timer de pause visible pour le gestionnaire
            let pauseInfo = '';
            if(f.statut==='en_pause' && f.secondes_en_pause > 0) {
                const mins = Math.floor(f.secondes_en_pause/60);
                const secsRest = f.secondes_en_pause % 60;
                pauseInfo = `<br><small style="color:#b45309;font-size:.72rem;"><i class="fa-solid fa-clock"></i> Parti depuis ${mins}min ${secsRest}s — retour attendu${f.motif_pause?' ('+escapeHtml(f.motif_pause)+')':''}</small>`;
            }
            if(isPauseRetourG) {
                pauseInfo += `<br><span style="display:inline-flex;align-items:center;gap:4px;background:#0052a0;color:white;font-size:.68rem;font-weight:700;border-radius:20px;padding:2px 8px;margin-top:3px;"><i class="fa-solid fa-star"></i> Priorité retour</span>`;
            }

            // Boutons d'action selon statut
            let actions = '';
            if(f.statut==='en_pause') {
                actions += `<button class="btn-sm btn-sm-resume" style="${isPauseRetourG?'font-weight:700;border:2px solid #0052a0;':''}" onclick="reprendreConsultationG(${f.id})"><i class="fa-solid fa-play"></i> ${isPauseRetourG?'⭐ Revenu (priorité)':'Revenu'}</button>`;
                actions += `<button class="btn-sm btn-sm-orange" onclick="soumettreActionG(${f.id},'absent')"><i class="fa-solid fa-user-slash"></i> Absent</button>`;
            } else {
                actions = `<form class="status-form" style="display:flex;gap:5px;flex-wrap:wrap;"><input type="hidden" name="action" value="maj_statut"><input type="hidden" name="consultation_id" value="${f.id}"><button type="submit" name="statut" value="traite" class="btn-sm btn-sm-green"><i class="fa-solid fa-check"></i> Traité</button><button type="submit" name="statut" value="absent" class="btn-sm btn-sm-orange"><i class="fa-solid fa-user-slash"></i> Absent</button><button type="submit" name="statut" value="annule" class="btn-sm btn-sm-red"><i class="fa-solid fa-xmark"></i> Annuler</button></form>`;
            }

            const rowStyleG = isPauseRetourG ? 'style="background:#eff6ff;border-left:3px solid #0052a0;"' : (f.statut==='en_pause'?'style="background:#fffbeb;"':'');
            h+=`<tr ${rowStyleG}>
                <td data-label="Rang"><div class="rang-badge ${f.statut==='en_cours'?'actif':''}">${f._rang_medecin ?? f.rang}</div></td>
                <td data-label="Patient"><strong>${escapeHtml(f.patient_nom)} ${escapeHtml(f.patient_prenom)}</strong>${pauseInfo}</td>
                <td data-label="Téléphone">${escapeHtml(f.telephone)}</td>
                <td data-label="Heure">${f.heure_passage_estimee||'—'}</td>
                <td data-label="Mode">${f.mode_prise==='LIGNE'?'<span class="badge badge-blue">En ligne</span>':'<span class="badge badge-green">Sur place</span>'}</td>
                <td data-label="Statut"><span class="badge ${badgeSt}">${labelSt}</span></td>
                <td data-label="Actions">${actions}</td></tr>`;
        }
        h+='</tbody></table></div>';
        h += renderPaginationG(file, _filePageG, totalPages, '_filePageG', 'file');
        c.innerHTML = h;
        document.querySelectorAll('.status-form').forEach(f=>f.addEventListener('submit',function(e){e.preventDefault();soumettreFormulaireStatut(this);}));
    }

    let _consultPageG = 1;

    function mettreAJourConsultations(consultations) {
        _consultationsData = consultations;
        const q = document.getElementById('searchConsultations')?.value||'';
        _consultPageG = 1;
        afficherConsultations(q ? _consultationsData.filter(c=>(c.patient_nom||'').toLowerCase().includes(q.toLowerCase())||(c.patient_prenom||'').toLowerCase().includes(q.toLowerCase())||(c.medecin_nom||'').toLowerCase().includes(q.toLowerCase())||(c.statut||'').toLowerCase().includes(q.toLowerCase())) : _consultationsData);
    }
    
    function afficherConsultations(consultations, filtered) {
        const c = document.getElementById('consultationsContent'); if(!c) return;
        if(consultations.length===0){ c.innerHTML=`<div class="search-no-result"><i class="fa-solid fa-search"></i><br>${filtered?'Aucun résultat pour cette recherche.':'Aucune consultation.'}</div>`; return; }
        const totalPages = Math.max(1, Math.ceil(consultations.length / _PER_PAGE_G));
        if(_consultPageG > totalPages) _consultPageG = totalPages;
        const debut = (_consultPageG - 1) * _PER_PAGE_G;
        const page = consultations.slice(debut, debut + _PER_PAGE_G);
        let h='<div style="overflow-x:auto;"><table class="file-table"><thead><tr><th>#</th><th>Patient</th><th>Médecin</th><th>Heure</th><th>Statut</th></tr></thead><tbody>';
        for(const ct of page){
            let bc='badge-grey',lb=ct.statut;
            if(ct.statut==='traite'){ bc='badge-green'; lb='Traitée'; }
            else if(ct.statut==='en_cours'){ bc='badge-blue'; lb='En cours'; }
            else if(ct.statut==='annule'){ bc='badge-red'; lb='Annulée'; }
            else if(ct.statut==='absent'){ bc='badge-red'; lb='Absent'; }
            else if(ct.statut==='en_attente'){ lb='En attente'; }
            else if(ct.statut==='confirme'){ lb='Confirmé'; }
            h+=`<tr>
                <td data-label="#">${ct.rang}</td>
                <td data-label="Patient"><strong>${escapeHtml(ct.patient_nom)} ${escapeHtml(ct.patient_prenom)}</strong></td>
                <td data-label="Médecin">${ct.medecin_nom?escapeHtml(ct.medecin_nom):'—'}</td>
                <td data-label="Heure">${ct.heure_passage_estimee||'—'}</td>
                <td data-label="Statut"><span class="badge ${bc}">${lb}</span></td>
            </tr>`;
        }
        h+='</tbody></table></div>';
        h += renderPaginationG(consultations, _consultPageG, totalPages, '_consultPageG', 'consultations');
        c.innerHTML = h;
    }

    function renderPaginationG(data, page, totalPages, varName, type) {
        if(totalPages <= 1) return '';
        const btnS = 'padding:5px 10px;border-radius:7px;border:1.5px solid #e2e8f0;background:white;cursor:pointer;font-size:.75rem;font-family:inherit;';
        const actS = 'padding:5px 10px;border-radius:7px;border:1.5px solid #1a4db5;background:#1a4db5;color:white;cursor:pointer;font-size:.75rem;font-family:inherit;font-weight:700;';
        let h = `<div style="display:flex;justify-content:center;align-items:center;gap:5px;margin-top:14px;flex-wrap:wrap;">`;
        h += `<button style="${btnS}" onclick="_goPageG(${page-1},'${type}',event)" ${page<=1?'disabled':''}><i class="fa-solid fa-angle-left"></i></button>`;
        for(let i=1; i<=totalPages; i++) h += `<button style="${i===page?actS:btnS}" onclick="_goPageG(${i},'${type}',event)">${i}</button>`;
        h += `<button style="${btnS}" onclick="_goPageG(${page+1},'${type}',event)" ${page>=totalPages?'disabled':''}><i class="fa-solid fa-angle-right"></i></button>`;
        h += `<span style="font-size:.72rem;color:#64748b;margin-left:4px;">${(page-1)*_PER_PAGE_G+1}–${Math.min(page*_PER_PAGE_G,data.length)} / ${data.length}</span>`;
        h += '</div>';
        return h;
    }

    function _goPageG(p, type, e) {
        e && e.stopPropagation();
        if(type === 'file') {
            _filePageG = p;
            const q = document.getElementById('searchFile')?.value||'';
            afficherFile(q ? _fileData.filter(f=>(f.patient_nom||'').toLowerCase().includes(q.toLowerCase())||(f.patient_prenom||'').toLowerCase().includes(q.toLowerCase())||(f.telephone||'').toLowerCase().includes(q.toLowerCase())) : _fileData, !!q);
        } else {
            _consultPageG = p;
            const q = document.getElementById('searchConsultations')?.value||'';
            afficherConsultations(q ? _consultationsData.filter(c=>(c.patient_nom||'').toLowerCase().includes(q.toLowerCase())||(c.medecin_nom||'').toLowerCase().includes(q.toLowerCase())||(c.statut||'').toLowerCase().includes(q.toLowerCase())) : _consultationsData, !!q);
        }
    }
    
    function mettreAJourStatistiques(stats) {
        const st=document.getElementById('statTotal'), stt=document.getElementById('statTraitees'), sea=document.getElementById('statEnAttente'), sa=document.getElementById('statAbsentes'), san=document.getElementById('statAnnulees'), sel=document.getElementById('statEnLigne'), ssp=document.getElementById('statSurPlace');
        if(st) st.innerHTML=stats.total||0; if(stt) stt.innerHTML=stats.traitees||0; if(sea) sea.innerHTML=stats.en_attente||0;
        if(sa) sa.innerHTML=stats.absentes||0; if(san) san.innerHTML=stats.annulees||0; if(sel) sel.innerHTML=stats.en_ligne||0; if(ssp) ssp.innerHTML=stats.sur_place||0;
    }
    
    function soumettreFormulaireStatut(form) {
        fetch('gestionnaire.php?action=traiter_action_ajax',{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{if(d.success){afficherMessage(d.message,'success');rafraichirDonnees();}else afficherMessage(d.message||'Erreur','error');})
            .catch(()=>afficherMessage('Erreur','error'));
    }

    function reprendreConsultationG(id) {
        if(!confirm('Le patient est revenu de son examen ? La consultation va reprendre.')) return;
        const fd = new FormData();
        fd.append('action', 'reprendre_consultation');
        fd.append('consultation_id', id);
        fetch('gestionnaire.php?action=traiter_action_ajax', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{
                if(d.success){ afficherMessage(d.message,'success'); rafraichirDonnees(); }
                else afficherMessage(d.message||'Erreur','error');
            }).catch(()=>afficherMessage('Erreur réseau','error'));
    }

    function soumettreActionG(id, statut) {
        const fd = new FormData();
        fd.append('action', 'maj_statut');
        fd.append('consultation_id', id);
        fd.append('statut', statut);
        fetch('gestionnaire.php?action=traiter_action_ajax', { method:'POST', body:fd, headers:{'X-Requested-With':'XMLHttpRequest'} })
            .then(r=>r.json()).then(d=>{
                if(d.success){ afficherMessage(d.message,'success'); rafraichirDonnees(); }
                else afficherMessage(d.message||'Erreur','error');
            }).catch(()=>afficherMessage('Erreur réseau','error'));
    }
    
    function soumettreConsultationManuelle() {
        const f=document.getElementById('formConsultManuelle'), fd=new FormData(f), btn=document.querySelector('#modalConsultation .btn-submit'), ot=btn?btn.innerHTML:'';
        if(btn){btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';btn.disabled=true;}
        fetch('gestionnaire.php?action=traiter_action_ajax',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r=>r.json()).then(d=>{
                if(btn){btn.innerHTML=ot;btn.disabled=false;}
                if(d.success){fermerModalConsultation();afficherMessage(d.message,'success');rafraichirDonnees();}
                else afficherMessage(d.message||'Erreur','error');
            }).catch(()=>{if(btn){btn.innerHTML=ot;btn.disabled=false;}afficherMessage('Erreur','error');});
    }
    
    // Fonction showSection modifiée pour sauvegarder la section active
    function showSection(section) {
        currentSection = section;
        
        // Sauvegarder dans localStorage pour restaurer après rafraîchissement
        localStorage.setItem('gestionnaire_active_section', section);
        
        const sf=document.getElementById('section-file'), sc=document.getElementById('section-consultations'), sst=document.getElementById('section-stats'), sp=document.getElementById('section-planning'), spr=document.getElementById('section-profil');
        if(sf) sf.style.display='none'; if(sc) sc.style.display='none'; if(sst) sst.style.display='none'; if(sp) sp.style.display='none'; if(spr) spr.style.display='none';
        const sh = document.getElementById('section-historique'); if(sh) sh.style.display='none';
        if(section==='file' && sf) sf.style.display='block';
        else if(section==='consultations' && sc) sc.style.display='block';
        else if(section==='stats' && sst) sst.style.display='grid';
        else if(section==='planning' && sp){ sp.style.display='block'; chargerEmploiTemps(); }
        else if(section==='historique' && sh){ sh.style.display='block'; chargerHistoriqueG(1); }
        else if(section==='profil' && spr){
            spr.style.display='block';
            // Réinitialiser le lock screen à chaque visite
            const ls=document.getElementById('profilLockScreen'), pc=document.getElementById('profilContent'), lp=document.getElementById('profilLockPassword');
            if(ls){ ls.style.display='block'; } if(pc){ pc.style.display='none'; }
            if(lp){ lp.value=''; } _profilPassword='';
            const le=document.getElementById('profilLockError'); if(le) le.style.display='none';
        }
        
        // Mettre à jour la classe active sur les éléments de navigation
        document.querySelectorAll('.nav-item').forEach(i=>i.classList.remove('active'));
        const activeNav = document.querySelector(`.nav-item[data-section="${section}"]`);
        if(activeNav) activeNav.classList.add('active');
    }
    
    // Restaurer la section active après chargement de la page
    function restaurerSectionActive() {
        const savedSection = localStorage.getItem('gestionnaire_active_section');
        const section = (savedSection && ['file', 'consultations', 'stats', 'planning', 'historique', 'profil'].includes(savedSection)) ? savedSection : 'file';
        // Initialiser la pagination avec les données PHP avant showSection
        if (_fileData.length > 0) { const fc = document.getElementById('fileCount'); if(fc) fc.innerHTML = _fileData.length + ' patient(s)'; afficherFile(_fileData); }
        if (_consultationsData.length > 0) { const cc = document.getElementById('consultationsCount'); if(cc) cc.innerHTML = _consultationsData.length; afficherConsultations(_consultationsData); }
        showSection(section);
    }
    
    // ── Historique gestionnaire ──
    let _histPageG = 1;

    function chargerHistoriqueG(page) {
        _histPageG = page || 1;
        const statut    = document.getElementById('histStatut')?.value || '';
        const dateDebut = document.getElementById('histDateDebut')?.value || '';
        const dateFin   = document.getElementById('histDateFin')?.value || '';
        const content   = document.getElementById('historiqueContent');
        const pagination = document.getElementById('historiquePagination');
        if(content) content.innerHTML = '<div class="empty-state"><i class="fa-solid fa-spinner fa-spin" style="font-size:1.4rem;color:#1a4db5;"></i></div>';
        if(pagination) pagination.innerHTML = '';

        const params = new URLSearchParams({ page: _histPageG, statut, date_debut: dateDebut, date_fin: dateFin });
        fetch('gestionnaire.php?action=get_historique&' + params.toString())
            .then(r => r.json())
            .then(d => {
                if(d.redirect) { window.location.href = d.redirect; return; }
                if(!d.success) { if(content) content.innerHTML = `<div class="empty-state">${d.message||'Erreur'}</div>`; return; }
                const tot = document.getElementById('historiqueTotal');
                if(tot) tot.textContent = d.total + ' consultation(s)';
                afficherTableauHistoriqueG(d.data);
                afficherPaginationHistG(d.page, d.last_page);
            })
            .catch(() => { if(content) content.innerHTML = '<div class="empty-state">Erreur réseau</div>'; });
    }

    function afficherTableauHistoriqueG(rows) {
        const c = document.getElementById('historiqueContent');
        if(!c) return;
        if(!rows || rows.length === 0) {
            c.innerHTML = '<div class="empty-state"><i class="fa-solid fa-inbox" style="font-size:2rem;margin-bottom:10px;"></i><br>Aucune consultation trouvée.</div>';
            return;
        }
        const bcMap = { traite:'badge-green', absent:'badge-red', annule:'badge-red', en_cours:'badge-blue', en_attente:'badge-grey', confirme:'badge-grey' };
        const lbMap = { traite:'Traité', absent:'Absent', annule:'Annulé', en_cours:'En cours', en_attente:'En attente', confirme:'Confirmé' };
        const modeMap = { LIGNE:'<span class="badge badge-blue" style="font-size:.65rem;">En ligne</span>', PLACE:'<span class="badge badge-grey" style="font-size:.65rem;">Sur place</span>', MANUEL:'<span class="badge badge-grey" style="font-size:.65rem;">Manuel</span>', QR_CODE:'<span class="badge badge-grey" style="font-size:.65rem;">QR Code</span>' };
        let h = '<div style="overflow-x:auto;"><table class="file-table"><thead><tr><th>Date</th><th>Patient</th><th>Médecin</th><th>Téléphone</th><th>Heure prévue</th><th>Début</th><th>Fin</th><th>Durée</th><th>Statut</th><th>Mode</th></tr></thead><tbody>';
        for(const v of rows) {
            h += `<tr>
                <td data-label="Date" style="white-space:nowrap;">${escapeHtml(v.date_consultation)}</td>
                <td data-label="Patient"><strong>${escapeHtml(v.patient_nom)} ${escapeHtml(v.patient_prenom)}</strong></td>
                <td data-label="Médecin">${v.medecin_nom ? escapeHtml(v.medecin_nom) : '—'}</td>
                <td data-label="Téléphone">${escapeHtml(v.telephone)}</td>
                <td data-label="Heure prévue">${escapeHtml(v.heure_estimee_fmt)}</td>
                <td data-label="Début">${escapeHtml(v.heure_debut_fmt)}</td>
                <td data-label="Fin">${escapeHtml(v.heure_fin_fmt)}</td>
                <td data-label="Durée">${escapeHtml(v.duree_fmt)}</td>
                <td data-label="Statut"><span class="badge ${bcMap[v.statut]||'badge-grey'}">${lbMap[v.statut]||v.statut}</span></td>
                <td data-label="Mode">${modeMap[v.mode_prise]||v.mode_prise}</td>
            </tr>`;
        }
        h += '</tbody></table></div>';
        c.innerHTML = h;
    }

    function afficherPaginationHistG(page, lastPage) {
        const p = document.getElementById('historiquePagination');
        if(!p || lastPage <= 1) return;
        const btnS = 'padding:6px 12px;border-radius:8px;border:1.5px solid #e2e8f0;background:white;cursor:pointer;font-size:.8rem;font-family:inherit;';
        const actS = 'padding:6px 12px;border-radius:8px;border:1.5px solid #1a4db5;background:#1a4db5;color:white;cursor:pointer;font-size:.8rem;font-family:inherit;font-weight:700;';
        let h = '';
        h += `<button style="${btnS}" onclick="chargerHistoriqueG(1)" ${page<=1?'disabled':''}><i class="fa-solid fa-angles-left"></i></button>`;
        h += `<button style="${btnS}" onclick="chargerHistoriqueG(${page-1})" ${page<=1?'disabled':''}><i class="fa-solid fa-angle-left"></i></button>`;
        const from = Math.max(1, page - 2), to = Math.min(lastPage, page + 2);
        if(from > 1) h += `<span style="padding:6px 4px;font-size:.8rem;color:#94a3b8;">…</span>`;
        for(let i=from; i<=to; i++) h += `<button style="${i===page?actS:btnS}" onclick="chargerHistoriqueG(${i})">${i}</button>`;
        if(to < lastPage) h += `<span style="padding:6px 4px;font-size:.8rem;color:#94a3b8;">…</span>`;
        h += `<button style="${btnS}" onclick="chargerHistoriqueG(${page+1})" ${page>=lastPage?'disabled':''}><i class="fa-solid fa-angle-right"></i></button>`;
        h += `<button style="${btnS}" onclick="chargerHistoriqueG(${lastPage})" ${page>=lastPage?'disabled':''}><i class="fa-solid fa-angles-right"></i></button>`;
        p.innerHTML = h;
    }

    function reinitFiltresHistG() {
        const s = document.getElementById('histStatut'), dd = document.getElementById('histDateDebut'), df = document.getElementById('histDateFin');
        if(s) s.value=''; if(dd) dd.value=''; if(df) df.value='';
        chargerHistoriqueG(1);
    }

    // ── Export PDF / Impression de l'historique ──
    function _getHistoriqueParams() {
        const statut    = document.getElementById('histStatut')?.value || '';
        const dateDebut = document.getElementById('histDateDebut')?.value || '';
        const dateFin   = document.getElementById('histDateFin')?.value || '';
        return { statut, dateDebut, dateFin };
    }

    async function _fetchTousHistorique() {
        const { statut, dateDebut, dateFin } = _getHistoriqueParams();
        // Charger TOUTES les pages (jusqu'à 1000 lignes max)
        const params = new URLSearchParams({ page: 1, per_page: 1000, statut, date_debut: dateDebut, date_fin: dateFin });
        const r = await fetch('gestionnaire.php?action=get_historique&' + params.toString());
        const d = await r.json();
        return d.success ? d.data : [];
    }

    function _buildPrintHTML(rows, titre) {
        const { statut, dateDebut, dateFin } = _getHistoriqueParams();
        const statMap = { traite:'Traité', absent:'Absent', annule:'Annulé', en_cours:'En cours', en_attente:'En attente', confirme:'Confirmé' };
        const modeMap = { LIGNE:'En ligne', PLACE:'Sur place', MANUEL:'Manuel', QR_CODE:'QR Code' };
        const filtre = [
            statut ? `Statut : ${statMap[statut]||statut}` : '',
            dateDebut ? `Du : ${dateDebut}` : '',
            dateFin ? `Au : ${dateFin}` : ''
        ].filter(Boolean).join(' | ');
        let rows_html = rows.map(v => `<tr>
            <td>${escapeHtml(v.date_consultation)}</td>
            <td><strong>${escapeHtml(v.patient_nom)} ${escapeHtml(v.patient_prenom)}</strong></td>
            <td>${v.medecin_nom ? escapeHtml(v.medecin_nom) : '—'}</td>
            <td>${escapeHtml(v.telephone)}</td>
            <td>${escapeHtml(v.heure_estimee_fmt)}</td>
            <td>${escapeHtml(v.heure_debut_fmt)}</td>
            <td>${escapeHtml(v.heure_fin_fmt)}</td>
            <td>${escapeHtml(v.duree_fmt)}</td>
            <td>${statMap[v.statut]||v.statut}</td>
            <td>${modeMap[v.mode_prise]||v.mode_prise}</td>
        </tr>`).join('');
        return `<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
        <title>${titre}</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; margin: 20px; }
            h1 { color: #0a2b5e; font-size: 18px; margin-bottom: 4px; }
            .subtitle { color: #64748b; font-size: 12px; margin-bottom: 8px; }
            .filtre { background: #f0f4f8; padding: 6px 10px; border-radius: 6px; font-size: 11px; color: #334155; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th { background: #0a2b5e; color: white; padding: 7px 6px; text-align: left; font-size: 10px; }
            td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
            tr:nth-child(even) td { background: #f8fafc; }
            .total { margin-top: 10px; font-size: 11px; color: #64748b; }
            @media print { body { margin: 10px; } }
        </style></head><body>
        <h1><i>QueueCare</i> — ${titre}</h1>
        <div class="subtitle"><?= htmlspecialchars($sousService['service_nom'] ?? '') ?> — <?= htmlspecialchars($sousService['nom'] ?? '') ?></div>
        ${filtre ? `<div class="filtre">Filtres : ${filtre}</div>` : ''}
        <table>
            <thead><tr>
                <th>Date</th><th>Patient</th><th>Médecin</th><th>Téléphone</th>
                <th>H. prévue</th><th>Début</th><th>Fin</th><th>Durée</th><th>Statut</th><th>Mode</th>
            </tr></thead>
            <tbody>${rows_html}</tbody>
        </table>
        <div class="total">Total : ${rows.length} consultation(s) — Généré le ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</div>
        </body></html>`;
    }

    async function exporterHistoriquePDF() {
        try {
            const rows = await _fetchTousHistorique();
            if(!rows.length){ alert('Aucune consultation à exporter.'); return; }

            const { statut, dateDebut, dateFin } = _getHistoriqueParams();
            const statMap = { traite:'Traité', absent:'Absent', annule:'Annulé', en_cours:'En cours', en_attente:'En attente', confirme:'Confirmé' };
            const modeMap = { LIGNE:'En ligne', PLACE:'Sur place', MANUEL:'Manuel', QR_CODE:'QR Code' };
            const filtre = [
                statut ? `Statut : ${statMap[statut]||statut}` : '',
                dateDebut ? `Du : ${dateDebut}` : '',
                dateFin ? `Au : ${dateFin}` : ''
            ].filter(Boolean).join('  |  ');

            const { jsPDF } = window.jspdf;
            const doc = new jsPDF({ unit: 'pt', format: 'a4', orientation: 'landscape' });
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            const marginX = 30;
            let y = 40;

            const navy = [10, 43, 94];
            const gray = [100, 116, 139];
            const lightGray = [248, 250, 252];
            const border = [226, 232, 240];

            const headers = ['Date','Patient','Médecin','Téléphone','H. prévue','Début','Fin','Durée','Statut','Mode'];
            const colWeights = [0.09, 0.16, 0.13, 0.11, 0.08, 0.08, 0.08, 0.08, 0.10, 0.09];
            const tableWidth = pageWidth - marginX * 2;
            const colW = colWeights.map(w => w * tableWidth);
            const rowH = 18;
            const headerH = 22;

            function drawHeaderBand() {
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(15);
                doc.setTextColor(navy[0], navy[1], navy[2]);
                doc.text('QueueCare — Historique des consultations', marginX, y);
                y += 16;
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(8.5);
                doc.setTextColor(gray[0], gray[1], gray[2]);
                doc.text(`<?= htmlspecialchars(addslashes($sousService['service_nom'] ?? '')) ?> — <?= htmlspecialchars(addslashes($sousService['nom'] ?? '')) ?>`, marginX, y);
                y += 14;
                if (filtre) {
                    doc.setFillColor(lightGray[0], lightGray[1], lightGray[2]);
                    doc.roundedRect(marginX, y - 9, tableWidth, 16, 3, 3, 'F');
                    doc.setFontSize(8);
                    doc.setTextColor(51, 65, 85);
                    doc.text('Filtres : ' + filtre, marginX + 8, y + 2);
                    y += 18;
                }
                y += 6;
            }

            function drawTableHeader() {
                doc.setFillColor(navy[0], navy[1], navy[2]);
                doc.rect(marginX, y, tableWidth, headerH, 'F');
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(8);
                doc.setTextColor(255, 255, 255);
                let x = marginX;
                headers.forEach((h, i) => {
                    doc.text(h, x + 6, y + 14);
                    x += colW[i];
                });
                y += headerH;
            }

            function checkPageBreak() {
                if (y + rowH > pageHeight - 36) {
                    doc.addPage();
                    y = 40;
                    drawTableHeader();
                }
            }

            drawHeaderBand();
            drawTableHeader();

            doc.setFont('helvetica', 'normal');
            rows.forEach((v, ri) => {
                checkPageBreak();
                if (ri % 2 === 0) {
                    doc.setFillColor(lightGray[0], lightGray[1], lightGray[2]);
                    doc.rect(marginX, y, tableWidth, rowH, 'F');
                }
                doc.setFontSize(7.8);
                doc.setTextColor(30, 41, 59);
                const cells = [
                    v.date_consultation || '—',
                    `${v.patient_nom || ''} ${v.patient_prenom || ''}`.trim() || '—',
                    v.medecin_nom || '—',
                    v.telephone || '—',
                    v.heure_estimee_fmt || '—',
                    v.heure_debut_fmt || '—',
                    v.heure_fin_fmt || '—',
                    v.duree_fmt || '—',
                    statMap[v.statut] || v.statut || '—',
                    modeMap[v.mode_prise] || v.mode_prise || '—'
                ];
                let x = marginX;
                cells.forEach((cell, i) => {
                    const maxChars = Math.floor(colW[i] / 4.2);
                    const text = String(cell).length > maxChars ? String(cell).slice(0, maxChars - 1) + '…' : String(cell);
                    doc.text(text, x + 6, y + 12);
                    x += colW[i];
                });
                y += rowH;
            });

            y += 14;
            checkPageBreak();
            doc.setFontSize(8.5);
            doc.setTextColor(gray[0], gray[1], gray[2]);
            doc.text(`Total : ${rows.length} consultation(s) — Généré le ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}`, marginX, y);

            const pageCount = doc.internal.getNumberOfPages();
            for (let p = 1; p <= pageCount; p++) {
                doc.setPage(p);
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(7.5);
                doc.setTextColor(148, 163, 184);
                doc.text(`Page ${p}/${pageCount}`, pageWidth - marginX, pageHeight - 16, { align: 'right' });
            }

            const nomFichier = `historique_consultations_${new Date().toISOString().slice(0,10)}.pdf`;
            doc.save(nomFichier);
        } catch(e) { alert('Erreur lors de l\'export : ' + e.message); }
    }

    async function imprimerHistorique() {
        try {
            const rows = await _fetchTousHistorique();
            if(!rows.length){ alert('Aucune consultation à imprimer.'); return; }
            const html = _buildPrintHTML(rows, 'Historique des consultations');
            const w = window.open('', '_blank');
            if(w) {
                w.document.write(html);
                w.document.close();
                w.addEventListener('load', () => setTimeout(() => w.print(), 400));
            }
        } catch(e) { alert('Erreur lors de l\'impression : ' + e.message); }
    }

    // ── Pagination file d'attente (côté client) ──
    let _filePageG = 1;
    const _PER_PAGE_G = 10;

    function toggleSidebar(){
        const sb=document.getElementById('sidebar'), ov=document.getElementById('sidebarOverlay'), hb=document.getElementById('hamburgerBtn');
        if(!sb) return;
        sb.classList.toggle('open');
        if(ov) ov.classList.toggle('active');
        if(hb) hb.classList.toggle('open');
    }

    // Fermer sidebar au clic sur un item nav (mobile)
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

    function ouvrirModalChoix(){ const m=document.getElementById('modalChoix'); if(m) m.classList.add('active'); }
    function fermerModalChoix(){ const m=document.getElementById('modalChoix'); if(m) m.classList.remove('active'); }
    function fermerModalConsultation(){ const m=document.getElementById('modalConsultation'); if(m) m.classList.remove('active'); const f=document.getElementById('formConsultManuelle'); if(f) f.reset(); }
    function choisirMode(mode){ fermerModalChoix(); if(mode==='manuel'){ const f=document.getElementById('formConsultManuelle'); if(f) f.reset(); const m=document.getElementById('modalConsultation'); if(m) m.classList.add('active'); } else if(mode==='qr') ouvrirModalQR(); }
    
    function ouvrirModalQR(){ const m=document.getElementById('modalQR'); if(m) m.classList.add('active'); chargerQRCode(); }
    function fermerModalQR(){ const m=document.getElementById('modalQR'); if(m) m.classList.remove('active'); if(qrTimerInterval) clearInterval(qrTimerInterval); }
    function chargerQRCode(){ showQRLoading(true); fetch('gestionnaire.php?action=get_qrcode_actif').then(r=>r.json()).then(d=>{if(d.redirect){showQRMessage(d.message||'Session expirée.','error');return;} if(d.success && d.qr_code_path) afficherQRCode(d.qr_code_path, d.expire_at); else genererNouveauQRCode();}).catch(()=>{showQRMessage('Erreur réseau.','error');}); }
    function genererNouveauQRCode(){ showQRLoading(true); const fd=new FormData(); fd.append('sous_service_id','<?= $sousService['id'] ?? 0 ?>'); fetch('gestionnaire.php?action=generer_qrcode',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.redirect){showQRMessage(d.message||'Session expirée.','error');return;} if(d.success) afficherQRCode(d.qr_code_path,d.expire_at); else {showQRMessage(d.error||d.message||'Erreur lors de la génération.','error'); showQRLoading(false);}}).catch(()=>{showQRMessage('Erreur réseau. Veuillez réessayer.','error');showQRLoading(false);}); }
    function regenererQRCode(){ if(confirm('Générer un nouveau QR code ?')) genererNouveauQRCode(); }
    function afficherQRCode(path,expireAt){
        // Normaliser le chemin : s'assurer qu'il pointe vers public/qrcodes/
        let imgSrc = path;
        if (imgSrc && !imgSrc.startsWith('public/') && !imgSrc.startsWith('http') && !imgSrc.startsWith('/')) {
            if (imgSrc.startsWith('qrcodes/')) {
                imgSrc = 'public/' + imgSrc;
            } else {
                imgSrc = 'public/qrcodes/' + imgSrc.replace(/^.*[\\/]/, '');
            }
        }
        const qi=document.getElementById('qrCodeImage');
        if(qi){ qi.src=imgSrc; qi.onerror=function(){ this.onerror=null; this.src='public/qrcodes/'+path.replace(/^.*[\\/]/,''); }; }
        currentQRPath=path; currentExpireAt=new Date(expireAt);
        showQRLoading(false);
        const qc=document.getElementById('qrContentState'); if(qc) qc.classList.remove('hidden');
        demarrerTimerQR();
        if(autoRegenerateInterval) clearInterval(autoRegenerateInterval);
        autoRegenerateInterval=setInterval(genererNouveauQRCode,20*60*1000);
    }
    function demarrerTimerQR(){ if(qrTimerInterval) clearInterval(qrTimerInterval); qrTimerInterval=setInterval(()=>{if(!currentExpireAt) return; const diff=currentExpireAt-new Date(), te=document.getElementById('qrTimer'); if(diff<=0){clearInterval(qrTimerInterval);if(te)te.textContent='Expiré';genererNouveauQRCode();}else{const m=Math.floor(diff/60000),s=Math.floor((diff%60000)/1000);if(te)te.textContent=`${m.toString().padStart(2,'0')}:${s.toString().padStart(2,'0')}`;}},1000); }
    function telechargerQRCode(){ if(currentQRPath) window.location.href='gestionnaire.php?action=telecharger_qrcode&path='+encodeURIComponent(currentQRPath); }
    function imprimerQRCode(){ if(currentQRPath){ const w=window.open('','_blank'); if(w){ w.document.write(`<html><head><title>QR Code</title><style>body{text-align:center;padding:50px;}img{width:250px;}</style></head><body><img src="${currentQRPath}"><h2>QueueCare</h2><p><?= htmlspecialchars($sousService['service_nom']??'') ?> - <?= htmlspecialchars($sousService['nom']??'') ?></p><script>window.print();<\/script></body></html>`); w.document.close(); } } }
    function showQRLoading(show){ const l=document.getElementById('qrLoadingState'), c=document.getElementById('qrContentState'); if(show){if(l)l.classList.remove('hidden');if(c)c.classList.add('hidden');}else if(l)l.classList.add('hidden'); }
    function showQRMessage(msg,type){ const d=document.getElementById('qrMessage'); if(!d) return; const qc=document.getElementById('qrContentState'); if(qc) qc.classList.remove('hidden'); const img=document.getElementById('qrCodeImage'); if(img) img.style.display='none'; d.textContent=msg; d.classList.remove('hidden','qr-success','qr-error'); d.classList.add(type==='success'?'qr-success':'qr-error'); setTimeout(()=>{if(d)d.classList.add('hidden');},3000); }
    
    // Emploi du temps
    function getLundiSemaine(offset=0){ const t=new Date(), dow=t.getDay(), diff=dow===0?-6:-(dow-1), l=new Date(t); l.setDate(t.getDate()+diff+(offset*7)); return l; }
    function formatDateISO(d){ return d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0'); }
    function formatDateFr(d){ return d.toLocaleDateString('fr-FR',{day:'2-digit',month:'2-digit',year:'numeric'}); }
    function getNomJour(d){ return d.toLocaleDateString('fr-FR',{weekday:'long'}); }
    function changerSemaine(delta){ if(delta===0) semaineOffset=0; else semaineOffset+=delta; chargerEmploiTemps(); }
    function chargerEmploiTemps(){
        const l=getLundiSemaine(semaineOffset), dd=formatDateISO(l), dim=new Date(l);
        dim.setDate(l.getDate()+6);
        const sl=document.getElementById('semaineLabel');
        if(sl) sl.innerHTML=`${formatDateFr(l)} - ${formatDateFr(dim)}`;
        const pl=document.getElementById('planningLoading'), pt=document.getElementById('planningTable');
        if(pl) pl.style.display='block';
        if(pt) pt.style.display='none';
        const selMed=document.getElementById('selectMedecin');
        const medecinId=selMed?selMed.value:'';
        const url=`gestionnaire.php?action=get_emploi_temps&date=${dd}`+(medecinId?`&medecin_id=${medecinId}`:'');
        fetch(url).then(r=>r.json()).then(d=>{
            if(pl) pl.style.display='none';
            if(d.error){if(pt){pt.innerHTML=`<div class="empty-state">${escapeHtml(d.error)}</div>`;pt.style.display='block';}return;}
            afficherEmploiTempsResponsive(d, medecinId?parseInt(medecinId):null);
        }).catch(()=>{if(pl)pl.style.display='none';if(pt){pt.innerHTML='<div class="empty-state">Erreur de chargement</div>';pt.style.display='block';}});
    }
    function afficherEmploiTempsResponsive(data, filtreMedecinId=null){
        const pt=document.getElementById('planningTable');
        const banner=document.getElementById('planningMedecinBanner');

        const sh = data.service_horaires || {};
        const ouv  = sh.horaires_ouverture ? sh.horaires_ouverture.substring(0,5) : '08:00';
        const fer  = sh.horaires_fermeture ? sh.horaires_fermeture.substring(0,5) : '18:00';
        const pauD = sh.pause_debut ? sh.pause_debut.substring(0,5) : null;
        const pauF = sh.pause_fin  ? sh.pause_fin.substring(0,5)  : null;
        const joursTravail = data.jours_travail || {};

        if(!data.medecins||data.medecins.length===0){
            if(pt){pt.innerHTML='<div class="empty-state"><i class="fa-solid fa-user-slash" style="font-size:2rem;color:#cbd5e1;"></i><p>Aucun médecin disponible</p></div>';pt.style.display='block';}
            if(banner) banner.style.display='none';
            return;
        }

        const medecinsAffiches = filtreMedecinId ? data.medecins.filter(m=>m.id==filtreMedecinId) : data.medecins;
        if(medecinsAffiches.length===0){
            if(pt){pt.innerHTML='<div class="empty-state">Médecin introuvable</div>';pt.style.display='block';}
            if(banner) banner.style.display='none';
            return;
        }

        // Bannière médecin sélectionné
        if(banner){
            if(filtreMedecinId && medecinsAffiches[0]){
                const med=medecinsAffiches[0];
                const initiale=(med.prenom||'M')[0].toUpperCase();
                banner.innerHTML=`<div class="planning-medecin-banner"><div class="planning-medecin-avatar">${escapeHtml(initiale)}</div><div><div class="planning-medecin-name">Dr. ${escapeHtml(med.prenom||'')} ${escapeHtml(med.nom||'')}</div><div class="planning-medecin-sub">${med.specialite?escapeHtml(med.specialite):'Médecin'} · Planning de la semaine</div></div></div>`;
                banner.style.display='block';
            } else { banner.style.display='none'; }
        }

        function toMin(hm){ const [h,m]=(hm||'00:00').split(':').map(Number); return h*60+m; }
        function fromMin(tot){ return String(Math.floor(tot/60)).padStart(2,'0')+':'+String(tot%60).padStart(2,'0'); }
        const ouvMin=toMin(ouv), ferMin=toMin(fer);
        const creneaux=[];
        for(let m=ouvMin;m<ferMin;m+=60) creneaux.push(fromMin(m));

        function estPause(hm){ if(!pauD||!pauF) return false; return hm>=pauD&&hm<pauF; }
        function getNumJour(d){ let n=d.getDay(); return n===0?7:n; }
        function medecinTravailleJour(medId, numJ){
            const jt=joursTravail[medId];
            if(!jt||jt.length===0) return true;
            return jt.includes(numJ)||jt.includes(String(numJ));
        }

        // Index consultations par date + créneau horaire arrondi à l'heure
        const consIndex={};
        (data.consultations||[]).forEach(c=>{
            if(filtreMedecinId && c.medecin_id!=filtreMedecinId) return;
            const [hh] = (c.heure_debut||'00:00').split(':');
            const hArrondie = hh.padStart(2,'0') + ':00';
            const key = c.date_consultation + '|' + hArrondie;
            if(!consIndex[key]) consIndex[key]=[];
            consIndex[key].push(c);
        });

        const l=getLundiSemaine(semaineOffset), jours=[];
        for(let i=0;i<7;i++){const j=new Date(l);j.setDate(l.getDate()+i);jours.push(j);}
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
            // Un jour est "off" si TOUS les médecins affichés ne travaillent pas
            const allOff = medecinsAffiches.every(m => !medecinTravailleJour(m.id, numJ));
            const estFerme = (sh.jours_fermeture||'').includes(String(numJ));
            const estAjd = dateStr===today;

            const jourNom = getNomJour(jour).substring(0,3).toUpperCase();
            const jourNum = jour.getDate();
            const moisStr = dateStr.substring(5).replace('-','/');

            let hColClass='gcal-header-cell'+(estAjd?' today-col':((allOff||estFerme)?' off-col':''));
            let numHtml=estAjd
                ? `<span class="gcal-day-num today-num">${jourNum}</span>`
                : `<span class="gcal-day-num">${jourNum}</span>`;

            html+=`<div class="gcal-day-col">`;
            html+=`<div class="${hColClass}"><span class="gcal-day-name">${jourNom}</span>${numHtml}<small style="font-size:.6rem;color:#94a3b8;">${moisStr}</small></div>`;

            creneaux.forEach(heure=>{
                const isPause = estPause(heure);
                let slotClass='gcal-slot';
                if(estFerme || allOff) slotClass+=' off-slot';
                else if(isPause) slotClass+=' pause-slot';

                html+=`<div class="${slotClass}">`;

                if(estFerme || allOff){
                    html+=`<div class="gcal-slot-empty" style="font-size:.62rem;color:#cbd5e1;"><i class="fa-solid fa-moon"></i></div>`;
                } else if(isPause){
                    html+=`<div class="gcal-event ev-pause" style="justify-content:center;"><span class="gcal-event-time"><i class="fa-solid fa-utensils"></i> Pause</span></div>`;
                } else {
                    const key=dateStr+'|'+heure;
                    const cons=(consIndex[key]||[]);
                    if(cons.length>0){
                        // Si plusieurs consultations dans le même créneau → empilement compact
                        const wrap = cons.length>1 ? '<div class="gcal-multi">' : '';
                        const wrapEnd = cons.length>1 ? '</div>' : '';
                        html+=wrap;
                        cons.forEach(c=>{
                            const evCls=c.statut==='en_cours'?'ev-cours':(c.statut==='traite'?'ev-traite':(c.statut==='en_pause'?'ev-pause':'ev-attente'));
                            const badge=c.statut==='en_cours'?'En cours':(c.statut==='traite'?'Traité':(c.statut==='en_pause'?'En pause':'Prog.'));
                            const hDebut = c.heure_debut || heure;
                            const hFin   = c.heure_fin   || '';
                            const plage  = hFin ? `${hDebut}–${hFin}` : hDebut;
                            const medLabel = !filtreMedecinId && c.medecin_nom ? `<span style="font-size:.56rem;opacity:.7;">Dr. ${escapeHtml((c.medecin_nom||'').split(' ')[0])}</span>` : '';
                            html+=`<div class="gcal-event ${evCls}" title="${escapeHtml(c.patient_nom)} ${escapeHtml(c.patient_prenom)} — ${escapeHtml(c.medecin_nom||'')} | ${plage}">
                                <span class="gcal-event-time">${plage}</span>
                                <span class="gcal-event-name">${escapeHtml(c.patient_nom)} ${escapeHtml((c.patient_prenom||'')[0]||'')}.</span>
                                ${medLabel}
                                <span class="gcal-event-badge">${badge}</span>
                            </div>`;
                        });
                        html+=wrapEnd;
                    } else {
                        // Vérifier disponibilité plage horaire
                        let isDispo=false;
                        if(data.plages_horaire){
                            for(const med of medecinsAffiches){
                                const pl=data.plages_horaire[med.id]||[];
                                for(const pg of pl){ if(pg.jour===dateStr&&heure>=pg.heure_debut&&heure<pg.heure_fin){isDispo=true;break;} }
                                if(isDispo) break;
                            }
                        }
                        if(isDispo){
                            html+=`<div class="gcal-slot-empty" style="font-size:.58rem;color:#a78bfa;"><i class="fa-regular fa-circle-check"></i></div>`;
                        }
                    }
                }
                html+='</div>';
            });
            html+='</div>';
        });
        html+='</div></div>';

        if(pt){pt.innerHTML=html;pt.style.display='block';}
    }
    
    // Profil – lock screen
    let _profilPassword = '';
    function chargerProfil(){
        // Ne rien faire ici – le lock screen gère déjà l'affichage
    }
    function verifierMdpProfil(){
        const pwd = document.getElementById('profilLockPassword').value;
        if(!pwd) return;
        const fd = new FormData(); fd.append('password', pwd);
        fetch('gestionnaire.php?action=verifier_mdp',{method:'POST',body:fd})
            .then(r=>r.json()).then(d=>{
                if(d.success){
                    _profilPassword = pwd;
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
    function chargerProfilData(){ const c=document.getElementById('profilContent'); if(!c) return; c.innerHTML='<div class="empty-state">Chargement...</div>'; fetch('gestionnaire.php?action=get_profil_data').then(r=>r.json()).then(d=>{if(d.success)afficherFormulaireProfil(d.profil, _profilPassword);else c.innerHTML=`<div class="profil-error">${d.message||'Erreur'}</div>`;}).catch(()=>c.innerHTML='<div class="profil-error">Erreur</div>'); }
    function afficherFormulaireProfil(profil, mdpDeverrouillage){ const h=`<div class="profil-section"><form id="profilForm"><div class="form-group"><label><i class="fa-solid fa-user"></i> Nom complet</label><input type="text" name="nom" id="profil_nom" value="${escapeHtml(profil.nom)}" required></div><div class="form-group"><label><i class="fa-solid fa-phone"></i> Téléphone</label><input type="tel" name="telephone" id="profil_telephone" value="${escapeHtml(profil.telephone)}" required></div><div class="form-group"><label><i class="fa-solid fa-envelope"></i> Email</label><input type="email" name="email" id="profil_email" value="${escapeHtml(profil.email)}" required></div><div class="separator"></div><h3>Changer le mot de passe</h3><div class="form-group"><label><i class="fa-solid fa-lock"></i> Mot de passe actuel</label><input type="password" name="password_actuel" id="profil_password_actuel" placeholder="Mot de passe actuel"></div><div class="form-group"><label><i class="fa-solid fa-key"></i> Nouveau mot de passe</label><input type="password" name="nouveau_password" id="profil_nouveau_password" placeholder="Min 8 car., 1 maj., 1 chiffre"></div><div class="form-group"><label><i class="fa-solid fa-check"></i> Confirmer</label><input type="password" name="confirmer_password" id="profil_confirmer_password" placeholder="Répéter"></div><button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Enregistrer</button></form><div id="profilMessage"></div></div>`; document.getElementById('profilContent').innerHTML=h; if(mdpDeverrouillage) document.getElementById('profil_password_actuel').value = mdpDeverrouillage; const pf=document.getElementById('profilForm'); if(pf) pf.addEventListener('submit',function(e){e.preventDefault();enregistrerProfil();}); }
    function enregistrerProfil(){ const fd=new FormData(); fd.append('nom',document.getElementById('profil_nom').value); fd.append('telephone',document.getElementById('profil_telephone').value); fd.append('email',document.getElementById('profil_email').value); fd.append('password_actuel',document.getElementById('profil_password_actuel').value||_profilPassword); fd.append('nouveau_password',document.getElementById('profil_nouveau_password').value); fd.append('confirmer_password',document.getElementById('profil_confirmer_password').value); const btn=document.querySelector('#profilForm button[type="submit"]'), ot=btn?btn.innerHTML:''; if(btn){btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';btn.disabled=true;} fetch('gestionnaire.php?action=mettre_a_jour_profil',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(btn){btn.innerHTML=ot;btn.disabled=false;} const md=document.getElementById('profilMessage'); if(d.success){if(md)md.innerHTML=`<div class="profil-success">${d.message}</div>`;setTimeout(()=>window.location.reload(),1500);}else{let err='';if(d.errors)for(const[k,v]of Object.entries(d.errors))err+=`<div class="error-msg">- ${v}</div>`;else err=d.message||'Erreur';if(md)md.innerHTML=`<div class="profil-error">${err}</div>`;}}).catch(()=>{if(btn){btn.innerHTML=ot;btn.disabled=false;}const md=document.getElementById('profilMessage');if(md)md.innerHTML='<div class="profil-error">Erreur</div>';}); }

    // Search / filter functions
    let _fileData = <?= json_encode(array_map(function($c) {
        return [
            'id' => $c['id'],
            'rang' => $c['rang'],
            'statut' => $c['statut'],
            'mode_prise' => $c['mode_prise'],
            'patient_nom' => $c['patient_nom'],
            'patient_prenom' => $c['patient_prenom'],
            'telephone' => $c['telephone'],
            'medecin_id' => isset($c['medecin_id']) ? (int)$c['medecin_id'] : null,
            'medecin_nom' => $c['medecin_nom'] ?? null,
            'heure_passage_estimee' => isset($c['heure_passage_estimee']) && $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : null,
        ];
    }, $file)) ?>;
    let _consultationsData = <?= json_encode(array_map(function($c) {
        return [
            'id' => $c['id'],
            'rang' => $c['rang'],
            'statut' => $c['statut'],
            'patient_nom' => $c['patient_nom'],
            'patient_prenom' => $c['patient_prenom'],
            'telephone' => $c['telephone'],
            'medecin_nom' => $c['medecin_nom'] ?? null,
            'heure_passage_estimee' => isset($c['heure_passage_estimee']) && $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : null,
        ];
    }, $consultations)) ?>;
    let _fileMedecinFilter = 0; // 0 = tous les médecins
    function filtrerFileMedecin(mid, btn) {
        _fileMedecinFilter = mid;
        document.querySelectorAll('.btn-filtre-medecin').forEach(b => {
            b.style.background = '#fff'; b.style.color = '#0052a0'; b.style.borderColor = '#e2e8f0';
        });
        if (btn) { btn.style.background = '#0052a0'; btn.style.color = '#fff'; btn.style.borderColor = '#0052a0'; }
        _filePageG = 1;
        filtrerFile(document.getElementById('searchFile')?.value || '');
    }
    function filtrerFile(q){ const t=q.toLowerCase().trim(); let f = _fileData; if(_fileMedecinFilter > 0) f = f.filter(p => (p.medecin_id||0) == _fileMedecinFilter); if(t){ f = f.filter(p=>(p.patient_nom||'').toLowerCase().includes(t)||(p.patient_prenom||'').toLowerCase().includes(t)||(p.patient_telephone||'').toLowerCase().includes(t)); } afficherFile(f, t.length > 0 || _fileMedecinFilter > 0); }
    function filtrerConsultations(q){ const t=q.toLowerCase().trim(); if(!t){ afficherConsultations(_consultationsData); return; } const f=_consultationsData.filter(c=>(c.patient_nom||'').toLowerCase().includes(t)||(c.patient_prenom||'').toLowerCase().includes(t)||(c.medecin_nom||'').toLowerCase().includes(t)||(c.statut||'').toLowerCase().includes(t)); afficherConsultations(f, true); }
    function clearSearch(inputId, contentId, type){ document.getElementById(inputId).value=''; if(type==='file') filtrerFile(''); else filtrerConsultations(''); }
    
    function rechercherPatient(tel){ const cl=tel.replace(/[^0-9+]/g,''); if(cl.length<8) return; clearTimeout(searchTimer); searchTimer=setTimeout(async()=>{try{const r=await fetch('gestionnaire.php?action=dashboard&api=patient&tel='+encodeURIComponent(cl)); const p=await r.json(); if(p){const nf=document.getElementById('m_nom'), pf=document.getElementById('m_prenom'), ef=document.getElementById('m_email'); if(nf) nf.value=p.nom||''; if(pf) pf.value=p.prenom||''; if(ef) ef.value=p.email||''; }}catch(e){}},500); }
    
    let idleTimer, warnTimer;
    function resetIdleTimer(){ if(idleTimer) clearTimeout(idleTimer); if(warnTimer) clearTimeout(warnTimer); warnTimer=setTimeout(()=>{const b=document.getElementById('timeoutBanner');if(b)b.style.display='flex';},14*60*1000); idleTimer=setTimeout(()=>{window.location.href='gestionnaire.php?action=deconnexion&timeout=1';},15*60*1000); }
    
    window.onload = function(){ 
        resetIdleTimer(); 
        restaurerSectionActive();
    };
    document.onmousemove = resetIdleTimer; document.onkeydown = resetIdleTimer; document.onclick = resetIdleTimer; document.onscroll = resetIdleTimer;
    
    <?php if ($openQR): ?>
    setTimeout(ouvrirModalQR, 500);
    <?php endif; ?>
</script>

<style>
/* Pause / Reprise */
.btn-sm-resume { background:#10b981!important;color:#fff!important;border:none; }
.btn-sm-resume:hover { background:#059669!important; }
.badge-pause { background:#fef3c7;color:#92400e;border:1px solid #fde68a;font-weight:600; }
</style>

</body>
</html>