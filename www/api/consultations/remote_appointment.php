<?php
$auth  = requireAuth();
$body  = getBody();
$ss_id = (int)   ($body['ss_id']    ?? 0);
$motif = trim($body['motif'] ?? '');

if (!$ss_id) jsonError('ss_id requis', 'MISSING_FIELDS');

$db = getDB();

// Vérifier que le sous-service existe
$stmt = $db->prepare("SELECT id, duree_estimee, nom FROM sous_services WHERE id = ? AND statut = 'actif' LIMIT 1");
$stmt->execute([$ss_id]);
$ss = $stmt->fetch();
if (!$ss) jsonError('Sous-service introuvable', 'NOT_FOUND', 404);

// Vérifier pas de doublon aujourd'hui
$stmt = $db->prepare(
    "SELECT id FROM consultations
     WHERE patient_id = ? AND sous_service_id = ?
       AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('traite', 'annule', 'absent') LIMIT 1"
);
$stmt->execute([$auth['sub'], $ss_id]);
if ($stmt->fetch()) jsonError('Vous avez déjà une consultation active pour ce service', 'DUPLICATE_TICKET', 409);

// Choisir le médecin le moins occupé pour la date demandée
$stmt = $db->prepare(
    'SELECT m.id,
            COALESCE(occ.nb, 0) AS nb_consultations
     FROM medecins m
     JOIN medecin_sous_service mss ON mss.medecin_id = m.id
     LEFT JOIN (
         SELECT medecin_id, COUNT(*) AS nb
         FROM consultations
         WHERE sous_service_id = :ss
           AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = :date
           AND medecin_id IS NOT NULL
           AND statut IN ("en_attente","confirme","en_cours","en_pause")
         GROUP BY medecin_id
     ) occ ON occ.medecin_id = m.id
     WHERE mss.sous_service_id = :ss2
       AND m.statut = "disponible"
     ORDER BY COALESCE(occ.nb, 0) ASC, m.id ASC
     LIMIT 1'
);
$stmt->execute([
    ':ss' => $ss_id,
    ':ss2' => $ss_id,
    ':date' => $date_rdv,
]);
$medecinId = (int)($stmt->fetchColumn() ?: 0);

// Calculer rang
$stmt = $db->prepare(
    "SELECT COUNT(*) AS nb FROM consultations
     WHERE sous_service_id = ? AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('annule', 'absent')"
);
$stmt->execute([$ss_id]);
$rang = (int) $stmt->fetch()['nb'] + 1;

$duree        = (int) $ss['duree_estimee'];
$heurePassage = date('Y-m-d H:i:s', time() + ($rang - 1) * $duree);

$stmt = $db->prepare(
    "INSERT INTO consultations
       (patient_id, sous_service_id, medecin_id, statut, rang, mode_prise,
        heure_emission, heure_passage_estimee, duree_estimee, motif)
     VALUES (?, ?, ?, 'confirme', ?, 'LIGNE', NOW(), ?, ?, ?)"
);
$stmt->execute([$auth['sub'], $ss_id, $medecinId ?: null, $rang, $heurePassage, $duree, $motif ?: null]);
$newId = (int) $db->lastInsertId();

jsonSuccess([
    'id'                    => $newId,
    'rang'                  => $rang,
    'statut'                => 'confirme',
    'heure_passage_estimee' => $heurePassage,
    'message'               => "Rendez-vous confirmé. Rang #{$rang}",
], 'Rendez-vous créé', 201);
