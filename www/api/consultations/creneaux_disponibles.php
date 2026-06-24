<?php
$subServiceId = (int)($_GET['sub_service_id'] ?? 0);
$date = trim($_GET['date'] ?? '');

if (!$subServiceId) {
    jsonError('sub_service_id invalide', 'INVALID_PARAM');
}

if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonError('Date invalide', 'INVALID_PARAM');
}

$tz = new DateTimeZone('Africa/Douala');
$today = (new DateTimeImmutable('now', $tz))->format('Y-m-d');
if ($date <= $today) {
    jsonError('La date doit etre dans le futur.', 'INVALID_DATE');
}

$db = getDB();

$stmt = $db->prepare(
    "SELECT ss.id, ss.nom, ss.capacite_horaire,
            s.horaires_ouverture, s.horaires_fermeture, s.pause_debut, s.pause_fin, s.jours_fermeture
     FROM sous_services ss
     JOIN services s ON s.id = ss.service_id
     WHERE ss.id = :id AND ss.statut = 'actif'
     LIMIT 1"
);
$stmt->execute([':id' => $subServiceId]);
$subService = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$subService) {
    jsonError('Sous-service introuvable', 'NOT_FOUND', 404);
}

$jourNum = (int)(new DateTimeImmutable($date, $tz))->format('w');
$joursFermeture = !empty($subService['jours_fermeture'])
    ? array_map('intval', explode(',', $subService['jours_fermeture']))
    : [];

if (in_array($jourNum, $joursFermeture, true)) {
    jsonSuccess([], 'Le service est ferme ce jour-la.');
}

$ouverture = $subService['horaires_ouverture'] ?: '08:00:00';
$fermeture = $subService['horaires_fermeture'] ?: '18:00:00';
$pauseDebut = $subService['pause_debut'] ?: null;
$pauseFin = $subService['pause_fin'] ?: null;
$capacite = max(1, (int)($subService['capacite_horaire'] ?? 10));

$hourStart = (int)date('H', strtotime($ouverture));
$hourEnd = (int)date('H', strtotime($fermeture));
$pauseStart = $pauseDebut ? (int)date('H', strtotime($pauseDebut)) : null;
$pauseEnd = $pauseFin ? (int)date('H', strtotime($pauseFin)) : null;

$stmtOcc = $db->prepare(
    'SELECT HOUR(heure_passage_estimee) AS heure, COUNT(*) AS nb
     FROM consultations
     WHERE sous_service_id = :ss
       AND DATE(heure_passage_estimee) = :date
       AND statut NOT IN ("annule", "absent")
     GROUP BY HOUR(heure_passage_estimee)'
);
$stmtOcc->execute([
    ':ss' => $subServiceId,
    ':date' => $date,
]);

$occupes = [];
foreach ($stmtOcc->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $occupes[(int)$row['heure']] = (int)$row['nb'];
}

$creneaux = [];
for ($h = $hourStart; $h < $hourEnd; $h++) {
    if ($pauseStart !== null && $pauseEnd !== null && $h >= $pauseStart && $h < $pauseEnd) {
        continue;
    }

    $pris = $occupes[$h] ?? 0;
    if ($pris >= $capacite) {
        continue;
    }

    $label = sprintf('%02d:00', $h);
    $creneaux[] = [
        'heure' => $label,
        'date' => $date,
        'disponible' => true,
        'capacite' => $capacite,
        'pris' => $pris,
        'restant' => $capacite - $pris,
        'label' => $label,
    ];
}

jsonSuccess($creneaux, 'Créneaux disponibles');
