<?php
// index.php - CryptoFun Trader
require_once 'config.php';
session_start();

// Initialisation automatique si nécessaire
if(!file_exists(__DIR__.'/initialized.marker')) {
    require_once 'init.php';
    file_put_contents(__DIR__.'/initialized.marker', 'done');
}

$db = getDB();

// Gestion de la session utilisateur
$userId = $_SESSION['user_id'] ?? null;
$me = null;

// Si pas de session, créer un nouvel utilisateur anonyme temporaire
if(!$userId) {
    // Créer un utilisateur temporaire avec token unique basé sur session
    $sessionToken = session_id();
    $stmt = $db->prepare("SELECT * FROM users WHERE secret_token = ?");
    $stmt->execute(['anon_'.$sessionToken]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$user) {
        // Nouvel utilisateur anonyme
        $username = "Trader_".bin2hex(random_bytes(4));
        $secretToken = 'anon_'.$sessionToken;
        $db->prepare("INSERT INTO users (secret_token, username, tokens) VALUES (?,?,1000000)")
           ->execute([$secretToken, $username]);
        $userId = $db->lastInsertId();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['is_anonymous'] = true;
        
        // Créer les 4 bots pour ce nouvel utilisateur
        $botStmt = $db->prepare("INSERT INTO bots (user_id, type, active) VALUES (?, ?, 1)");
        foreach(['performance','security','ai_learning','custom'] as $type) {
            $botStmt->execute([$userId, $type]);
        }
    } else {
        $userId = $user['id'];
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_anonymous'] = empty($user['password_hash']);
    }
}

// Récupérer les infos utilisateur
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

