/**
 * public/js/medecin.js
 * JavaScript pour l'espace Médecin
 * NOTE: showSection() est défini dans dashboard.php — ne pas redéfinir ici.
 */

// Ouvrir modal de confirmation
function openConfirmModal(consultationId, patientName, action) {
    const modal = document.getElementById('modalConfirm');
    const message = document.getElementById('confirmMessage');
    const actionInput = document.getElementById('confirmAction');
    const consultationIdInput = document.getElementById('confirmConsultationId');

    let actionText = '';
    if (action === 'traite') actionText = 'terminer cette consultation';
    else if (action === 'absent') actionText = 'marquer ce patient comme absent';
    else if (action === 'annule') actionText = 'annuler cette consultation';

    message.innerHTML = `Êtes-vous sûr de vouloir ${actionText} pour <strong>${patientName}</strong> ?`;
    actionInput.value = action;
    consultationIdInput.value = consultationId;

    modal.classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('modalConfirm').classList.remove('active');
}

function submitConfirmAction() {
    document.getElementById('confirmForm').submit();
}

// Annuler toutes les consultations
function annulerToutesConsultations() {
    if (confirm('⚠️ ATTENTION : Êtes-vous sûr de vouloir annuler TOUTES vos consultations du jour ? Cette action est irréversible.')) {
        document.getElementById('annulerToutesForm').submit();
    }
}

// Horloge en temps réel
function updateClock() {
    const clockElement = document.getElementById('clock');
    if (clockElement) {
        clockElement.textContent = new Date().toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
    }
}
updateClock();
setInterval(updateClock, 1000);

// Timeout inactivité 15 minutes
const TIMEOUT_MS = 15 * 60 * 1000;
const WARN_MS = 14 * 60 * 1000;
let idleTimer, warnTimer;

function resetIdleTimer() {
    clearTimeout(idleTimer);
    clearTimeout(warnTimer);

    warnTimer = setTimeout(() => {
        const banner = document.getElementById('timeoutBanner');
        if (banner) banner.style.display = 'flex';
    }, WARN_MS);

    idleTimer = setTimeout(() => {
        window.location.href = 'medecin.php?action=deconnexion&timeout=1';
    }, TIMEOUT_MS);
}

['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click'].forEach(evt => {
    document.addEventListener(evt, resetIdleTimer, { passive: true });
});
resetIdleTimer();

// NOTE : le rechargement automatique brutal a été supprimé car il interrompait
// les requêtes AJAX en cours (démarrer/terminer consultation).
// Le rafraîchissement des données est géré directement dans le dashboard via
// rafraichirConsultations() toutes les 30 secondes, sans recharger la page.