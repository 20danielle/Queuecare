<?php
/**
 * views/admin/creer_utilisateur.php
 * Formulaire de création d'un gestionnaire ou d'un médecin par le directeur
 */
$role      = $role ?? 'gestionnaire';
$isMedecin = ($role === 'medecin');
$titre     = $isMedecin ? 'Ajouter un médecin' : 'Ajouter un gestionnaire';
$icone     = $isMedecin ? 'fa-user-doctor'     : 'fa-user-tie';
$couleur   = $isMedecin ? 'var(--blue)'        : 'var(--green)';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titre ?> — QueueCare</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,700|outfit:300,400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="public/css/style.css">
    <link rel="stylesheet" href="public/css/dashboard.css">
    <style>
        /* ── Layout ───────────────────────────────────────────── */
        .cu-wrap {
            max-width: 700px;
            margin: 32px auto;
            padding: 0 16px 60px;
        }

        /* ── Card ─────────────────────────────────────────────── */
        .cu-card {
            background: var(--white);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .cu-card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 22px 28px 18px;
            border-bottom: 1px solid var(--border);
        }

        .cu-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
            background: color-mix(in srgb, <?= $couleur ?> 12%, transparent);
            color: <?= $couleur ?>;
        }

        .cu-card-title {
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--blue-dark);
            margin: 0 0 2px;
        }

        .cu-card-sub {
            font-size: .8rem;
            color: var(--text-muted);
            margin: 0;
        }

        .cu-card-body {
            padding: 28px 28px 32px;
        }

        /* ── Grid ─────────────────────────────────────────────── */
        .cu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .cu-grid.one-col,
        .cu-col-full {
            grid-column: 1 / -1;
        }

        /* ── Field ────────────────────────────────────────────── */
        .cu-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .cu-label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .cu-label .req {
            color: #ef4444;
            margin-left: 2px;
        }

        .cu-input-wrap {
            position: relative;
        }

        .cu-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .85rem;
            pointer-events: none;
        }

        .cu-input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: .92rem;
            color: var(--text);
            background: var(--bg-input, #fafafa);
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
        }

        .cu-input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--blue) 14%, transparent);
            background: var(--white);
        }

        .cu-input.error {
            border-color: #ef4444;
            box-shadow: 0 0 0 3px rgba(239,68,68,.12);
        }

        .cu-select-wrap {
            position: relative;
        }

        .cu-select-wrap .cu-icon {
            left: 13px;
        }

        .cu-select-arrow {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .75rem;
            pointer-events: none;
        }

        select.cu-input {
            appearance: none;
            padding-right: 34px;
            cursor: pointer;
        }

        .cu-pw-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            font-size: .85rem;
            transition: color .15s;
        }

        .cu-pw-btn:hover { color: var(--text); }

        .cu-error-msg {
            font-size: .78rem;
            color: #ef4444;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* ── Alert ────────────────────────────────────────────── */
        .cu-alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: .875rem;
            margin-bottom: 20px;
        }

        .cu-alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        /* ── Divider ──────────────────────────────────────────── */
        .cu-divider {
            height: 1px;
            background: var(--border);
            margin: 20px 0;
        }

        .cu-section-title {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        /* ── Actions ──────────────────────────────────────────── */
        .cu-actions {
            display: flex;
            gap: 12px;
            margin-top: 28px;
            flex-wrap: wrap;
        }

        .cu-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 8px;
            font-family: var(--font-body);
            font-size: .9rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: opacity .15s, transform .1s;
        }

        .cu-btn:active { transform: scale(.98); }

        .cu-btn-primary {
            background: var(--blue);
            color: #fff;
        }

        .cu-btn-primary.medecin { background: var(--blue); }
        .cu-btn-primary.gestionnaire { background: var(--green); }

        .cu-btn-primary:hover { opacity: .88; }

        .cu-btn-secondary {
            background: var(--bg-subtle, #f1f5f9);
            color: var(--text);
            border: 1.5px solid var(--border);
        }

        .cu-btn-secondary:hover { background: var(--border); }

        /* ── Hint (password auto) ─────────────────────────────── */
        .cu-hint {
            font-size: .78rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 2px;
        }

        /* ── Responsive ───────────────────────────────────────── */
        @media (max-width: 560px) {
            .cu-wrap {
                margin: 16px auto;
                padding: 0 10px 40px;
            }

            .cu-card-header {
                padding: 18px 16px 14px;
            }

            .cu-card-body {
                padding: 20px 16px 24px;
            }

            .cu-grid {
                grid-template-columns: 1fr;
            }

            .cu-actions {
                flex-direction: column;
            }

            .cu-btn {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>
<body class="dash-body">

<!-- ── Topbar ─────────────────────────────────────────────────── -->
<header class="topbar">
    <a href="admin.php?action=dashboard" class="topbar-logo">
        <div class="topbar-logo-icon"><i class="fa-solid fa-hospital"></i></div>
        QueueCare
    </a>
    <div class="topbar-sep"></div>
    <a href="admin.php?action=dashboard" class="topbar-logout">
        <i class="fa-solid fa-arrow-left"></i>
        <span class="topbar-logout-label">Retour</span>
    </a>
</header>

<div class="cu-wrap">
    <div class="cu-card anim-fade-up">

        <!-- En-tête ─────────────────────────────────────────── -->
        <div class="cu-card-header">
            <div class="cu-card-icon">
                <i class="fa-solid <?= $icone ?>"></i>
            </div>
            <div>
                <p class="cu-card-title"><?= htmlspecialchars($titre) ?></p>
                <p class="cu-card-sub">Le compte sera immédiatement actif. Communiquez les identifiants à l'utilisateur.</p>
            </div>
        </div>

        <!-- Formulaire ──────────────────────────────────────── -->
        <div class="cu-card-body">

            <?php if (!empty($erreurs['global'])): ?>
            <div class="cu-alert error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <?= htmlspecialchars($erreurs['global']) ?>
            </div>
            <?php endif; ?>

            <form method="POST"
                  action="admin.php?action=creer_utilisateur"
                  style="display:flex;flex-direction:column;gap:0"
                  novalidate>

                <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">

                <!-- ── Identité ─────────────────────────────── -->
                <p class="cu-section-title"><i class="fa-solid fa-id-card" style="margin-right:6px"></i>Identité</p>

                <?php if ($isMedecin): ?>
                <!-- Médecin : nom + prénom sur 2 colonnes -->
                <div class="cu-grid" style="margin-bottom:18px">
                    <div class="cu-field">
                        <label class="cu-label" for="nom">Nom <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-user cu-icon"></i>
                            <input id="nom" type="text" name="nom"
                                   class="cu-input <?= !empty($erreurs['nom']) ? 'error' : '' ?>"
                                   placeholder="Nom de famille"
                                   value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>"
                                   required autocomplete="family-name">
                        </div>
                        <?php if (!empty($erreurs['nom'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['nom']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="cu-field">
                        <label class="cu-label" for="prenom">Prénom <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-user cu-icon"></i>
                            <input id="prenom" type="text" name="prenom"
                                   class="cu-input <?= !empty($erreurs['prenom']) ? 'error' : '' ?>"
                                   placeholder="Prénom"
                                   value="<?= htmlspecialchars($anciens['prenom'] ?? '') ?>"
                                   required autocomplete="given-name">
                        </div>
                        <?php if (!empty($erreurs['prenom'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['prenom']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Spécialité -->
                <div class="cu-grid cu-col-full" style="grid-template-columns:1fr;margin-bottom:18px">
                    <div class="cu-field">
                        <label class="cu-label" for="specialite">Spécialité <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-stethoscope cu-icon"></i>
                            <input id="specialite" type="text" name="specialite"
                                   class="cu-input <?= !empty($erreurs['specialite']) ? 'error' : '' ?>"
                                   placeholder="ex : Cardiologie, Pédiatrie…"
                                   value="<?= htmlspecialchars($anciens['specialite'] ?? '') ?>"
                                   required>
                        </div>
                        <?php if (!empty($erreurs['specialite'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['specialite']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php else: ?>
                <!-- Gestionnaire : nom complet sur toute la largeur -->
                <div class="cu-grid" style="grid-template-columns:1fr;margin-bottom:18px">
                    <div class="cu-field">
                        <label class="cu-label" for="nom">Nom complet <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-user cu-icon"></i>
                            <input id="nom" type="text" name="nom"
                                   class="cu-input <?= !empty($erreurs['nom']) ? 'error' : '' ?>"
                                   placeholder="Nom complet du gestionnaire"
                                   value="<?= htmlspecialchars($anciens['nom'] ?? '') ?>"
                                   required autocomplete="name">
                        </div>
                        <?php if (!empty($erreurs['nom'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['nom']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="cu-divider"></div>

                <!-- ── Contact ──────────────────────────────── -->
                <p class="cu-section-title"><i class="fa-solid fa-address-book" style="margin-right:6px"></i>Contact</p>

                <div class="cu-grid" style="margin-bottom:18px">
                    <div class="cu-field">
                        <label class="cu-label" for="email">Email <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-regular fa-envelope cu-icon"></i>
                            <input id="email" type="email" name="email"
                                   class="cu-input <?= !empty($erreurs['email']) ? 'error' : '' ?>"
                                   placeholder="email@hopital.cm"
                                   value="<?= htmlspecialchars($anciens['email'] ?? '') ?>"
                                   required autocomplete="email">
                        </div>
                        <?php if (!empty($erreurs['email'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['email']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="cu-field">
                        <label class="cu-label" for="telephone">Téléphone <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-phone cu-icon"></i>
                            <input id="telephone" type="tel" name="telephone"
                                   class="cu-input <?= !empty($erreurs['telephone']) ? 'error' : '' ?>"
                                   placeholder="+237 6xx xxx xxx"
                                   value="<?= htmlspecialchars($anciens['telephone'] ?? '') ?>"
                                   required autocomplete="tel">
                        </div>
                        <?php if (!empty($erreurs['telephone'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['telephone']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cu-divider"></div>

                <!-- ── Affectation (gestionnaire uniquement) ── -->
                <?php if (!$isMedecin): ?>
                <p class="cu-section-title"><i class="fa-solid fa-layer-group" style="margin-right:6px"></i>Affectation</p>

                <div class="cu-grid" style="grid-template-columns:1fr;margin-bottom:18px">
                    <div class="cu-field">
                        <label class="cu-label" for="sous_service_id">Sous-service <span class="req">*</span></label>
                        <div class="cu-input-wrap cu-select-wrap">
                            <i class="fa-solid fa-layer-group cu-icon"></i>
                            <select id="sous_service_id" name="sous_service_id"
                                    class="cu-input <?= !empty($erreurs['sous_service_id']) ? 'error' : '' ?>">
                                <option value="0">— Sélectionner un sous-service —</option>
                                <?php foreach ($sousServices as $ss): ?>
                                <option value="<?= $ss['id'] ?>"
                                    <?= ((int)($anciens['sousServiceId'] ?? 0) === (int)$ss['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ss['nom'] ?? $ss['libelle'] ?? '') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down cu-select-arrow"></i>
                        </div>
                        <?php if (!empty($erreurs['sous_service_id'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['sous_service_id']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cu-divider"></div>
                <?php endif; ?>

                <!-- ── Mot de passe ──────────────────────────── -->
                <p class="cu-section-title"><i class="fa-solid fa-shield-halved" style="margin-right:6px"></i>Mot de passe</p>

                <div class="cu-grid" style="margin-bottom:8px">
                    <div class="cu-field">
                        <label class="cu-label" for="password">Mot de passe <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-lock cu-icon"></i>
                            <input id="password" type="password" name="password"
                                   class="cu-input <?= !empty($erreurs['password']) ? 'error' : '' ?>"
                                   placeholder="Min. 8 caractères" required autocomplete="new-password">
                            <button type="button" class="cu-pw-btn" onclick="togglePw('password',this)" title="Afficher/Masquer">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($erreurs['password'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['password']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="cu-field">
                        <label class="cu-label" for="confirm">Confirmer <span class="req">*</span></label>
                        <div class="cu-input-wrap">
                            <i class="fa-solid fa-lock cu-icon"></i>
                            <input id="confirm" type="password" name="confirm"
                                   class="cu-input <?= !empty($erreurs['confirm']) ? 'error' : '' ?>"
                                   placeholder="Répéter" required autocomplete="new-password">
                            <button type="button" class="cu-pw-btn" onclick="togglePw('confirm',this)" title="Afficher/Masquer">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                        <?php if (!empty($erreurs['confirm'])): ?>
                        <span class="cu-error-msg"><i class="fa-solid fa-circle-exclamation"></i><?= htmlspecialchars($erreurs['confirm']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="cu-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Ce mot de passe sera communiqué à l'utilisateur pour sa première connexion.
                </p>

                <!-- ── Boutons ───────────────────────────────── -->
                <div class="cu-actions">
                    <button type="submit" class="cu-btn cu-btn-primary <?= $role ?>">
                        <i class="fa-solid fa-user-plus"></i>
                        Créer le compte
                    </button>
                    <a href="admin.php?action=dashboard&tab=<?= $role === 'medecin' ? 'medecins' : 'gestionnaires' ?>"
                       class="cu-btn cu-btn-secondary">
                        <i class="fa-solid fa-xmark"></i>
                        Annuler
                    </a>
                </div>

            </form>
        </div><!-- /.cu-card-body -->
    </div><!-- /.cu-card -->
</div><!-- /.cu-wrap -->

<script>
function togglePw(id, btn) {
    const inp = document.getElementById(id);
    inp.type  = inp.type === 'password' ? 'text' : 'password';
    btn.querySelector('i').className =
        inp.type === 'password' ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
}

/* Validation légère côté client avant envoi */
document.querySelector('form').addEventListener('submit', function(e) {
    const pw  = document.getElementById('password').value;
    const cfm = document.getElementById('confirm').value;
    if (pw.length < 8) {
        e.preventDefault();
        document.getElementById('password').classList.add('error');
        document.getElementById('password').focus();
        return;
    }
    if (pw !== cfm) {
        e.preventDefault();
        document.getElementById('confirm').classList.add('error');
        document.getElementById('confirm').focus();
    }
});
</script>
</body>
</html>