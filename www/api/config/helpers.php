<?php
// ─── Réponses JSON ────────────────────────────────────────────
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

// ─── JWT HS256 sans librairie ─────────────────────────────────
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

// ─── Auth middleware ──────────────────────────────────────────
function requireAuth(): array {
    $header  = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token   = str_replace('Bearer ', '', $header);
    $payload = jwtDecode($token);
    if (!$payload) jsonError('Token invalide ou expiré', 'UNAUTHORIZED', 401);
    return $payload;
}

// ─── Body JSON de la requête ──────────────────────────────────
function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function sendOtpEmail(string $toEmail, string $toName, string $otpCode): bool {
    $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@queuecare.local';
    $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'QueueCare';
    $subject   = 'Code OTP QueueCare';
    $message   = "Bonjour {$toName},\n\n"
        . "Votre code OTP QueueCare est : {$otpCode}\n"
        . "Il expire dans 10 minutes.\n\n"
        . "Si vous n'etes pas a l'origine de cette demande, ignorez ce message.\n";

    $smtpHost = defined('SMTP_HOST') ? SMTP_HOST : '';
    $smtpUser = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
    $smtpPass = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';

    if ($smtpHost !== '' && $smtpUser !== '' && $smtpPass !== '') {
        $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $smtpEncryption = defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'tls';
        return sendOtpEmailViaSmtp($smtpHost, $smtpPort, $smtpEncryption, $smtpUser, $smtpPass, $fromEmail, $fromName, $toEmail, $subject, $message);
    }

    $headers = [];
    $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail);
    $headers[] = 'Reply-To: ' . $fromEmail;
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    return @mail($toEmail, $subject, $message, implode("\r\n", $headers));
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
    string $message
): bool {
    $remoteHost = ($encryption === 'ssl') ? "ssl://{$host}" : $host;
    $fp = @stream_socket_client("{$remoteHost}:{$port}", $errno, $errstr, 20);
    if (!$fp) {
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

    $expect = function (string $prefix) use ($read): bool {
        $response = $read();
        return str_starts_with($response, $prefix);
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

    $body = implode("\r\n", [
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail),
        'To: <' . $toEmail . '>',
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        '',
        $message,
        '.',
    ]);
    fwrite($fp, $body . "\r\n");
    $final = $read();
    $write('QUIT');
    fclose($fp);

    return str_starts_with($final, '250');
}
