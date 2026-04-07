-- schema.sql - Base de données CryptoFun Trader V2
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    secret_token TEXT UNIQUE NOT NULL,
    username TEXT,
    password_hash TEXT,
    tokens REAL DEFAULT 1000000,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_active DATETIME
);

CREATE TABLE IF NOT EXISTS stocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    symbol TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    price REAL DEFAULT 1.0,
    volatility REAL DEFAULT 0.05,
    category TEXT,
    trend_score REAL DEFAULT 0,
    security_score REAL DEFAULT 0.5,
    ai_personality TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS holdings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    stock_id INTEGER,
    quantity REAL DEFAULT 0,
    avg_price REAL DEFAULT 0,
    UNIQUE(user_id, stock_id),
    FOREIGN KEY(user_id) REFERENCES users(id),
    FOREIGN KEY(stock_id) REFERENCES stocks(id)
);

CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    stock_id INTEGER,
    type TEXT CHECK(type IN ('buy','sell')),
    quantity REAL,
    price REAL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_bot INTEGER DEFAULT 0,
    bot_type TEXT
);

CREATE TABLE IF NOT EXISTS bots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    type TEXT CHECK(type IN ('performance','security','ai_learning','custom')),
    active INTEGER DEFAULT 1,
    config TEXT,
    last_run DATETIME,
    FOREIGN KEY(user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS market_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    stock_id INTEGER,
    price REAL,
    volume REAL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_secret ON users(secret_token);
CREATE INDEX idx_stocks_symbol ON stocks(symbol);
CREATE INDEX idx_holdings_user ON holdings(user_id);
CREATE INDEX idx_transactions_user ON transactions(user_id);
CREATE INDEX idx_market_history_stock ON market_history(stock_id);
