<?php
// views/medecin/inscription.php
// Version mono-service - Plus de sélection d'hôpital, uniquement le sous-service
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription Médecin — QueueCare</title>
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/medecin.css">
    <style>
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin: 10px auto;
            display: block;
            border: 3px solid #0052a0;
            background: #f0f4f8;
        }
        .photo-upload-area {
            text-align: center;
            margin-bottom: 20px;
        }
        .photo-upload-area input {
            display: none;
        }
        .photo-upload-label {
            display: inline-block;
            padding: 8px 16px;
            background: #0052a0;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        .photo-upload-label:hover {
            background: #003d7a;
        }
    </style>
</head>
<body>
<div class="med-auth-wrapper">
    
    <!-- Panneau gauche - Présentation -->
    <div class="med-presentation">
        <div class="med-presentation-inner">
            <div class="med-logo med-animate">
                <div class="med-logo-icon">
                    <i class="fa-solid fa-user-doctor"></i>
                </div>
                <div class="med-logo-text">QueueCare</div>
            </div>
            
            <h1 class="med-title-big med-animate-delay-1">
                Rejoignez<br>
                <em>l'équipe médicale</em>
            </h1>
            
            <p class="med-subtitle med-animate-delay-1">
                Créez votre espace médecin pour gérer vos consultations, 
                votre planning et suivre vos patients en temps réel.
            </p>
            
            <ul class="med-features-list med-animate-delay-2">
                <li><i class="fa-solid fa-circle-check"></i> Tableau de bord personnalisé</li>
                <li><i class="fa-solid fa-circle-check"></i> Gestion des consultations en temps réel</li>
                <li><i class="fa-solid fa-circle-check"></i> Planning hebdomadaire intégré</li>
                <li><i class="fa-solid fa-circle-check"></i> Statistiques et indicateurs de performance</li>
                <li><i class="fa-solid fa-circle-check"></i> Suivi des patients en file d'attente</li>
            </ul>
        </div>
        <div class="med-doctor-icon">
            <i class="fa-solid fa-user-doctor"></i>
        </div>
    </div>
    
    <!-- Panneau droit - Formulaire d'inscription -->
    <div class="med-form-container">
        <div class="med-form-card med-animate">
            
            <div class="med-form-header">
                <a href="accueil.php" class="med-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                </a>
                <div class="med-badge">
                    <i class="fa-solid fa-user-doctor"></i> Espace Médecin
                </div>
                <h2 class="med-form-title">Créer un compte</h2>
                <p class="med-subtitle" style="color: var(--primary-text-light); font-size: 0.85rem;">
                    Déjà inscrit ?
                    <a href="medecin.php?action=connexion" style="color: var(--primary-blue); text-decoration: none; font-weight: 600;">
                        Se connecter <i class="fa-solid fa-arrow-right fa-xs"></i>
                    </a>
                </p>
            </div>
            
            <!-- Messages d'erreur -->
            <?php if (!empty($erreurs['global'])): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($erreurs['global']) ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="medecin.php?action=inscription" id="medecinForm" enctype="multipart/form-data">
                
                <!-- Photo -->
                <div class="photo-upload-area">
                    <img id="photoPreview" class="photo-preview" src="public/images/default-avatar.png" alt="Photo de profil">
                    <div>
                        <label class="photo-upload-label">
                            <i class="fa-solid fa-camera"></i> Choisir une photo
                            <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/jpg">
                        </label>
                    </div>
                    <small style="color: #6b83a8; font-size: 0.7rem;">Formats acceptés: JPG, PNG (max 2MB)</small>
                    <?php if (isset($erreurs['photo'])): ?>
                    <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['photo'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Nom et Prénom sur la même ligne -->
                <div class="med-row">
                    <div class="med-field">
                        <label class="med-label" for="nom">
                            <i class="fa-solid fa-user"></i> Nom *
                        </label>
                        <div class="med-input-wrapper">
                            <span class="med-input-icon"><i class="fa-solid fa-user"></i></span>
                            <input type="text" id="nom" name="nom" class="med-input <?= isset($erreurs['nom']) ? 'error' : '' ?>"
                                   placeholder="Dupont"
                                   value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>" required>
                        </div>
                        <?php if (isset($erreurs['nom'])): ?>
                        <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['nom'] ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="med-field">
                        <label class="med-label" for="prenom">
                            <i class="fa-solid fa-user"></i> Prénom *
                        </label>
                        <div class="med-input-wrapper">
                            <span class="med-input-icon"><i class="fa-solid fa-user"></i></span>
                            <input type="text" id="prenom" name="prenom" class="med-input <?= isset($erreurs['prenom']) ? 'error' : '' ?>"
                                   placeholder="Jean"
                                   value="<?= htmlspecialchars($anciens['prenom'] ?? '') ?>" required>
                        </div>
                        <?php if (isset($erreurs['prenom'])): ?>
                        <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['prenom'] ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Téléphone -->
                <div class="med-field">
                    <label class="med-label" for="telephone">
                        <i class="fa-solid fa-phone"></i> Téléphone *
                    </label>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-phone"></i></span>
                        <input type="tel" id="telephone" name="telephone" class="med-input <?= isset($erreurs['telephone']) ? 'error' : '' ?>"
                               placeholder="+237699123456"
                               value="<?= htmlspecialchars($anciens['telephone'] ?? '') ?>" required>
                    </div>
                    <?php if (isset($erreurs['telephone'])): ?>
                    <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['telephone'] ?></span>
                    <?php endif; ?>
                    <small class="med-small"><i class="fa-solid fa-circle-info"></i> Chiffres et le signe + uniquement</small>
                </div>
                
                <!-- Email -->
                <div class="med-field">
                    <label class="med-label" for="email">
                        <i class="fa-solid fa-envelope"></i> Email *
                    </label>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="med-input <?= isset($erreurs['email']) ? 'error' : '' ?>"
                               placeholder="medecin@hopital.cm"
                               value="<?= htmlspecialchars($anciens['email'] ?? '') ?>" required>
                    </div>
                    <?php if (isset($erreurs['email'])): ?>
                    <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['email'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Sous-service / Spécialité (direct, sans sélection d'hôpital) -->
                <div class="med-field">
                    <label class="med-label" for="sous_service_id">
                        <i class="fa-solid fa-stethoscope"></i> Spécialité / Sous-service *
                    </label>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-stethoscope"></i></span>
                        <select id="sous_service_id" name="sous_service_id" class="med-input med-select <?= isset($erreurs['sous_service_id']) ? 'error' : '' ?>" required>
                            <option value="" disabled <?= empty($anciens['specialiteId']) ? 'selected' : '' ?>>Sélectionner votre sous-service</option>
                            <?php foreach ($sousServices as $ss): ?>
                            <option value="<?= (int)$ss['id'] ?>" <?= (int)($anciens['specialiteId'] ?? 0) === (int)$ss['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($ss['nom']) ?>
                                <?php if (!empty($ss['capacite_horaire'])): ?>
                                (<?= $ss['capacite_horaire'] ?> consultations/heure)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="med-select-arrow"><i class="fa-solid fa-chevron-down"></i></span>
                    </div>
                    <?php if (isset($erreurs['sous_service_id'])): ?>
                    <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['sous_service_id'] ?></span>
                    <?php endif; ?>
                    <small class="med-small"><i class="fa-solid fa-circle-info"></i> Sélectionnez votre spécialité médicale</small>
                </div>
                
                <!-- Mot de passe -->
                <div class="med-field">
                    <label class="med-label" for="password">
                        <i class="fa-solid fa-lock"></i> Mot de passe *
                    </label>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="med-input <?= isset($erreurs['password']) ? 'error' : '' ?>"
                               placeholder="Min. 8 car., 1 maj., 1 chiffre" autocomplete="new-password" required>
                        <button type="button" class="med-pw-toggle" id="togglePw">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                    <div class="med-pw-strength">
                        <div class="med-pw-bar"><div class="med-pw-fill" id="pwFill"></div></div>
                        <span class="med-pw-label" id="pwLabel"></span>
                    </div>
                    <?php if (isset($erreurs['password'])): ?>
                    <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['password'] ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Confirmation mot de passe -->
                <div class="med-field">
                    <label class="med-label" for="confirm">
                        <i class="fa-solid fa-shield-halved"></i> Confirmer le mot de passe *
                    </label>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-shield-halved"></i></span>
                        <input type="password" id="confirm" name="confirm" class="med-input <?= isset($erreurs['confirm']) ? 'error' : '' ?>"
                               placeholder="Répéter le mot de passe" autocomplete="new-password" required>
                    </div>
                    <?php if (isset($erreurs['confirm'])): ?>
                    <span class="med-error-msg"><i class="fa-solid fa-circle-exclamation"></i> <?= $erreurs['confirm'] ?></span>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="med-btn" id="submitBtn">
                    <i class="fa-solid fa-user-plus"></i> Créer mon compte
                </button>
                
            </form>
            
            <div class="med-legal">
                En vous inscrivant, vous acceptez les 
                <a href="#">conditions d'utilisation</a> et la 
                <a href="#">politique de confidentialité</a>.
            </div>
            
        </div>
    </div>
</div>

<script>
// Aperçu de la photo
const photoInput = document.getElementById('photoInput');
const photoPreview = document.getElementById('photoPreview');

photoInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            photoPreview.src = event.target.result;
        };
        reader.readAsDataURL(file);
    }
});

