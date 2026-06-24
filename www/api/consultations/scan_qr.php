<?php
$auth = requireAuth();
$body = getBody();
$rawQr = trim($body['qr_code'] ?? $body['token'] ?? '');
$motif = trim($body['motif'] ?? '');

if (!$rawQr) {
    jsonError('qr_code requis', 'MISSING_FIELDS');
}

function extractQrToken(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/[a-f0-9]{64}/i', $value, $matches)) {
        return $matches[0];
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return $value;
    }

    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (['token', 'qr_token', 'code'] as $key) {
            if (!empty($query[$key])) {
                return trim((string) $query[$key]);
            }
        }
    }

    return $value;
}

$token = extractQrToken($rawQr);

if (!$token) {
    jsonError('Token QR invalide', 'INVALID_QR', 400);
}

$db = getDB();

// 1. Trouver le QR code actif via son token
$stmt = $db->prepare(
    "SELECT qr.id AS qr_id, qr.sous_service_id,
            ss.nom AS ss_nom, ss.duree_estimee, ss.duree_rdv_defaut,
            s.id  AS s_id,   s.nom AS s_nom
     FROM qr_codes qr
     JOIN sous_services ss ON qr.sous_service_id = ss.id
     JOIN services s       ON ss.service_id = s.id
     WHERE qr.token = ?
       AND qr.statut = 'actif'
       AND qr.expire_at > NOW()
     LIMIT 1"
);
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    jsonError('QR code invalide ou expire', 'INVALID_QR', 404);
}

// 2. Verifier qu'il n'a pas deja un ticket actif aujourd'hui
$stmt = $db->prepare(
    "SELECT id FROM consultations
     WHERE patient_id = ? AND sous_service_id = ?
       AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('traite', 'annule', 'absent')
     LIMIT 1"
);
$stmt->execute([$auth['sub'], $qr['sous_service_id']]);
if ($stmt->fetch()) {
    jsonError('Vous avez deja un ticket actif pour ce service aujourd\'hui', 'DUPLICATE_TICKET', 409);
}

// 3. Choisir le médecin le moins occupé du sous-service pour aujourd'hui
$stmt = $db->prepare(
    'SELECT m.id,
            COALESCE(occ.nb, 0) AS nb_consultations
     FROM medecins m
     JOIN medecin_sous_service mss ON mss.medecin_id = m.id
     LEFT JOIN (
         SELECT medecin_id, COUNT(*) AS nb
         FROM consultations
         WHERE sous_service_id = :ss
           AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = CURDATE()
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
    ':ss' => $qr['sous_service_id'],
    ':ss2' => $qr['sous_service_id'],
]);
$medecinId = (int)($stmt->fetchColumn() ?: 0);

// 3. Calculer le rang (nombre de tickets actifs aujourd'hui)
$stmt = $db->prepare(
    "SELECT COUNT(*) AS nb FROM consultations
     WHERE sous_service_id = ?
       AND DATE(heure_emission) = CURDATE()
       AND statut NOT IN ('annule', 'absent')"
);
$stmt->execute([$qr['sous_service_id']]);
$rang = (int) $stmt->fetch()['nb'] + 1;

// 4. Calculer l'heure de passage estimee
// duree_estimee est en secondes
$dureeSecondes = (int) $qr['duree_estimee'] ?: (int) $qr['duree_rdv_defaut'];
$tempsAttenteSecondes = ($rang - 1) * $dureeSecondes;
$heurePassage = date('Y-m-d H:i:s', time() + $tempsAttenteSecondes);

// 5. Creer la consultation
$stmt = $db->prepare(
    "INSERT INTO consultations
       (patient_id, sous_service_id, medecin_id, qr_code_id, statut, rang,
        mode_prise, heure_emission, heure_passage_estimee, duree_estimee, motif)
     VALUES (?, ?, ?, ?, 'en_attente', ?, 'QR_CODE', NOW(), ?, ?, ?)"
);
$stmt->execute([
    $auth['sub'],
    $qr['sous_service_id'],
    $medecinId ?: null,
    $qr['qr_id'],
    $rang,
    $heurePassage,
    $dureeSecondes,
    $motif ?: null,
]);
$consultationId = (int) $db->lastInsertId();

// 6. Incrementer le scan_count du QR
$db->prepare("UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = ?")
   ->execute([$qr['qr_id']]);

// 7. Creer une notification de confirmation
$db->prepare(
    "INSERT INTO notifications (patient_id, consultation_id, type, contenu, canal, statut)
     VALUES (?, ?, 'CONFIRMATION', ?, 'FCM', 'en_attente')"
)->execute([
    $auth['sub'],
    $consultationId,
    "Ticket #{$rang} cree pour {$qr['ss_nom']}. Passage estime a " . date('H:i', strtotime($heurePassage)),
]);

jsonSuccess([
    'consultation_id' => $consultationId,
    'rang' => $rang,
    'heure_estimee' => $heurePassage,
    'heure_passage_estimee' => $heurePassage,
    'message' => "Ticket #{$rang} cree. Passage estime a " . date('H:i', strtotime($heurePassage)),
    'consultation' => [
        'id' => $consultationId,
        'statut' => 'en_attente',
        'rang' => $rang,
        'heure_passage_estimee' => $heurePassage,
        'heure_emission' => date('Y-m-d H:i:s'),
        'mode_prise' => 'QR_CODE',
        'motif' => $motif ?: null,
        'service' => ['id' => (int) $qr['s_id'], 'nom' => $qr['s_nom']],
        'sousService' => ['id' => (int) $qr['sous_service_id'], 'nom' => $qr['ss_nom']],
        'sous_service' => ['id' => (int) $qr['sous_service_id'], 'nom' => $qr['ss_nom']],
    ],
]);
