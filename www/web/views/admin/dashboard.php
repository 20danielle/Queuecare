<?php
/**
 * views/admin/dashboard.php
 * Variables injectées par AdminController::afficherDashboard() :
 *   $hopital        array|false
 *   $gestionnaires  array
 *   $medecins       array
 *   $sousServices   array
 *   $setupDone      bool
 *   $nouveauUser    array|null
 */

$nomHopital = $hopital['nom_hopital'] ?? __('my_hospital_default');
$nomAdmin   = AuthHelper::getUserNom();

// Onglet actif
$activeTab = $_GET['tab'] ?? 'hopital';
$validTabs = ['hopital', 'gestionnaires', 'medecins', 'sous_services', 'planning', 'statistiques'];
// Onglet consultations toujours accessible (formulaire d'activation si pas encore médecin)
$isAdminMedecin = \AuthHelper::estAdminMedecin();
$validTabs[] = 'consultations_medecin';
if (!in_array($activeTab, $validTabs)) $activeTab = 'hopital';

// Messages
$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$successMap = [
    'hopital_sauvegarde'  => ['icon' => 'fa-check-circle',    'txt' => __('msg_hopital_sauvegarde')],
    'utilisateur_cree'    => ['icon' => 'fa-user-check',      'txt' => __('msg_utilisateur_cree')],
    'statut_mis_a_jour'   => ['icon' => 'fa-toggle-on',       'txt' => __('msg_statut_mis_a_jour')],
    'ss_cree'             => ['icon' => 'fa-check-circle',    'txt' => __('msg_ss_cree')],
    'ss_modifie'          => ['icon' => 'fa-check-circle',    'txt' => __('msg_ss_modifie')],
    'ss_supprime'         => ['icon' => 'fa-trash-alt',       'txt' => __('msg_ss_supprime')],
    'ss_statut_maj'       => ['icon' => 'fa-toggle-on',       'txt' => __('msg_ss_statut_maj')],
];
$errorMap = [
    'nom_hopital_requis'       => __('err_nom_hopital_requis'),
    'champs_vides'             => __('err_champs_vides'),
    'email_invalide'           => __('err_email_invalide'),
    'email_existe'             => __('err_email_existe'),
    'role_invalide'            => __('err_role_invalide'),
    'creation_echouee'         => __('err_creation_echouee'),
    'sauvegarde_echouee'       => __('err_sauvegarde_echouee'),
    'id_invalide'              => __('err_id_invalide'),
    'ss_nom_requis'            => __('err_ss_nom_requis'),
    'ss_nom_existe'            => __('err_ss_nom_existe'),
    'ss_donnees_invalides'     => __('err_ss_donnees_invalides'),
    'ss_echec'                 => __('err_ss_echec'),
    'ss_suppression_impossible'=> ($_SESSION['ss_error_msg'] ?? __('err_ss_suppression_imp')),
];

$successData = $successMap[$success] ?? null;
$errorMsg    = $errorMap[$error]     ?? '';
unset($_SESSION['ss_error_msg']);

// Compteurs stats
$nbActifsSS = count(array_filter($sousServices, fn($s) => $s['statut'] === 'actif'));
?>
<!DOCTYPE html>
<html lang="<?= \LangHelper::getLang() ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('admin_title_prefix') ?> <?= htmlspecialchars($nomHopital) ?></title>
  <link rel="icon" type="image/png" href="public/img/favicon-32.png">
  <link rel="icon" type="image/png" sizes="64x64" href="public/img/favicon-64.png">
  <link rel="stylesheet" href="vendor/fontawesome/css/all.min.css">
  <link rel="stylesheet" href="public/css/admin.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