// Toggle mot de passe
const pwInput = document.getElementById('password');
const eyeIcon = document.getElementById('eyeIcon');
document.getElementById('togglePw').addEventListener('click', () => {
    const isPassword = pwInput.type === 'password';
    pwInput.type = isPassword ? 'text' : 'password';
    eyeIcon.className = isPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
});

// Indicateur de force du mot de passe
pwInput.addEventListener('input', function() {
    const val = this.value;
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    
    const levels = [
        { width: '0%', color: '#e2e8f0', text: '' },
        { width: '25%', color: '#ef4444', text: 'Très faible' },
        { width: '50%', color: '#f59e0b', text: 'Faible' },
        { width: '75%', color: '#3b82f6', text: 'Moyen' },
        { width: '100%', color: '#00a86b', text: 'Fort' }
    ];
    
    document.getElementById('pwFill').style.width = levels[score].width;
    document.getElementById('pwFill').style.backgroundColor = levels[score].color;
    document.getElementById('pwLabel').textContent = levels[score].text;
    document.getElementById('pwLabel').style.color = levels[score].color;
});

// Filtre téléphone
const telInput = document.getElementById('telephone');
telInput.addEventListener('input', function() {
    this.value = this.value.replace(/[^0-9+]/g, '');
});

// Validation avant soumission
document.getElementById('medecinForm').addEventListener('submit', function(e) {
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
            errorMsg.className = 'med-error-msg';
            confirmField.parentElement.parentElement.appendChild(errorMsg);
        }
        errorMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Les mots de passe ne correspondent pas';
        return;
    }
    
    const btn = document.getElementById('submitBtn');
    setTimeout(() => {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Création en cours...';
        btn.disabled = true;
    }, 50);
});
</script>
</body>
</html>