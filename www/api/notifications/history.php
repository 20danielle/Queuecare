<?php
$auth      = requireAuth();
$patientId = (int) ($_GET['patient_id'] ?? 0);

if ($patientId && $patientId !== (int) $auth['sub']) {
    jsonError('Accès non autorisé', 'FORBIDDEN', 403);
}

$targetId = $patientId ?: (int) $auth['sub'];
$db       = getDB();

$stmt = $db->prepare(
    "SELECT n.id, n.type, n.contenu, n.canal, n.statut, n.sent_at, n.created_at, n.consultation_id,
            c.statut AS consultation_statut,
            c.rang AS consultation_rang,
            s.id AS service_id,
            s.nom AS service_nom,
            ss.id AS sous_service_id,
            ss.nom AS sous_service_nom,
            (SELECT nom_hopital FROM configuration_hopital ORDER BY id ASC LIMIT 1) AS hopital_nom
     FROM notifications n
     LEFT JOIN consultations c ON c.id = n.consultation_id
     LEFT JOIN sous_services ss ON ss.id = c.sous_service_id
     LEFT JOIN services s ON s.id = ss.service_id
     WHERE n.patient_id = ?
     ORDER BY n.created_at DESC
     LIMIT 50"
);
$stmt->execute([$targetId]);

$notifications = array_map(fn($r) => [
    'id'              => (int) $r['id'],
    'type'            => $r['type'],
    'contenu'         => $r['contenu'],
    'message'         => $r['contenu'],
    'canal'           => $r['canal'],
    'statut'          => $r['statut'],
    'status'          => $r['statut'],
    'sent_at'         => $r['sent_at'],
    'created_at'      => $r['created_at'],
    'consultation_id' => $r['consultation_id'] ? (int) $r['consultation_id'] : null,
    'consultation'    => $r['consultation_id'] ? [
        'id' => (int) $r['consultation_id'],
        'statut' => $r['consultation_statut'] ?? null,
        'rang' => isset($r['consultation_rang']) ? (int) $r['consultation_rang'] : null,
    ] : null,
    'hopital'         => $r['hopital_nom'] ?? null,
    'service'         => $r['service_id'] ? [
        'id' => (int) $r['service_id'],
        'nom' => $r['service_nom'],
    ] : null,
    'sousService'     => $r['sous_service_id'] ? [
        'id' => (int) $r['sous_service_id'],
        'nom' => $r['sous_service_nom'],
    ] : null,
    'sous_service'    => $r['sous_service_id'] ? [
        'id' => (int) $r['sous_service_id'],
        'nom' => $r['sous_service_nom'],
    ] : null,
], $stmt->fetchAll());

// Marquer comme lues
$db->prepare(
    "UPDATE notifications SET statut = 'lu' WHERE patient_id = ? AND statut = 'envoye'"
)->execute([$targetId]);

jsonSuccess($notifications);
