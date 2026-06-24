<?php
/**
 * AdminController.php — Espace administrateur (directeur)
 * Gestion : configuration hôpital, utilisateurs, sous-services
 */

require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../models/UtilisateurModel.php';
require_once __DIR__ . '/../models/MedecinModel.php';
require_once __DIR__ . '/../models/GestionnaireModel.php';
require_once __DIR__ . '/../models/HopitalModel.php';
require_once __DIR__ . '/../models/ServiceModel.php';

class AdminController {

    private UtilisateurModel  $utilisateurModel;
    private HopitalModel      $hopitalModel;
    private ServiceModel      $serviceModel;

    public function __construct() {
        $this->utilisateurModel = new UtilisateurModel();
        $this->hopitalModel     = new HopitalModel();
        $this->serviceModel     = new ServiceModel();
    }

    // ══════════════════════════════════════════════════════
    //  Dashboard
    // ══════════════════════════════════════════════════════

    public function afficherDashboard(): void {
        AuthHelper::exigerRole('admin');

        $hopital       = $this->hopitalModel->getData();
        $gestionnaires = $this->utilisateurModel->listerParRole('gestionnaire');
        $medecins      = $this->utilisateurModel->listerParRole('medecin');
        $sousServices  = $this->serviceModel->getSousServicesParService(1);
        $setupDone     = isset($_GET['setup']);
        $nouveauUser   = $_SESSION['nouveau_utilisateur'] ?? null;
        unset($_SESSION['nouveau_utilisateur']);

        include __DIR__ . '/../views/admin/dashboard.php';
    }

    // ══════════════════════════════════════════════════════
    //  Configuration hôpital
    // ══════════════════════════════════════════════════════

    public function sauvegarderHopital(): void {
        AuthHelper::exigerRole('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php'); exit; }

        $data = [
            'nom_hopital' => trim($_POST['nom_hopital'] ?? ''),
            'adresse'     => trim($_POST['adresse']     ?? ''),
            'telephone'   => trim($_POST['telephone']   ?? ''),
            'email'       => trim($_POST['email']       ?? ''),
            'logo_path'   => null,
        ];

        if (empty($data['nom_hopital'])) {
            header('Location: admin.php?tab=hopital&error=nom_hopital_requis'); exit;
        }

