<?php
// views/gestionnaire/inscription.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Gestionnaire — QueueCare</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/gestionnaire.css">
</head>
<body>
<div class="medical-wrapper">
    
    <!-- Panel gauche - Présentation médicale -->
    <div class="medical-presentation">
        <div class="medical-presentation-inner">
            <div class="medical-logo medical-animate">
                <div class="medical-logo-icon">
                    <i class="fa-solid fa-heart-pulse"></i>
                </div>
                <div class="medical-logo-text">QueueCare</div>
            </div>
            
            <h1 class="medical-title medical-animate-delay-1">
                Gérez vos consultations<br>
                en toute <em>sérénité</em>
            </h1>
            
            <p class="medical-subtitle medical-animate-delay-1">
                Une plateforme complète pour optimiser la gestion de votre service 
                et améliorer l'expérience patient.
            </p>
            
            <ul class="medical-features medical-animate-delay-2">
                <li><i class="fa-solid fa-circle-check"></i> File d'attente en temps réel</li>
                <li><i class="fa-solid fa-circle-check"></i> Prise de rendez-vous en ligne</li>
                <li><i class="fa-solid fa-circle-check"></i> Notifications automatiques</li>
                <li><i class="fa-solid fa-circle-check"></i> Statistiques et rapports</li>
                <li><i class="fa-solid fa-circle-check"></i> QR Code pour accès rapide</li>
            </ul>
        </div>
        <div class="medical-doctor-icon">
            <i class="fa-solid fa-user-doctor"></i>
        </div>
    </div>
    
    <!-- Panel droit - Formulaire d'inscription -->
    <div class="medical-form-container">
        <div class="medical-form-card medical-animate">
            
            <div class="medical-form-header">
                <a href="accueil.php" class="medical-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                </a>
                <div class="medical-badge">
                    <i class="fa-solid fa-user-tie"></i> Espace Gestionnaire
                </div>
                <h2 class="medical-form-title">Créer un compte</h2>
                <p class="medical-form-subtitle">
                    Déjà inscrit ? 
                    <a href="gestionnaire.php?action=connexion">Se connecter</a>
                </p>
            </div>
            
            <!-- Messages d'erreur globaux -->
            <?php if (!empty($erreurs['global'])): ?>
            <div class="medical-alert medical-alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($erreurs['global']) ?>
            </div>
            <?php endif; ?>
            
            <!-- Formulaire -->
            <form method="POST" action="gestionnaire.php?action=inscription" id="inscriptionForm">
                
                <!-- Nom complet -->
                <div class="medical-field">
                    <label class="medical-label" for="nom">
                        <i class="fa-solid fa-user"></i> Nom complet
                    </label>
                    <div class="medical-input-wrapper">
                        <span class="medical-input-icon"><i class="fa-solid fa-user"></i></span>
                        <input type="text" id="nom" name="nom" class="medical-input <?= isset($erreurs['nom']) ? 'medical-error' : '' ?>"
                               placeholder="ex : Dr Karim Ben Ali"
                               value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>"
                               autocomplete="name" required>
                    </div>
                    <?php if (isset($erreurs['nom'])): ?>
                    <span class="medical-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['nom'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Téléphone et Email sur la même ligne -->
                <div class="medical-row">
                    <div class="medical-field">
                        <label class="medical-label" for="telephone">
                            <i class="fa-solid fa-phone"></i> Téléphone
                        </label>
                        <div class="medical-input-wrapper">
                            <span class="medical-input-icon"><i class="fa-solid fa-phone"></i></span>
                            <input type="tel" id="telephone" name="telephone" class="medical-input <?= isset($erreurs['telephone']) ? 'medical-error' : '' ?>"
                                   placeholder="699 123 456"
                                   value="<?= htmlspecialchars($anciens['telephone'] ?? '') ?>"
                                   autocomplete="tel" required>
                        </div>
                        <?php if (isset($erreurs['telephone'])): ?>
                        <span class="medical-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['telephone'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="medical-field">
                        <label class="medical-label" for="email">
                            <i class="fa-solid fa-envelope"></i> Email
                        </label>
                        <div class="medical-input-wrapper">
                            <span class="medical-input-icon"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" id="email" name="email" class="medical-input <?= isset($erreurs['email']) ? 'medical-error' : '' ?>"
                                   placeholder="exemple@hopital.cm"
                                   value="<?= htmlspecialchars($anciens['email'] ?? '') ?>"
                                   autocomplete="email" required>
                        </div>
                        <?php if (isset($erreurs['email'])): ?>
                        <span class="medical-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['email'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Sous-service -->
                <div class="medical-field">
                    <label class="medical-label" for="sous_service_id">
                        <i class="fa-solid fa-building"></i> Sous-service
                    </label>
                    <div class="medical-input-wrapper">
                        <span class="medical-input-icon"><i class="fa-solid fa-building"></i></span>
                        <select id="sous_service_id" name="sous_service_id" 
                                class="medical-input medical-select <?= isset($erreurs['sous_service_id']) ? 'medical-error' : '' ?>" required>
                            <option value="" disabled <?= empty($anciens['sous_service_id']) ? 'selected' : '' ?>>
                                Sélectionner un sous-service
                            </option>
                            <?php foreach ($sousServices as $ss): ?>
                            <option value="<?= (int)$ss['id'] ?>" 
                                <?= (int)($anciens['sous_service_id'] ?? 0) === (int)$ss['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ss['service_nom']) ?> — <?= htmlspecialchars($ss['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="medical-select-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                    <?php if (isset($erreurs['sous_service_id'])): ?>
                    <span class="medical-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['sous_service_id'] ?></span>
                    <?php endif; ?>
                    <small class="medical-small">
                        <i class="fa-solid fa-circle-info"></i> Les sous-services sont créés par le médecin chef de service
                    </small>
                </div>
                
                <!-- Mot de passe et confirmation sur la même ligne -->
                <div class="medical-row">
                    <div class="medical-field">
                        <label class="medical-label" for="password">
                            <i class="fa-solid fa-lock"></i> Mot de passe
                        </label>
                        <div class="medical-input-wrapper">
                            <span class="medical-input-icon"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" id="password" name="password" class="medical-input <?= isset($erreurs['password']) ? 'medical-error' : '' ?>"
                                   placeholder="8 caractères min." autocomplete="new-password" required>
                            <button type="button" class="medical-pw-toggle" id="togglePw">
                                <i class="fa-solid fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div class="medical-pw-strength">
                            <div class="medical-pw-bar"><div class="medical-pw-fill" id="pwFill"></div></div>
                            <span class="medical-pw-label" id="pwLabel"></span>
                        </div>
                        <?php if (isset($erreurs['password'])): ?>
                        <span class="medical-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['password'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="medical-field">
                        <label class="medical-label" for="confirm">
                            <i class="fa-solid fa-shield"></i> Confirmer
                        </label>
                        <div class="medical-input-wrapper">
                            <span class="medical-input-icon"><i class="fa-solid fa-shield"></i></span>
                            <input type="password" id="confirm" name="confirm" class="medical-input <?= isset($erreurs['confirm']) ? 'medical-error' : '' ?>"
                                   placeholder="Répéter le mot de passe" autocomplete="new-password" required>
                        </div>
                        <?php if (isset($erreurs['confirm'])): ?>
                        <span class="medical-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['confirm'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Bouton d'inscription -->
                <button type="submit" class="medical-btn" id="submitBtn">
                    <i class="fa-solid fa-user-plus"></i> Créer mon compte
                </button>
                
                <!-- Mentions légales -->
                <div class="medical-legal">
                    En créant un compte, vous acceptez nos 
                    <a href="#">conditions d'utilisation</a> et notre 
                    <a href="#">politique de confidentialité</a>.
                </div>
                
            </form>
        </div>
    </div>
</div>

<script>
// Toggle mot de passe
const pwInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');
const toggleBtn = document.getElementById('togglePw');

if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
        const isPassword = pwInput.type === 'password';
        pwInput.type = isPassword ? 'text' : 'password';
        eyeIcon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
    });
}

