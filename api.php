<?php
// api.php
require 'config.php';
session_start();
header('Content-Type: application/json');
$db = getDB();

$action = $_GET['action'] ?? '';

// Vérif auth pour toutes les actions sauf logout
if($action !== 'logout' && !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthorized']); exit;
}

switch($action) {
    case 'list_stocks':
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
            'volume'=>'(SELECT SUM(volume) FROM market_history WHERE stock_id=stocks.id AND timestamp>datetime(\'now\'\'-24 hours\')) DESC'
        ];
        $sql .= " ORDER BY ".$sorts[$sort] ?? "RANDOM()";
        $sql .= " LIMIT 100";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
        
    case 'buy':
        $data = json_decode(file_get_contents('php://input'), true);
        $stock = $db->prepare("SELECT * FROM stocks WHERE id=?");
        $stock->execute([$data['stock_id']]);
        $stock = $stock->fetch(PDO::FETCH_ASSOC);
        
        if(!$stock) { echo json_encode(['error'=>'Stock not found']); break; }
        
        $cost = $stock['price'] * $data['quantity'];
        $user = $db->prepare("SELECT tokens FROM users WHERE id=?");
        $user->execute([$_SESSION['user_id']]);
        $tokens = $user->fetchColumn();
        
        if($tokens < $cost) {
            echo json_encode(['error'=>'Solde insuffisant']); break;
        }
        
        // Exécuter l'achat (code simplifié - voir bot_engine.php pour version complète)
        $db->beginTransaction();
        try {
            $db->prepare("UPDATE users SET tokens=tokens-? WHERE id=?")->execute([$cost, $_SESSION['user_id']]);
            $db->prepare("
                INSERT INTO holdings (user_id,stock_id,quantity,avg_price) VALUES (?,?,?,?)
                ON CONFLICT(user_id,stock_id) DO UPDATE SET quantity=quantity+?, avg_price=(avg_price*holdings.quantity+excluded.avg_price*excluded.quantity)/(holdings.quantity+excluded.quantity)
            ")->execute([$_SESSION['user_id'], $data['stock_id'], $data['quantity'], $stock['price'], $data['quantity'], $stock['price']]);
            $db->prepare("INSERT INTO transactions (user_id,stock_id,type,quantity,price) VALUES (?,?,?,?,?)")
               ->execute([$_SESSION['user_id'], $data['stock_id'], 'buy', $data['quantity'], $stock['price']]);
            $db->commit();
            echo json_encode(['success'=>true]);
        } catch(Exception $e) {
            $db->rollBack();
            echo json_encode(['error'=>$e->getMessage()]);
        }
        break;
        
    case 'run_bots':
        require 'bot_engine.php';
        $engine = new BotEngine();
        $engine->runAllBotsForUser($_SESSION['user_id']);
        echo json_encode(['success'=>true]);
        break;
        
    case 'get_bot_config':
        $type = $_GET['type'] ?? 'custom';
        $bot = $db->prepare("SELECT config FROM bots WHERE user_id=? AND type=?");
        $bot->execute([$_SESSION['user_id'], $type]);
        $config = $bot->fetchColumn();
        echo $config ?: '{}';
        break;
        
    case 'set_bot_config':
        $data = json_decode(file_get_contents('php://input'), true);
        $db->prepare("UPDATE bots SET config=? WHERE user_id=? AND type=?")
           ->execute([json_encode($data['config']), $_SESSION['user_id'], $data['type']]);
        echo json_encode(['success'=>true]);
        break;
        
    case 'set_password':
        $data = json_decode(file_get_contents('php://input'), true);
        if(strlen($data['password']) < 6) {
            echo json_encode(['error'=>'Mot de passe trop court']); break;
        }
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $_SESSION['user_id']]);
        echo json_encode(['success'=>true]);
        break;
        
    case 'logout':
        session_destroy();
        echo json_encode(['success'=>true]);
        break;
        
    default:
        echo json_encode(['error'=>'Unknown action']);
}
?>