        if (!empty($_FILES['logo']['tmp_name'])) {
            $ext     = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
            if (in_array($ext, $allowed, true)) {
                $destDir = __DIR__ . '/../public/uploads/hopital/';
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $filename = 'logo_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $destDir . $filename)) {
                    $data['logo_path'] = 'public/uploads/hopital/' . $filename;
                }
            }
        } else {
            $existing        = $this->hopitalModel->getData();
            $data['logo_path'] = $existing['logo_path'] ?? null;
        }

        $ok = $this->hopitalModel->sauvegarder($data);
        header('Location: admin.php?tab=hopital&' . ($ok ? 'success=hopital_sauvegarde' : 'error=sauvegarde_echouee'));
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Utilisateurs  (médecin / gestionnaire)
    // ══════════════════════════════════════════════════════

    /**
     * Affiche le formulaire de création via views/admin/creer_utilisateur.php
     * URL : admin.php?action=creer_utilisateur&role=medecin|gestionnaire  (GET)
     */
    public function afficherFormulaireUtilisateur(): void {
        AuthHelper::exigerRole('admin');

        $role        = in_array($_GET['role'] ?? '', ['medecin', 'gestionnaire'], true)
                       ? $_GET['role']
                       : 'gestionnaire';
        $sousServices = $this->serviceModel->getSousServicesParService(1);

        // Erreurs et anciennes valeurs transmises via session (PRG pattern)
        $erreurs = $_SESSION['creer_erreurs'] ?? [];
        $anciens = $_SESSION['creer_anciens'] ?? [];
        unset($_SESSION['creer_erreurs'], $_SESSION['creer_anciens']);

        include __DIR__ . '/../views/admin/creer_utilisateur.php';
    }

    /**
     * Traite la soumission du formulaire
     * URL : admin.php?action=creer_utilisateur  (POST)
     */
    public function creerUtilisateur(): void {
        AuthHelper::exigerRole('admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: admin.php');
            exit;
        }

        $role          = $_POST['role']          ?? '';
        $nom           = trim($_POST['nom']       ?? '');
        $prenom        = trim($_POST['prenom']    ?? '');   // médecin uniquement
        $email         = strtolower(trim($_POST['email']    ?? ''));
        $telephone     = trim($_POST['telephone'] ?? '');
        $specialite    = trim($_POST['specialite'] ?? '');  // médecin uniquement
        $sousServiceId = (int) ($_POST['sous_service_id'] ?? 0);
        $password      = $_POST['password']       ?? '';
        $confirm       = $_POST['confirm']        ?? '';

        $tab = $role === 'medecin' ? 'medecins' : 'gestionnaires';

        // ── Validation ────────────────────────────────────────
        $erreurs = [];

        if (!in_array($role, ['gestionnaire', 'medecin'], true)) {
            header('Location: admin.php?tab=' . $tab . '&error=role_invalide');
            exit;
        }

        if (empty($nom)) {
            $erreurs['nom'] = 'Le nom est requis.';
        }
        if ($role === 'medecin' && empty($prenom)) {
            $erreurs['prenom'] = 'Le prénom est requis.';
        }
        if (empty($email)) {
            $erreurs['email'] = "L'email est requis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = "L'email est invalide.";
        } elseif ($this->utilisateurModel->emailExiste($email)) {
            $erreurs['email'] = 'Cette adresse email est déjà utilisée.';
        }
        if (empty($telephone)) {
            $erreurs['telephone'] = 'Le téléphone est requis.';
        }
        if ($role === 'medecin' && empty($specialite)) {
            $erreurs['specialite'] = 'La spécialité est requise.';
        }
        if ($role === 'gestionnaire' && $sousServiceId <= 0) {
            $erreurs['sous_service_id'] = 'Veuillez sélectionner un sous-service.';
        }
        if (strlen($password) < 8) {
            $erreurs['password'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }
        if ($password !== $confirm) {
            $erreurs['confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($erreurs)) {
            $_SESSION['creer_erreurs'] = $erreurs;
            $_SESSION['creer_anciens'] = [
                'nom'            => $nom,
                'prenom'         => $prenom,
                'email'          => $email,
                'telephone'      => $telephone,
                'specialite'     => $specialite,
                'sousServiceId'  => $sousServiceId,
            ];
            header('Location: admin.php?action=creer_utilisateur&role=' . $role);
            exit;
        }

        // ── Insertion dans la table métier + utilisateurs ─────
        if ($role === 'medecin') {
            $this->_creerMedecin($nom, $prenom, $specialite, $email, $telephone, $password);
        } else {
            $this->_creerGestionnaire($nom, $email, $telephone, $password, $sousServiceId);
        }
    }

    // ── Helpers privés ────────────────────────────────────────────────────

    private function _creerMedecin(
        string $nom,
        string $prenom,
        string $specialite,
        string $email,
        string $telephone,
        string $password
    ): void {
        $medecinModel = new MedecinModel();

        $ok = $medecinModel->creer([
            'nom'        => $nom,
            'prenom'     => $prenom,
            'specialite' => $specialite,
            'email'      => $email,
            'telephone'  => $telephone,
            'password'   => $password,
        ]);

        if (!$ok) {
            header('Location: admin.php?tab=medecins&error=creation_echouee');
            exit;
        }

        $medecinId = $medecinModel->dernierID();

        // Crée le compte utilisateur lié
        $userId = $this->utilisateurModel->creer([
            'email'      => $email,
            'password'   => $password,
            'nom'        => $prenom . ' ' . $nom,
            'role'       => 'medecin',
            'medecin_id' => $medecinId,
        ]);

        if (!$userId) {
            header('Location: admin.php?tab=medecins&error=creation_echouee');
            exit;
        }

        $_SESSION['nouveau_utilisateur'] = [
            'nom'            => $prenom . ' ' . $nom,
            'email'          => $email,
            'role'           => 'medecin',
            'password_clair' => $password,
        ];

        header('Location: admin.php?tab=medecins&success=utilisateur_cree');
        exit;
    }

    private function _creerGestionnaire(
        string $nom,
        string $email,
        string $telephone,
        string $password,
        int    $sousServiceId
    ): void {
        $gestionnaireModel = new GestionnaireModel();

        $ok = $gestionnaireModel->creer([
            'nom'            => $nom,
            'email'          => $email,
            'telephone'      => $telephone,
            'password'       => $password,
            'sous_service_id'=> $sousServiceId,
        ]);

        if (!$ok) {
            header('Location: admin.php?tab=gestionnaires&error=creation_echouee');
            exit;
        }

        $gestionnaireId = $gestionnaireModel->dernierID();

        // Crée le compte utilisateur lié
        $userId = $this->utilisateurModel->creer([
            'email'           => $email,
            'password'        => $password,
            'nom'             => $nom,
            'role'            => 'gestionnaire',
            'gestionnaire_id' => $gestionnaireId,
        ]);

        if (!$userId) {
            header('Location: admin.php?tab=gestionnaires&error=creation_echouee');
            exit;
        }

        $_SESSION['nouveau_utilisateur'] = [
            'nom'            => $nom,
            'email'          => $email,
            'role'           => 'gestionnaire',
            'password_clair' => $password,
        ];

        header('Location: admin.php?tab=gestionnaires&success=utilisateur_cree');
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Toggle statut utilisateur
    // ══════════════════════════════════════════════════════

    public function toggleStatutUtilisateur(): void {
        AuthHelper::exigerRole('admin');
        $id     = (int) ($_POST['user_id'] ?? 0);
        $statut = ($_POST['statut'] ?? '') === 'actif' ? 'actif' : 'inactif';
        $tab    = $_POST['tab'] ?? 'gestionnaires';
        if (!$id) { header('Location: admin.php?error=id_invalide'); exit; }
        $this->utilisateurModel->mettreAJourStatut($id, $statut);
        header('Location: admin.php?tab=' . $tab . '&success=statut_mis_a_jour');
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Sous-services CRUD
    // ══════════════════════════════════════════════════════

    public function creerSousService(): void {
        AuthHelper::exigerRole('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php?tab=sous_services'); exit; }

        $nom         = trim($_POST['nom']          ?? '');
        $description = trim($_POST['description']  ?? '');
        $duree       = max(300, (int) ($_POST['duree_rdv_defaut'] ?? 1800));
        $capacite    = max(1,   (int) ($_POST['capacite_horaire'] ?? 10));

        if (empty($nom)) {
            header('Location: admin.php?tab=sous_services&error=ss_nom_requis'); exit;
        }
        if ($this->serviceModel->nomSsExiste($nom, 1)) {
            header('Location: admin.php?tab=sous_services&error=ss_nom_existe'); exit;
        }

        $ok = $this->serviceModel->creerSousService([
            'service_id'       => 1,
            'nom'              => $nom,
            'description'      => $description,
            'duree_rdv_defaut' => $duree,
            'capacite_horaire' => $capacite,
        ]);

        header('Location: admin.php?tab=sous_services&' . ($ok ? 'success=ss_cree' : 'error=ss_echec'));
        exit;
    }

    public function modifierSousService(): void {
        AuthHelper::exigerRole('admin');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin.php?tab=sous_services'); exit; }

        $id          = (int) ($_POST['ss_id']           ?? 0);
        $nom         = trim($_POST['nom']               ?? '');
        $description = trim($_POST['description']       ?? '');
        $duree       = max(300, (int) ($_POST['duree_rdv_defaut'] ?? 1800));
        $capacite    = max(1,   (int) ($_POST['capacite_horaire'] ?? 10));
        $statut      = in_array($_POST['statut'] ?? '', ['actif', 'inactif']) ? $_POST['statut'] : 'actif';

        if (!$id || empty($nom)) {
            header('Location: admin.php?tab=sous_services&error=ss_donnees_invalides'); exit;
        }
        if ($this->serviceModel->nomSsExiste($nom, 1, $id)) {
            header('Location: admin.php?tab=sous_services&error=ss_nom_existe'); exit;
        }

        $ok = $this->serviceModel->modifierSousService($id, [
            'nom'              => $nom,
            'description'      => $description,
            'duree_rdv_defaut' => $duree,
            'capacite_horaire' => $capacite,
            'statut'           => $statut,
        ]);

        header('Location: admin.php?tab=sous_services&' . ($ok ? 'success=ss_modifie' : 'error=ss_echec'));
        exit;
    }

    public function supprimerSousService(): void {
        AuthHelper::exigerRole('admin');
        $id = (int) ($_POST['ss_id'] ?? 0);
        if (!$id) { header('Location: admin.php?tab=sous_services&error=id_invalide'); exit; }

        $result = $this->serviceModel->supprimerSousService($id);
        if ($result['ok']) {
            header('Location: admin.php?tab=sous_services&success=ss_supprime');
        } else {
            $_SESSION['ss_error_msg'] = $result['msg'];
            header('Location: admin.php?tab=sous_services&error=ss_suppression_impossible');
        }
        exit;
    }

    public function toggleStatutSousService(): void {
        AuthHelper::exigerRole('admin');
        $id = (int) ($_POST['ss_id'] ?? 0);
        if (!$id) { header('Location: admin.php?tab=sous_services&error=id_invalide'); exit; }
        $this->serviceModel->basculerStatutSs($id);
        header('Location: admin.php?tab=sous_services&success=ss_statut_maj');
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Planning médecins
    // ══════════════════════════════════════════════════════

    public function getPlanningMedecin(): void {
        header('Content-Type: application/json');
        $id = (int)($_GET['medecin_id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $medecinModel = new MedecinModel();
        $jours = $medecinModel->getJoursTravailMedecin($id);
        // Congés: on crée la table si besoin et on fetch
        $db = \Database::getInstance()->getConnection();
        $conges = $db->prepare("SELECT date_debut, date_fin, motif FROM conges_medecins WHERE medecin_id = :id ORDER BY date_debut");
        $conges->execute([':id' => $id]);
        echo json_encode([
            'success' => true,
            'jours_travail' => $jours,
            'conges' => $conges->fetchAll(\PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    public function sauvegarderPlanningMedecin(): void {
        header('Content-Type: application/json');
        $id    = (int)($_POST['medecin_id'] ?? 0);
        $jours = $_POST['jours'] ?? [];
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID invalide']); exit; }
        $medecinModel = new MedecinModel();
        $ok = $medecinModel->sauvegarderJoursTravailMedecin($id, array_map('intval', $jours));
        // Congés
        $db = \Database::getInstance()->getConnection();
        $db->prepare("DELETE FROM conges_medecins WHERE medecin_id = :id")->execute([':id'=>$id]);
        $conges = $_POST['conges'] ?? [];
        if (!empty($conges)) {
            $stmt = $db->prepare("INSERT INTO conges_medecins (medecin_id, date_debut, date_fin, motif) VALUES (:id,:dd,:df,:m)");
            foreach ($conges as $c) {
                if (!empty($c['date_debut']) && !empty($c['date_fin'])) {
                    $stmt->execute([':id'=>$id,':dd'=>$c['date_debut'],':df'=>$c['date_fin'],':m'=>$c['motif']??'']);
                }
            }
        }
        echo json_encode(['success'=>$ok]);
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Planning gestionnaires
    // ══════════════════════════════════════════════════════

    public function getPlanningGestionnaire(): void {
        header('Content-Type: application/json');
        $id = (int)($_GET['gestionnaire_id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $db = \Database::getInstance()->getConnection();
        $jours = $db->prepare("SELECT jour_semaine FROM gestionnaire_jours_travail WHERE gestionnaire_id = :id AND actif = 1");
        $jours->execute([':id'=>$id]);
        $conges = $db->prepare("SELECT date_debut, date_fin, motif FROM conges_gestionnaires WHERE gestionnaire_id = :id ORDER BY date_debut");
        $conges->execute([':id'=>$id]);
        echo json_encode([
            'success'       => true,
            'jours_travail' => array_column($jours->fetchAll(\PDO::FETCH_ASSOC), 'jour_semaine'),
            'conges'        => $conges->fetchAll(\PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    public function sauvegarderPlanningGestionnaire(): void {
        header('Content-Type: application/json');
        $id    = (int)($_POST['gestionnaire_id'] ?? 0);
        $jours = array_map('intval', $_POST['jours'] ?? []);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID invalide']); exit; }
        $db = \Database::getInstance()->getConnection();
        $db->prepare("DELETE FROM gestionnaire_jours_travail WHERE gestionnaire_id = :id")->execute([':id'=>$id]);
        if (!empty($jours)) {
            $stmt = $db->prepare("INSERT INTO gestionnaire_jours_travail (gestionnaire_id, jour_semaine, actif) VALUES (:id,:j,1)");
            foreach ($jours as $j) $stmt->execute([':id'=>$id,':j'=>$j]);
        }
        $db->prepare("DELETE FROM conges_gestionnaires WHERE gestionnaire_id = :id")->execute([':id'=>$id]);
        $conges = $_POST['conges'] ?? [];
        if (!empty($conges)) {
            $stmt = $db->prepare("INSERT INTO conges_gestionnaires (gestionnaire_id, date_debut, date_fin, motif) VALUES (:id,:dd,:df,:m)");
            foreach ($conges as $c) {
                if (!empty($c['date_debut']) && !empty($c['date_fin'])) {
                    $stmt->execute([':id'=>$id,':dd'=>$c['date_debut'],':df'=>$c['date_fin'],':m'=>$c['motif']??'']);
                }
            }
        }
        echo json_encode(['success'=>true]);
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Profil admin — vérification mdp + modification
    // ══════════════════════════════════════════════════════

    public function verifierMdpAdmin(): void {
        header('Content-Type: application/json');
        $password = $_POST['password'] ?? '';
        $adminId  = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
        $db = \Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = :id");
        $stmt->execute([':id' => $adminId]);
        $hash = $stmt->fetchColumn();
        $ok = $hash && password_verify($password, $hash);
        echo json_encode(['success' => $ok]);
        exit;
    }

    public function modifierProfilAdmin(): void {
        header('Content-Type: application/json');
        $adminId     = (int)($_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0);
        $nom         = trim($_POST['nom'] ?? '');
        $email       = trim($_POST['email'] ?? '');
        $nouveauMdp  = $_POST['nouveau_password'] ?? '';
        if (!$nom || !$email) { echo json_encode(['success'=>false,'message'=>'Champs requis']); exit; }
        $db = \Database::getInstance()->getConnection();
        $db->prepare("UPDATE utilisateurs SET nom=:nom, email=:email WHERE id=:id")
           ->execute([':nom'=>$nom,':email'=>$email,':id'=>$adminId]);
        if ($nouveauMdp) {
            $hash = password_hash($nouveauMdp, PASSWORD_DEFAULT);
            $db->prepare("UPDATE utilisateurs SET mot_de_passe=:h WHERE id=:id")->execute([':h'=>$hash,':id'=>$adminId]);
        }
        $_SESSION['user_nom']   = $nom;
        $_SESSION['user_email'] = $email;
        echo json_encode(['success'=>true]);
        exit;
    }

    // ══════════════════════════════════════════════════════
    //  Statistiques — AJAX
    // ══════════════════════════════════════════════════════

    /**
     * Retourne les statistiques globales de l'hôpital et par sous-service
     * pour les graphiques du dashboard admin.
     */
    public function getStatsAdmin(): void {
        AuthHelper::exigerRole('admin');
        header('Content-Type: application/json');

        $jours = max(7, min(365, (int)($_GET['jours'] ?? 30)));
        $db = \Database::getInstance()->getConnection();

        // ── Évolution globale hôpital ──
        $stmtGlobal = $db->prepare(
            "SELECT
                DATE(heure_passage_estimee) AS jour,
                COUNT(*) AS total,
                SUM(statut = 'traite') AS traitees,
                SUM(statut = 'absent') AS absentes,
                SUM(statut = 'annule') AS annulees
             FROM consultations
             WHERE DATE(heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
               AND DATE(heure_passage_estimee) <= CURDATE()
             GROUP BY DATE(heure_passage_estimee)
             ORDER BY jour ASC"
        );
        $stmtGlobal->execute([':jours' => $jours]);
        $evolutionGlobale = $stmtGlobal->fetchAll(\PDO::FETCH_ASSOC);

        // ── Totaux globaux ──
        $stmtTot = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(statut = 'traite') AS traitees,
                SUM(statut = 'absent') AS absentes,
                SUM(statut = 'annule') AS annulees,
                COALESCE(ROUND(AVG(
                  CASE WHEN heure_debut_reelle IS NOT NULL AND heure_fin_reelle IS NOT NULL
                       THEN TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)
                  END
                )), 0) AS duree_moy_sec
             FROM consultations
             WHERE DATE(heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)"
        );
        $stmtTot->execute([':jours' => $jours]);
        $totauxGlobaux = $stmtTot->fetch(\PDO::FETCH_ASSOC);

        // ── Évolution par sous-service ──
        $stmtSS = $db->prepare(
            "SELECT
                ss.id AS ss_id,
                ss.nom AS ss_nom,
                DATE(c.heure_passage_estimee) AS jour,
                COUNT(*) AS total,
                SUM(c.statut = 'traite') AS traitees,
                SUM(c.statut = 'absent') AS absentes,
                SUM(c.statut = 'annule') AS annulees
             FROM consultations c
             JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE DATE(c.heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
               AND DATE(c.heure_passage_estimee) <= CURDATE()
             GROUP BY ss.id, ss.nom, DATE(c.heure_passage_estimee)
             ORDER BY ss.nom ASC, jour ASC"
        );
        $stmtSS->execute([':jours' => $jours]);
        $rawSS = $stmtSS->fetchAll(\PDO::FETCH_ASSOC);

        // Regrouper par sous-service
        $parSousService = [];
        foreach ($rawSS as $row) {
            $ssId = $row['ss_id'];
            if (!isset($parSousService[$ssId])) {
                $parSousService[$ssId] = ['ss_id' => $ssId, 'ss_nom' => $row['ss_nom'], 'evolution' => []];
            }
            $parSousService[$ssId]['evolution'][] = [
                'jour'     => $row['jour'],
                'total'    => (int)$row['total'],
                'traitees' => (int)$row['traitees'],
                'absentes' => (int)$row['absentes'],
                'annulees' => (int)$row['annulees'],
            ];
        }

        // ── Totaux par sous-service ──
        $stmtTotSS = $db->prepare(
            "SELECT
                ss.id AS ss_id,
                ss.nom AS ss_nom,
                COUNT(*) AS total,
                SUM(c.statut = 'traite') AS traitees,
                SUM(c.statut = 'absent') AS absentes,
                SUM(c.statut = 'annule') AS annulees
             FROM consultations c
             JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE DATE(c.heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
             GROUP BY ss.id, ss.nom
             ORDER BY ss.nom ASC"
        );
        $stmtTotSS->execute([':jours' => $jours]);
        $totauxSS = $stmtTotSS->fetchAll(\PDO::FETCH_ASSOC);

        echo json_encode([
            'success'          => true,
            'jours'            => $jours,
            'evolution_globale'=> $evolutionGlobale,
            'totaux_globaux'   => $totauxTot = $totauxGlobaux,
            'par_sous_service' => array_values($parSousService),
            'totaux_ss'        => $totauxSS,
        ]);
        exit;
    }

    /**
     * Retourne les données de temps d'attente moyen par sous-service et pour l'hôpital
     */
    public function getTempsAttenteAdmin(): void {
        AuthHelper::exigerRole('admin');
        header('Content-Type: application/json');
        $jours = max(7, min(9999, (int)($_GET['jours'] ?? 30)));
        $db = \Database::getInstance()->getConnection();
        $intervalSql = $jours >= 9999 ? '3650' : (string)(int)$jours;

        // ── Temps d'attente moyen global hôpital ──
        $stmtG = $db->prepare(
            "SELECT
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle)), 1) AS attente_moy_min,
                ROUND(AVG(CASE WHEN DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                               THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle) END), 1) AS attente_7j_min,
                ROUND(AVG(CASE WHEN DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                               THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle) END), 1) AS attente_30j_min,
                COUNT(*) AS nb_mesures,
                MIN(DATE(c.heure_emission)) AS premier_jour
             FROM consultations c
             WHERE c.statut = 'traite'
               AND c.heure_debut_reelle IS NOT NULL"
        );
        $stmtG->execute();
        $globalHopital = $stmtG->fetch(\PDO::FETCH_ASSOC);

        // ── Évolution globale du temps d'attente par jour ──
        $stmtEv = $db->prepare(
            "SELECT
                DATE(c.heure_emission) AS jour,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle)), 1) AS attente_moy_min,
                COUNT(*) AS nb_mesures
             FROM consultations c
             WHERE c.statut = 'traite'
               AND c.heure_debut_reelle IS NOT NULL
               AND DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
             GROUP BY DATE(c.heure_emission)
             ORDER BY jour ASC"
        );
        $stmtEv->execute([':jours' => $intervalSql]);
        $evolutionAttente = $stmtEv->fetchAll(\PDO::FETCH_ASSOC);

        // ── Temps d'attente moyen par sous-service ──
        $stmtSS = $db->prepare(
            "SELECT
                ss.id AS ss_id,
                ss.nom AS ss_nom,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle)), 1) AS attente_moy_min,
                ROUND(AVG(CASE WHEN DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                               THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle) END), 1) AS attente_7j_min,
                ROUND(AVG(CASE WHEN DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                               THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle) END), 1) AS attente_30j_min,
                COUNT(*) AS nb_mesures
             FROM consultations c
             JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE c.statut = 'traite'
               AND c.heure_debut_reelle IS NOT NULL
             GROUP BY ss.id, ss.nom
             ORDER BY ss.nom ASC"
        );
        $stmtSS->execute();
        $attenteParSS = $stmtSS->fetchAll(\PDO::FETCH_ASSOC);

        // ── Évolution par sous-service ──
        $stmtSSEv = $db->prepare(
            "SELECT
                ss.id AS ss_id,
                ss.nom AS ss_nom,
                DATE(c.heure_emission) AS jour,
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle)), 1) AS attente_moy_min,
                COUNT(*) AS nb_mesures
             FROM consultations c
             JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE c.statut = 'traite'
               AND c.heure_debut_reelle IS NOT NULL
               AND DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
             GROUP BY ss.id, ss.nom, DATE(c.heure_emission)
             ORDER BY ss.nom ASC, jour ASC"
        );
        $stmtSSEv->execute([':jours' => $intervalSql]);
        $rawSSEv = $stmtSSEv->fetchAll(\PDO::FETCH_ASSOC);

        // Grouper évolution par SS
        $evolutionParSS = [];
        foreach ($rawSSEv as $row) {
            $id = $row['ss_id'];
            if (!isset($evolutionParSS[$id])) {
                $evolutionParSS[$id] = ['ss_id' => $id, 'ss_nom' => $row['ss_nom'], 'evolution' => []];
            }
            $evolutionParSS[$id]['evolution'][] = [
                'jour' => $row['jour'],
                'attente_moy_min' => $row['attente_moy_min'],
                'nb_mesures' => (int)$row['nb_mesures'],
            ];
        }

        echo json_encode([
            'success'           => true,
            'global_hopital'    => $globalHopital,
            'evolution_globale' => $evolutionAttente,
            'par_sous_service'  => $attenteParSS,
            'evolution_ss'      => array_values($evolutionParSS),
        ]);
        exit;
    }

    /**
     * Prépare et rend la vue embarquée consultation pour l'admin-medecin.
     * Appelé via AdminController quand tab=consultations_medecin.
     */
    public function afficherConsultationsAdmin(): void
    {
        AuthHelper::exigerAuthentification();
        if (!AuthHelper::estAdminMedecin()) {
            // Rediriger vers dashboard admin si pas admin-médecin
            header('Location: admin.php');
            exit;
        }

        $medecinId = (int)$_SESSION['medecin_id'];

        // Charger le modèle médecin pour obtenir les données
        require_once __DIR__ . '/../models/MedecinModel.php';
        $medecinModel = new MedecinModel();

        $medecin     = $medecinModel->trouverParId($medecinId);
        $affectation = $medecinModel->getSousServiceMedecin($medecinId);
        $consultations = [];
        $stats         = ['total'=>0,'traitees'=>0,'en_attente'=>0,'absentes'=>0,'annulees'=>0,'duree_moy_sec'=>0];

        if ($affectation && !empty($affectation['ss_id'])) {
            $ssId          = (int)$affectation['ss_id'];
            $stats         = $medecinModel->statsJour($ssId) ?: $stats;
            $consultations = $medecinModel->consultationsDuJour($ssId, $medecinId) ?: [];
        }

        // Mode refresh JSON uniquement (AJAX polling)
        if (!empty($_GET['fragment']) && $_GET['fragment'] === 'data') {
            header('Content-Type: application/json');
            echo json_encode([
                'success'       => true,
                'consultations' => $consultations,
                'stats'         => $stats,
            ]);
            exit;
        }

        // Rendre le fragment HTML complet (premier chargement)
        if (!defined('QUEUECARE_EMBED'))       define('QUEUECARE_EMBED', true);
        if (!defined('QUEUECARE_EMBED_ADMIN')) define('QUEUECARE_EMBED_ADMIN', true);
        require_once __DIR__ . '/../views/medecin/consultation_embed.php';
        exit;
    }


    // ══════════════════════════════════════════════════════
    //  Rôle médecin du directeur
    // ══════════════════════════════════════════════════════

    /**
     * Active le rôle médecin pour l'admin :
     * - Crée un enregistrement dans medecins
     * - Affecte l'admin à un sous-service
     * - Met à jour utilisateurs.medecin_id
     * - Met medecin_id en session
     */
    public function activerRoleMedecin(): void {
        AuthHelper::exigerRole('admin');
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode invalide.']);
            exit;
        }

        $specialite   = trim($_POST['specialite']    ?? '');
        $sousServiceId = (int)($_POST['sous_service_id'] ?? 0);
        $telephone    = trim($_POST['telephone']     ?? '');

        if (empty($specialite) || $sousServiceId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Spécialité et sous-service requis.']);
            exit;
        }

        // Déjà activé ?
        if (!empty($_SESSION['medecin_id'])) {
            echo json_encode(['success' => true, 'message' => 'Rôle médecin déjà actif.', 'medecin_id' => $_SESSION['medecin_id']]);
            exit;
        }

        require_once __DIR__ . '/../models/MedecinModel.php';
        $medecinModel = new MedecinModel();
        $db = \Database::getInstance()->getConnection();

        // Extraire nom/prénom depuis user_nom (ex. "Dr Ebele Magloire" → prenom=Ebele, nom=Magloire)
        $nomComplet = $_SESSION['user_nom'] ?? 'Directeur';
        // Retirer préfixe Dr/Dr. s'il existe
        $nomComplet = preg_replace('/^(Dr\.?|Pr\.?)\s*/i', '', trim($nomComplet));
        $parts = explode(' ', $nomComplet, 2);
        $prenom = $parts[0] ?? $nomComplet;
        $nom    = $parts[1] ?? $nomComplet;

        $email  = $_SESSION['user_email'] ?? '';
        // Générer un hash de mot de passe temporaire (l'admin se connecte via utilisateurs, pas via medecins)
        $passwordHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        // Vérifier si l'email médecin existe déjà
        if ($medecinModel->emailExiste($email)) {
            $medecinExist = $medecinModel->trouverParEmail($email);
            $medecinId = $medecinExist ? (int)$medecinExist['id'] : 0;
        } else {
            // Créer le médecin
            $ok = $medecinModel->creer([
                'nom'        => strtoupper($nom),
                'prenom'     => $prenom,
                'specialite' => $specialite,
                'telephone'  => $telephone,
                'email'      => $email,
                'password'   => $passwordHash,
            ]);
            if (!$ok) {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du profil médecin.']);
                exit;
            }
            $medecinId = $medecinModel->dernierID();
        }

        // Affecter au sous-service
        $medecinModel->affecterSousService($medecinId, $sousServiceId);

        // Ajouter jours de travail par défaut (lun-ven)
        try {
            $stmt = $db->prepare("INSERT IGNORE INTO medecin_jours_travail (medecin_id, jour_semaine, actif) VALUES (?,?,1),(?,?,1),(?,?,1),(?,?,1),(?,?,1)");
            $stmt->execute([$medecinId,1,$medecinId,2,$medecinId,3,$medecinId,4,$medecinId,5]);
        } catch (\Exception $e) {}

        // Mettre à jour utilisateurs.medecin_id
        $userId = $_SESSION['user_id'] ?? 0;
        try {
            $stmt = $db->prepare("UPDATE utilisateurs SET medecin_id = ? WHERE id = ?");
            $stmt->execute([$medecinId, $userId]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Profil médecin créé mais liaison échouée : ' . $e->getMessage()]);
            exit;
        }

        // Mettre en session
        $_SESSION['medecin_id'] = $medecinId;

        echo json_encode([
            'success'    => true,
            'message'    => 'Rôle médecin activé avec succès. Rechargement…',
            'medecin_id' => $medecinId,
        ]);
        exit;
    }

    /**
     * Retourne JSON des actions médecin (demarrer, terminer, absent, pause, reprendre)
     * pour l'admin-médecin — délègue au MedecinController.
     */
    public function actionConsultationAdmin(): void {
        AuthHelper::exigerRole('admin');
        header('Content-Type: application/json');
        if (empty($_SESSION['medecin_id'])) {
            echo json_encode(['success' => false, 'message' => 'Rôle médecin non activé.']);
            exit;
        }
        require_once __DIR__ . '/MedecinController.php';
        $ctrl = new MedecinController();
        $action = $_GET['sous_action'] ?? '';
        switch ($action) {
            case 'demarrer_consultation_ajax':   $ctrl->demarrerConsultationAjax();   break;
            case 'terminer_consultation_ajax':   $ctrl->terminerConsultationAjax();   break;
            case 'marquer_absent_ajax':          $ctrl->marquerAbsentAjax();           break;
            case 'mettre_en_pause_ajax':         $ctrl->mettreEnPauseAjax();          break;
            case 'reprendre_consultation_ajax':  $ctrl->reprendreConsultationAjax();  break;
            case 'fixer_prochain_rdv_ajax':      $ctrl->fixerProchainRdvAjax();       break;
            case 'annuler_toutes':               $ctrl->annulerToutesAjax();          break;
            default:
                echo json_encode(['success'=>false,'message'=>'Action inconnue : '.$action]);
                exit;
        }
    }

}