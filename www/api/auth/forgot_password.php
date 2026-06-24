<?php
$body    = getBody();
$contact = trim($body['contact'] ?? '');

if (!$contact) jsonError('Email ou téléphone requis', 'MISSING_FIELDS');

$db   = getDB();
$stmt = $db->prepare(
    "SELECT id, nom, prenom, email, telephone FROM patients
     WHERE (email = ? OR telephone = ?) AND statut = 'actif'
     LIMIT 1"
);
$stmt->execute([$contact, $contact]);
$patient = $stmt->fetch();

// Sécurité : on ne révèle pas si l'utilisateur existe
if (!$patient) {
    jsonSuccess(null, 'Si ce contact existe, un code a été envoyé.');
}

$otp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', time() + 600); // 10 min

// Stocker dans une table temporaire (créée si elle n'existe pas)
$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    otp_code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_patient (patient_id),
    KEY idx_password_resets_expires_at (expires_at)
) ENGINE=InnoDB");

$db->prepare("INSERT INTO password_resets (patient_id, otp_code, expires_at)
              VALUES (?, ?, ?)
              ON DUPLICATE KEY UPDATE otp_code = VALUES(otp_code), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP")
   ->execute([$patient['id'], $otp, $expires]);

$emailSent = false;
if (!empty($patient['email'])) {
    $displayName = trim(($patient['prenom'] ?? '') . ' ' . ($patient['nom'] ?? ''));
    $emailSent = sendOtpEmail($patient['email'], $displayName !== '' ? $displayName : 'Patient', $otp);
}

$payload = [
    'delivery' => $emailSent ? 'email' : 'dev',
];

if (DEBUG_OTP || !$emailSent) {
    $payload['otp_code'] = $otp;
}

$message = $emailSent
    ? 'Code OTP envoye avec succes'
    : 'Code OTP genere. Configurez SMTP pour l\'envoi email, ou utilisez la valeur renvoyee en dev.';

jsonSuccess($payload, $message);
