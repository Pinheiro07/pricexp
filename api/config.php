<?php
session_start();

// Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:;");

define('DEBUG_MODE', true); // Altere para false em produção para exigir e-mail real

// Configurações SMTP para MailCow ou outro servidor de e-mail
define('SMTP_HOST', 'mail.seudominio.com'); // Ex: mail.seuprovedor.com
define('SMTP_PORT', 587);                    // 587 (TLS), 465 (SSL) ou 25
define('SMTP_USER', 'no-reply@pricexp.com');
define('SMTP_PASS', 'sua-senha-aqui');
define('SMTP_FROM', 'no-reply@pricexp.com');
define('SMTP_SECURE', 'tls');                // 'tls', 'ssl' ou ''

$host = '172.17.0.1;port=3307';
$db   = 'financas_db';
$user = 'financas_user';
$pass = 'financas_password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Auto-migrate database tables for Credit Cards
    $pdo->exec("CREATE TABLE IF NOT EXISTS credit_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        credit_limit DECIMAL(10, 2) NOT NULL,
        closing_day INT NOT NULL,
        due_day INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $checkColumn = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'card_id'");
    if ($checkColumn->rowCount() === 0) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN card_id INT DEFAULT NULL,
                    ADD CONSTRAINT fk_transaction_card FOREIGN KEY (card_id) REFERENCES credit_cards(id) ON DELETE SET NULL;");
    }

    // Auto-migrate bank_name column on transactions table
    $checkBankColumn = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'bank_name'");
    if ($checkBankColumn->rowCount() === 0) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN bank_name VARCHAR(100) DEFAULT 'Geral';");
    }

    // Auto-migrate user profile fields (first_name, last_name, profile_picture)
    $checkFirstName = $pdo->query("SHOW COLUMNS FROM users LIKE 'first_name'");
    if ($checkFirstName->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users 
                    ADD COLUMN first_name VARCHAR(100) DEFAULT NULL,
                    ADD COLUMN last_name VARCHAR(100) DEFAULT NULL,
                    ADD COLUMN profile_picture VARCHAR(255) DEFAULT 'default.png';");
    }

    // Auto-migrate user verification fields
    $checkUserActive = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($checkUserActive->rowCount() === 0) {
        $pdo->exec("ALTER TABLE users 
                    ADD COLUMN is_active TINYINT(1) DEFAULT 0,
                    ADD COLUMN verification_code VARCHAR(6) DEFAULT NULL,
                    ADD COLUMN code_expires_at DATETIME DEFAULT NULL;");
    }

    // Auto-migrate remember tokens table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Auto-migrate custom categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(50) NOT NULL,
        type ENUM('receita', 'despesa') NOT NULL,
        UNIQUE KEY unique_user_cat (user_id, name, type),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (\PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database connection or migration failed: ' . $e->getMessage()]);
    exit;
}

// Utility to check if logged in
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }
}
?>
