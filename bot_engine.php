<?php
// bot_engine.php
require 'config.php';

class BotEngine {
    private $db, $apiKey;
    
    public function __construct() {
        $this->db = getDB();
        global $MISTRAL_KEYS;
        $this->apiKey = $MISTRAL_KEYS[array_rand($MISTRAL_KEYS)];
    }
    
    private function callMistral($prompt, $userId) {
        $data = [
            "model" => MISTRAL_MODEL,
            "messages" => [
                ["role" => "system", "content" => "Tu es un expert trading crypto. Réponds en JSON uniquement."],
                ["role" => "user", "content" => $prompt]
            ],
            "temperature" => 0.7,
            "response_format" => ["type" => "json_object"]
        ];
        
        $ch = curl_init(MISTRAL_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->apiKey}",
                "Content-Type: application/json"
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return json_decode($result['choices'][0]['message']['content'] ?? '{}', true);
    }
    
    public function runPerformanceBot($userId) {
        // Cherche les actions avec fort momentum
        $stocks = $this->db->query("
            SELECT s.*, 
                   (SELECT AVG(price) FROM market_history WHERE stock_id=s.id AND timestamp > datetime('now','-1 hour')) as avg_1h
            FROM stocks s 
            WHERE s.trend_score > 0.7 
            ORDER BY s.volatility * s.trend_score DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($stocks as $stock) {
            if(rand(0,100) < 70) { // 70% de chance d'acheter
                $this->executeTrade($userId, $stock['id'], 'buy', 2500, $stock['price'], 'performance');
            }
        }
    }
    
    public function runSecurityBot($userId) {
        // Privilégie stabilité et sécurité
        $stocks = $this->db->query("
            SELECT * FROM stocks 
            WHERE security_score > 0.8 AND volatility < 0.1 
            ORDER BY security_score DESC, price ASC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($stocks as $stock) {
            $this->executeTrade($userId, $stock['id'], 'buy', 2500, $stock['price'], 'security');
        }
    }
    
    public function runAIBot($userId) {
        // Bot qui apprend via Mistral
        $portfolio = $this->db->prepare("
            SELECT s.symbol, s.name, h.quantity, h.avg_price, s.price as current_price,
                   (s.price - h.avg_price)/h.avg_price as pnl_pct
            FROM holdings h JOIN stocks s ON h.stock_id=s.id 
            WHERE h.user_id=? AND h.quantity>0
        ");
        $portfolio->execute([$userId]);
        $holdings = $portfolio->fetchAll(PDO::FETCH_ASSOC);
        
        $market = $this->db->query("
            SELECT symbol, price, volatility, trend_score, security_score 
            FROM stocks ORDER BY RANDOM() LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $prompt = "Portfolio: ".json_encode(array_slice($holdings,0,10))."
                   Marché: ".json_encode($market)."
                   Donne-moi UNE action à acheter (symbol) et une quantité (1000-5000) en JSON: {\"action\":\"SYMBOL\",\"quantity\":2500,\"reason\":\"...\"}";
        
        $decision = $this->callMistral($prompt, $userId);
        
        if(!empty($decision['action']) && !empty($decision['quantity'])) {
            $stock = $this->db->prepare("SELECT * FROM stocks WHERE symbol=?");
            $stock->execute([$decision['action']]);
            $stock = $stock->fetch(PDO::FETCH_ASSOC);
            
            if($stock) {
                $this->executeTrade($userId, $stock['id'], 'buy', 
                                  min($decision['quantity'],2500), $stock['price'], 'ai_learning');
                // Auto-amélioration: mettre à jour les scores basés sur le résultat
                $this->updateAIScores($userId, $stock['id'], $decision);
            }
        }
    }
    
    public function runCustomBot($userId, $config) {
        // Bot personnalisé par l'utilisateur
        $params = json_decode($config, true);
        $filters = [];
        $values = [];
        
        if(!empty($params['min_price'])) {
            $filters[] = "price >= ?"; $values[] = $params['min_price'];
        }
        if(!empty($params['max_volatility'])) {
            $filters[] = "volatility <= ?"; $values[] = $params['max_volatility'];
        }
        if(!empty($params['category'])) {
            $filters[] = "category = ?"; $values[] = $params['category'];
        }
        
        $where = $filters ? "WHERE ".implode(' AND ',$filters) : "";
        $stocks = $this->db->prepare("SELECT * FROM stocks $where ORDER BY RANDOM() LIMIT 5");
        $stocks->execute($values);
        
        foreach($stocks->fetchAll(PDO::FETCH_ASSOC) as $stock) {
            $qty = $params['quantity_per_trade'] ?? 2500;
            $this->executeTrade($userId, $stock['id'], 'buy', $qty, $stock['price'], 'custom');
        }
    }
    
    private function executeTrade($userId, $stockId, $type, $quantity, $price, $botType) {
        $db = getDB();
        $db->beginTransaction();
        
        try {
            // Vérifier solde
            $user = $db->prepare("SELECT tokens FROM users WHERE id=?");
            $user->execute([$userId]);
            $tokens = $user->fetchColumn();
            
            $cost = $quantity * $price;
            if($type === 'buy' && $tokens < $cost) {
                $db->rollBack();
                return false;
            }
            
            // Mettre à jour solde
            $newTokens = $type === 'buy' ? $tokens - $cost : $tokens + $cost;
            $db->prepare("UPDATE users SET tokens=? WHERE id=?")
               ->execute([$newTokens, $userId]);
            
            // Mettre à jour holdings
            $db->prepare("
                INSERT INTO holdings (user_id, stock_id, quantity, avg_price) 
                VALUES (?,?,?,?) 
                ON CONFLICT(user_id,stock_id) DO UPDATE SET 
                    quantity = quantity + ?,
                    avg_price = CASE 
                        WHEN excluded.quantity > 0 THEN 
                            (holdings.avg_price * holdings.quantity + excluded.avg_price * excluded.quantity) / (holdings.quantity + excluded.quantity)
                        ELSE holdings.avg_price 
                    END
            ")->execute([$userId, $stockId, $quantity, $price, $quantity, $price]);
            
            // Enregistrer transaction
            $db->prepare("INSERT INTO transactions (user_id,stock_id,type,quantity,price,is_bot,bot_type) VALUES (?,?,?,?,?,?,?)")
               ->execute([$userId, $stockId, $type, $quantity, $price, 1, $botType]);
            
            // Historique marché
            $db->prepare("INSERT INTO market_history (stock_id,price,volume) VALUES (?,?,?)")
               ->execute([$stockId, $price, $quantity]);
            
            $db->commit();
            return true;
        } catch(Exception $e) {
            $db->rollBack();
            error_log("Bot trade error: ".$e->getMessage());
            return false;
        }
    }
    
    private function updateAIScores($userId, $stockId, $decision) {
        // Logique simplifiée d'apprentissage
        $db = getDB();
        $db->prepare("UPDATE stocks SET trend_score = MIN(1.0, trend_score + 0.01) WHERE id=?")
           ->execute([$stockId]);
    }
    
    public function runAllBotsForUser($userId) {
        // Exécuter les 4 bots (2500 tokens chacun = 10000/jour max)
        $bots = $this->db->prepare("SELECT * FROM bots WHERE user_id=? AND active=1");
        $bots->execute([$userId]);
        
        foreach($bots->fetchAll(PDO::FETCH_ASSOC) as $bot) {
            // Limiter à 1 exécution/jour par bot
            if($bot['last_run'] && strtotime($bot['last_run']) > time() - 86400) continue;
            
            switch($bot['type']) {
                case 'performance': $this->runPerformanceBot($userId); break;
                case 'security': $this->runSecurityBot($userId); break;
                case 'ai_learning': $this->runAIBot($userId); break;
                case 'custom': 
                    if($bot['config']) $this->runCustomBot($userId, $bot['config']); 
                    break;
            }
            
            $this->db->prepare("UPDATE bots SET last_run=CURRENT_TIMESTAMP WHERE id=?")
                     ->execute([$bot['id']]);
        }
    }
}
?>