<?php if (!empty($_SESSION['must_reset_password'])): include __DIR__ . '/../partials/modal_reset_password.php'; endif; ?>
<div class="adm-shell">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="adm-sidebar" id="admSidebar">
    <div class="adm-sidebar-brand">
      <div class="brand-icon"><img src="public/img/logo-queuecare-icon.png" alt="QueueCare"></div>
      <div>
        <div class="brand-name">QueueCare</div>
        <div class="brand-sub"><?= __('hospital') ?></div>
      </div>
    </div>

    <ul class="adm-nav">
      <li class="adm-nav-section"><?= __('navigation_label') ?></li>

      <li>
        <a href="admin.php?tab=hopital" class="<?= $activeTab === 'hopital' ? 'active' : '' ?>">
          <i class="fas fa-building"></i> <?= __('hospital_config') ?>
        </a>
      </li>
      <li>
        <a href="admin.php?tab=gestionnaires" class="<?= $activeTab === 'gestionnaires' ? 'active' : '' ?>">
          <i class="fas fa-user-tie"></i> <?= __('managers') ?>
          <span style="margin-left:auto;background:rgba(255,255,255,.15);border-radius:10px;padding:.1rem .45rem;font-size:.7rem;"><?= count($gestionnaires) ?></span>
        </a>
      </li>
      <li>
        <a href="admin.php?tab=medecins" class="<?= $activeTab === 'medecins' ? 'active' : '' ?>">
          <i class="fas fa-user-md"></i> <?= __('doctors') ?>
          <span style="margin-left:auto;background:rgba(255,255,255,.15);border-radius:10px;padding:.1rem .45rem;font-size:.7rem;"><?= count($medecins) ?></span>
        </a>
      </li>
      <li>
        <a href="admin.php?tab=sous_services" class="<?= $activeTab === 'sous_services' ? 'active' : '' ?>">
          <i class="fas fa-stethoscope"></i> <?= __('sub_service') ?>s
          <span style="margin-left:auto;background:rgba(255,255,255,.15);border-radius:10px;padding:.1rem .45rem;font-size:.7rem;"><?= count($sousServices) ?></span>
        </a>
      </li>
      <li>
        <a href="admin.php?tab=planning" class="<?= $activeTab === 'planning' ? 'active' : '' ?>">
          <i class="fas fa-calendar-alt"></i> <?= __('nav_planning') ?>
        </a>
      </li>
      <li>
        <a href="admin.php?tab=statistiques" class="<?= $activeTab === 'statistiques' ? 'active' : '' ?>">
          <i class="fas fa-chart-line"></i> <?= __('statistics') ?>
        </a>
      </li>
      <li>
        <a href="admin.php?tab=consultations_medecin" class="<?= $activeTab === 'consultations_medecin' ? 'active' : '' ?>" style="color:#0052a0;font-weight:700;">
          <i class="fas fa-stethoscope"></i> <?= __('my_consultations_nav') ?><?= $isAdminMedecin ? '' : ' <small style="font-size:.65rem;background:#f59e0b;color:#fff;border-radius:4px;padding:1px 4px;">SETUP</small>' ?>
        </a>
      </li>

      <li class="adm-nav-section"><?= __('account_label') ?></li>
      <li class="nav-logout">
        <a href="index.php?action=logout">
          <i class="fas fa-sign-out-alt"></i> <?= __('sidebar_logout') ?>
        </a>
      </li>
    </ul>

    <div class="adm-sidebar-footer">QueueCare &copy; <?= date('Y') ?></div>
  </aside>

  <!-- Overlay mobile -->
  <div class="adm-overlay" id="admOverlay" onclick="closeSidebar()"></div>

  <!-- ═══════════ MAIN ═══════════ -->
  <div class="adm-main">

    <!-- Topbar -->
    <header class="adm-topbar">
      <div class="adm-topbar-left">
        <button class="adm-hamburger" id="admHamburger" onclick="toggleSidebar()" aria-label="<?= __('menu') ?>">
          <i class="fas fa-bars"></i>
        </button>
        <div>
          <div class="page-title"><?= __('dashboard') ?></div>
          <div class="breadcrumb-text"><?= htmlspecialchars($nomHopital) ?></div>
        </div>
      </div>
      <div class="adm-topbar-right">
        <!-- Sélecteur de langue -->
        <?php $currentLang = \LangHelper::getLang(); ?>
        <div style="display:flex;align-items:center;background:#f1f5f9;border-radius:10px;padding:3px;gap:2px;margin-right:14px;border:1px solid #e2e8f0">
          <button type="button" onclick="adminChangerLangue('fr')" title="<?= __('lang_fr_full') ?>"
                  style="display:flex;align-items:center;gap:5px;padding:5px 12px;border:none;border-radius:7px;font-size:.8rem;
                         cursor:pointer;font-family:inherit;transition:all .2s;
                         background:<?= $currentLang==='fr'?'#fff':'transparent' ?>;
                         color:<?= $currentLang==='fr'?'#1e40af':'#64748b' ?>;
                         font-weight:<?= $currentLang==='fr'?'700':'500' ?>;
                         box-shadow:<?= $currentLang==='fr'?'0 1px 4px rgba(0,0,0,.12)':'none' ?>">
            🇫🇷 <span>FR</span>
          </button>
          <button type="button" onclick="adminChangerLangue('en')" title="<?= __('lang_en_full') ?>"
                  style="display:flex;align-items:center;gap:5px;padding:5px 12px;border:none;border-radius:7px;font-size:.8rem;
                         cursor:pointer;font-family:inherit;transition:all .2s;
                         background:<?= $currentLang==='en'?'#fff':'transparent' ?>;
                         color:<?= $currentLang==='en'?'#1e40af':'#64748b' ?>;
                         font-weight:<?= $currentLang==='en'?'700':'500' ?>;
                         box-shadow:<?= $currentLang==='en'?'0 1px 4px rgba(0,0,0,.12)':'none' ?>">
            🇬🇧 <span>EN</span>
          </button>
        </div>
        <div class="adm-user-badge">
          <i class="fas fa-user-shield"></i>
          <span><?= htmlspecialchars($nomAdmin) ?></span>
        </div>
      </div>
    </header>

    <!-- Body -->
    <div class="adm-body">

      <!-- Setup banner -->
      <?php if ($setupDone): ?>
      <div class="adm-alert adm-alert-success" id="setupBanner">
        <i class="fas fa-check-circle"></i>
        <div><?= __('setup_done_msg') ?></div>
      </div>
      <?php endif; ?>

      <!-- Alertes -->
      <?php if ($successData): ?>
      <div class="adm-alert adm-alert-success">
        <i class="fas <?= $successData['icon'] ?>"></i>
        <div><?= htmlspecialchars($successData['txt']) ?></div>
      </div>
      <?php endif; ?>
      <?php if ($errorMsg): ?>
      <div class="adm-alert adm-alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <div><?= htmlspecialchars($errorMsg) ?></div>
      </div>
      <?php endif; ?>

      <!-- Mot de passe temporaire -->
      <?php if ($nouveauUser): ?>
      <div class="adm-alert adm-alert-warning">
        <i class="fas fa-key"></i>
        <div style="flex:1">
          <strong><?= __('temp_password_title') ?></strong>
          <p style="margin:.35rem 0 .5rem;font-size:.85rem">
            <?= __('account_colon') ?> <strong><?= htmlspecialchars($nouveauUser['nom']) ?></strong>
            &lt;<?= htmlspecialchars($nouveauUser['email']) ?>&gt; —
            <span class="adm-badge <?= $nouveauUser['role'] ?>"><?= $nouveauUser['role'] ?></span>
          </p>
          <div class="pwd-reveal"><?= htmlspecialchars($nouveauUser['password_clair']) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="adm-stats">
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-hospital-alt"></i></div>
          <div>
            <div class="stat-label"><?= __('hospital') ?></div>
            <div class="stat-value" style="font-size:1rem"><?= htmlspecialchars($nomHopital) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon blue"><i class="fas fa-user-tie"></i></div>
          <div>
            <div class="stat-label"><?= __('managers') ?></div>
            <div class="stat-value"><?= count($gestionnaires) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon green"><i class="fas fa-user-md"></i></div>
          <div>
            <div class="stat-label"><?= __('doctors') ?></div>
            <div class="stat-value"><?= count($medecins) ?></div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon amber"><i class="fas fa-stethoscope"></i></div>
          <div>
            <div class="stat-label"><?= __('sub_service') ?>s</div>
            <div class="stat-value"><?= $nbActifsSS ?> <span style="font-size:.75rem;color:var(--slate-400)">/ <?= count($sousServices) ?></span></div>
          </div>
        </div>
      </div>

      <!-- Tab nav -->
      <nav class="adm-tabs-nav">
        <button class="adm-tab-btn <?= $activeTab === 'hopital'       ? 'active' : '' ?>" onclick="switchTab('hopital')">
          <i class="fas fa-building"></i> <?= __('hospital') ?>
        </button>
        <button class="adm-tab-btn <?= $activeTab === 'gestionnaires' ? 'active' : '' ?>" onclick="switchTab('gestionnaires')">
          <i class="fas fa-user-tie"></i> <?= __('managers') ?>
        </button>
        <button class="adm-tab-btn <?= $activeTab === 'medecins'      ? 'active' : '' ?>" onclick="switchTab('medecins')">
          <i class="fas fa-user-md"></i> <?= __('doctors') ?>
        </button>
        <button class="adm-tab-btn <?= $activeTab === 'sous_services' ? 'active' : '' ?>" onclick="switchTab('sous_services')">
          <i class="fas fa-stethoscope"></i> <?= __('sub_service') ?>s
        </button>
        <button class="adm-tab-btn <?= $activeTab === 'planning' ? 'active' : '' ?>" onclick="switchTab('planning')">
          <i class="fas fa-calendar-alt"></i> <?= __('nav_planning') ?>
        </button>
        <button class="adm-tab-btn <?= $activeTab === 'statistiques' ? 'active' : '' ?>" onclick="switchTab('statistiques')">
          <i class="fas fa-chart-line"></i> <?= __('statistics') ?>
        </button>
        <button class="adm-tab-btn <?= $activeTab === 'consultations_medecin' ? 'active' : '' ?>" onclick="switchTab('consultations_medecin')" style="<?= $activeTab === 'consultations_medecin' ? 'background:linear-gradient(135deg,#0052a0,#1a73c8);color:#fff;border-color:#0052a0;' : 'color:#0052a0;font-weight:700;border-color:#0052a0;' ?>">
            <i class="fas fa-stethoscope"></i> <?= __('my_consultations_nav') ?><?= $isAdminMedecin ? '' : ' <span style="font-size:.65rem;background:#f59e0b;color:#fff;border-radius:4px;padding:1px 5px;margin-left:4px;">SETUP</span>' ?>
        </button>
      </nav>

      <!-- ═══ TAB : HÔPITAL ═══ -->
      <div class="adm-tab-pane <?= $activeTab === 'hopital' ? 'active' : '' ?>" id="tab-hopital">
        <div class="adm-card">
          <div class="adm-card-header">
            <h5><i class="fas fa-building"></i> <?= __('hospital_config_title') ?></h5>
          </div>
          <div class="adm-card-body">
            <form method="POST" action="admin.php?action=sauvegarder_hopital" enctype="multipart/form-data">
              <div class="adm-form-grid">
                <div>
                  <label class="adm-label"><?= __('hospital_name_label') ?> <span style="color:var(--red-500)">*</span></label>
                  <input class="adm-input" type="text" name="nom_hopital"
                         value="<?= htmlspecialchars($hopital['nom_hopital'] ?? '') ?>" required>
                </div>
                <div>
                  <label class="adm-label"><?= __('contact_email') ?></label>
                  <input class="adm-input" type="email" name="email"
                         value="<?= htmlspecialchars($hopital['email'] ?? '') ?>">
                </div>
                <div>
                  <label class="adm-label"><?= __('phone') ?></label>
                  <input class="adm-input" type="text" name="telephone"
                         value="<?= htmlspecialchars($hopital['telephone'] ?? '') ?>">
                </div>
                <div>
                  <label class="adm-label"><?= __('logo_label') ?></label>
                  <?php if (!empty($hopital['logo_path'])): ?>
                    <div style="margin-bottom:.5rem">
                      <img src="<?= htmlspecialchars($hopital['logo_path']) ?>"
                           alt="Logo" style="height:48px;border-radius:6px;object-fit:contain;border:1px solid var(--slate-200);">
                    </div>
                  <?php endif; ?>
                  <input class="adm-input" type="file" name="logo" accept=".jpg,.jpeg,.png,.svg,.webp">
                </div>
                <div class="col-full">
                  <label class="adm-label"><?= __('address_label') ?></label>
                  <textarea class="adm-input" name="adresse"><?= htmlspecialchars($hopital['adresse'] ?? '') ?></textarea>
                </div>
              </div>

              <!-- ── Horaires généraux des médecins ── -->
              <?php
                $hgOuv  = isset($horairesGeneraux['horaires_ouverture']) ? substr($horairesGeneraux['horaires_ouverture'], 0, 5) : '08:00';
                $hgFer  = isset($horairesGeneraux['horaires_fermeture']) ? substr($horairesGeneraux['horaires_fermeture'], 0, 5) : '18:00';
                $hgPauD = !empty($horairesGeneraux['pause_debut']) ? substr($horairesGeneraux['pause_debut'], 0, 5) : '';
                $hgPauF = !empty($horairesGeneraux['pause_fin'])   ? substr($horairesGeneraux['pause_fin'],   0, 5) : '';
              ?>
              <div style="border-top:1px solid var(--slate-200);margin:24px 0 20px;"></div>
              <h6 style="font-size:.875rem;font-weight:700;color:var(--slate-700);margin-bottom:16px;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-clock" style="color:var(--blue-600)"></i>
                <?= __('general_hours_title') ?>
                <span style="font-size:.72rem;font-weight:400;color:var(--slate-500);margin-left:4px;"><?= __('general_hours_sync_note') ?></span>
              </h6>
              <div class="adm-form-grid">
                <div>
                  <label class="adm-label">
                    <i class="fas fa-play" style="color:var(--green-600);font-size:.75rem;margin-right:4px;"></i>
                    <?= __('work_start_time') ?>
                  </label>
                  <input class="adm-input" type="time" name="medecin_heure_debut"
                         value="<?= htmlspecialchars($hgOuv) ?>" required>
                </div>
                <div>
                  <label class="adm-label">
                    <i class="fas fa-stop" style="color:var(--red-500);font-size:.75rem;margin-right:4px;"></i>
                    <?= __('work_end_time') ?>
                  </label>
                  <input class="adm-input" type="time" name="medecin_heure_fin"
                         value="<?= htmlspecialchars($hgFer) ?>" required>
                </div>
                <div>
                  <label class="adm-label">
                    <i class="fas fa-coffee" style="font-size:.75rem;margin-right:4px;"></i>
                    <?= __('break_start_optional') ?>
                  </label>
                  <input class="adm-input" type="time" name="medecin_pause_debut"
                         value="<?= htmlspecialchars($hgPauD) ?>">
                </div>
                <div>
                  <label class="adm-label">
                    <i class="fas fa-coffee" style="font-size:.75rem;margin-right:4px;"></i>
                    <?= __('break_end_optional') ?>
                  </label>
                  <input class="adm-input" type="time" name="medecin_pause_fin"
                         value="<?= htmlspecialchars($hgPauF) ?>">
                </div>
                <div class="col-full">
                  <p style="font-size:.78rem;color:var(--slate-500);margin-bottom:0;">
                    <i class="fas fa-info-circle" style="color:var(--blue-400);margin-right:4px;"></i>
                    <?= __('hours_info_note') ?>
                  </p>
                </div>
                <div class="col-full">
                  <button type="submit" class="adm-btn adm-btn-primary">
                    <i class="fas fa-save"></i> <?= __('save_btn') ?>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ═══ TAB : GESTIONNAIRES ═══ -->
      <div class="adm-tab-pane <?= $activeTab === 'gestionnaires' ? 'active' : '' ?>" id="tab-gestionnaires">
        <div class="adm-card">
          <div class="adm-card-header">
            <h5><i class="fas fa-user-tie"></i> <?= __('managers') ?></h5>
          </div>
          <div class="adm-card-body">
            <form method="POST" action="admin.php?action=creer_utilisateur">
              <input type="hidden" name="role" value="gestionnaire">
              <div class="adm-add-form">
                <div>
                  <label class="adm-label"><?= __('full_name') ?></label>
                  <input class="adm-input" type="text" name="nom" placeholder="Jean Dupont" required>
                </div>
                <div>
                  <label class="adm-label"><?= __('email_label') ?></label>
                  <input class="adm-input" type="email" name="email" placeholder="gestionnaire@hopital.cm" required>
                </div>
                <div>
                  <label class="adm-label">&nbsp;</label>
                  <button type="submit" class="adm-btn adm-btn-primary" style="width:100%">
                    <i class="fas fa-plus"></i> <?= __('add') ?>
                  </button>
                </div>
              </div>
            </form>
            <?php if (empty($gestionnaires)): ?>
              <div class="adm-empty"><i class="fas fa-user-tie"></i><p><?= __('no_manager') ?></p></div>
            <?php else: ?>
              <div class="adm-search-bar-wrap"><div class="adm-search-bar">
                <i class="fa-solid fa-magnifying-glass adm-search-icon"></i>
                <input type="text" id="searchGestionnaires" placeholder="<?= __('search_placeholder') ?>" oninput="admFiltrerTable('searchGestionnaires','tableGestionnaires')">
                <button class="adm-search-clear" onclick="document.getElementById('searchGestionnaires').value='';admFiltrerTable('searchGestionnaires','tableGestionnaires')" title="<?= __('clear_btn') ?>"><i class="fa-solid fa-xmark"></i></button>
                <button class="adm-search-filter-btn" onclick="admFiltrerTable('searchGestionnaires','tableGestionnaires')"><i class="fa-solid fa-filter"></i> <?= __('filter_btn') ?></button>
              </div></div>
              <div class="adm-table-wrap"><table class="adm-table" id="tableGestionnaires">
                <thead><tr><th><?= __('name') ?></th><th><?= __('email_label') ?></th><th><?= __('status') ?></th><th><?= __('created_at') ?></th><th><?= __('action') ?></th></tr></thead>
                <tbody>
                <?php foreach ($gestionnaires as $g): ?>
                  <tr data-search="<?= htmlspecialchars(mb_strtolower($g['nom'] . ' ' . $g['email'])) ?>">
                    <td><?= htmlspecialchars($g['nom']) ?></td>
                    <td><?= htmlspecialchars($g['email']) ?></td>
                    <td><span class="adm-badge <?= $g['statut'] ?>"><?= $g['statut'] === 'actif' ? __('active') : __('inactive') ?></span></td>
                    <td><?= substr($g['created_at'] ?? '', 0, 10) ?></td>
                    <td>
                      <form method="POST" action="admin.php?action=toggle_statut" style="display:inline">
                        <input type="hidden" name="user_id" value="<?= $g['id'] ?>">
                        <input type="hidden" name="tab" value="gestionnaires">
                        <input type="hidden" name="statut" value="<?= $g['statut'] === 'actif' ? 'inactif' : 'actif' ?>">
                        <button type="submit" class="adm-btn adm-btn-sm <?= $g['statut'] === 'actif' ? 'adm-btn-warning' : 'adm-btn-success' ?>">
                          <?= $g['statut'] === 'actif' ? __('disable_btn') : __('enable_btn') ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ═══ TAB : MÉDECINS ═══ -->
      <div class="adm-tab-pane <?= $activeTab === 'medecins' ? 'active' : '' ?>" id="tab-medecins">
        <div class="adm-card">
          <div class="adm-card-header"><h5><i class="fas fa-user-md"></i> <?= __('doctors') ?></h5></div>
          <div class="adm-card-body">
            <form method="POST" action="admin.php?action=creer_utilisateur">
              <input type="hidden" name="role" value="medecin">
              <div class="adm-add-form">
                <div><label class="adm-label"><?= __('full_name') ?></label><input class="adm-input" type="text" name="nom" placeholder="Dr. Marie Curie" required></div>
                <div><label class="adm-label"><?= __('email_label') ?></label><input class="adm-input" type="email" name="email" placeholder="medecin@hopital.cm" required></div>
                <div><label class="adm-label">&nbsp;</label><button type="submit" class="adm-btn adm-btn-success" style="width:100%"><i class="fas fa-plus"></i> <?= __('add') ?></button></div>
              </div>
            </form>
            <?php if (empty($medecins)): ?>
              <div class="adm-empty"><i class="fas fa-user-md"></i><p><?= __('no_doctor') ?></p></div>
            <?php else: ?>
              <div class="adm-search-bar-wrap"><div class="adm-search-bar">
                <i class="fa-solid fa-magnifying-glass adm-search-icon"></i>
                <input type="text" id="searchMedecins" placeholder="<?= __('search_placeholder') ?>" oninput="admFiltrerTable('searchMedecins','tableMedecins')">
                <button class="adm-search-clear" onclick="document.getElementById('searchMedecins').value='';admFiltrerTable('searchMedecins','tableMedecins')" title="<?= __('clear_btn') ?>"><i class="fa-solid fa-xmark"></i></button>
                <button class="adm-search-filter-btn" onclick="admFiltrerTable('searchMedecins','tableMedecins')"><i class="fa-solid fa-filter"></i> <?= __('filter_btn') ?></button>
              </div></div>
              <div class="adm-table-wrap"><table class="adm-table" id="tableMedecins">
                <thead><tr><th><?= __('name') ?></th><th><?= __('email_label') ?></th><th><?= __('status') ?></th><th><?= __('created_at') ?></th><th><?= __('action') ?></th></tr></thead>
                <tbody>
                <?php foreach ($medecins as $m): ?>
                  <tr data-search="<?= htmlspecialchars(mb_strtolower($m['nom'] . ' ' . $m['email'])) ?>">
                    <td><?= htmlspecialchars($m['nom']) ?></td>
                    <td><?= htmlspecialchars($m['email']) ?></td>
                    <td><span class="adm-badge <?= $m['statut'] ?>"><?= $m['statut'] === 'actif' ? __('active') : __('inactive') ?></span></td>
                    <td><?= substr($m['created_at'] ?? '', 0, 10) ?></td>
                    <td>
                      <form method="POST" action="admin.php?action=toggle_statut" style="display:inline">
                        <input type="hidden" name="user_id" value="<?= $m['id'] ?>">
                        <input type="hidden" name="tab" value="medecins">
                        <input type="hidden" name="statut" value="<?= $m['statut'] === 'actif' ? 'inactif' : 'actif' ?>">
                        <button type="submit" class="adm-btn adm-btn-sm <?= $m['statut'] === 'actif' ? 'adm-btn-warning' : 'adm-btn-success' ?>">
                          <?= $m['statut'] === 'actif' ? __('disable_btn') : __('enable_btn') ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ═══ TAB : SOUS-SERVICES ═══ -->
      <div class="adm-tab-pane <?= $activeTab === 'sous_services' ? 'active' : '' ?>" id="tab-sous_services">
        <div class="adm-card" style="margin-bottom:1.25rem">
          <div class="adm-card-header"><h5><i class="fas fa-plus-circle"></i> <?= __('new_sub_service') ?></h5></div>
          <div class="adm-card-body">
            <form method="POST" action="admin.php?action=creer_ss">
              <div class="adm-form-grid">
                <div><label class="adm-label"><?= __('sub_service_name_label') ?> <span style="color:var(--red-500)">*</span></label><input class="adm-input" type="text" name="nom" placeholder="Ex : Cardiologie" required></div>
                <div><label class="adm-label"><?= __('description') ?></label><input class="adm-input" type="text" name="description" placeholder="Courte description"></div>
                <div><label class="adm-label"><?= __('default_duration') ?></label>
                  <select class="adm-input" name="duree_rdv_defaut">
                    <option value="900">15 min</option><option value="1800" selected>30 min</option><option value="2700">45 min</option><option value="3600">1 h</option><option value="5400">1 h 30</option><option value="7200">2 h</option>
                  </select>
                </div>
                <div><label class="adm-label"><?= __('hourly_capacity') ?></label><input class="adm-input" type="number" name="capacite_horaire" value="10" min="1" max="100"></div>
                <div class="col-full"><button type="submit" class="adm-btn adm-btn-primary"><i class="fas fa-plus"></i> <?= __('create_sub_service_btn') ?></button></div>
              </div>
            </form>
          </div>
        </div>
        <div class="adm-card">
          <div class="adm-card-header">
            <h5><i class="fas fa-stethoscope"></i> <?= __('sub_service') ?>s (<?= count($sousServices) ?>)</h5>
            <span style="font-size:.8rem;color:var(--slate-400)"><?= $nbActifsSS ?> <?= __('active') ?></span>
          </div>
          <div class="adm-card-body" style="padding:1rem">
            <?php if (empty($sousServices)): ?>
              <div class="adm-empty"><i class="fas fa-stethoscope"></i><p><?= __('no_sub_service') ?></p></div>
            <?php else: ?>
              <div class="ss-grid">
                <?php foreach ($sousServices as $ss): $dureeMin = (int)($ss['duree_rdv_defaut'] ?? 1800) / 60; ?>
                <div class="ss-card <?= $ss['statut'] !== 'actif' ? 'inactif' : '' ?>">
                  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.5rem">
                    <div class="ss-card-name"><?= htmlspecialchars($ss['nom']) ?></div>
                    <span class="adm-badge <?= $ss['statut'] ?>"><?= $ss['statut'] === 'actif' ? __('active') : __('inactive') ?></span>
                  </div>
                  <div class="ss-card-desc"><?= htmlspecialchars($ss['description'] ?: '—') ?></div>
                  <div class="ss-card-meta">
                    <span><i class="fas fa-clock"></i> <?= $dureeMin ?> min</span>
                    <span><i class="fas fa-users"></i> <?= (int)($ss['capacite_horaire'] ?? 10) ?>/h</span>
                    <?php if (!empty($ss['nb_medecins'])): ?><span><i class="fas fa-user-md"></i> <?= (int)$ss['nb_medecins'] ?> <?= __('doctors') ?></span><?php endif; ?>
                  </div>
                  <div class="ss-card-actions">
                    <button class="adm-btn adm-btn-sm adm-btn-ghost" onclick="openEditModal(<?= htmlspecialchars(json_encode($ss)) ?>)">
                      <i class="fas fa-edit"></i> <?= __('edit') ?>
                    </button>
                    <form method="POST" action="admin.php?action=toggle_statut_ss" style="display:inline">
                      <input type="hidden" name="ss_id" value="<?= $ss['id'] ?>">
                      <button type="submit" class="adm-btn adm-btn-sm <?= $ss['statut'] === 'actif' ? 'adm-btn-warning' : 'adm-btn-success' ?>">
                        <?= $ss['statut'] === 'actif' ? '<i class="fas fa-pause"></i> ' . __('disable_btn') : '<i class="fas fa-play"></i> ' . __('enable_btn') ?>
                      </button>
                    </form>
                    <button class="adm-btn adm-btn-sm adm-btn-danger" onclick="confirmDelete(<?= $ss['id'] ?>, '<?= htmlspecialchars(addslashes($ss['nom'])) ?>')">
                      <i class="fas fa-trash"></i>
                    </button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ═══ TAB : PLANNING ═══ -->\
      <div class="adm-tab-pane <?= $activeTab === 'planning' ? 'active' : '' ?>" id="tab-planning">
        <?php require __DIR__ . '/planning.php'; ?>
      </div><!-- /tab-planning -->

      <!-- ═══ TAB : STATISTIQUES ═══ -->
      <div class="adm-tab-pane <?= $activeTab === 'statistiques' ? 'active' : '' ?>" id="tab-statistiques">
        <?php require __DIR__ . '/statistiques.php'; ?>
      </div><!-- /tab-statistiques -->

      <div class="adm-tab-pane <?= $activeTab === 'consultations_medecin' ? 'active' : '' ?>" id="tab-consultations-medecin">
        <?php if ($isAdminMedecin): ?>
        <div id="embConsultWrapper" style="min-height:200px;">
          <div style="text-align:center;padding:40px;color:#94a3b8;" id="embConsultLoading">
            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;"></i><br><br><?= __('loading_consultations') ?>
          </div>
        </div>
        <?php else: ?>
        <div style="max-width:560px;margin:0 auto;padding:32px 0;">
          <div style="background:linear-gradient(135deg,#0052a0,#1a73c8);color:#fff;border-radius:16px;padding:24px 28px;margin-bottom:28px;text-align:center;">
            <div style="font-size:2.2rem;margin-bottom:10px;"><i class="fas fa-stethoscope"></i></div>
            <h3 style="margin:0 0 6px;font-size:1.2rem;"><?= __('doctor_role_title') ?></h3>
            <p style="margin:0;opacity:.85;font-size:.85rem;"><?= __('doctor_role_desc') ?></p>
          </div>
          <div id="activerMsgOk" style="display:none;background:#d1fae5;color:#065f46;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-weight:600;"></div>
          <div id="activerMsgErr" style="display:none;background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-weight:600;"></div>
          <div style="background:#fff;border-radius:14px;padding:24px;box-shadow:0 2px 12px rgba(0,0,0,.07);">
            <div style="margin-bottom:16px;">
              <label style="font-size:.82rem;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= __('specialty_label') ?> <span style="color:#ef4444;">*</span></label>
              <input type="text" id="actSpecialite" placeholder="ex. Médecine générale, Cardiologie…" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;box-sizing:border-box;outline:none;" onfocus="this.style.borderColor='#0052a0'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div style="margin-bottom:16px;">
              <label style="font-size:.82rem;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= __('sub_service_consult_label') ?> <span style="color:#ef4444;">*</span></label>
              <select id="actSousService" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;box-sizing:border-box;outline:none;background:#fff;" onfocus="this.style.borderColor='#0052a0'" onblur="this.style.borderColor='#e2e8f0'">
                <option value=""><?= __('choose_sub_service') ?></option>
                <?php foreach($sousServices as $ss): ?><option value="<?= $ss['id'] ?>"><?= htmlspecialchars($ss['nom']) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div style="margin-bottom:20px;">
              <label style="font-size:.82rem;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= __('pro_phone_label') ?> <span style="color:#94a3b8;font-weight:400;">(<?= __('optional') ?>)</span></label>
              <input type="tel" id="actTelephone" placeholder="ex. 677000000" style="width:100%;padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:.88rem;box-sizing:border-box;outline:none;" onfocus="this.style.borderColor='#0052a0'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <button onclick="admActiverRoleMedecin()" id="btnActiverMedecin" style="width:100%;padding:12px;background:linear-gradient(135deg,#0052a0,#1a73c8);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;">
              <i class="fas fa-stethoscope"></i> <?= __('activate_role_btn') ?>
            </button>
          </div>
          <p style="text-align:center;font-size:.75rem;color:#94a3b8;margin-top:16px;">
            <i class="fas fa-lock" style="margin-right:4px;"></i> <?= __('activation_security_note') ?>
          </p>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /adm-body -->
  </div><!-- /adm-main -->
</div><!-- /adm-shell -->

<!-- ═══ MODAL : Modifier sous-service ═══ -->
<div class="adm-modal-backdrop" id="editModal">
  <div class="adm-modal">
    <div class="adm-modal-header">
      <h6><i class="fas fa-edit" style="color:var(--blue-500);margin-right:.5rem"></i><?= __('edit_sub_service_title') ?></h6>
      <button class="adm-modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="admin.php?action=modifier_ss">
      <input type="hidden" name="ss_id" id="edit_ss_id">
      <div class="adm-modal-body">
        <div class="adm-form-grid">
          <div class="col-full"><label class="adm-label"><?= __('sub_service_name_label') ?> <span style="color:var(--red-500)">*</span></label><input class="adm-input" type="text" name="nom" id="edit_nom" required></div>
          <div class="col-full"><label class="adm-label"><?= __('description') ?></label><input class="adm-input" type="text" name="description" id="edit_description"></div>
          <div><label class="adm-label"><?= __('default_duration') ?></label>
            <select class="adm-input" name="duree_rdv_defaut" id="edit_duree">
              <option value="900">15 min</option><option value="1800">30 min</option><option value="2700">45 min</option><option value="3600">1 h</option><option value="5400">1 h 30</option><option value="7200">2 h</option>
            </select>
          </div>
          <div><label class="adm-label"><?= __('hourly_capacity') ?></label><input class="adm-input" type="number" name="capacite_horaire" id="edit_capacite" min="1" max="100"></div>
          <div><label class="adm-label"><?= __('status') ?></label>
            <select class="adm-input" name="statut" id="edit_statut">
              <option value="actif"><?= __('active') ?></option>
              <option value="inactif"><?= __('inactive') ?></option>
            </select>
          </div>
        </div>
      </div>
      <div class="adm-modal-footer">
        <button type="button" class="adm-btn adm-btn-ghost" onclick="closeModal('editModal')"><?= __('cancel_btn') ?></button>
        <button type="submit" class="adm-btn adm-btn-primary"><i class="fas fa-save"></i> <?= __('save_btn') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ MODAL : Confirmer suppression ═══ -->
<div class="adm-modal-backdrop" id="deleteModal">
  <div class="adm-modal" style="max-width:400px">
    <div class="adm-modal-header">
      <h6 style="color:var(--red-500)"><i class="fas fa-trash-alt" style="margin-right:.5rem"></i><?= __('confirm_delete_title') ?></h6>
      <button class="adm-modal-close" onclick="closeModal('deleteModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="adm-modal-body">
      <p><?= __('confirm_delete_text') ?> <strong id="delete_nom"></strong> ?</p>
      <p style="font-size:.82rem;color:var(--slate-500);margin-top:.5rem"><?= __('confirm_delete_warning') ?></p>
    </div>
    <div class="adm-modal-footer">
      <button type="button" class="adm-btn adm-btn-ghost" onclick="closeModal('deleteModal')"><?= __('cancel_btn') ?></button>
      <form method="POST" action="admin.php?action=supprimer_ss" style="display:inline">
        <input type="hidden" name="ss_id" id="delete_ss_id">
        <button type="submit" class="adm-btn adm-btn-danger"><i class="fas fa-trash"></i> <?= __('delete_btn') ?></button>
      </form>
    </div>
  </div>
</div>

<script>
// ── Internationalisation ──
const I18N = <?= \LangHelper::toJson() ?>;
function t(key) { return I18N[key] || key; }

// ── Sidebar mobile ──
function toggleSidebar() {
  document.getElementById('admSidebar').classList.toggle('open');
  document.getElementById('admOverlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('admSidebar').classList.remove('open');
  document.getElementById('admOverlay').classList.remove('open');
}

// ── Tabs ──
function switchTab(name) {
  document.querySelectorAll('.adm-tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.adm-tab-btn').forEach(b => b.classList.remove('active'));
  const tabId = 'tab-' + name.replace(/_/g, '-');
  document.getElementById(tabId).classList.add('active');
  document.querySelectorAll('.adm-tab-btn').forEach(b => {
    if (b.getAttribute('onclick') === `switchTab('${name}')`) b.classList.add('active');
  });
  document.querySelectorAll('.adm-nav a').forEach(a => {
    a.classList.toggle('active', a.href.includes('tab=' + name));
  });
  const url = new URL(window.location);
  url.searchParams.set('tab', name);
  history.replaceState(null, '', url);
  if (name === 'statistiques' && typeof admInitStats === 'function') admInitStats();
  if (name === 'statistiques' && typeof admInitTempsAttente === 'function') admInitTempsAttente();
  if (name === 'consultations_medecin') admChargerConsultations();
}

// ── Chargement consultations directeur-médecin ──
let _embCharge = false;
function admChargerConsultations() {
  const wrapper = document.getElementById('embConsultWrapper');
  if (!wrapper) return;
  if (_embCharge) { if (typeof embRafraichir === 'function') embRafraichir(); return; }
  fetch('admin.php?action=consultations_medecin')
    .then(r => r.text())
    .then(html => {
      wrapper.innerHTML = html;
      _embCharge = true;
      wrapper.querySelectorAll('script').forEach(oldScript => {
        const s = document.createElement('script');
        s.textContent = oldScript.textContent;
        document.body.appendChild(s);
      });
    })
    .catch(() => {
      wrapper.innerHTML = `<div style="text-align:center;padding:32px;color:#ef4444;"><i class="fas fa-triangle-exclamation"></i> ${t('load_consult_error')}</div>`;
    });
}

document.addEventListener('DOMContentLoaded', function() {
  <?php if ($isAdminMedecin && $activeTab === 'consultations_medecin'): ?>
  admChargerConsultations();
  <?php endif; ?>
});

// ── Filtrage tableaux ──
function admFiltrerTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  const q = input.value.toLowerCase().trim();
  table.querySelectorAll('tbody tr').forEach(row => {
    const txt = row.getAttribute('data-search') || row.textContent.toLowerCase();
    row.style.display = !q || txt.includes(q) ? '' : 'none';
  });
}

// ── Activation du rôle médecin ──
function admActiverRoleMedecin() {
  const specialite  = document.getElementById('actSpecialite')?.value.trim();
  const sousService = document.getElementById('actSousService')?.value;
  const telephone   = document.getElementById('actTelephone')?.value.trim();
  const btn         = document.getElementById('btnActiverMedecin');
  const msgOk       = document.getElementById('activerMsgOk');
  const msgErr      = document.getElementById('activerMsgErr');

  if (msgOk)  msgOk.style.display  = 'none';
  if (msgErr) msgErr.style.display = 'none';

  if (!specialite)  { admShowActiverErr(t('enter_specialty_please'));    return; }
  if (!sousService) { admShowActiverErr(t('choose_sub_service_please')); return; }

  if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${t('activation_in_progress')}`; }

  const body = new FormData();
  body.append('specialite',      specialite);
  body.append('sous_service_id', sousService);
  body.append('telephone',       telephone);

  fetch('admin.php?action=activer_role_medecin', { method: 'POST', body })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        if (msgOk) { msgOk.textContent = d.message; msgOk.style.display = 'block'; }
        if (btn)   { btn.innerHTML = `<i class="fas fa-check"></i> ${t('activated_label')}`; }
        setTimeout(() => window.location.href = 'admin.php?tab=consultations_medecin', 1200);
      } else {
        admShowActiverErr(d.message || t('activation_error'));
        if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fas fa-stethoscope"></i> ${t('activate_role_btn')}`; }
      }
    })
    .catch(() => {
      admShowActiverErr(t('network_error'));
      if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fas fa-stethoscope"></i> ${t('activate_role_btn')}`; }
    });
}

