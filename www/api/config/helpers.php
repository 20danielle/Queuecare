<?php
// --- Réponses JSON ---
function jsonSuccess($data = null, string $message = 'OK', int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => true, 'data' => $data, 'message' => $message]);
    exit;
}

function jsonError(string $message, string $errorCode = 'ERROR', int $httpCode = 400): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false, 'data' => null, 'message' => $message,
        'error'   => ['code' => $errorCode, 'message' => $message],
    ]);
    exit;
}

// --- JWT HS256 sans librairie ---
function jwtEncode(array $payload): string {
    $h = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $p = base64url(json_encode($payload));
    $s = base64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    return "$h.$p.$s";
}

function jwtDecode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h, $p, $s] = $parts;
    $expected = base64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    if (!hash_equals($expected, $s)) return null;
    $data = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    if (isset($data['exp']) && $data['exp'] < time()) return null;
    return $data;
}

function base64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// --- Auth middleware ---
function requireAuth(): array {
    $header  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token   = str_replace('Bearer ', '', $header);
    $payload = jwtDecode($token);
    if (!$payload) jsonError('Token invalide ou expiré', 'UNAUTHORIZED', 401);
    return $payload;
}

// --- Body JSON de la requête ---
function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function renderOtpEmailHtml(string $toName, string $otpCode): string {
    $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
    $safeOtp   = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');

    return '<!doctype html>'
        . '<html lang="fr"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Code OTP QueueCare</title></head>'
        . '<body style="margin:0;padding:0;background:#f3f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<div style="max-width:640px;margin:0 auto;padding:32px 18px;">'
        . '<div style="background:#ffffff;border-radius:20px;padding:32px;border:1px solid #e5eef5;box-shadow:0 12px 30px rgba(15,23,42,.08);">'
        . '<div style="display:inline-block;padding:8px 14px;border-radius:999px;background:#e8f4ff;color:#0f4d7a;font-weight:700;font-size:13px;letter-spacing:.4px;">QueueCare</div>'
        . '<h1 style="margin:18px 0 10px;font-size:24px;line-height:1.2;color:#123a54;">Votre code à usage unique</h1>'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.6;color:#334155;">Bonjour <strong>' . $safeName . '</strong>,</p>'
        . '<p style="margin:0 0 20px;font-size:16px;line-height:1.6;color:#334155;">Nous avons reçu une demande de réinitialisation de mot de passe pour votre compte QueueCare. Utilisez le code ci-dessous dans l’application.</p>'
        . '<div style="margin:26px 0 22px;padding:22px 20px;text-align:center;background:linear-gradient(135deg,#0f4765,#1b6b8a);border-radius:16px;">'
        . '<div style="font-size:13px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.8);margin-bottom:10px;">Code OTP</div>'
        . '<div style="font-size:40px;font-weight:800;letter-spacing:8px;color:#ffffff;">' . $safeOtp . '</div>'
        . '</div>'
        . '<p style="margin:0 0 8px;font-size:15px;line-height:1.6;color:#334155;">Ce code expire dans <strong>10 minutes</strong>.</p>'
        . '<p style="margin:0;font-size:14px;line-height:1.6;color:#64748b;">Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer ce message.</p>'
        . '</div>'
        . '<p style="margin:14px 0 0;text-align:center;font-size:12px;color:#94a3b8;">QueueCare - Sécurité et accessibilité patient</p>'
        . '</div>'
        . '</body></html>';
}

function renderOtpEmailText(string $toName, string $otpCode): string {
    return "Bonjour {$toName},\n\n"
        . "Votre code à usage unique QueueCare est : {$otpCode}\n"
        . "Il expire dans 10 minutes.\n\n"
        . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.\n";
}

