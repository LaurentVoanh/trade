<?php
// config.php
define('DB_PATH', __DIR__.'/cryptofun.db');
define('APP_URL', 'https://zok.voanh.art/');
define('SECRET_PREFIX', 'cf_');

// Clés Mistral (à déplacer en ENV en prod)
$MISTRAL_KEYS = [
    '5qaRTjWUjGJpAgfdsH8Rake',
    'o3rG1zvdq1ygfd7Z4J3J3eHXRShytu',
    'vEzQMKN74Ez8gfds30ENDjFruXkF'
];
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'pixtral-12b-2409');

function getDB() {
    static $db = null;
    if (!$db) {
        $db = new PDO('sqlite:'.DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode=WAL;');
    }
    return $db;
}

function generateSecret() {
    return SECRET_PREFIX.bin2hex(random_bytes(16));
}

function getMistralKey() {
    global $MISTRAL_KEYS;
    return $MISTRAL_KEYS[array_rand($MISTRAL_KEYS)];
}
?>