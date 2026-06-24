<?php
/**
 * scan_ticket.php — Page publique de scan QR code
 * Accessible sans compte. Le patient entre son nom + téléphone
 * et reçoit un ticket numéroté dans la file d'attente.
 *
 * URL encodée dans le QR code : .../scan_ticket.php?token=XXXX
 */

session_start();
date_default_timezone_set('Africa/Douala');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/QRCodeModel.php';
require_once __DIR__ . '/models/TicketModel.php';

$token  = trim($_GET['token'] ?? '');
$erreur = '';
$ticket = null;
$qrCode = null;
$sousService = null;

// ── 1. Valider le token ──────────────────────────────────────────────────────
if (empty($token)) {
    $erreur = 'Lien QR code invalide ou manquant.';
} else {
    $qrModel = new QRCodeModel();
    $qrCode  = $qrModel->findByToken($token);

    if (!$qrCode) {
        $erreur = 'Ce QR code est introuvable.';
    } elseif ($qrCode['statut'] !== 'actif') {
        $erreur = 'Ce QR code n\'est plus actif.';
    } elseif ($qrCode['expire_at'] < date('Y-m-d H:i:s')) {
        $erreur = 'Ce QR code a expiré. Demandez au gestionnaire d\'en générer un nouveau.';
    } else {
        $sousService = [
            'id'  => $qrCode['sous_service_id'],
            'nom' => $qrCode['sous_service_nom'] ?? 'Service',
        ];
    }
}

// ── 2. Traitement du formulaire ──────────────────────────────────────────────
$erreurs  = [];
$anciens  = ['nom' => '', 'prenom' => '', 'telephone' => ''];
$success  = false;

