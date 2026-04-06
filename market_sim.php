<?php
// market_sim.php - À exécuter via cron toutes les 5-15 min
require 'config.php';
$db = getDB();

// Sélectionner aléatoirement 20-50 actions à faire bouger
$stocks = $db->query("SELECT id, price, volatility FROM stocks ORDER BY RANDOM() LIMIT ".rand(20,50))->fetchAll(PDO::FETCH_ASSOC);

foreach($stocks as $stock) {
    // Variation aléatoire basée sur la volatilité
    $change = (rand(-100,100)/100) * $stock['volatility'] * $stock['price'];
    $newPrice = max(0.0001, $stock['price'] + $change);
    
    // Mettre à jour le prix
    $db->prepare("UPDATE stocks SET price=? WHERE id=?")->execute([$newPrice, $stock['id']]);
    
    // Enregistrer dans l'historique
    $volume = rand(100, 10000);
    $db->prepare("INSERT INTO market_history (stock_id, price, volume) VALUES (?,?,?)")
       ->execute([$stock['id'], $newPrice, $volume]);
    
    // Ajuster trend/security scores progressivement
    if($change > 0) {
        $db->prepare("UPDATE stocks SET trend_score=MIN(1.0, trend_score+0.005) WHERE id=?")->execute([$stock['id']]);
    } else {
        $db->prepare("UPDATE stocks SET trend_score=MAX(0, trend_score-0.003), security_score=MIN(1.0, security_score+0.002) WHERE id=?")->execute([$stock['id']]);
    }
}

// Faire trader les 200 membres bots aléatoirement
$botUsers = $db->query("SELECT id FROM users WHERE id>1 LIMIT 50")->fetchAll(PDO::FETCH_COLUMN); // exclure user 1 = vous
require 'bot_engine.php';
$engine = new BotEngine();

foreach($botUsers as $uid) {
    if(rand(0,100) < 30) { // 30% de chance de trader à chaque tick
        $engine->runPerformanceBot($uid); // Les bots "marché" utilisent surtout performance
    }
}

echo "✅ Market simulation completed at ".date('Y-m-d H:i:s')."\n";
?>