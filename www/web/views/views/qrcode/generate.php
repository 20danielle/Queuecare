<?php
// views/qrcode/generate.php
// Vérifier la session
if (!isset($_SESSION['gestionnaire_id'])) {
    header('Location: gestionnaire.php?action=connexion');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code - QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .qr-container {
            max-width: 650px;
            width: 100%;
            background: white;
            border-radius: 32px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }

        .qr-header {
            background: linear-gradient(135deg, #1a4db5, #2563eb);
            padding: 28px 32px;
            color: white;
        }

        .qr-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.6rem;
            margin-bottom: 8px;
        }

        .qr-header p {
            opacity: 0.85;
            font-size: 0.9rem;
        }

        .qr-body {
            padding: 32px;
        }

        .service-info {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 32px;
        }

        .service-info h3 {
            color: #166534;
            font-size: 0.85rem;
            margin-bottom: 8px;
        }

        .service-info p {
            color: #14532d;
            font-size: 1rem;
            font-weight: 600;
        }

        .qr-display {
            text-align: center;
            padding: 30px;
            background: #f8fafc;
            border-radius: 24px;
            margin-bottom: 24px;
            min-height: 350px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .qr-image {
            display: inline-block;
            padding: 16px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .qr-image img {
            width: 220px;
            height: 220px;
            display: block;
        }

        .qr-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Outfit', sans-serif;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1a4db5, #2563eb);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 77, 181, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #c8dff2;
            color: #1a4db5;
        }

        .btn-outline:hover {
            background: #f0f4fa;
        }

        .loading {
            width: 50px;
            height: 50px;
            border: 4px solid #e2e8f0;
            border-top-color: #1a4db5;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .hidden {
            display: none !important;
        }

        .info-card {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 12px;
            padding: 14px 18px;
            margin-top: 20px;
        }

        .info-card p {
            color: #92400e;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .expire-info {
            margin-top: 20px;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 12px;
            font-size: 0.8rem;
            color: #475569;
            text-align: center;
        }

        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 20px;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #6b83a8;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .back-link:hover {
            color: #1a4db5;
        }

        .success-msg {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
<div class="qr-container">
    <div class="qr-header">
        <h1><i class="fa-solid fa-qrcode"></i> QR Code de consultation</h1>
        <p>Générez un QR code pour permettre aux patients de prendre rendez-vous</p>
    </div>

    <div class="qr-body">
        <div class="service-info">
            <h3><i class="fa-solid fa-hospital"></i> Service actuel</h3>
            <p><?= htmlspecialchars($sousService['service_nom'] ?? 'Non défini') ?> — <strong><?= htmlspecialchars($sousService['nom'] ?? 'Non défini') ?></strong></p>
        </div>

        <div id="qrDisplay" class="qr-display">
            <div id="qrLoading" class="loading"></div>
            <div id="qrContent" class="hidden">
                <div class="qr-image">
                    <img id="qrImage" src="" alt="QR Code">
                </div>
                <div class="qr-actions">
                    <button class="btn btn-secondary" onclick="telechargerQR()">
                        <i class="fa-solid fa-download"></i> Télécharger
                    </button>
                    <button class="btn btn-outline" onclick="imprimerQR()">
                        <i class="fa-solid fa-print"></i> Imprimer
                    </button>
                </div>
            </div>
        </div>

        <div class="button-group">
            <button id="generateBtn" class="btn btn-primary" onclick="genererQRCode()">
                <i class="fa-solid fa-arrows-rotate"></i> Générer un nouveau QR Code
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='gestionnaire.php?action=dashboard'">
                <i class="fa-solid fa-arrow-left"></i> Retour
            </button>
        </div>

        <div id="infoCard" class="info-card hidden">
            <p><i class="fa-solid fa-circle-info"></i> <span id="infoText"></span></p>
        </div>

        <div id="expireInfo" class="expire-info hidden">
            <i class="fa-regular fa-clock"></i> ⚠️ Ce QR code expire le : <strong><span id="expireDate"></span></strong>
        </div>
    </div>
</div>

<script>
    let currentQRPath = '';
    
    // Charger le QR code actif au chargement
    document.addEventListener('DOMContentLoaded', function() {
        chargerQRCodeActif();
    });
    
    function chargerQRCodeActif() {
        showLoading(true);
        
        fetch('gestionnaire.php?action=get_qrcode_actif&sous_service_id=<?= $sousService['id'] ?? 0 ?>')
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                if (data.success && data.qr_code_path) {
                    afficherQRCode(data.qr_code_path, data.expire_at);
                    currentQRPath = data.qr_code_path;
                } else {
                    document.getElementById('infoCard').classList.remove('hidden');
                    document.getElementById('infoText').innerHTML = '📱 Aucun QR code actif. Cliquez sur "Générer" pour en créer un.';
                }
            })
            .catch(error => {
                showLoading(false);
                console.error('Erreur:', error);
            });
    }
    
    function genererQRCode() {
        if (!confirm('⚠️ Générer un nouveau QR code ? L\'ancien ne sera plus valide.')) {
            return;
        }
        
        showLoading(true);
        
        const formData = new FormData();
        formData.append('sous_service_id', '<?= $sousService['id'] ?? 0 ?>');
        
        fetch('gestionnaire.php?action=generer_qrcode', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoading(false);
            if (data.success) {
                afficherQRCode(data.qr_code_path, data.expire_at);
                currentQRPath = data.qr_code_path;
                
                document.getElementById('infoCard').classList.remove('hidden');
                document.getElementById('infoCard').classList.add('success-msg');
                document.getElementById('infoText').innerHTML = '✓ QR Code généré avec succès !';
                setTimeout(() => {
                    document.getElementById('infoCard').classList.add('hidden');
                    document.getElementById('infoCard').classList.remove('success-msg');
                }, 3000);
            } else {
                document.getElementById('infoCard').classList.remove('hidden');
                document.getElementById('infoText').innerHTML = '❌ ' + (data.error || 'Erreur lors de la génération');
            }
        })
        .catch(error => {
            showLoading(false);
            console.error('Erreur:', error);
            document.getElementById('infoCard').classList.remove('hidden');
            document.getElementById('infoText').innerHTML = '❌ Une erreur est survenue';
        });
    }
    
    function afficherQRCode(path, expireAt) {
        const qrImage = document.getElementById('qrImage');
        qrImage.src = path;
        
        const expireDate = new Date(expireAt);
        const formattedDate = expireDate.toLocaleDateString('fr-FR') + ' à ' + expireDate.toLocaleTimeString('fr-FR');
        
        document.getElementById('expireDate').innerText = formattedDate;
        document.getElementById('expireInfo').classList.remove('hidden');
        document.getElementById('qrContent').classList.remove('hidden');
    }
    
    function telechargerQR() {
        if (currentQRPath) {
            window.location.href = 'gestionnaire.php?action=telecharger_qrcode&path=' + encodeURIComponent(currentQRPath);
        }
    }
    
    function imprimerQR() {
        if (currentQRPath) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>QR Code - QueueCare</title>
                    <style>
                        body {
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            height: 100vh;
                            font-family: Arial, sans-serif;
                            text-align: center;
                            margin: 0;
                        }
                        .print-container {
                            text-align: center;
                        }
                        img {
                            width: 250px;
                            height: 250px;
                            margin-bottom: 20px;
                        }
                        h2 {
                            color: #1a4db5;
                            margin-bottom: 10px;
                        }
                        p {
                            color: #666;
                            margin: 5px 0;
                        }
                        .footer {
                            margin-top: 30px;
                            font-size: 12px;
                            color: #999;
                        }
                    </style>
                </head>
                <body>
                    <div class="print-container">
                        <img src="${currentQRPath}" alt="QR Code">
                        <h2>QueueCare - Prise de rendez-vous</h2>
                        <p><strong><?= htmlspecialchars($sousService['service_nom'] ?? '') ?></strong></p>
                        <p><?= htmlspecialchars($sousService['nom'] ?? '') ?></p>
                        <p>Scannez ce QR code pour prendre votre rendez-vous en ligne</p>
                        <div class="footer">Généré le <?= date('d/m/Y H:i') ?></div>
                    </div>
                    <script>window.print();<\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }
    }
    
    function showLoading(show) {
        const loading = document.getElementById('qrLoading');
        const content = document.getElementById('qrContent');
        
        if (show) {
            loading.classList.remove('hidden');
            content.classList.add('hidden');
        } else {
            loading.classList.add('hidden');
        }
    }
</script>
</body>
</html>