// Mettre à jour dernière activité
$db->prepare("UPDATE users SET last_active=CURRENT_TIMESTAMP WHERE id=?")->execute([$userId]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>🚀 CryptoFun Trader</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <header class="header">
        <div class="logo">🚀 CryptoFun</div>
        <div style="display:flex;gap:1rem;align-items:center">
            <span class="badge">👤 <?=htmlspecialchars($me['username'])?></span>
            <span class="price">💰 <?=number_format($me['tokens'],0,',',' ')?> tokens</span>
            <?php if(empty($me['password_hash'])): ?>
                <button class="btn" onclick="setPassword()">🔒 Sécuriser mon compte</button>
            <?php else: ?>
                <span class="badge" style="background:#00f5a0;color:#0a0e17">✅ Compte sécurisé</span>
            <?php endif; ?>
            <button class="btn" style="background:#2a3447" onclick="logout()">Déconnexion</button>
        </div>
    </header>

    <!-- Panneau des bots -->
    <section class="card" style="margin-bottom:1.5rem">
        <h3>🤖 Mes Bots Automatiques</h3>
        <div class="bot-panel">
            <div class="card bot-card">
                <strong>⚡ Performance</strong>
                <p style="color:var(--muted);font-size:0.9rem">Cherche le meilleur rendement</p>
                <small>Dernier run: <span id="bot1-run">--</span></small>
            </div>
            <div class="card bot-card security">
                <strong>🛡️ Sécurité</strong>
                <p style="color:var(--muted);font-size:0.9rem">Privilégie la stabilité</p>
                <small>2500 tokens/jour</small>
            </div>
            <div class="card bot-card ai">
                <strong>🧠 AI Learning</strong>
                <p style="color:var(--muted);font-size:0.9rem">S'améliore avec Mistral AI</p>
                <small>Modèle: pixtral-12b</small>
            </div>
            <div class="card bot-card custom">
                <strong>⚙️ Custom</strong>
                <button class="btn" style="padding:0.4rem 0.8rem;font-size:0.85rem" onclick="openCustomBot()">Configurer</button>
            </div>
        </div>
    </section>

    <!-- Recherche & Filtres -->
    <div style="display:flex;gap:1rem;margin-bottom:1rem;flex-wrap:wrap">
        <input type="search" id="search" placeholder="🔍 Chercher une action..." style="flex:1;min-width:200px;padding:0.75rem 1rem;border-radius:12px;border:1px solid #2a3447;background:var(--card);color:var(--text)">
        <select id="filter-cat" style="padding:0.75rem;border-radius:12px;border:1px solid #2a3447;background:var(--card);color:var(--text)">
            <option value="">Toutes catégories</option>
            <option value="meme">😂 Meme</option>
            <option value="gaming">🎮 Gaming</option>
            <option value="ai">🤖 AI</option>
            <option value="defi">💰 DeFi</option>
        </select>
        <select id="sort-by" style="padding:0.75rem;border-radius:12px;border:1px solid #2a3447;background:var(--card);color:var(--text)">
            <option value="trending">🔥 Tendance</option>
            <option value="price_asc">💵 Prix ↑</option>
            <option value="price_desc">💸 Prix ↓</option>
            <option value="volume">📊 Volume</option>
        </select>
    </div>

    <!-- Grille des actions -->
    <div class="grid" id="stocks-grid">
        <!-- Chargé via AJAX -->
        <div class="card loading" style="grid-column:1/-1;text-align:center;padding:3rem">Chargement des actions...</div>
    </div>
</div>

<!-- Modal achat -->
<div id="buy-modal" class="modal" style="display:none">
    <div class="modal-content">
        <h3 id="modal-stock-name">Achat</h3>
        <p>Prix actuel: <span id="modal-price" class="price"></span></p>
        <p>Quantité: <strong>1000 tokens</strong> (par clic)</p>
        <p>Coût total: <span id="modal-total" class="price"></span></p>
        <div style="display:flex;gap:1rem;margin-top:1.5rem">
            <button class="btn" onclick="confirmBuy()">✅ Acheter 1000</button>
            <button class="btn" style="background:#2a3447" onclick="closeModal()">Annuler</button>
        </div>
    </div>
</div>

<!-- Modal config bot custom -->
<div id="custom-modal" class="modal" style="display:none">
    <div class="modal-content">
        <h3>⚙️ Configurer le Bot Custom</h3>
        <form id="custom-form" style="display:grid;gap:1rem;margin-top:1rem">
            <label>Prix minimum: <input type="number" step="0.01" name="min_price" style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #2a3447;background:var(--card);color:var(--text)"></label>
            <label>Volatilité max: <input type="number" step="0.01" min="0" max="1" name="max_volatility" style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #2a3447;background:var(--card);color:var(--text)"></label>
            <label>Catégorie préférée: 
                <select name="category" style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #2a3447;background:var(--card);color:var(--text)">
                    <option value="">Toutes</option>
                    <option value="meme">😂 Meme</option>
                    <option value="gaming">🎮 Gaming</option>
                    <option value="ai">🤖 AI</option>
                </select>
            </label>
            <label>Quantité par trade: <input type="number" name="quantity_per_trade" value="2500" min="100" max="5000" style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #2a3447;background:var(--card);color:var(--text)"></label>
            <button type="submit" class="btn">💾 Enregistrer</button>
        </form>
        <button class="btn" style="background:#2a3447;margin-top:1rem;width:100%" onclick="closeCustomModal()">Fermer</button>
    </div>
</div>

<!-- Footer avec info sauvegarde -->
<footer style="margin-top:3rem;padding:2rem 0;border-top:1px solid #2a3447;text-align:center">
    <div class="card" style="max-width:600px;margin:0 auto">
        <h4 style="margin-bottom:1rem">🔐 Sauvegarder votre progression</h4>
        <p style="color:var(--muted);margin-bottom:1rem;font-size:0.9rem">
            Vous naviguez actuellement en mode invité. Pour retrouver votre compte et vos trades sur n'importe quel appareil, 
            définissez un mot de passe ci-dessous.
        </p>
        <?php if(empty($me['password_hash'])): ?>
            <form id="save-account-form" style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">
                <input type="password" id="save-password" placeholder="Choisissez un mot de passe" 
                       style="flex:1;min-width:200px;padding:0.75rem 1rem;border-radius:12px;border:1px solid #2a3447;background:var(--card);color:var(--text)"
                       minlength="6" required>
                <button type="submit" class="btn">💾 Sauvegarder mon compte</button>
            </form>
            <small style="display:block;margin-top:0.5rem;color:var(--muted)">Minimum 6 caractères</small>
        <?php else: ?>
            <p style="color:#00f5a0;font-weight:600">✅ Votre compte est sécurisé ! Vous pouvez vous connecter avec votre mot de passe.</p>
        <?php endif; ?>
    </div>
</footer>

<script>
const USER_ID = <?=$me['id']?>;
let selectedStock = null;

// Chargement des actions
async function loadStocks() {
    const search = document.getElementById('search').value;
    const cat = document.getElementById('filter-cat').value;
    const sort = document.getElementById('sort-by').value;
    
    const res = await fetch(`api.php?action=list_stocks&search=${encodeURIComponent(search)}&cat=${cat}&sort=${sort}`);
    const stocks = await res.json();
    
    const grid = document.getElementById('stocks-grid');
    grid.innerHTML = stocks.map(s => `
        <div class="card stock-card" onclick="openBuy(${s.id},'${s.symbol}',${s.price})">
            <div style="display:flex;justify-content:space-between;align-items:start">
                <div>
                    <strong style="font-size:1.1rem">${s.symbol}</strong>
                    <div style="color:var(--muted);font-size:0.9rem">${s.name}</div>
                </div>
                <span class="badge">${s.category}</span>
            </div>
            <p style="margin:0.75rem 0;color:var(--muted);font-size:0.9rem">${s.description}</p>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:1rem">
                <span class="price">${s.price.toFixed(4)} ₮</span>
                <div style="text-align:right">
                    <div style="font-size:0.8rem;color:${s.trend_score>0.6?'#00f5a0':'#8a94a6'}">
                        ${s.trend_score>0.6?'🔥 Trending':s.security_score>0.7?'🛡️ Safe':'⚡ Volatile'}
                    </div>
                    <small style="color:var(--muted)">Vol: ${(s.volatility*100).toFixed(1)}%</small>
                </div>
            </div>
        </div>
    `).join('');
}

function openBuy(id, symbol, price) {
    selectedStock = {id, symbol, price};
    document.getElementById('modal-stock-name').textContent = `Acheter ${symbol}`;
    document.getElementById('modal-price').textContent = price.toFixed(4) + ' ₮';
    document.getElementById('modal-total').textContent = (price * 1000).toFixed(2) + ' ₮';
    document.getElementById('buy-modal').style.display = 'flex';
}

function closeModal() { document.getElementById('buy-modal').style.display = 'none'; }
async function confirmBuy() {
    if(!selectedStock) return;
    const res = await fetch('api.php?action=buy', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({stock_id: selectedStock.id, quantity: 1000})
    });
    const result = await res.json();
    if(result.success) {
        alert('✅ Achat réussi !');
        location.reload();
    } else {
        alert('❌ '+result.error);
    }
    closeModal();
}

