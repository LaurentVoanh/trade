<?php
// init.php - À exécuter UNE FOIS
require_once 'config.php';
$db = getDB();

// Charger le schema
$db->exec(file_get_contents('schema.sql'));

// 400 actions crypto marrantes 🎭
$funnyCryptos = [
    ['DOGE2','Dogecoin Ultra','🐕 Le chien qui aboie plus fort','meme',0.15],
    ['MOON','ToTheMoon','🚀 Direction la lune, promis !','hype',2.5],
    ['BANANA','BananaChain','🍌 Potassium-backed cryptocurrency','food',0.8],
    // ... générer 400 entrées avec variations
];

// Générateur procédural pour atteindre 400
$adjs = ['Mega','Ultra','Super','Hyper','Nano','Quantum','Cyber','Neo','Astro','Turbo'];
$nouns = ['Coin','Token','Chain','Swap','Fi','Doge','Cat','Rocket','Pizza','Coffee'];
$emojis = ['🚀','🌙','💎','🔥','🦄','🎮','🎨','🤖','🧠','⚡'];
$categories = ['meme','defi','gaming','ai','nft','social','utility','privacy'];
$descriptions = ['La révolution arrive !','HODL or die 🤝','To the moon & back','DYOR but trust vibes','Community powered ✨'];

$stmt = $db->prepare('INSERT OR IGNORE INTO stocks (symbol,name,description,category,price,volatility) VALUES (?,?,?,?,?,?)');

for($i=0;$i<400;$i++){
    $adj = $adjs[array_rand($adjs)];
    $noun = $nouns[array_rand($nouns)];
    $symbol = strtoupper(substr($adj,0,3).substr($noun,0,2)).rand(1,9);
    $name = "$adj $noun".($i>200?" X":"");
    $emoji = $emojis[array_rand($emojis)];
    $desc = "$emoji $name - ".$descriptions[array_rand($descriptions)];
    $cat = $categories[array_rand($categories)];
    $price = round(rand(5,500)/100,4);
    $vol = round(rand(2,25)/100,3);
    $stmt->execute([$symbol,$name,$desc,$cat,$price,$vol]);
}

// 200 membres bots + URLs secrètes
$userStmt = $db->prepare('INSERT INTO users (secret_token,username,tokens) VALUES (?,?,?)');
$botStmt = $db->prepare('INSERT INTO bots (user_id,type,active) VALUES (?,?,1)');

for($i=1;$i<=200;$i++){
    $secret = generateSecret();
    $username = "Trader_".str_pad($i,3,'0',STR_PAD_LEFT);
    $userStmt->execute([$secret,$username,1000000]);
    $uid = $db->lastInsertId();
    
    // Créer les 4 bots pour chaque membre
    foreach(['performance','security','ai_learning','custom'] as $type){
        $botStmt->execute([$uid,$type]);
    }
}

// Créer un utilisateur "vous" avec URL affichée
$mySecret = generateSecret();
$db->prepare('INSERT INTO users (secret_token,username,tokens) VALUES (?,?,?)')
   ->execute([$mySecret,'YOU',1000000]);

echo "✅ Initialisation terminée !\n";
echo "🔑 Votre URL secrète : ".APP_URL."/?access=$mySecret\n";
echo "📊 400 actions créées\n";
echo "🤖 200 membres bots actifs\n";
?>