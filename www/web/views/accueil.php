<?php

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    require_once __DIR__ . '/helpers/AuthHelper.php';
    
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: admin.php');
            exit;
        case 'medecin':
            header('Location: medecin.php');
            exit;
        case 'gestionnaire':
            header('Location: gestionnaire.php');
            exit;
        default:
            break;
    }
}

$pageTitle  = "QueueCare - Gestion des files d'attente hospitalières";
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="QueueCare - Solution de gestion intelligente des files d'attente pour établissements de santé">
    <title><?= $pageTitle ?></title>

    <link href="https://fonts.bunny.net/css?family=playfair-display:400,600,700,800|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        :root {
            --blue-dark : #0a2b5e;
            --blue      : #1a4db5;
            --blue-light: #2563eb;
            --green     : #10b981;
            --amber     : #f59e0b;
            --text      : #1e293b;
            --muted     : #64748b;
            --border    : #e2e8f0;
            --bg        : #f8fafc;
            --white     : #ffffff;
            --radius    : 16px;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--white);
            color: var(--text);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ── Animations ──────────────────────────────────── */
        @keyframes fadeUp  { from { opacity:0; transform:translateY(28px); } to { opacity:1; transform:translateY(0); } }
        @keyframes floatY  { 0%,100% { transform:translateY(0); } 50% { transform:translateY(-14px); } }
        @keyframes gradBG  { 0%,100% { background-position:0% 50%; } 50% { background-position:100% 50%; } }

        .anim-fade-up { animation: fadeUp .8s ease both; }

        /* ── Nav ─────────────────────────────────────────── */
        .nav {
            position: absolute;
            top: 0; left: 0; right: 0;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 40px;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -.01em;
        }

        .nav-logo-icon {
            width: 36px; height: 36px;
            background: rgba(255,255,255,.2);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .9rem;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 18px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-size: .875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all .2s;
        }

        .nav-btn-ghost {
            background: rgba(255,255,255,.15);
            color: white;
            border: 1.5px solid rgba(255,255,255,.35);
        }
        .nav-btn-ghost:hover { background: rgba(255,255,255,.28); }

        .nav-btn-white {
            background: white;
            color: var(--blue);
        }
        .nav-btn-white:hover { opacity:.9; transform:translateY(-1px); }

        /* ── Hero ─────────────────────────────────────────── */
        .hero {
            background: linear-gradient(135deg, #0a2b5e 0%, #1a4db5 55%, #2563eb 100%);
            background-size: 200% 200%;
            animation: gradBG 18s ease infinite;
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            padding: 120px 20px 80px;
        }

        /* Wave décoratif bas du hero */
        .hero::after {
            content: '';
            position: absolute;
            bottom: -2px; left: 0; right: 0;
            height: 80px;
            background: var(--white);
            clip-path: ellipse(55% 100% at 50% 100%);
        }

        /* Cercles déco */
        .hero-deco {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
            pointer-events: none;
        }
        .hero-deco-1 { width:420px; height:420px; top:-120px; right:-100px; }
        .hero-deco-2 { width:280px; height:280px; bottom:40px; left:-80px; }

        .hero-inner {
            position: relative;
            z-index: 2;
            max-width: 680px;
            animation: fadeUp .9s ease both;
        }

        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,.18);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255,255,255,.3);
            padding: 6px 18px;
            border-radius: 50px;
            font-size: .8rem;
            font-weight: 500;
            margin-bottom: 22px;
            letter-spacing: .03em;
        }

        .hero-icon {
            font-size: 64px;
            margin-bottom: 16px;
            animation: floatY 3.5s ease-in-out infinite;
        }

        .hero h1 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.2rem, 6vw, 3.6rem);
            font-weight: 800;
            margin-bottom: 14px;
            line-height: 1.1;
        }

        .hero-sub {
            font-size: clamp(.95rem, 2.5vw, 1.15rem);
            opacity: .92;
            margin-bottom: 36px;
            line-height: 1.6;
        }

        /* ── Boutons hero ─────────────────────────────────── */
        .hero-btns {
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .hbtn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            padding: 13px 28px;
            border-radius: 50px;
            font-family: 'Outfit', sans-serif;
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: transform .2s, box-shadow .2s, opacity .2s;
        }
        .hbtn:hover { transform: translateY(-3px); }

        .hbtn-primary {
            background: white;
            color: var(--blue);
            box-shadow: 0 4px 18px rgba(0,0,0,.2);
        }
        .hbtn-primary:hover { box-shadow: 0 8px 28px rgba(0,0,0,.28); }

        .hbtn-outline {
            background: transparent;
            color: white;
            border: 2px solid rgba(255,255,255,.65);
        }
        .hbtn-outline:hover { background: rgba(255,255,255,.12); }

        .hbtn-register {
            background: var(--green);
            color: white;
            box-shadow: 0 4px 18px rgba(16,185,129,.35);
        }
        .hbtn-register:hover { opacity:.9; box-shadow: 0 8px 28px rgba(16,185,129,.45); }

        /* ── Sections communes ────────────────────────────── */
        .section { padding: 72px 20px; }
        .section-alt { background: var(--bg); }

        .container { max-width: 1100px; margin: 0 auto; }

        .sec-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .sec-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.6rem, 4vw, 2.3rem);
            color: var(--blue-dark);
            margin-bottom: 10px;
        }

        .sec-header p {
            font-size: 1rem;
            color: var(--muted);
            max-width: 520px;
            margin: 0 auto;
        }

        /* ── Features ─────────────────────────────────────── */
        .feat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .feat-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 24px;
            transition: transform .25s, box-shadow .25s;
        }
        .feat-card:hover { transform: translateY(-6px); box-shadow: 0 16px 36px rgba(0,0,0,.08); }

        .feat-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            background: #eff6ff;
            display: flex; align-items: center; justify-content: center;
            color: var(--blue);
            font-size: 1.3rem;
            margin-bottom: 16px;
        }

        .feat-card h3 { font-size: 1rem; font-weight: 600; margin-bottom: 7px; color: var(--blue-dark); }
        .feat-card p  { font-size: .875rem; color: var(--muted); line-height: 1.6; }

        /* ── Stats ────────────────────────────────────────── */
        .stats-band {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: white;
            padding: 52px 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 32px;
            text-align: center;
        }

        .stat-num {
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 800;
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label { font-size: .875rem; opacity: .85; }

        /* ── Inscription section ──────────────────────────── */
        .register-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e8f5f0 100%);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .register-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: center;
        }

        .register-text h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.5rem, 3.5vw, 2rem);
            color: var(--blue-dark);
            margin-bottom: 14px;
            line-height: 1.25;
        }

        .register-text p {
            color: var(--muted);
            font-size: .95rem;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .register-roles {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 28px;
        }

        .register-role-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .875rem;
            color: var(--text);
        }

        .register-role-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .8rem;
            flex-shrink: 0;
        }

        .role-admin { background: #fef3c7; color: var(--amber); }
        .role-medecin { background: #dbeafe; color: var(--blue); }
        .role-gestionnaire { background: #d1fae5; color: var(--green); }

        .register-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 36px 32px;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
        }

        .register-card-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #d1fae5;
            color: #065f46;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 4px 12px;
            border-radius: 50px;
            margin-bottom: 18px;
        }

        .register-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--blue-dark);
            margin-bottom: 8px;
        }

        .register-card > p {
            font-size: .875rem;
            color: var(--muted);
            margin-bottom: 24px;
            line-height: 1.6;
        }

        .register-card-steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }

        .reg-step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .reg-step-num {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--blue);
            color: white;
            font-size: .75rem;
            font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .reg-step-text strong { display: block; font-size: .875rem; color: var(--text); }
        .reg-step-text span   { font-size: .8rem; color: var(--muted); }

        .reg-cta-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .reg-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-family: 'Outfit', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all .2s;
        }

        .reg-btn-primary {
            background: var(--blue);
            color: white;
        }
        .reg-btn-primary:hover { background: var(--blue-dark); transform: translateY(-1px); }

        .reg-btn-secondary {
            background: var(--bg);
            color: var(--text);
            border: 1.5px solid var(--border);
        }
        .reg-btn-secondary:hover { background: var(--border); }

        .reg-note {
            font-size: .75rem;
            color: var(--muted);
            text-align: center;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        /* ── Espaces ──────────────────────────────────────── */
        .spaces-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }

        .space-card {
            background: var(--white);
            border: 1.5px solid var(--border);
            border-radius: var(--radius);
            padding: 28px 22px;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: transform .25s, box-shadow .25s, border-color .25s;
        }
        .space-card:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,.08); border-color: var(--blue); }

        .space-icon {
            width: 60px; height: 60px;
            border-radius: 50%;
            background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            font-size: 1.5rem;
        }

        .space-card h3 { font-size: 1rem; font-weight: 600; color: var(--blue-dark); margin-bottom: 8px; }
        .space-card p  { font-size: .85rem; color: var(--muted); line-height: 1.55; }

        .space-card.medecin .space-icon { color: var(--blue); }
        .space-card.gestionnaire .space-icon { color: var(--green); }
        .space-card.admin .space-icon { color: var(--amber); }

        /* ── CTA final ────────────────────────────────────── */
        .cta-section {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            color: white;
            text-align: center;
            padding: 64px 20px;
        }

        .cta-section h2 {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.4rem, 3.5vw, 2rem);
            margin-bottom: 12px;
        }

        .cta-section p { opacity: .88; margin-bottom: 28px; font-size: .95rem; }

        .cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

        /* ── Footer ───────────────────────────────────────── */
        .footer {
            background: #0f172a;
            color: #94a3b8;
            text-align: center;
            padding: 32px 20px;
            font-size: .85rem;
        }

        .footer-sep { margin: 8px 0; opacity: .3; }

        /* ── Grille 3 cartes inscription ─────────────────── */
        .register-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        @media (max-width: 900px) {
            .register-cards-grid { grid-template-columns: 1fr; }
        }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 768px) {
            .nav { padding: 16px 20px; }
            .nav-btn span { display: none; }

            .register-layout {
                grid-template-columns: 1fr;
                gap: 32px;
            }

            .register-card { padding: 28px 22px; }

            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 480px) {
            .hero-btns { flex-direction: column; align-items: center; }
            .hbtn { width: 100%; max-width: 300px; justify-content: center; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .cta-btns { flex-direction: column; align-items: center; }
            .hbtn { font-size: .875rem; }
            .nav-actions { gap: 6px; }
        }
    </style>
</head>
<body>


<!-- ── Hero ─────────────────────────────────────────────────── -->
<section class="hero">
    <!-- Déco -->
    <div class="hero-deco hero-deco-1"></div>
    <div class="hero-deco hero-deco-2"></div>

    <!-- Nav -->
    <nav class="nav">
        <a href="accueil.php" class="nav-logo">
            <div class="nav-logo-icon"><i class="fa-solid fa-hospital"></i></div>
            QueueCare
        </a>
        <div class="nav-actions">
            <a href="#inscription" class="nav-btn nav-btn-ghost">
                <i class="fa-solid fa-user-plus"></i>
                <span>S'inscrire</span>
            </a>
            <a href="index.php?action=login" class="nav-btn nav-btn-white">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>
                <span>Connexion</span>
            </a>
        </div>
    </nav>

    <div class="hero-inner">
        <div class="hero-pill">
            <i class="fa-solid fa-shield-halved"></i> Solution hospitalière certifiée
        </div>
        <div class="hero-icon">
            <i class="fa-solid fa-hospital-user"></i>
        </div>
        <h1>QueueCare</h1>
        <p class="hero-sub">
            La solution intelligente pour gérer les files d'attente<br>
            dans vos établissements de santé.
        </p>
        <div class="hero-btns">
            <a href="index.php?action=register_admin" class="hbtn hbtn-register">
                <i class="fa-solid fa-user-plus"></i> Inscrire mon établissement
            </a>
            <a href="index.php?action=login" class="hbtn hbtn-primary">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Se connecter
            </a>
            <a href="#comment-ca-marche" class="hbtn hbtn-outline">
                <i class="fa-solid fa-circle-play"></i> Comment ça marche
            </a>
        </div>
    </div>
</section>

<!-- ── Fonctionnalités ───────────────────────────────────────── -->
<section class="section section-alt">
    <div class="container">
        <div class="sec-header">
            <h2>Pourquoi choisir QueueCare ?</h2>
            <p>Une solution complète pour optimiser la gestion de votre établissement</p>
        </div>
        <div class="feat-grid">
            <div class="feat-card">
                <div class="feat-icon"><i class="fa-solid fa-clock"></i></div>
                <h3>Gain de temps</h3>
                <p>Réduisez l'attente des patients et optimisez le flux de consultations.</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon"><i class="fa-solid fa-chart-line"></i></div>
                <h3>Statistiques en temps réel</h3>
                <p>Suivez les performances et identifiez les axes d'amélioration.</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon"><i class="fa-solid fa-qrcode"></i></div>
                <h3>QR Code intelligent</h3>
                <p>Générez des QR codes pour une prise de ticket simplifiée.</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon"><i class="fa-solid fa-bell"></i></div>
                <h3>Notifications push</h3>
                <p>Tenez les patients informés en temps réel via l'app mobile.</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon"><i class="fa-solid fa-mobile-screen"></i></div>
                <h3>Application mobile</h3>
                <p>Une expérience fluide pour les patients sur Android.</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon"><i class="fa-solid fa-shield-alt"></i></div>
                <h3>Sécurisé</h3>
                <p>Données protégées et conformes aux normes en vigueur.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<div class="stats-band">
    <div class="container">
        <div class="stats-grid">
            <div>
                <div class="stat-num"><span class="counter" data-target="5000">0</span>+</div>
                <div class="stat-label">Consultations gérées</div>
            </div>
            <div>
                <div class="stat-num"><span class="counter" data-target="98">0</span>%</div>
                <div class="stat-label">Satisfaction patient</div>
            </div>
            <div>
                <div class="stat-num"><span class="counter" data-target="30">0</span> min</div>
                <div class="stat-label">Attente réduite en moyenne</div>
            </div>
            <div>
                <div class="stat-num">24/7</div>
                <div class="stat-label">Disponibilité</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Inscription ───────────────────────────────────────────── -->
<section class="section register-section" id="inscription">
    <div class="container">
        <div class="sec-header" style="margin-bottom:36px">
            <h2>Créez votre compte</h2>
            <p>Choisissez votre rôle et rejoignez QueueCare en quelques minutes</p>
        </div>

        <div class="register-cards-grid">

            <!-- Carte Médecin -->
            <div class="register-card">
                <div class="register-card-badge" style="background:#dbeafe;color:#1e40af">
                    <i class="fa-solid fa-user-doctor"></i> Médecin
                </div>
                <h3>Espace Médecin</h3>
                <p>Accédez à votre file d'attente, gérez vos consultations et suivez l'état de vos patients en temps réel.</p>
                <div class="register-card-steps">
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#2563eb">1</div>
                        <div class="reg-step-text">
                            <strong>Créer votre compte</strong>
                            <span>Renseignez vos informations médicales</span>
                        </div>
                    </div>
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#2563eb">2</div>
                        <div class="reg-step-text">
                            <strong>Rejoindre un établissement</strong>
                            <span>Le directeur valide votre accès</span>
                        </div>
                    </div>
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#2563eb">3</div>
                        <div class="reg-step-text">
                            <strong>Gérer votre file</strong>
                            <span>Appel, consultation et clôture</span>
                        </div>
                    </div>
                </div>
                <div class="reg-cta-group">
                    <a href="medecin.php?action=inscription" class="reg-btn reg-btn-primary" style="background:#2563eb">
                        <i class="fa-solid fa-user-doctor"></i>
                        S'inscrire comme médecin
                    </a>
                    <a href="index.php?action=login" class="reg-btn reg-btn-secondary">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Se connecter
                    </a>
                </div>
            </div>

            <!-- Carte Gestionnaire -->
            <div class="register-card">
                <div class="register-card-badge" style="background:#d1fae5;color:#065f46">
                    <i class="fa-solid fa-user-tie"></i> Gestionnaire
                </div>
                <h3>Espace Gestionnaire</h3>
                <p>Générez des tickets QR, organisez les flux de patients et coordonnez les services de l'établissement.</p>
                <div class="register-card-steps">
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#059669">1</div>
                        <div class="reg-step-text">
                            <strong>Créer votre compte</strong>
                            <span>Informations de contact et identité</span>
                        </div>
                    </div>
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#059669">2</div>
                        <div class="reg-step-text">
                            <strong>Rejoindre un établissement</strong>
                            <span>Le directeur valide votre accès</span>
                        </div>
                    </div>
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#059669">3</div>
                        <div class="reg-step-text">
                            <strong>Gérer les files d'attente</strong>
                            <span>Tickets, QR codes et statistiques</span>
                        </div>
                    </div>
                </div>
                <div class="reg-cta-group">
                    <a href="gestionnaire.php?action=inscription" class="reg-btn reg-btn-primary" style="background:#059669">
                        <i class="fa-solid fa-user-tie"></i>
                        S'inscrire comme gestionnaire
                    </a>
                    <a href="index.php?action=login" class="reg-btn reg-btn-secondary">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Se connecter
                    </a>
                </div>
            </div>

            <!-- Carte Directeur -->
            <div class="register-card" style="border-color:#fbbf24;box-shadow:0 4px 24px rgba(251,191,36,.15)">
                <div class="register-card-badge" style="background:#fef3c7;color:#92400e">
                    <i class="fa-solid fa-crown"></i> Directeur
                </div>
                <h3>Espace Directeur</h3>
                <p>Créez et administrez votre établissement. Ajoutez médecins et gestionnaires, configurez les services.</p>
                <div class="register-card-steps">
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#d97706">1</div>
                        <div class="reg-step-text">
                            <strong>Créer l'établissement</strong>
                            <span>Nom, type (hôpital, clinique…)</span>
                        </div>
                    </div>
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#d97706">2</div>
                        <div class="reg-step-text">
                            <strong>Configurer les services</strong>
                            <span>Spécialités et sous-services</span>
                        </div>
                    </div>
                    <div class="reg-step">
                        <div class="reg-step-num" style="background:#d97706">3</div>
                        <div class="reg-step-text">
                            <strong>Ajouter l'équipe</strong>
                            <span>Médecins & gestionnaires</span>
                        </div>
                    </div>
                </div>
                <div class="reg-cta-group">
                    <a href="index.php?action=register_admin" class="reg-btn reg-btn-primary" style="background:#d97706">
                        <i class="fa-solid fa-building-columns"></i>
                        Inscrire mon établissement
                    </a>
                    <a href="index.php?action=login" class="reg-btn reg-btn-secondary">
                        <i class="fa-solid fa-arrow-right-to-bracket"></i>
                        Se connecter
                    </a>
                </div>
                <p class="reg-note">
                    <i class="fa-solid fa-lock"></i>
                    Données sécurisées · Aucune carte requise
                </p>
            </div>

        </div>
    </div>
</section>

<!-- ── Comment ça marche ─────────────────────────────────────── -->
<section class="section section-alt" id="comment-ca-marche">
    <div class="container">
        <div class="sec-header">
            <h2>Comment ça marche ?</h2>
            <p>Un processus simple en 4 étapes</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:28px;margin-top:8px">
            <?php
            $steps = [
                ['icon'=>'fa-user-plus',      'num'=>1, 'titre'=>'Inscription',        'desc'=>"Le directeur crée son compte et configure l'établissement."],
                ['icon'=>'fa-sliders',         'num'=>2, 'titre'=>'Configuration',      'desc'=>"Il crée les sous-services et ajoute médecins & gestionnaires."],
                ['icon'=>'fa-qrcode',          'num'=>3, 'titre'=>'Génération QR',      'desc'=>"Le gestionnaire génère des QR codes pour les patients."],
                ['icon'=>'fa-bell',            'num'=>4, 'titre'=>'Suivi en temps réel','desc'=>"Les consultations sont suivies et notifiées via l'app."],
            ];
            foreach ($steps as $s): ?>
            <div style="text-align:center">
                <div style="width:52px;height:52px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:white;font-size:1.1rem">
                    <i class="fa-solid <?= $s['icon'] ?>"></i>
                </div>
                <h4 style="font-size:1rem;font-weight:600;color:var(--blue-dark);margin-bottom:7px"><?= $s['titre'] ?></h4>
                <p style="font-size:.86rem;color:var(--muted);line-height:1.6"><?= $s['desc'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Espaces ───────────────────────────────────────────────── -->
<section class="section">
    <div class="container">
        <div class="sec-header">
            <h2>Trois espaces, une solution</h2>
            <p>Des interfaces adaptées à chaque profil utilisateur</p>
        </div>
        <div class="spaces-grid">
            <a href="index.php?action=login" class="space-card medecin">
                <div class="space-icon"><i class="fa-solid fa-user-md"></i></div>
                <h3>Espace Médecin</h3>
                <p>Consultez votre planning, gérez vos consultations et suivez vos statistiques.</p>
            </a>
            <a href="index.php?action=login" class="space-card gestionnaire">
                <div class="space-icon"><i class="fa-solid fa-chart-simple"></i></div>
                <h3>Espace Gestionnaire</h3>
                <p>Gérez la file d'attente, générez des QR codes et consultez les statistiques.</p>
            </a>
            <a href="index.php?action=login" class="space-card admin">
                <div class="space-icon"><i class="fa-solid fa-crown"></i></div>
                <h3>Espace Directeur</h3>
                <p>Configurez les sous-services, horaires et gérez les accès utilisateurs.</p>
            </a>
        </div>
    </div>
</section>

<!-- ── CTA ───────────────────────────────────────────────────── -->
<section class="cta-section">
    <div class="container">
        <h2>Prêt à optimiser votre établissement ?</h2>
        <p>Rejoignez les établissements qui font confiance à QueueCare</p>
        <div class="cta-btns">
            <a href="index.php?action=register_admin" class="hbtn hbtn-primary" style="background:white;color:var(--blue)">
                <i class="fa-solid fa-user-plus"></i> Inscrire mon établissement
            </a>
            <a href="index.php?action=login" class="hbtn hbtn-outline">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Se connecter
            </a>
        </div>
    </div>
</section>

<!-- ── Footer ────────────────────────────────────────────────── -->
<footer class="footer">
    <div class="container">
        <p>&copy; <?= $currentYear ?> QueueCare · Tous droits réservés</p>
        <hr class="footer-sep">
        <p><i class="fa-solid fa-heart" style="color:#ef4444"></i> Solution de gestion des files d'attente hospitalières</p>
    </div>
</footer>

<script>
/* ── Compteurs animés ─────────────────────────────────────── */
function runCounters() {
    document.querySelectorAll('.counter').forEach(el => {
        const target = +el.dataset.target;
        let val = 0;
        const step = target / 55;
        const tick = () => {
            val += step;
            if (val < target) { el.textContent = Math.floor(val); requestAnimationFrame(tick); }
            else               { el.textContent = target; }
        };
        tick();
    });
}

const statsBand = document.querySelector('.stats-band');
if (statsBand) {
    new IntersectionObserver(entries => {
        if (entries[0].isIntersecting) { runCounters(); }
    }, { threshold: .5 }).observe(statsBand);
}

/* ── Scroll doux pour ancres ──────────────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});


/* ── Fade-in au scroll ────────────────────────────────────── */
const observer = new IntersectionObserver(entries => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.style.opacity = '1';
            e.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: .1 });

document.querySelectorAll('.feat-card, .space-card').forEach(el => {
    el.style.cssText += 'opacity:0;transform:translateY(20px);transition:opacity .55s ease,transform .55s ease';
    observer.observe(el);
});
</script>
</body>
</html>