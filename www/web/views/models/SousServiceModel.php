<?php
/**
 * models/SousServiceModel.php
 * Modèle MVC — Gestion des sous-services (spécialités médicales)
 * 
 * VERSION MONO-SERVICE
 * Ce modèle gère les sous-services qui sont rattachés au service unique (id=1)
 */

require_once __DIR__ . '/../config/database.php';

class SousServiceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════
       RÉCUPÉRATION DES SOUS-SERVICES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère tous les sous-services (uniquement du service principal)
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE s.id = 1
             ORDER BY ss.nom'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère tous les sous-services actifs
     */
    public function getAllActifs(): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE s.id = 1 AND ss.statut = "actif"
             ORDER BY ss.nom'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère un sous-service par son ID
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom, s.adresse as service_adresse,
                    s.horaires_ouverture, s.horaires_fermeture, s.pause_debut, s.pause_fin, s.jours_fermeture
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupère un sous-service par son nom
     */
    public function getByNom(string $nom)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.nom = :nom AND s.id = 1
             LIMIT 1'
        );
        $stmt->execute([':nom' => $nom]);
        return $stmt->fetch();
    }

    /**
     * Récupère les sous-services associés à un médecin
     */
    public function getByMedecinId(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*
             FROM sous_services ss
             JOIN medecin_sous_service mss ON mss.sous_service_id = ss.id
             WHERE mss.medecin_id = :mid
             ORDER BY ss.nom'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère le sous-service d'un gestionnaire
     */
    public function getByGestionnaireId(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             JOIN gestionnaires g ON g.sous_service_id = ss.id
             WHERE g.id = :gid
             LIMIT 1'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    /* ═══════════════════════════════════════════════════════════
       CRUD SOUS-SERVICES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Vérifie si un nom de sous-service existe déjà
     */
    public function nomExiste(string $nom, ?int $exclureId = null): bool
    {
        if ($exclureId) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sous_services 
                 WHERE nom = :nom AND service_id = 1 AND id != :id'
            );
            $stmt->execute([':nom' => $nom, ':id' => $exclureId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sous_services WHERE nom = :nom AND service_id = 1'
            );
            $stmt->execute([':nom' => $nom]);
        }
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouveau sous-service
     */
    public function creer(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sous_services (service_id, nom, description, duree_rdv_defaut, duree_estimee, capacite_horaire, statut)
             VALUES (1, :nom, :description, :duree_rdv_defaut, :duree_estimee, :capacite_horaire, :statut)'
        );
        
        $duree = (int)($data['duree_estimee'] ?? $data['duree_rdv_defaut'] ?? 1800);
        
        $success = $stmt->execute([
            ':nom'               => htmlspecialchars(trim($data['nom'])),
            ':description'       => htmlspecialchars(trim($data['description'] ?? '')),
            ':duree_rdv_defaut'  => $duree,
            ':duree_estimee'     => $duree,
            ':capacite_horaire'  => (int)($data['capacite_horaire'] ?? 10),
            ':statut'            => $data['statut'] ?? 'actif'
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Met à jour un sous-service
     */
    public function mettreAJour(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services 
             SET nom = :nom, 
                 description = :description, 
                 duree_rdv_defaut = :duree_rdv_defaut,
                 duree_estimee = :duree_estimee,
                 capacite_horaire = :capacite_horaire,
                 statut = :statut
             WHERE id = :id'
        );
        
        $duree = (int)($data['duree_estimee'] ?? $data['duree_rdv_defaut'] ?? 1800);
        
        return $stmt->execute([
            ':id'                => $id,
            ':nom'               => htmlspecialchars(trim($data['nom'])),
            ':description'       => htmlspecialchars(trim($data['description'] ?? '')),
            ':duree_rdv_defaut'  => $duree,
            ':duree_estimee'     => $duree,
            ':capacite_horaire'  => (int)($data['capacite_horaire'] ?? 10),
            ':statut'            => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Met à jour la durée estimée d'un sous-service (recalcul automatique)
     */
    public function mettreAJourDureeEstimee(int $id, int $dureeSecondes): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services SET duree_estimee = :duree WHERE id = :id'
        );
        return $stmt->execute([':duree' => $dureeSecondes, ':id' => $id]);
    }

    /**
     * Bascule le statut actif/inactif d'un sous-service
     */
    public function basculerStatut(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services 
             SET statut = IF(statut = "actif", "inactif", "actif") 
             WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Active un sous-service
     */
    public function activer(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE sous_services SET statut = "actif" WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Désactive un sous-service
     */
    public function desactiver(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE sous_services SET statut = "inactif" WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Supprime un sous-service (vérifie les dépendances)
     */
    public function supprimer(int $id): array
    {
        // Vérifier les consultations associées
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM consultations WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbConsultations = (int)$stmt->fetchColumn();

        if ($nbConsultations > 0) {
            return [
                'ok' => false, 
                'msg' => "Impossible de supprimer : {$nbConsultations} consultation(s) associée(s)."
            ];
        }

        // Vérifier les médecins associés
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecin_sous_service WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbMedecins = (int)$stmt->fetchColumn();

        if ($nbMedecins > 0) {
            return [
                'ok' => false, 
                'msg' => "Impossible de supprimer : {$nbMedecins} médecin(s) associé(s)."
            ];
        }

        // Vérifier les gestionnaires associés
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gestionnaires WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbGestionnaires = (int)$stmt->fetchColumn();

        if ($nbGestionnaires > 0) {
            return [
                'ok' => false, 
                'msg' => "Impossible de supprimer : {$nbGestionnaires} gestionnaire(s) associé(s)."
            ];
        }

        // Supprimer
        $stmt = $this->db->prepare('DELETE FROM sous_services WHERE id = :id');
        $ok = $stmt->execute([':id' => $id]);
        
        return $ok 
            ? ['ok' => true, 'msg' => 'Sous-service supprimé avec succès.'] 
            : ['ok' => false, 'msg' => 'Erreur lors de la suppression.'];
    }

    /* ═══════════════════════════════════════════════════════════
       STATISTIQUES ET MÉTRIQUES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère les statistiques d'un sous-service
     */
    public function getStats(int $id): array
    {
        $stats = [];
        
        // Nombre de consultations aujourd'hui
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as total,
                    SUM(statut = "traite") as traitees,
                    SUM(statut IN ("en_attente", "confirme")) as en_attente
             FROM consultations
             WHERE sous_service_id = :id AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':id' => $id]);
        $stats['aujourdhui'] = $stmt->fetch() ?: ['total' => 0, 'traitees' => 0, 'en_attente' => 0];
        
        // Nombre de médecins affectés
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as nb_medecins FROM medecin_sous_service WHERE sous_service_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $stats['nb_medecins'] = (int)$stmt->fetchColumn();
        
        // Nombre de gestionnaires affectés
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as nb_gestionnaires FROM gestionnaires WHERE sous_service_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $stats['nb_gestionnaires'] = (int)$stmt->fetchColumn();
        
        // Durée moyenne réelle (sur les 30 derniers jours)
        $stmt = $this->db->prepare(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)) as duree_moyenne
             FROM consultations
             WHERE sous_service_id = :id
               AND statut = "traite"
               AND heure_debut_reelle IS NOT NULL
               AND heure_fin_reelle IS NOT NULL
               AND heure_emission > DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        $stats['duree_moyenne_reelle'] = $result['duree_moyenne'] ? round($result['duree_moyenne'] / 60) : null;
        
        return $stats;
    }

    /**
     * Récupère la durée estimée actuelle d'un sous-service
     */
    public function getDureeEstimee(int $id): int
    {
        $stmt = $this->db->prepare('SELECT duree_estimee FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ? (int)$result['duree_estimee'] : 1800;
    }

    /**
     * Calcule le temps d'attente estimé pour un sous-service
     * @param int $sousServiceId ID du sous-service
     * @return int Temps d'attente en minutes
     */
    public function calculerTempsAttente(int $sousServiceId): int
    {
        // Récupérer le nombre de personnes en attente
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as nb_attente
             FROM consultations
             WHERE sous_service_id = :id
               AND statut IN ("en_attente", "confirme")
               AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':id' => $sousServiceId]);
        $nbAttente = (int)$stmt->fetchColumn();
        
        // Récupérer la durée estimée
        $dureeEstimee = $this->getDureeEstimee($sousServiceId);
        $dureeMinutes = ceil($dureeEstimee / 60);
        
        // Temps d'attente = (nombre de personnes devant) × durée moyenne
        return $nbAttente * $dureeMinutes;
    }

    /* ═══════════════════════════════════════════════════════════
       MÉDECINS ASSOCIÉS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère tous les médecins d'un sous-service
     */
    public function getMedecins(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             WHERE mss.sous_service_id = :ss_id
             ORDER BY m.nom, m.prenom'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Affecte un médecin à un sous-service
     */
    public function affecterMedecin(int $sousServiceId, int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medecin_sous_service (medecin_id, sous_service_id, date_affectation)
             VALUES (:mid, :ssid, CURDATE())
             ON DUPLICATE KEY UPDATE date_affectation = date_affectation'
        );
        return $stmt->execute([':mid' => $medecinId, ':ssid' => $sousServiceId]);
    }

    /**
     * Retire l'affectation d'un médecin à un sous-service
     */
    public function retirerMedecin(int $sousServiceId, int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM medecin_sous_service 
             WHERE medecin_id = :mid AND sous_service_id = :ssid'
        );
        return $stmt->execute([':mid' => $medecinId, ':ssid' => $sousServiceId]);
    }

    /**
     * Vérifie si un médecin est affecté à un sous-service
     */
    public function estMedecinAffecte(int $sousServiceId, int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM medecin_sous_service 
             WHERE medecin_id = :mid AND sous_service_id = :ssid'
        );
        $stmt->execute([':mid' => $medecinId, ':ssid' => $sousServiceId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les médecins non affectés à un sous-service
     */
    public function getMedecinsNonAffectes(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*
             FROM medecins m
             WHERE m.id NOT IN (
                 SELECT medecin_id FROM medecin_sous_service WHERE sous_service_id = :ssid
             )
             ORDER BY m.nom, m.prenom'
        );
        $stmt->execute([':ssid' => $sousServiceId]);
        return $stmt->fetchAll();
    }

    /* ═══════════════════════════════════════════════════════════
       PRISE DE RDV EN LIGNE (application mobile patient)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère, pour un sous-service donné, tous les créneaux horaires
     * disponibles sur les N prochains jours (par défaut 7 = une semaine),
     * agrégés sur TOUS les médecins affectés à ce sous-service.
     *
     * Le patient choisit uniquement un sous-service + un créneau horaire :
     * le médecin qui le prendra en charge n'est pas choisi à l'avance,
     * il sera déterminé automatiquement au moment de la réservation
     * parmi les médecins encore disponibles sur ce créneau.
     *
     * @return array Liste de jours, chacun avec sa liste de créneaux :
     *  [
     *    [
     *      'date' => '2026-06-24',
     *      'jour_semaine' => 3,
     *      'jour_label' => 'Mercredi',
     *      'creneaux' => [
     *          ['heure' => '08:00', 'capacite_totale' => 20, 'pris' => 4, 'disponible' => 16],
     *          ...
     *      ]
     *    ],
     *    ...
     *  ]
     */
    public function getCreneauxSemaine(int $sousServiceId, int $nbJours = 7): array
    {
        $sousService = $this->getById($sousServiceId);
        if (!$sousService) {
            return [];
        }

        $capaciteHoraireParMedecin = (int)($sousService['capacite_horaire'] ?? 10);
        $ouverture = $sousService['horaires_ouverture'] ?? '08:00:00';
        $fermeture = $sousService['horaires_fermeture'] ?? '18:00:00';
        $pauseDebut = $sousService['pause_debut'] ?? null;
        $pauseFin   = $sousService['pause_fin'] ?? null;
        $joursFermeture = !empty($sousService['jours_fermeture'])
            ? array_map('intval', explode(',', $sousService['jours_fermeture']))
            : [];

        $hOuv  = (int)date('H', strtotime($ouverture));
        $hFerm = (int)date('H', strtotime($fermeture));
        $hPauseDebut = $pauseDebut ? (int)date('H', strtotime($pauseDebut)) : null;
        $hPauseFin   = $pauseFin ? (int)date('H', strtotime($pauseFin)) : null;

        // Médecins affectés à ce sous-service avec leurs jours de travail
        $stmtMed = $this->db->prepare(
            'SELECT m.id, m.statut, GROUP_CONCAT(mjt.jour_semaine) AS jours_travail
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN medecin_jours_travail mjt ON mjt.medecin_id = m.id AND mjt.actif = 1
             WHERE mss.sous_service_id = :ssid
             GROUP BY m.id, m.statut'
        );
        $stmtMed->execute([':ssid' => $sousServiceId]);
        $medecins = $stmtMed->fetchAll();

        // Pré-traiter les jours de travail de chaque médecin disponible
        $medecinsParJour = []; // [jour_semaine => nb_medecins]
        foreach ($medecins as $m) {
            if ($m['statut'] !== 'disponible') {
                continue; // indisponible ou en congé : n'apporte pas de capacité
            }
            $jours = !empty($m['jours_travail']) ? array_map('intval', explode(',', $m['jours_travail'])) : [];
            foreach ($jours as $j) {
                $medecinsParJour[$j] = ($medecinsParJour[$j] ?? 0) + 1;
            }
        }

        $labelsJours = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

        $resultat = [];

        for ($i = 1; $i <= $nbJours; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} day"));
            // Convention déjà utilisée dans le projet : 1=Lundi ... 7=Dimanche
            $jourSemaineIso = (int)date('N', strtotime($date));

            if (in_array($jourSemaineIso, $joursFermeture, true)) {
                continue; // jour de fermeture de l'établissement
            }

            $nbMedecinsDispo = $medecinsParJour[$jourSemaineIso] ?? 0;
            if ($nbMedecinsDispo === 0) {
                continue; // aucun médecin ne travaille ce jour pour ce sous-service
            }

            $capaciteTotaleParHeure = $capaciteHoraireParMedecin * $nbMedecinsDispo;

            // Consultations déjà prises ce jour-là pour ce sous-service, par heure,
            // tous médecins confondus
            $stmtOcc = $this->db->prepare(
                'SELECT HOUR(heure_passage_estimee) AS heure, COUNT(*) AS nb
                 FROM consultations
                 WHERE sous_service_id = :ssid
                   AND DATE(heure_passage_estimee) = :date
                   AND statut NOT IN ("annule", "absent")
                 GROUP BY HOUR(heure_passage_estimee)'
            );
            $stmtOcc->execute([':ssid' => $sousServiceId, ':date' => $date]);
            $occupes = [];
            foreach ($stmtOcc->fetchAll() as $row) {
                $occupes[(int)$row['heure']] = (int)$row['nb'];
            }

            $creneauxJour = [];
            for ($h = $hOuv; $h < $hFerm; $h++) {
                // Exclure la tranche de pause du service si définie
                if ($hPauseDebut !== null && $hPauseFin !== null && $h >= $hPauseDebut && $h < $hPauseFin) {
                    continue;
                }

                $pris = $occupes[$h] ?? 0;
                $disponible = $capaciteTotaleParHeure - $pris;

                if ($disponible > 0) {
                    $creneauxJour[] = [
                        'heure'           => sprintf('%02d:00', $h),
                        'capacite_totale' => $capaciteTotaleParHeure,
                        'pris'            => $pris,
                        'disponible'      => $disponible,
                    ];
                }
            }

            if (!empty($creneauxJour)) {
                $resultat[] = [
                    'date'         => $date,
                    'jour_semaine' => $jourSemaineIso,
                    'jour_label'   => $labelsJours[$jourSemaineIso] ?? '',
                    'creneaux'     => $creneauxJour,
                ];
            }
        }

        return $resultat;
    }

    /**
     * Choisit automatiquement, parmi les médecins affectés à un sous-service,
     * un médecin encore disponible (statut "disponible", jour de travail actif,
     * et n'ayant pas atteint sa capacité horaire) pour un jour/heure donné.
     * Retourne l'ID du médecin choisi (celui ayant le moins de consultations
     * sur ce créneau, pour équilibrer la charge), ou null si aucun n'est libre.
     */
    public function getMedecinDisponiblePourCreneau(int $sousServiceId, string $date, string $heure): ?int
    {
        $jourSemaineIso = (int)date('N', strtotime($date));
        $heureDebut = date('H:00:00', strtotime($heure));
        $heureFin   = date('H:59:59', strtotime($heure));

        $sousService = $this->getById($sousServiceId);
        $capaciteHoraireParMedecin = (int)($sousService['capacite_horaire'] ?? 10);

        $stmt = $this->db->prepare(
            'SELECT m.id,
                    (SELECT COUNT(*) FROM consultations c
                       WHERE c.medecin_id = m.id
                         AND c.sous_service_id = :ssid2
                         AND DATE(c.heure_passage_estimee) = :date2
                         AND c.heure_passage_estimee BETWEEN :hdebut AND :hfin
                         AND c.statut NOT IN ("annule", "absent")) AS nb_prises
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             JOIN medecin_jours_travail mjt ON mjt.medecin_id = m.id AND mjt.actif = 1 AND mjt.jour_semaine = :jour
             WHERE mss.sous_service_id = :ssid
               AND m.statut = "disponible"
             ORDER BY nb_prises ASC
             LIMIT 1'
        );
        $stmt->execute([
            ':ssid2'   => $sousServiceId,
            ':date2'   => $date,
            ':hdebut'  => $date . ' ' . $heureDebut,
            ':hfin'    => $date . ' ' . $heureFin,
            ':jour'    => $jourSemaineIso,
            ':ssid'    => $sousServiceId,
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        if ((int)$row['nb_prises'] >= $capaciteHoraireParMedecin) {
            return null; // ce médecin (le moins chargé) a déjà atteint sa capacité
        }

        return (int)$row['id'];
    }

    /**
     * Calcule le rang RÉEL et DYNAMIQUE d'une consultation dans la file
     * d'attente de son sous-service, pour le jour de son passage.
     *
     * Contrairement au champ `rang` stocké en base (figé à la création),
     * ce rang est recalculé à chaque appel : il correspond au nombre de
     * consultations encore actives (en_attente, confirme, en_pause) qui
     * précèdent celle-ci, +1. Ainsi le rang DIMINUE automatiquement dès
     * qu'une consultation devant elle est terminée, marquée absente ou
     * annulée — sans qu'aucune tâche de fond n'ait besoin de réécrire les
     * rangs de tous les patients en attente.
     *
     * Retourne null si la consultation n'existe pas, ou si elle n'est plus
     * en attente (déjà en cours, traitée, annulée...).
     */
    public function getRangDynamique(int $consultationId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, sous_service_id, heure_passage_estimee, statut, rang
             FROM consultations
             WHERE id = :id'
        );
        $stmt->execute([':id' => $consultationId]);
        $consultation = $stmt->fetch();

        if (!$consultation) {
            return null;
        }

        // Une consultation en cours est "rang 0" (c'est son tour).
        if ($consultation['statut'] === 'en_cours') {
            return ['rang' => 0, 'personnes_devant' => 0, 'statut' => $consultation['statut']];
        }

        // Rang significatif uniquement pour les statuts encore actifs dans la file
        if (!in_array($consultation['statut'], ['en_attente', 'confirme', 'en_pause'], true)) {
            return ['rang' => null, 'personnes_devant' => null, 'statut' => $consultation['statut']];
        }

        $dateRef = $consultation['heure_passage_estimee']
            ? date('Y-m-d', strtotime($consultation['heure_passage_estimee']))
            : date('Y-m-d');

        // Nombre de consultations actives qui sont devant elle dans la file :
        // - toute consultation déjà "en_cours" est forcément devant (c'est son tour) ;
        // - sinon on compare le rang d'arrivée stocké, et en cas d'égalité
        //   on départage par l'heure de passage estimée.
        $stmtDevant = $this->db->prepare(
            'SELECT COUNT(*) FROM consultations
             WHERE sous_service_id = :ssid
               AND DATE(heure_passage_estimee) = :date
               AND id != :id
               AND (
                    statut = "en_cours"
                    OR (
                        statut IN ("en_attente", "confirme", "en_pause")
                        AND (
                            rang < :rang
                            OR (rang = :rang AND heure_passage_estimee < :heure_passage_estimee)
                        )
                    )
               )'
        );
        $stmtDevant->execute([
            ':ssid'                   => $consultation['sous_service_id'],
            ':date'                   => $dateRef,
            ':id'                     => $consultationId,
            ':rang'                   => $consultation['rang'],
            ':heure_passage_estimee'  => $consultation['heure_passage_estimee'],
        ]);
        $personnesDevant = (int)$stmtDevant->fetchColumn();

        return [
            'rang'             => $personnesDevant + 1,
            'personnes_devant' => $personnesDevant,
            'statut'           => $consultation['statut'],
        ];
    }

    /* ═══════════════════════════════════════════════════════════
       QR CODES ASSOCIÉS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère le QR code actif d'un sous-service
     */
    public function getQRCodeActif(int $sousServiceId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM qr_codes 
             WHERE sous_service_id = :ss_id 
               AND statut = "actif" 
               AND expire_at > NOW()
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetch();
    }

    /**
     * Génère un nouveau QR code pour un sous-service
     */
    public function genererQRCode(int $sousServiceId, int $createdBy, string $token, string $qrCodePath, string $expireAt): int|false
    {
        $baseUrl = $this->getBaseUrl();
        $content = $baseUrl . '/index.php?action=scanner_qr&token=' . $token;
        
        $stmt = $this->db->prepare(
            'INSERT INTO qr_codes (sous_service_id, token, qr_code_path, expire_at, content, created_by, statut)
             VALUES (:ss_id, :token, :path, :expire_at, :content, :created_by, "actif")'
        );
        
        $success = $stmt->execute([
            ':ss_id'     => $sousServiceId,
            ':token'     => $token,
            ':path'      => $qrCodePath,
            ':expire_at' => $expireAt,
            ':content'   => $content,
            ':created_by'=> $createdBy
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . ($script != '/' ? $script : '');
    }
}