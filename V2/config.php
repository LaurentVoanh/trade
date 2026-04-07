<?php
// config.php - Configuration centrale CryptoFun Trader V2
define('DB_PATH', __DIR__.'/cryptofun.db');
define('APP_URL', 'https://zok.voanh.art/');
define('SECRET_PREFIX', 'cf_');

// Clés Mistral pour l'IA
$MISTRAL_KEYS = [
    '5qaRTjWUjGJpAgfdsH8Rake',
    'o3rG1zvdq1ygfd7Z4J3J3eHXRShytu',
    'vEzQMKN74Ez8gfds30ENDjFruXkF'
];
define('MISTRAL_ENDPOINT', 'https://api.mistral.ai/v1/chat/completions');
define('MISTRAL_MODEL', 'pixtral-12b-2409');

// Obtention de la connexion DB
function getDB() {
    static $db = null;
    if (!$db) {
        $db = new PDO('sqlite:'.DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode=WAL;');
    }
    return $db;
}

// Génération de token secret
function generateSecret() {
    return SECRET_PREFIX.bin2hex(random_bytes(16));
}

// Sélection aléatoire d'une clé API
function getMistralKey() {
    global $MISTRAL_KEYS;
    return $MISTRAL_KEYS[array_rand($MISTRAL_KEYS)];
}

// Vérifier si l'initialisation a été faite
function isInitialized() {
    return file_exists(__DIR__.'/initialized.marker');
}

// Récupérer ou créer un utilisateur par session/token
function getOrCreateUser($db, $token = null) {
    // Si token fourni (URL directe), chercher cet utilisateur
    if ($token) {
        $stmt = $db->prepare("SELECT * FROM users WHERE secret_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }
    }
    
    // Sinon, utiliser le token de session
    $sessionToken = session_id();
    $stmt = $db->prepare("SELECT * FROM users WHERE secret_token = ?");
    $stmt->execute(['anon_'.$sessionToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Créer nouvel utilisateur anonyme
        $username = "Trader_".bin2hex(random_bytes(4));
        $secretToken = 'anon_'.$sessionToken;
        $db->prepare("INSERT INTO users (secret_token, username, tokens) VALUES (?,?,1000000)")
           ->execute([$secretToken, $username]);
        
        $userId = $db->lastInsertId();
        
        // Créer les 4 bots pour ce nouvel utilisateur
        $botStmt = $db->prepare("INSERT INTO bots (user_id, type, active) VALUES (?, ?, 1)");
        foreach(['performance','security','ai_learning','custom'] as $type) {
            $botStmt->execute([$userId, $type]);
        }
        
        // Recharger l'utilisateur
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $user;
}
?>
