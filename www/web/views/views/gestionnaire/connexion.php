<?php
// views/gestionnaire/connexion.php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — QueueCare</title>
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
                Bon retour<br>
                <em>parmi nous</em>
            </h1>
            
            <p class="medical-subtitle medical-animate-delay-1">
                Connectez-vous pour accéder à votre tableau de bord 
                et gérer vos consultations en toute simplicité.
            </p>
            
            <ul class="medical-features medical-animate-delay-2">
                <li><i class="fa-solid fa-circle-check"></i> Suivi en temps réel de la file</li>
                <li><i class="fa-solid fa-circle-check"></i> Appel et gestion des patients</li>
                <li><i class="fa-solid fa-circle-check"></i> Déclaration des urgences</li>
                <li><i class="fa-solid fa-circle-check"></i> Rapports &amp; statistiques du jour</li>
                <li><i class="fa-solid fa-circle-check"></i> Génération de QR code</li>
            </ul>
        </div>
        <div class="medical-doctor-icon">
            <i class="fa-solid fa-user-doctor"></i>
        </div>
    </div>
    
    <!-- Panel droit - Formulaire de connexion -->
    <div class="medical-form-container">
        <div class="medical-form-card medical-animate">
            
            <div class="medical-form-header">
                <a href="accueil.php" class="medical-back-link">
                    <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                </a>
                <div class="medical-badge">
                    <i class="fa-solid fa-shield-halved"></i> Accès sécurisé
                </div>
                <h2 class="medical-form-title">Se connecter</h2>
                <p class="medical-form-subtitle">
                    Pas encore de compte ? 
                    <a href="gestionnaire.php?action=inscription">S'inscrire</a>
                </p>
            </div>
            
            <!-- Messages d'alerte -->
            <?php if (!empty($erreurs['global'])): ?>
            <div class="medical-alert medical-alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($erreurs['global']) ?>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['inscription']) && $_GET['inscription'] === 'succes'): ?>
            <div class="medical-alert medical-alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Compte créé avec succès ! Veuillez vous connecter.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['deconnecte']) && $_GET['deconnecte'] == 1): ?>
            <div class="medical-alert medical-alert-success">
                <i class="fa-solid fa-circle-check"></i>
                Vous avez été déconnecté avec succès.
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
            <div class="medical-alert medical-alert-error">
                <i class="fa-solid fa-clock"></i>
                Session expirée après 15 minutes d'inactivité. Veuillez vous reconnecter.
            </div>
            <?php endif; ?>
            
            <!-- Formulaire de connexion -->
            <form method="POST" action="gestionnaire.php?action=connexion" id="connexionForm">
                
                <!-- Email -->
                <div class="medical-field">
                    <label class="medical-label" for="email">
                        <i class="fa-solid fa-envelope"></i> Adresse email
                    </label>
                    <div class="medical-input-wrapper">
                        <span class="medical-input-icon"><i class="fa-solid fa-envelope"></i></span>
                        <input type="email" id="email" name="email" class="medical-input"
                               placeholder="exemple@hopital.cm"
                               value="<?= htmlspecialchars($ancien_email ?? '') ?>"
                               autocomplete="email" required autofocus>
                    </div>
                </div>
                
                <!-- Mot de passe -->
                <div class="medical-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <label class="medical-label" for="password" style="margin-bottom: 0;">
                            <i class="fa-solid fa-lock"></i> Mot de passe
                        </label>
                        <a href="#" class="medical-forgot-link">
                            <i class="fa-solid fa-rotate-left"></i> Mot de passe oublié ?
                        </a>
                    </div>
                    <div class="medical-input-wrapper">
                        <span class="medical-input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" id="password" name="password" class="medical-input"
                               placeholder="Votre mot de passe" autocomplete="current-password" required>
                        <button type="button" class="medical-pw-toggle" id="togglePw">
                            <i class="fa-solid fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Se souvenir de moi -->
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 28px;">
                    <input type="checkbox" id="remember" name="remember" class="medical-checkbox">
                    <label for="remember" class="medical-checkbox-label">Se souvenir de moi</label>
                </div>
                
                <!-- Bouton de connexion -->
                <button type="submit" class="medical-btn" id="submitBtn">
                    <i class="fa-solid fa-right-to-bracket"></i> Se connecter
                </button>
                
                <!-- Séparateur -->
                <div class="medical-divider">
                    <span>ou</span>
                </div>
                
                <!-- Informations d'accès -->
                <div class="medical-info-box">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Accès réservé aux gestionnaires autorisés</span>
                </div>
                
                <!-- Lien retour accueil -->
                <div class="medical-footer-links">
                    <a href="accueil.php">
                        <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
                    </a>
                </div>
                
            </form>
        </div>
    </div>
</div>

<style>
/* Styles spécifiques à la page de connexion */
.medical-forgot-link {
    font-size: 0.7rem;
    color: var(--medical-text-light);
    text-decoration: none;
    transition: color 0.2s;
}

.medical-forgot-link:hover {
    color: var(--medical-blue);
}

.medical-checkbox {
    width: 16px;
    height: 16px;
    accent-color: var(--medical-green);
    cursor: pointer;
}

.medical-checkbox-label {
    font-size: 0.85rem;
    color: var(--medical-text-light);
    cursor: pointer;
}

.medical-divider {
    text-align: center;
    margin: 24px 0 20px;
    position: relative;
    border-top: 1px solid var(--medical-gray-dark);
}

.medical-divider span {
    position: relative;
    top: -12px;
    background: var(--medical-white);
    padding: 0 16px;
    font-size: 0.8rem;
    color: var(--medical-text-light);
}

.medical-info-box {
    text-align: center;
    padding: 14px;
    background: var(--medical-gray);
    border-radius: 12px;
    border: 1px solid var(--medical-gray-dark);
    margin: 20px 0;
}

.medical-info-box i {
    color: var(--medical-blue);
    margin-right: 8px;
}

.medical-info-box span {
    font-size: 0.8rem;
    color: var(--medical-text-light);
}

.medical-footer-links {
    text-align: center;
    margin-top: 16px;
}

.medical-footer-links a {
    font-size: 0.8rem;
    color: var(--medical-text-light);
    text-decoration: none;
    transition: color 0.2s;
}

.medical-footer-links a:hover {
    color: var(--medical-blue);
}

.medical-footer-links i {
    margin-right: 6px;
}
</style>

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
</body>
</html>