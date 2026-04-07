<?php
// api.php - API CryptoFun Trader V2
require 'config.php';
session_start();
header('Content-Type: application/json');

$db = getDB();

// Initialisation automatique si nécessaire
if (!isInitialized()) {
    require_once 'init.php';
    initializeDatabase();
}

$action = $_GET['action'] ?? '';

// Récupérer le token d'accès depuis l'URL si présent
$access_token = $_GET['token'] ?? $_POST['token'] ?? null;

// Pour login, on gère la connexion par mot de passe
if($action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    if(empty($data['password'])) {
        echo json_encode(['error'=>'Mot de passe requis']); exit;
    }
    
    // Chercher un utilisateur avec ce mot de passe
    $stmt = $db->prepare("SELECT * FROM users WHERE password_hash IS NOT NULL");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $found = false;
    foreach($users as $user) {
        if(password_verify($data['password'], $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['secret_token'] = $user['secret_token'];
            $_SESSION['is_anonymous'] = false;
            echo json_encode(['success'=>true, 'user'=>['id'=>$user['id'], 'username'=>$user['username']], 'token'=>$user['secret_token']]);
            $found = true;
            break;
        }
    }
    
    if(!$found) {
        http_response_code(401);
        echo json_encode(['error'=>'Mot de passe incorrect']);
    }
    exit;
}

// Récupérer ou créer l'utilisateur pour toutes les actions sauf logout
$user = null;
if ($action !== 'logout') {
    // Si token d'accès fourni dans l'URL
    if ($access_token) {
        $stmt = $db->prepare("SELECT * FROM users WHERE secret_token = ?");
        $stmt->execute([$access_token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['secret_token'] = $user['secret_token'];
        }
    }
    
    // Sinon utiliser la session
    if (!$user && isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Si toujours pas d'utilisateur, en créer un nouveau via session
    if (!$user) {
        $user = getOrCreateUser($db);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['secret_token'] = $user['secret_token'];
    }
}

// Vérifier auth pour toutes les actions sauf logout et list_stocks (public)
if($action !== 'logout' && $action !== 'list_stocks' && !$user) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthorized']); exit;
}

switch($action) {
    case 'list_stocks':
        // Action publique - tout le monde peut voir le marché
        $search = $_GET['search'] ?? '';
        $cat = $_GET['cat'] ?? '';
        $sort = $_GET['sort'] ?? 'trending';
        
        $sql = "SELECT * FROM stocks WHERE 1=1";
        $params = [];
        
        if($search) {
            $sql .= " AND (symbol LIKE ? OR name LIKE ? OR description LIKE ?)";
            $params = array_fill(0,3,"%$search%");
        }
        if($cat) {
            $sql .= " AND category = ?";
            $params[] = $cat;
        }
        
        $sorts = [
            'trending'=>'trend_score DESC, price ASC',
            'price_asc'=>'price ASC',
            'price_desc'=>'price DESC',
            'volume'=>"(SELECT SUM(volume) FROM market_history WHERE stock_id=stocks.id AND timestamp>datetime('now','-24 hours')) DESC"
        ];
        $sql .= " ORDER BY ".($sorts[$sort] ?? "RANDOM()");
        $sql .= " LIMIT 100";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    case 'buy':
        if (!$user) { echo json_encode(['error'=>'Unauthorized']); break; }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $stock = $db->prepare("SELECT * FROM stocks WHERE id=?");
        $stock->execute([$data['stock_id']]);
        $stock = $stock->fetch(PDO::FETCH_ASSOC);
        
        if(!$stock) { echo json_encode(['error'=>'Stock not found']); break; }
        
        $cost = $stock['price'] * $data['quantity'];
        $tokens = $user['tokens'];
        
        if($tokens < $cost) {
            echo json_encode(['error'=>'Solde insuffisant']); break;
        }
        
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE users SET tokens=tokens-? WHERE id=?")->execute([$cost, $user['id']]);
            $db->prepare("
                INSERT INTO holdings (user_id,stock_id,quantity,avg_price) VALUES (?,?,?,?)
                ON CONFLICT(user_id,stock_id) DO UPDATE SET quantity=quantity+?, avg_price=(holdings.avg_price*holdings.quantity+excluded.avg_price*excluded.quantity)/(holdings.quantity+excluded.quantity)
            ")->execute([$user['id'], $data['stock_id'], $data['quantity'], $stock['price'], $data['quantity'], $stock['price']]);
            $db->prepare("INSERT INTO transactions (user_id,stock_id,type,quantity,price) VALUES (?,?,?,?,?)")
               ->execute([$user['id'], $data['stock_id'], 'buy', $data['quantity'], $stock['price']]);
            $db->commit();
            
            // Mettre à jour l'utilisateur en session
            $_SESSION['user_id'] = $user['id'];
            
            echo json_encode(['success'=>true]);
        } catch(Exception $e) {
            $db->rollBack();
            echo json_encode(['error'=>$e->getMessage()]);
        }
        break;
        
    case 'run_bots':
        if (!$user) { echo json_encode(['error'=>'Unauthorized']); break; }
        require 'bot_engine.php';
        $engine = new BotEngine();
        $engine->runAllBotsForUser($user['id']);
        echo json_encode(['success'=>true]);
        break;
        
    case 'get_bot_config':
        if (!$user) { echo json_encode(['error'=>'Unauthorized']); break; }
        $type = $_GET['type'] ?? 'custom';
        $bot = $db->prepare("SELECT config FROM bots WHERE user_id=? AND type=?");
        $bot->execute([$user['id'], $type]);
        $config = $bot->fetchColumn();
        echo $config ?: '{}';
        break;
        
    case 'set_bot_config':
        if (!$user) { echo json_encode(['error'=>'Unauthorized']); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        $db->prepare("UPDATE bots SET config=? WHERE user_id=? AND type=?")
           ->execute([json_encode($data['config']), $user['id'], $data['type']]);
        echo json_encode(['success'=>true]);
        break;
        
    case 'set_password':
        if (!$user) { echo json_encode(['error'=>'Unauthorized']); break; }
        $data = json_decode(file_get_contents('php://input'), true);
        if(strlen($data['password']) < 6) {
            echo json_encode(['error'=>'Mot de passe trop court (min 6)']); break;
        }
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $user['id']]);
        echo json_encode(['success'=>true]);
        break;
        
    case 'get_user_info':
        if (!$user) { echo json_encode(['error'=>'Unauthorized']); break; }
        // Mettre à jour dernière activité
        $db->prepare("UPDATE users SET last_active=CURRENT_TIMESTAMP WHERE id=?")->execute([$user['id']]);
        // Recharger les données fraîches
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($freshUser);
        break;
        
    case 'logout':
        session_destroy();
        echo json_encode(['success'=>true]);
        break;
        
    default:
        echo json_encode(['error'=>'Unknown action']);
}
?>
