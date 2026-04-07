<?php
// init.php - Initialisation automatique CryptoFun Trader V2
require_once 'config.php';

function initializeDatabase() {
    $db = getDB();
    
    // Charger le schema
    $db->exec(file_get_contents('schema.sql'));
    
    // Vérifier si déjà initialisé
    $count = $db->query("SELECT COUNT(*) FROM stocks")->fetchColumn();
    if ($count > 0) {
        return ['success' => true, 'message' => 'Déjà initialisé'];
    }
    
    // 400 actions crypto marrantes - génération avec symboles uniques
    $usedSymbols = [];
    $stmt = $db->prepare('INSERT INTO stocks (symbol,name,description,category,price,volatility,trend_score,security_score) VALUES (?,?,?,?,?,?,?,?)');
    
    $adjs = ['Mega','Ultra','Super','Hyper','Nano','Quantum','Cyber','Neo','Astro','Turbo','Giga','Tera','Peta','Exa','Zetta','Yotta','Kilo','Milli','Micro','Femto'];
    $nouns = ['Coin','Token','Chain','Swap','Fi','Doge','Cat','Rocket','Pizza','Coffee','Moon','Star','Planet','Galaxy','Nebula','Comet','Meteor','Pulse','Wave','Spark'];
    $emojis = ['🚀','🌙','💎','🔥','🦄','🎮','🎨','🤖','🧠','⚡','💰','📈','📉','🎯','🏆','👑','💫','🌟','✨','🎪'];
    $categories = ['meme','defi','gaming','ai','nft','social','utility','privacy','metaverse','dao'];
    $descriptions = [
        'La révolution arrive !','HODL or die 🤝','To the moon & back','DYOR but trust vibes','Community powered ✨',
        'Next gen technology','Built different','WAGMI forever','Diamond hands only 💎','Ape in responsibly 🦍',
        'Institutional grade','Decentralized future','Trust the process','Bullish AF 📈','Bear trap activated 🐻',
        'Liquidity locked','Audited & verified','Community driven','Meme magic','Utility incoming'
    ];
    
    for($i = 0; $i < 400; $i++) {
        // Générer un symbole unique
        do {
            $adj = $adjs[array_rand($adjs)];
            $noun = $nouns[array_rand($nouns)];
            $symbol = strtoupper(substr($adj,0,3).substr($noun,0,2)).rand(10,99);
        } while(in_array($symbol, $usedSymbols));
        $usedSymbols[] = $symbol;
        
        $name = "$adj $noun".($i > 200 ? " X" : "");
        $emoji = $emojis[array_rand($emojis)];
        $desc = "$emoji $name - ".$descriptions[array_rand($descriptions)];
        $cat = $categories[array_rand($categories)];
        $price = round(rand(5,500)/100,4);
        $vol = round(rand(2,25)/100,3);
        $trend = round(rand(0,100)/100,2);
        $security = round(rand(30,90)/100,2);
        $stmt->execute([$symbol,$name,$desc,$cat,$price,$vol,$trend,$security]);
    }
    
    // 200 membres bots + URLs secrètes
    $userStmt = $db->prepare('INSERT INTO users (secret_token,username,tokens) VALUES (?,?,?)');
    $botStmt = $db->prepare('INSERT INTO bots (user_id,type,active) VALUES (?,?,1)');
    
    for($i = 1; $i <= 200; $i++) {
        $secret = generateSecret();
        $username = "Trader_".str_pad($i,3,'0',STR_PAD_LEFT);
        $userStmt->execute([$secret,$username,1000000]);
        $uid = $db->lastInsertId();
        
        // Créer les 4 bots pour chaque membre
        foreach(['performance','security','ai_learning','custom'] as $type) {
            $botStmt->execute([$uid,$type]);
        }
    }
    
    // Marquer comme initialisé
    file_put_contents(__DIR__.'/initialized.marker', 'done');
    
    return [
        'success' => true,
        'stocks' => 400,
        'botUsers' => 200,
        'message' => 'Initialisation terminée avec succès'
    ];
}

// Exécution auto si appelé directement
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($argv[0] ?? '')) {
    $result = initializeDatabase();
    echo "✅ ".$result['message']."\n";
    echo "📊 {$result['stocks']} actions créées\n";
    echo "🤖 {$result['botUsers']} utilisateurs bots créés\n";
}
?>
