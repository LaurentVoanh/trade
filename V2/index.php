<?php
// index.php - CryptoFun Trader V2 - Page d'accueil publique
require_once 'config.php';
session_start();

$db = getDB();

// Initialisation automatique si nécessaire
if (!isInitialized()) {
    require_once 'init.php';
    initializeDatabase();
}

// Récupérer le token d'accès depuis l'URL si présent
$access_token = $_GET['token'] ?? null;
$user = null;

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

// Mettre à jour dernière activité
$db->prepare("UPDATE users SET last_active=CURRENT_TIMESTAMP WHERE id=?")->execute([$user['id']]);

// URL de partage pour retrouver son compte
$shareUrl = APP_URL . '?token=' . urlencode($user['secret_token']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>🚀 CryptoFun Trader - Tradez avec des bots IA</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
</head>
<body>
<div class="container">
    <header class="header">
        <div class="logo">🚀 CryptoFun</div>
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
            <span class="badge">👤 <?=htmlspecialchars($user['username'])?></span>
            <span class="price">💰 <?=number_format($user['tokens'],0,',',' ')?> tokens</span>
            <?php if(empty($user['password_hash'])): ?>
                <button class="btn" onclick="showPasswordModal()">🔒 Sécuriser mon compte</button>
            <?php else: ?>
                <span class="badge" style="background:#00f5a0;color:#0a0e17">✅ Compte sécurisé</span>
            <?php endif; ?>
            <button class="btn" style="background:#2a3447" onclick="logout()">Déconnexion</button>
        </div>
    </header>

    <!-- Section URL de partage -->
    <section class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,#667eea 0%,#f093fb 100%)">
        <h3 style="color:#fff;margin-bottom:0.5rem">🔗 Votre lien personnel</h3>
        <p style="color:#fff;opacity:0.9;font-size:0.9rem;margin-bottom:1rem">
            Sauvegardez ce lien pour retrouver votre compte sur n'importe quel appareil :
        </p>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <input type="text" id="share-url" value="<?=$shareUrl?>" readonly 
                   style="flex:1;min-width:200px;padding:0.75rem 1rem;border-radius:12px;border:none;background:rgba(255,255,255,0.2);color:#fff;font-family:monospace">
            <button class="btn" style="background:#fff;color:#667eea" onclick="copyUrl()">📋 Copier</button>
        </div>
        <small style="display:block;margin-top:0.5rem;color:#fff;opacity:0.8;font-size:0.8rem">
            💡 Astuce : Ajoutez cette page à vos favoris ou envoyez-vous le lien par email
        </small>
    </section>

    <!-- Panneau des bots -->
    <section class="card" style="margin-bottom:1.5rem">
        <h3>🤖 Mes Bots Automatiques</h3>
        <p style="color:var(--muted);font-size:0.9rem;margin-bottom:1rem">
            Vos 4 bots tradent automatiquement chaque jour. Configurez le bot Custom selon vos préférences.
        </p>
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
        <button class="btn" style="margin-top:1rem;width:100%" onclick="runBotsNow()">▶️ Lancer les bots maintenant</button>
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
            <option value="nft">🖼️ NFT</option>
            <option value="metaverse">🌐 Métavers</option>
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
                    <option value="defi">💰 DeFi</option>
                </select>
            </label>
            <label>Quantité par trade: <input type="number" name="quantity_per_trade" value="2500" min="100" max="5000" style="width:100%;padding:0.5rem;border-radius:8px;border:1px solid #2a3447;background:var(--card);color:var(--text)"></label>
            <button type="submit" class="btn">💾 Enregistrer</button>
        </form>
        <button class="btn" style="background:#2a3447;margin-top:1rem;width:100%" onclick="closeCustomModal()">Fermer</button>
    </div>
</div>

<!-- Modal sécurisation compte -->
<div id="password-modal" class="modal" style="display:none">
    <div class="modal-content">
        <h3>🔐 Sécuriser votre compte</h3>
        <p style="color:var(--muted);margin:1rem 0">
            Définissez un mot de passe pour protéger votre compte et pouvoir vous connecter sur n'importe quel appareil.
        </p>
        <form id="save-account-form" style="display:grid;gap:1rem">
            <label>Mot de passe (min 6 caractères):
                <input type="password" id="save-password" placeholder="Choisissez un mot de passe" 
                       style="width:100%;padding:0.75rem 1rem;border-radius:12px;border:1px solid #2a3447;background:var(--card);color:var(--text)"
                       minlength="6" required>
            </label>
            <button type="submit" class="btn">💾 Sauvegarder mon compte</button>
        </form>
        <button class="btn" style="background:#2a3447;margin-top:1rem;width:100%" onclick="closePasswordModal()">Plus tard</button>
    </div>
</div>

<!-- Footer -->
<footer style="margin-top:3rem;padding:2rem 0;border-top:1px solid #2a3447;text-align:center">
    <div class="card" style="max-width:600px;margin:0 auto">
        <h4 style="margin-bottom:1rem">🎮 Comment jouer ?</h4>
        <ol style="text-align:left;color:var(--muted);line-height:1.8;margin-bottom:1rem">
            <li>👀 <strong>Explorez</strong> le marché - 400 actions crypto disponibles</li>
            <li>🛒 <strong>Achetez</strong> des actions avec vos 1M de tokens initiaux</li>
            <li>🤖 <strong>Configurez</strong> vos bots pour trader automatiquement</li>
            <li>🔗 <strong>Sauvegardez</strong> votre lien personnel pour retrouver votre compte</li>
            <li>🔐 <strong>Sécurisez</strong> avec un mot de passe pour accéder depuis n'importe où</li>
        </ol>
        <p style="color:var(--muted);font-size:0.85rem">
            🌐 Site visible par tous - Rejoignez les <?=number_format((int)($db->query("SELECT COUNT(*) FROM users")->fetchColumn()) - 1, 0, ',', ' ')?> traders actifs !
        </p>
    </div>
</footer>

<script>
const USER_ID = <?=$user['id']?>;
const USER_TOKEN = '<?=htmlspecialchars($user['secret_token'])?>';
let selectedStock = null;

// Copier l'URL de partage
function copyUrl() {
    const input = document.getElementById('share-url');
    input.select();
    document.execCommand('copy');
    alert('✅ Lien copié ! Sauvegardez-le pour retrouver votre compte.');
}

// Chargement des actions
async function loadStocks() {
    const search = document.getElementById('search').value;
    const cat = document.getElementById('filter-cat').value;
    const sort = document.getElementById('sort-by').value;
    
    try {
        const res = await fetch(`api.php?action=list_stocks&search=${encodeURIComponent(search)}&cat=${cat}&sort=${sort}`);
        const stocks = await res.json();
        
        const grid = document.getElementById('stocks-grid');
        if(stocks.length === 0) {
            grid.innerHTML = '<div class="card" style="grid-column:1/-1;text-align:center;padding:3rem">Aucune action trouvée</div>';
            return;
        }
        
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
    } catch(e) {
        console.error('Erreur chargement stocks:', e);
    }
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
    try {
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
    } catch(e) {
        alert('❌ Erreur de connexion');
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

// Lancer les bots
async function runBotsNow() {
    if(confirm('Lancer tous vos bots maintenant ?')) {
        await fetch('api.php?action=run_bots');
        alert('✅ Bots lancés ! Rechargez la page pour voir les résultats.');
        location.reload();
    }
}

// Modal password
function showPasswordModal() { document.getElementById('password-modal').style.display = 'flex'; }
function closePasswordModal() { document.getElementById('password-modal').style.display = 'none'; }

document.getElementById('save-account-form').onsubmit = async(e)=>{
    e.preventDefault();
    const pwd = document.getElementById('save-password').value;
    if(pwd && pwd.length>=6) {
        try {
            const res = await fetch('api.php?action=set_password',{
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({password: pwd})
            });
            const result = await res.json();
            if(result.success) {
                alert('✅ Compte sécurisé ! Vous pouvez maintenant vous connecter avec ce mot de passe.');
                location.reload();
            } else {
                alert('❌ '+result.error);
            }
        } catch(e) {
            alert('❌ Erreur de connexion');
        }
    }
};

// Logout
function logout() { 
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
</script>
</body>
</html>
