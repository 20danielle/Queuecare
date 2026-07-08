<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QueueCare — Présentation Jury</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#0a2b5e;
               min-height:100vh; color:#e2e8f0; }

        .hero { background:linear-gradient(135deg,#0a2b5e,#1a4db5 50%,#0a5c36);
                padding:60px 20px 50px; text-align:center; }
        .logo { font-size:56px; margin-bottom:16px; }
        .hero h1 { font-size:clamp(28px,5vw,52px); font-weight:800; color:#fff; margin-bottom:10px; }
        .hero p  { color:#93c5fd; font-size:16px; max-width:540px; margin:0 auto 24px; line-height:1.6; }

        .badge { display:inline-block; background:rgba(255,255,255,0.1);
                 border:1px solid rgba(255,255,255,0.2); color:#bfdbfe;
                 padding:4px 14px; border-radius:20px; font-size:13px; margin:3px; }

        .section { max-width:880px; margin:0 auto; padding:50px 20px; }
        .section-title { font-size:12px; text-transform:uppercase; letter-spacing:2px;
                         color:#60a5fa; text-align:center; margin-bottom:28px; }

        .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:20px; }

        .card { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                border-radius:16px; padding:28px; text-decoration:none; color:inherit;
                transition:transform .2s,border-color .2s; display:flex; flex-direction:column; gap:12px; }
        .card:hover { transform:translateY(-4px); border-color:var(--c); }

        .card.web  { --c:#60a5fa; }
        .card.apk  { --c:#34d399; }
        .card.api  { --c:#c084fc; }

        .icon { font-size:36px; }
        .card h3 { font-size:18px; font-weight:700; color:#fff; }
        .card p  { font-size:14px; color:#93c5fd; line-height:1.5; flex:1; }
        .card-link { font-size:13px; font-weight:700; color:var(--c); }

        .creds { max-width:880px; margin:0 auto 50px; padding:0 20px; }
        .creds-box { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                     border-radius:16px; padding:28px; }
        .creds-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-top:16px; }
        .cred-label { font-size:11px; color:#60a5fa; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .cred-value { background:rgba(0,0,0,0.3); border:1px solid rgba(255,255,255,0.1);
                      border-radius:8px; padding:10px 14px; font-family:monospace;
                      font-size:13px; color:#34d399; word-break:break-all; }

        .stack { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; padding:0 20px 50px; }
        .tech { background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1);
                border-radius:8px; padding:6px 14px; font-size:13px; color:#bfdbfe; }

        footer { text-align:center; padding:30px; color:#3b82f6; font-size:13px;
                 border-top:1px solid rgba(255,255,255,0.08); }
    </style>
</head>
<body>

<div class="hero">
    <div class="logo">🏥</div>
    <h1>QueueCare</h1>
    <p>Système automatisé de gestion des files d'attente au sein d'un service hospitalier.</p>
    <div>
        <span class="badge">Projet PFE <?= date('Y') ?></span>
        <span class="badge">Web + Android</span>
    </div>
</div>

<div class="section">
    <div class="section-title">Accéder aux applications</div>
    <div class="cards">

        <a href="/web/" class="card web">
            <div class="icon">🌐</div>
            <h3>Application Web</h3>
            <p>Interface gestionnaires et médecins — tableau de bord, files d'attente, QR codes, statistiques.</p>
            <span class="card-link">Ouvrir →</span>
        </a>

        <a href="/download/queuecare.apk" class="card apk">
            <div class="icon">📱</div>
            <h3>Application Android</h3>
            <p>Téléchargez l'APK et installez-le sur votre smartphone Android pour tester l'expérience patient.</p>
            <span class="card-link">Télécharger l'APK →</span>
        </a>

        <a href="/api/services" class="card api">
            <div class="icon">⚡</div>
            <h3>API REST</h3>
            <p>Backend partagé entre l'app Web et l'app Android. Cliquez pour tester un endpoint.</p>
            <span class="card-link">Tester l'API →</span>
        </a>

    </div>
</div>

<div class="creds">
    <div class="creds-box">
        <div class="section-title" style="text-align:left;margin-bottom:4px">Comptes de démonstration</div>
        <p style="color:#60a5fa;font-size:13px;margin-bottom:4px">App Android (patient)</p>
        <div class="creds-grid">
            <div><div class="cred-label">Email</div><div class="cred-value">test@queuecare.com</div></div>
            <div><div class="cred-label">Mot de passe</div><div class="cred-value">Test1234!</div></div>
        </div>
        <p style="color:#60a5fa;font-size:13px;margin-top:20px;margin-bottom:4px">App Web (gestionnaire)</p>
        <div class="creds-grid">
            <div><div class="cred-label">Email</div><div class="cred-value">gestionnaire@test.com</div></div>
            <div><div class="cred-label">Mot de passe</div><div class="cred-value">Admin1234!</div></div>
        </div>
    </div>
</div>

<footer>QueueCare — Projet de Fin d'Études <?= date('Y') ?></footer>
</body>
</html>