// Filtre téléphone (chiffres et + uniquement)
const telInput = document.getElementById('telephone');
if (telInput) {
    telInput.addEventListener('keypress', (e) => {
        if (!/[0-9+]/.test(e.key)) e.preventDefault();
    });
    telInput.addEventListener('input', () => {
        telInput.value = telInput.value.replace(/[^0-9+]/g, '');
    });
}

// Indicateur de force du mot de passe
if (pwInput) {
    pwInput.addEventListener('input', () => {
        const val = pwInput.value;
        let score = 0;
        if (val.length >= 8) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        
        const levels = [
            { width: '0%', color: '#e2e8f0', text: '' },
            { width: '25%', color: '#ef4444', text: 'Très faible' },
            { width: '50%', color: '#f59e0b', text: 'Faible' },
            { width: '75%', color: '#0052a0', text: 'Moyen' },
            { width: '100%', color: '#00a86b', text: 'Fort' }
        ];
        
        const fill = document.getElementById('pwFill');
        const label = document.getElementById('pwLabel');
        
        if (fill) {
            fill.style.width = levels[score].width;
            fill.style.backgroundColor = levels[score].color;
        }
        if (label) {
            label.textContent = levels[score].text;
            label.style.color = levels[score].color;
        }
    });
}

// Validation avant soumission
const form = document.getElementById('inscriptionForm');
if (form) {
    form.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm').value;
        
        if (password !== confirm) {
            e.preventDefault();
            const confirmField = document.getElementById('confirm');
            confirmField.style.borderColor = '#ef4444';
            confirmField.focus();
            
            let errorMsg = document.getElementById('confirmErrorMsg');
            if (!errorMsg) {
                errorMsg = document.createElement('span');
                errorMsg.id = 'confirmErrorMsg';
                errorMsg.className = 'medical-error-msg';
                confirmField.parentElement.parentElement.appendChild(errorMsg);
            }
            errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Les mots de passe ne correspondent pas';
            return;
        }
        
        // Désactiver le bouton pendant l'envoi
        const btn = document.getElementById('submitBtn');
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Création en cours...';
            btn.disabled = true;
        }, 10);
    });
}
</script>
</body>
</html>