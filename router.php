<?php
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = __DIR__ . '/www';

// ── 1. Fichiers statiques (CSS, JS, images, fonts) ────────────────────────
$static = $base . $uri;
if (is_file($static)) {
    return false; // PHP built-in server sert le fichier directement
}

// ── 2. Racine → page d'accueil jury ───────────────────────────────────────
if ($uri === '/' || $uri === '') {
    require $base . '/index.php';
    exit;
}

// ── 3. API REST ────────────────────────────────────────────────────────────
if (str_starts_with($uri, '/api')) {
    require $base . '/api/index.php';
    exit;
}

// ── 4. Téléchargement APK ─────────────────────────────────────────────────
if (str_starts_with($uri, '/download/')) {
    $file = $base . $uri;
    if (is_file($file)) {
        return false;
    }
    http_response_code(404);
    echo 'APK introuvable';
    exit;
}

// ── 5. App Web — toutes les routes /web/* ────────────────────────────────
// L'app web gère son propre routing via index.php
// On lui transmet la requête telle quelle
if (str_starts_with($uri, '/web')) {
    // Extraire le chemin relatif à /web
    $webPath = substr($uri, 4); // supprime "/web"
    if ($webPath === '' || $webPath === '/') {
        require $base . '/web/index.php';
        exit;
    }

    // Fichier statique dans /web (CSS, JS, images)
    $webFile = $base . '/web' . $webPath;
    if (is_file($webFile)) {
        return false;
    }

    // Fichier PHP direct dans /web (ex: /web/accueil.php → /web/accueil.php)
    if (is_file($base . '/web' . $webPath)) {
        require $base . '/web' . $webPath;
        exit;
    }

    // Sinon → router vers index.php de l'app web qui gère ?action=
    require $base . '/web/index.php';
    exit;
}

// ── 6. 404 ────────────────────────────────────────────────────────────────
http_response_code(404);
echo json_encode(['error' => 'Route introuvable : ' . $uri]);