// Bot custom
function openCustomBot() {
    fetch(`api.php?action=get_bot_config&type=custom`)
        .then(r=>r.json())
        .then(cfg=>{
            if(cfg) Object.entries(cfg).forEach(([k,v])=>{
                const el = document.querySelector(`[name="${k}"]`);
                if(el) el.value = v;
            });
        });
    document.getElementById('custom-modal').style.display = 'flex';
}
function closeCustomModal() { document.getElementById('custom-modal').style.display = 'none'; }
document.getElementById('custom-form').onsubmit = async(e)=>{
    e.preventDefault();
    const config = Object.fromEntries(new FormData(e.target).entries());
    await fetch('api.php?action=set_bot_config',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({type:'custom', config})
    });
    alert('✅ Configuration sauvegardée !');
    closeCustomModal();
};

// Sécurité compte - Formulaire footer
document.getElementById('save-account-form')?.addEventListener('submit', async(e)=>{
    e.preventDefault();
    const pwd = document.getElementById('save-password').value;
    if(pwd && pwd.length>=6) {
        const res = await fetch('api.php?action=set_password',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({password: pwd})
        });
        const result = await res.json();
        if(result.success) {
            alert('✅ Compte sauvegardé ! Vous pouvez maintenant vous connecter avec ce mot de passe sur n\'importe quel appareil.');
            location.reload();
        } else {
            alert('❌ '+result.error);
        }
    }
});

// Ancienne fonction setPassword (pour le bouton header)
async function setPassword() {
    const pwd = prompt('Choisissez un mot de passe (min 6 caractères) :');
    if(pwd && pwd.length>=6) {
        const res = await fetch('api.php?action=set_password',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({password: pwd})
        });
        if((await res.json()).success) {
            alert('✅ Compte sécurisé ! Vous pouvez maintenant vous connecter avec mot de passe.');
            location.reload();
        }
    }
}

function logout() { 
    // Pour les utilisateurs anonymes, on peut optionnellement détruire la session
    // Mais on garde les données en DB au cas où
    fetch('api.php?action=logout').then(()=>{
        sessionStorage.clear();
        location.reload();
    }); 
}

// Filtres & recherche
['search','filter-cat','sort-by'].forEach(id=>{
    document.getElementById(id).oninput = loadStocks;
});

// Rafraîchissement périodique
setInterval(loadStocks, 30000);
loadStocks();

// Exécution bots en background (toutes les heures)
setInterval(()=>fetch('api.php?action=run_bots'), 3600000);
</script>
</body>
</html>