if (!$erreur && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom       = trim($_POST['nom']       ?? '');
    $prenom    = trim($_POST['prenom']    ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    $anciens = compact('nom', 'prenom', 'telephone');

    if (mb_strlen($nom) < 2)    $erreurs['nom']       = 'Nom trop court (min 2 caractères).';
    if (mb_strlen($prenom) < 2) $erreurs['prenom']    = 'Prénom trop court (min 2 caractères).';
    if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone))
                                 $erreurs['telephone'] = 'Numéro invalide (ex: 699000000).';

    if (empty($erreurs)) {
        $db = Database::getInstance()->getConnection();

        // Rechercher ou créer le patient (sans compte, uniquement par téléphone)
        $stmt = $db->prepare("SELECT id FROM patients WHERE telephone = :tel LIMIT 1");
        $stmt->execute([':tel' => $telephone]);
        $patient = $stmt->fetch();

        if (!$patient) {
            // Créer un patient anonyme (sans email obligatoire)
            $emailFallback = 'anon_' . preg_replace('/[^0-9]/', '', $telephone) . '@queuecare.local';
            $stmt2 = $db->prepare(
                "INSERT INTO patients (nom, prenom, telephone, email, statut, date_inscription)
                 VALUES (:nom, :prenom, :tel, :email, 'actif', NOW())"
            );
            $stmt2->execute([
                ':nom'    => strtoupper($nom),
                ':prenom' => ucfirst(strtolower($prenom)),
                ':tel'    => $telephone,
                ':email'  => $emailFallback,
            ]);
            $patientId = (int)$db->lastInsertId();
        } else {
            $patientId = (int)$patient['id'];
        }

        // Calculer le rang et le temps d'attente
        $ticketModel = new TicketModel();
        $rang        = $ticketModel->calculerRang($qrCode['id']);
        $dureeMin    = isset($qrCode['duree_estimee']) ? (int)round($qrCode['duree_estimee'] / 60) : 15;
        $tempsAttente = $ticketModel->calculerTempsAttente($qrCode['id'], $dureeMin);

        $heureDebut = date('Y-m-d H:i:s', strtotime("+{$tempsAttente} minutes"));
        $heureFin   = date('Y-m-d H:i:s', strtotime('+' . ($tempsAttente + $dureeMin) . ' minutes'));

        $ticketId = $ticketModel->creer([
            'patient_id'          => $patientId,
            'qr_code_id'          => $qrCode['id'],
            'consultation_id'     => null,
            'rang'                => $rang,
            'heure_creation'      => date('Y-m-d H:i:s'),
            'heure_debut_estimee' => $heureDebut,
            'heure_fin_estimee'   => $heureFin,
            'temps_attente_minutes' => $tempsAttente,
            'statut'              => 'en_attente',
        ]);

        if ($ticketId) {
            // Incrémenter le compteur de scans
            $qrModel->incrementScanCount($qrCode['id']);

            $ticket  = $ticketModel->obtenirParId($ticketId);
            $success = true;

            // Stocker en session pour rafraîchissement
            $_SESSION['last_ticket_id'] = $ticketId;
        } else {
            $erreurs['global'] = 'Erreur lors de la création du ticket. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QueueCare — Prendre un ticket</title>
    <link href="https://fonts.bunny.net/css?family=outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue:       #2563eb;
            --blue-dark:  #1e40af;
            --green:      #10b981;
            --orange:     #f59e0b;
            --red:        #ef4444;
            --bg:         #f0f4ff;
            --white:      #ffffff;
            --text:       #1e293b;
            --muted:      #64748b;
            --border:     #e2e8f0;
            --radius:     16px;
        }
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 24px 16px 48px;
        }

        /* ── Header ── */
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        .logo {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--blue-dark);
            margin-bottom: 6px;
        }
        .logo i { color: var(--blue); font-size: 1.6rem; }
        .header p { color: var(--muted); font-size: 0.875rem; }

        /* ── Card ── */
        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: 0 4px 24px rgba(37,99,235,.10);
            width: 100%;
            max-width: 460px;
            padding: 32px 28px;
        }
        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--blue-dark);
            margin-bottom: 4px;
        }
        .card-sub {
            font-size: 0.8rem;
            color: var(--muted);
            margin-bottom: 24px;
        }

        /* ── Service badge ── */
        .service-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #dbeafe;
            color: var(--blue-dark);
            border-radius: 20px;
            padding: 6px 14px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        /* ── Form ── */
        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text);
            transition: border-color .2s;
            outline: none;
        }
        .form-group input:focus { border-color: var(--blue); }
        .form-group input.error { border-color: var(--red); }
        .error-msg {
            font-size: 0.75rem;
            color: var(--red);
            margin-top: 4px;
        }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* ── Submit ── */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 8px;
            transition: opacity .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover { opacity: .9; }
        .btn-submit:disabled { opacity: .6; cursor: not-allowed; }

        /* ── Error / info banners ── */
        .banner {
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 20px;
            font-size: 0.875rem;
        }
        .banner-error  { background: #fee2e2; color: #991b1b; }
        .banner-global { background: #fff3cd; color: #92400e; }
        .banner i { margin-top: 2px; flex-shrink: 0; }

        /* ── Ticket de succès ── */
        .ticket-card {
            background: linear-gradient(135deg, var(--blue-dark), var(--blue));
            border-radius: var(--radius);
            color: white;
            padding: 32px 28px;
            text-align: center;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 8px 32px rgba(37,99,235,.3);
        }
        .ticket-card .icon-wrap {
            width: 72px;
            height: 72px;
            background: rgba(255,255,255,.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }
        .ticket-num {
            font-size: 4rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 4px;
            letter-spacing: -2px;
        }
        .ticket-num-label {
            font-size: 0.8rem;
            opacity: .75;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 24px;
        }
        .ticket-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }
        .ticket-info-item {
            background: rgba(255,255,255,.12);
            border-radius: 12px;
            padding: 12px;
        }
        .ticket-info-label {
            font-size: 0.7rem;
            opacity: .75;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 4px;
        }
        .ticket-info-value {
            font-size: 1.05rem;
            font-weight: 700;
        }
        .ticket-statut {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 8px 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .ticket-statut i { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

        .ticket-note {
            font-size: 0.78rem;
            opacity: .7;
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .btn-print {
            background: rgba(255,255,255,.2);
            border: 1.5px solid rgba(255,255,255,.4);
            color: white;
            padding: 10px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background .2s;
        }
        .btn-print:hover { background: rgba(255,255,255,.3); }

        /* ── Erreur page ── */
        .error-page {
            text-align: center;
            width: 100%;
            max-width: 460px;
        }
        .error-page .icon-wrap {
            width: 80px;
            height: 80px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: var(--red);
        }
        .error-page h2 { color: var(--text); margin-bottom: 10px; }
        .error-page p { color: var(--muted); font-size: 0.9rem; line-height: 1.6; }

        @media(max-width: 480px) {
            .card { padding: 24px 18px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .ticket-info-grid { grid-template-columns: 1fr; gap: 8px; }
            .ticket-num { font-size: 3rem; }
        }

        @media print {
            body { background: white; padding: 0; }
            .header, .card { display: none; }
            .ticket-card {
                background: white !important;
                color: black !important;
                box-shadow: none;
                border: 2px solid #000;
                max-width: 100%;
            }
            .ticket-num { color: black !important; }
            .ticket-info-item { background: #f0f0f0 !important; }
            .ticket-statut { background: #e0e0e0 !important; color: black !important; }
            .btn-print { display: none; }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="logo"><i class="fa-solid fa-list-check"></i> QueueCare</div>
    <p>Système de gestion de file d'attente</p>
</div>

<?php if ($erreur): ?>
<!-- ── Page d'erreur QR invalide ── -->
<div class="error-page">
    <div class="icon-wrap"><i class="fa-solid fa-qrcode"></i></div>
    <h2>QR Code invalide</h2>
    <p><?= htmlspecialchars($erreur) ?></p>
</div>

<?php elseif ($success && $ticket): ?>
<!-- ── Ticket créé avec succès ── -->
<div class="ticket-card" id="ticketCard">
    <div class="icon-wrap"><i class="fa-solid fa-ticket"></i></div>
    <div class="ticket-num">#<?= str_pad($ticket['id'], 4, '0', STR_PAD_LEFT) ?></div>
    <div class="ticket-num-label">Votre numéro de ticket</div>

    <div class="ticket-statut">
        <i class="fa-solid fa-circle"></i>
        En attente de consultation
    </div>

    <div class="ticket-info-grid">
        <div class="ticket-info-item">
            <div class="ticket-info-label"><i class="fa-solid fa-list-ol"></i> Rang</div>
            <div class="ticket-info-value"><?= $ticket['rang'] ?></div>
        </div>
        <div class="ticket-info-item">
            <div class="ticket-info-label"><i class="fa-regular fa-clock"></i> Attente estimée</div>
            <div class="ticket-info-value"><?= $ticket['temps_attente_minutes'] ?> min</div>
        </div>
        <div class="ticket-info-item">
            <div class="ticket-info-label"><i class="fa-solid fa-play"></i> Début estimé</div>
            <div class="ticket-info-value"><?= date('H:i', strtotime($ticket['heure_debut_estimee'])) ?></div>
        </div>
        <div class="ticket-info-item">
            <div class="ticket-info-label"><i class="fa-solid fa-stethoscope"></i> Service</div>
            <div class="ticket-info-value" style="font-size:.85rem"><?= htmlspecialchars($ticket['sous_service_nom'] ?? $sousService['nom']) ?></div>
        </div>
    </div>

    <p class="ticket-note">
        Gardez ce numéro précieusement. Restez à proximité de la salle d'attente — vous serez appelé(e) lorsque ce sera votre tour.
    </p>

    <button class="btn-print" onclick="window.print()">
        <i class="fa-solid fa-print"></i> Imprimer le ticket
    </button>
</div>

<?php else: ?>
<!-- ── Formulaire patient ── -->
<div class="card">
    <div class="card-title">Prendre un ticket</div>
    <div class="card-sub">Remplissez le formulaire pour rejoindre la file d'attente.</div>

    <?php if ($sousService): ?>
    <div class="service-badge">
        <i class="fa-solid fa-hospital"></i>
        <?= htmlspecialchars($sousService['nom']) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($erreurs['global'])): ?>
    <div class="banner banner-global">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?= htmlspecialchars($erreurs['global']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="scan_ticket.php?token=<?= urlencode($token) ?>" id="ticketForm">
        <div class="form-row">
            <div class="form-group">
                <label for="nom"><i class="fa-solid fa-user"></i> Nom</label>
                <input type="text" id="nom" name="nom"
                       value="<?= htmlspecialchars($anciens['nom']) ?>"
                       class="<?= isset($erreurs['nom']) ? 'error' : '' ?>"
                       placeholder="Ex: MOUMI" required autocomplete="family-name">
                <?php if (isset($erreurs['nom'])): ?>
                <div class="error-msg"><?= htmlspecialchars($erreurs['nom']) ?></div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label for="prenom"><i class="fa-solid fa-user"></i> Prénom</label>
                <input type="text" id="prenom" name="prenom"
                       value="<?= htmlspecialchars($anciens['prenom']) ?>"
                       class="<?= isset($erreurs['prenom']) ? 'error' : '' ?>"
                       placeholder="Ex: Marie" required autocomplete="given-name">
                <?php if (isset($erreurs['prenom'])): ?>
                <div class="error-msg"><?= htmlspecialchars($erreurs['prenom']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-group">
            <label for="telephone"><i class="fa-solid fa-phone"></i> Numéro de téléphone</label>
            <input type="tel" id="telephone" name="telephone"
                   value="<?= htmlspecialchars($anciens['telephone']) ?>"
                   class="<?= isset($erreurs['telephone']) ? 'error' : '' ?>"
                   placeholder="Ex: 699000000" required autocomplete="tel">
            <?php if (isset($erreurs['telephone'])): ?>
            <div class="error-msg"><?= htmlspecialchars($erreurs['telephone']) ?></div>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit" id="submitBtn">
            <i class="fa-solid fa-ticket"></i>
            Obtenir mon ticket
        </button>
    </form>
</div>
<?php endif; ?>

<script>
// Désactiver le bouton pendant la soumission
document.getElementById('ticketForm')?.addEventListener('submit', function(){
    const btn = document.getElementById('submitBtn');
    if(btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Génération...'; }
});
</script>
</body>
</html>
