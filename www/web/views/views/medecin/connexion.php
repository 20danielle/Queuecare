<?php
// views/medecin/connexion.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Médecin — QueueCare</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/medecin.css">
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
                Bon retour,<br>
                <em>Docteur</em>
            </h1>
            
            <p class="med-subtitle med-animate-delay-1">
                Accédez à votre espace pour gérer vos consultations, 
                votre planning et suivre vos patients en temps réel.
            </p>
            
            <ul class="med-features-list med-animate-delay-2">
                <li><i class="fa-solid fa-circle-check"></i> Consultation de votre planning</li>
                <li><i class="fa-solid fa-circle-check"></i> Gestion des consultations en temps réel</li>
                <li><i class="fa-solid fa-circle-check"></i> Suivi des patients en file d'attente</li>
                <li><i class="fa-solid fa-circle-check"></i> Statistiques de votre activité</li>
                <li><i class="fa-solid fa-circle-check"></i> Interface intuitive et ergonomique</li>
            </ul>
        </div>
        <div class="med-doctor-icon">
            <i class="fa-solid fa-user-doctor"></i>
        </div>
    </div>
    
    <!-- Panneau droit - Formulaire de connexion -->
    <div class="med-form-container">
        <div class="med-form-card med-animate">
            
            <div class="med-form-header">
                <a href="accueil.php" class="med-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                </a>
                <div class="med-badge">
                    <i class="fa-solid fa-shield-halved"></i> Accès sécurisé
                </div>
                <h2 class="med-form-title">Se connecter</h2>
                <p class="med-subtitle" style="color: var(--primary-text-light); font-size: 0.85rem;">
                    Pas encore de compte ?
                    <a href="medecin.php?action=inscription" style="color: var(--primary-blue); text-decoration: none; font-weight: 600;">
                        S'inscrire <i class="fa-solid fa-arrow-right fa-xs"></i>
                    </a>
                </p>
            </div>
            
            <!-- Messages d'alerte -->
            <?php if (!empty($erreurs['global'])): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($erreurs['global']) ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['inscription']) && $_GET['inscription'] === 'succes'): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Compte créé avec succès ! Veuillez vous connecter.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['deconnecte']) && $_GET['deconnecte'] == 1): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Vous avez été déconnecté avec succès.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-clock"></i>
                Session expirée après 15 minutes d'inactivité. Veuillez vous reconnecter.
            </div>
            <?php endif; ?>
            
            <form method="POST" action="medecin.php?action=connexion" id="connexionForm">
                
                <!-- Email -->
                <div class="med-field">
                    <label class="med-label" for="email">
                        <i class="fa-solid fa-envelope"></i> Adresse email
                    </label>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="med-input"
                               placeholder="medecin@hopital.cm"
                               value="<?= htmlspecialchars($ancien_email ?? '') ?>"
                               autocomplete="email" required autofocus>
                    </div>
                </div>
                
                <!-- Mot de passe -->
                <div class="med-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label class="med-label" for="password" style="margin-bottom: 0;">
                            <i class="fa-solid fa-lock"></i> Mot de passe
                        </label>
                        <a href="#" class="med-forgot-link" style="font-size: 0.7rem; color: var(--primary-text-light); text-decoration: none;">
                            <i class="fa-solid fa-rotate-left"></i> Mot de passe oublié ?
                        </a>
                    </div>
                    <div class="med-input-wrapper">
                        <span class="med-input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="med-input"
                               placeholder="Votre mot de passe" autocomplete="current-password" required>
                        <button type="button" class="med-pw-toggle" id="togglePw">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Se souvenir de moi -->
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 28px;">
                    <input type="checkbox" id="remember" name="remember" class="med-checkbox">
                    <label for="remember" class="med-checkbox-label">Se souvenir de moi</label>
                </div>
                
                <!-- Bouton de connexion -->
                <button type="submit" class="med-btn" id="submitBtn">
                    <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                </button>
                
                <!-- Séparateur -->
                <div class="med-divider">
                    <span>ou</span>
                </div>
                
                <!-- Informations d'accès -->
                <div class="med-info-box">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Accès réservé aux médecins enregistrés</span>
                </div>
                
                <!-- Lien retour accueil -->
                <div class="med-footer-links">
                    <a href="accueil.php">
                        <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                    </a>
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

// Désactiver le bouton pendant la soumission
const form = document.getElementById('connexionForm');
if (form) {
    form.addEventListener('submit', () => {
        const btn = document.getElementById('submitBtn');
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Connexion en cours...';
        btn.disabled = true;
    });
}
</script>

<style>
/* Styles spécifiques pour la page de connexion médecin */
.med-forgot-link:hover {
    color: var(--primary-blue);
}

.med-checkbox {
    width: 16px;
    height: 16px;
    accent-color: var(--primary-green);
    cursor: pointer;
}

.med-checkbox-label {
    font-size: 0.85rem;
    color: var(--primary-text-light);
    cursor: pointer;
}

.med-footer-links {
    text-align: center;
    margin-top: 16px;
}

.med-footer-links a {
    font-size: 0.8rem;
    color: var(--primary-text-light);
    text-decoration: none;
    transition: color 0.2s;
}

.med-footer-links a:hover {
    color: var(--primary-blue);
}

.med-footer-links i {
    margin-right: 6px;
}
</style>
</body>
</html>