function admShowActiverErr(msg) {
  const el = document.getElementById('activerMsgErr');
  if (el) { el.textContent = msg; el.style.display = 'block'; }
}

// ── Modals ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.adm-modal-backdrop').forEach(el => {
  el.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
});

// ── Edit sous-service ──
function openEditModal(ss) {
  document.getElementById('edit_ss_id').value      = ss.id;
  document.getElementById('edit_nom').value         = ss.nom;
  document.getElementById('edit_description').value = ss.description || '';
  document.getElementById('edit_duree').value       = ss.duree_rdv_defaut;
  document.getElementById('edit_capacite').value    = ss.capacite_horaire;
  document.getElementById('edit_statut').value      = ss.statut;
  openModal('editModal');
}

// ── Delete confirm ──
function confirmDelete(id, nom) {
  document.getElementById('delete_ss_id').value = id;
  document.getElementById('delete_nom').textContent = nom;
  openModal('deleteModal');
}

// ── Auto-dismiss setup banner ──
const banner = document.getElementById('setupBanner');
if (banner) setTimeout(() => banner.style.display = 'none', 6000);

// ── Changement de langue admin ──
async function adminChangerLangue(langue) {
  const fd = new FormData();
  fd.append('langue', langue);
  const res = await fetch('admin.php?action=changer_langue_admin', { method: 'POST', body: fd });
  const d = await res.json();
  if (d.success) window.location.reload();
}
</script>
</body>
</html>