function sendOtpEmail(string $toEmail, string $toName, string $otpCode): bool {
    $smtpHost = defined('SMTP_HOST') ? trim((string)SMTP_HOST) : '';
    $smtpUser = defined('SMTP_USERNAME') ? trim((string)SMTP_USERNAME) : '';
    $smtpPass = defined('SMTP_PASSWORD') ? preg_replace('/\s+/', '', (string)SMTP_PASSWORD) : '';

    $fromEmail = defined('SMTP_FROM_EMAIL') && trim((string)SMTP_FROM_EMAIL) !== ''
        ? trim((string)SMTP_FROM_EMAIL)
        : ($smtpUser !== '' ? $smtpUser : 'no-reply@queuecare.local');
    $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'QueueCare';
    $subject   = 'Votre code à usage unique QueueCare';
    $plainText = renderOtpEmailText($toName, $otpCode);
    $htmlBody  = renderOtpEmailHtml($toName, $otpCode);

    if ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
        $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $smtpEncryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
        return sendOtpEmailViaSmtp($smtpHost, $smtpPort, $smtpEncryption, $smtpUser, $smtpPass, $fromEmail, $fromName, $toEmail, $subject, $plainText, $htmlBody);
    }

    $boundary = '=_QueueCare_' . md5(uniqid((string)mt_rand(), true));
    $headers = [];
    $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail);
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
        . $plainText . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
        . $htmlBody . "\r\n"
        . "--{$boundary}--";

    return @mail($toEmail, $subject, $body, implode("\r\n", $headers));
}

function sendOtpEmailViaSmtp(
    string $host,
    int $port,
    string $encryption,
    string $username,
    string $password,
    string $fromEmail,
    string $fromName,
    string $toEmail,
    string $subject,
    string $plainText,
    string $htmlBody
): bool {
    $remoteHost = ($encryption === 'ssl') ? "ssl://{$host}" : $host;
    $fp = @stream_socket_client("{$remoteHost}:{$port}", $errno, $errstr, 8);
    if (!$fp) {
        error_log("QueueCare SMTP connection failed: {$errstr} ({$errno})");
        return false;
    }

    $read = function () use ($fp): string {
        $buffer = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $buffer .= $line;
            if (preg_match('/^\d{3}\s/', $line)) {
                break;
            }
        }
        return $buffer;
    };

    $write = function (string $command) use ($fp): void {
        fwrite($fp, $command . "\r\n");
    };

    $read();
    $write('EHLO queuecare.local');
    $read();
    if ($encryption === 'tls') {
        $write('STARTTLS');
        $response = $read();
        if (!str_starts_with($response, '220')) {
            fclose($fp);
            return false;
        }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return false;
        }
        $write('EHLO queuecare.local');
        $read();
    }

    $write('AUTH LOGIN');
    if (!str_starts_with($read(), '334')) {
        fclose($fp);
        return false;
    }
    $write(base64_encode($username));
    if (!str_starts_with($read(), '334')) {
        fclose($fp);
        return false;
    }
    $write(base64_encode($password));
    if (!str_starts_with($read(), '235')) {
        fclose($fp);
        return false;
    }

    $write('MAIL FROM: <' . $fromEmail . '>');
    if (!str_starts_with($read(), '250')) {
        fclose($fp);
        return false;
    }

    $write('RCPT TO: <' . $toEmail . '>');
    if (!preg_match('/^(250|251)/', $read())) {
        fclose($fp);
        return false;
    }

    $write('DATA');
    if (!str_starts_with($read(), '354')) {
        fclose($fp);
        return false;
    }

    $boundary = '=_QueueCare_' . md5(uniqid((string)mt_rand(), true));
    $headers = [
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
        'To: <' . $toEmail . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $mime = implode("\r\n", $headers) . "\r\n\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
        . $plainText . "\r\n"
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n\r\n"
        . $htmlBody . "\r\n"
        . "--{$boundary}--\r\n.";

    fwrite($fp, $mime . "\r\n");
    $final = $read();
    $write('QUIT');
    fclose($fp);

    $success = str_starts_with($final, '250');
    if (!$success) {
        error_log('QueueCare SMTP send failed: ' . trim($final));
    }
    return $success;
}
