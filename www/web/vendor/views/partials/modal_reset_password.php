<?php
/**
 * views/partials/modal_reset_password.php
 *
 * Modale bloquante affichée sur les dashboards (admin/gestionnaire/médecin)
 * lorsque $_SESSION['must_reset_password'] est vrai, c'est-à-dire lorsque
 * l'utilisateur vient de se connecter avec le code reçu par email
 * (flux "mot de passe oublié"). Tant qu'il n'a pas défini un nouveau mot
 * de passe confirmé, le reste du dashboard reste masqué derrière elle.
 */
?>
<div id="overlayResetPwd" style="position:fixed;inset:0;background:rgba(15,23,42,.55);
     backdrop-filter:blur(3px);z-index:99999;display:flex;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:14px;max-width:420px;width:92%;
                padding:2rem 2.2rem;box-shadow:0 10px 40px rgba(0,0,0,.25);">
        <div style="text-align:center;margin-bottom:1.2rem;">
            <div style="width:56px;height:56px;border-radius:50%;background:#e7f0ff;
                        display:flex;align-items:center;justify-content:center;margin:0 auto .8rem;">
                <i class="fa-solid fa-key" style="color:#0d6efd;font-size:1.4rem;"></i>
            </div>
            <h2 style="font-size:1.2rem;font-weight:700;color:#1e293b;margin:0;">Nouveau mot de passe requis</h2>
            <p style="font-size:.85rem;color:#64748b;margin-top:.4rem;">
                Vous vous êtes connecté avec un code temporaire. Définissez votre nouveau
                mot de passe pour continuer.
            </p>
        </div>

        <div id="resetPwdAlert" style="display:none;font-size:.85rem;padding:.6rem .8rem;
             border-radius:8px;margin-bottom:.9rem;"></div>

        <form id="formResetPwd" onsubmit="return false;">
            <div style="margin-bottom:.9rem;">
                <label style="font-size:.85rem;font-weight:600;color:#1e293b;display:block;margin-bottom:.3rem;">
                    Nouveau mot de passe
                </label>
                <input type="password" id="rp_nouveau" minlength="8" required
                       placeholder="••••••••"
                       style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.9rem;">
            </div>

            <div style="margin-bottom:1.3rem;">
                <label style="font-size:.85rem;font-weight:600;color:#1e293b;display:block;margin-bottom:.3rem;">
                    Confirmer le mot de passe
                </label>
                <input type="password" id="rp_confirmation" minlength="8" required
                       placeholder="••••••••"
                       style="width:100%;padding:.6rem .8rem;border:1px solid #cbd5e1;border-radius:8px;font-size:.9rem;">
            </div>

            <button type="submit" id="rp_submitBtn" onclick="soumettreResetPwd()"
                    style="width:100%;padding:.7rem;background:#0d6efd;color:#fff;border:none;
                           border-radius:8px;font-weight:600;font-size:.92rem;cursor:pointer;">
                Valider et continuer
            </button>
        </form>
    </div>
</div>

<script>
function soumettreResetPwd() {
    const nouveau    = document.getElementById('rp_nouveau').value;
    const confirme   = document.getElementById('rp_confirmation').value;
    const alertBox   = document.getElementById('resetPwdAlert');
    const submitBtn  = document.getElementById('rp_submitBtn');

    function afficherAlerte(message, type) {
        alertBox.style.display = 'block';
        alertBox.style.background = type === 'success' ? '#dcfce7' : '#fee2e2';
        alertBox.style.color      = type === 'success' ? '#166534' : '#991b1b';
        alertBox.textContent = message;
    }

    if (nouveau.length < 8) {
        afficherAlerte('Le mot de passe doit contenir au moins 8 caractères.', 'error');
        return;
    }
    if (nouveau !== confirme) {
        afficherAlerte('Les deux mots de passe ne correspondent pas.', 'error');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Validation…';

    const body = new URLSearchParams();
    body.append('nouveau_mot_de_passe', nouveau);
    body.append('confirmation_mot_de_passe', confirme);

    fetch('index.php?action=finaliser_reset_password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            afficherAlerte(data.message, 'success');
            setTimeout(() => { window.location.reload(); }, 900);
        } else {
            afficherAlerte(data.message || 'Une erreur est survenue.', 'error');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Valider et continuer';
        }
    })
    .catch(() => {
        afficherAlerte('Erreur réseau. Réessayez.', 'error');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Valider et continuer';
    });
}
</script>