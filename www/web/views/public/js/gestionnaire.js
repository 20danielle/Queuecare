/**
 * public/js/gestionnaire.js
 * JavaScript pour l'espace Gestionnaire
 * NOTE: showSection() est défini dans dashboard.php — ne pas redéfinir ici.
 */

// Navigation sections
// Protection contre la double soumission
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> En cours...';
        }
    });
});


// Modals
function ouvrirModalChoix() {
    document.getElementById('modalChoix').classList.add('active');
}

function fermerModalChoix() {
    document.getElementById('modalChoix').classList.remove('active');
}

function fermerModalConsultation() {
    document.getElementById('modalConsultation').classList.remove('active');
    document.getElementById('formConsultManuelle').reset();
}

function choisirMode(mode) {
    fermerModalChoix();
    if (mode === 'manuel') {
        document.getElementById('modalConsultation').classList.add('active');
    } else if (mode === 'qr') {
        ouvrirModalQR();
    }
}

// QR Code functions
let currentQRPath = '';
let currentExpireAt = null;
let qrTimerInterval = null;
let autoRegenerateInterval = null;

function ouvrirModalQR() {
    document.getElementById('modalQR').classList.add('active');
    chargerQRCode();
}

function fermerModalQR() {
    document.getElementById('modalQR').classList.remove('active');
    if (qrTimerInterval) clearInterval(qrTimerInterval);
}

function chargerQRCode() {
    showQRLoading(true);
    fetch('gestionnaire.php?action=get_qrcode_actif')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.qr_code_path) {
                afficherQRCode(data.qr_code_path, data.expire_at);
            } else {
                genererNouveauQRCode();
            }
        })
        .catch(error => {
            genererNouveauQRCode();
        });
}

function genererNouveauQRCode() {
    showQRLoading(true);
    const formData = new FormData();
    formData.append('sous_service_id', sousServiceId);

    fetch('gestionnaire.php?action=generer_qrcode', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                afficherQRCode(data.qr_code_path, data.expire_at);
                showQRMessage('✓ QR Code généré avec succès !', 'success');
            } else {
                showQRMessage('❌ ' + (data.error || 'Erreur lors de la génération'), 'error');
                showQRLoading(false);
            }
        })
        .catch(error => {
            showQRMessage('❌ Une erreur est survenue', 'error');
            showQRLoading(false);
        });
}

function regenererQRCode() {
    if (confirm('Générer un nouveau QR code ? L\'ancien ne sera plus valide.')) {
        genererNouveauQRCode();
    }
}

function afficherQRCode(path, expireAt) {
    document.getElementById('qrCodeImage').src = path;
    currentQRPath = path;
    currentExpireAt = new Date(expireAt);
    showQRLoading(false);
    document.getElementById('qrContentState').classList.remove('hidden');
    demarrerTimerQR();

    if (autoRegenerateInterval) clearInterval(autoRegenerateInterval);
    autoRegenerateInterval = setInterval(() => {
        genererNouveauQRCode();
    }, 20 * 60 * 1000);
}

function demarrerTimerQR() {
    if (qrTimerInterval) clearInterval(qrTimerInterval);
    qrTimerInterval = setInterval(() => {
        if (!currentExpireAt) return;
        const diff = currentExpireAt - new Date();
        if (diff <= 0) {
            clearInterval(qrTimerInterval);
            document.getElementById('qrTimer').textContent = 'Expiré';
            genererNouveauQRCode();
        } else {
            const minutes = Math.floor(diff / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);
            document.getElementById('qrTimer').textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }, 1000);
}

function telechargerQRCode() {
    if (currentQRPath) {
        window.location.href = 'gestionnaire.php?action=telecharger_qrcode&path=' + encodeURIComponent(currentQRPath);
    }
}

function imprimerQRCode() {
    if (currentQRPath) {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>QR Code - QueueCare</title>
                <style>
                    body { display: flex; justify-content: center; align-items: center; height: 100vh; text-align: center; margin: 0; }
                    img { width: 250px; margin-bottom: 20px; }
                    h2 { color: #1a4db5; margin-bottom: 10px; }
                    .footer { margin-top: 30px; font-size: 12px; color: #999; }
                </style>
            </head>
            <body>
                <div>
                    <img src="${currentQRPath}" alt="QR Code">
                    <h2>QueueCare - Prise de rendez-vous</h2>
                    <p><strong>${serviceNom}</strong></p>
                    <p>${sousServiceNom}</p>
                    <p>Scannez ce QR code pour prendre votre rendez-vous en ligne</p>
                    <div class="footer">Généré le ${new Date().toLocaleDateString('fr-FR')} ${new Date().toLocaleTimeString('fr-FR')}</div>
                </div>
                <script>window.print();<\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}

function showQRLoading(show) {
    const loading = document.getElementById('qrLoadingState');
    const content = document.getElementById('qrContentState');
    if (show) {
        loading.classList.remove('hidden');
        content.classList.add('hidden');
    } else {
        loading.classList.add('hidden');
    }
}

function showQRMessage(message, type) {
    const msgDiv = document.getElementById('qrMessage');
    msgDiv.textContent = message;
    msgDiv.classList.remove('hidden', 'qr-success', 'qr-error');
    msgDiv.classList.add(type === 'success' ? 'qr-success' : 'qr-error');
    setTimeout(() => msgDiv.classList.add('hidden'), 3000);
}

// Recherche patient
let searchTimer;

function rechercherPatient(tel) {
    const clean = tel.replace(/[^0-9+]/g, '');
    if (clean.length < 8) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(async() => {
        try {
            const r = await fetch('gestionnaire.php?action=dashboard&api=patient&tel=' + encodeURIComponent(clean));
            const p = await r.json();
            if (p) {
                document.getElementById('m_nom').value = p.nom || '';
                document.getElementById('m_prenom').value = p.prenom || '';
                document.getElementById('m_email').value = p.email || '';
            }
        } catch (e) {}
    }, 500);
}

// NOTE : le rechargement automatique brutal a été supprimé car il interrompait
// les requêtes AJAX en cours (démarrer/terminer consultation).
// Le rafraîchissement des données est géré directement dans le dashboard.