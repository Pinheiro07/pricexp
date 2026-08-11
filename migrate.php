<?php
require 'api/config.php';

try {
    // 1. Create credit_cards table
    $sql = "CREATE TABLE IF NOT EXISTS credit_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        credit_limit DECIMAL(10, 2) NOT NULL,
        closing_day INT NOT NULL,
        due_day INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $pdo->exec($sql);
    echo "Table credit_cards created successfully.\n";

    // 2. Add card_id to transactions table if it doesn't exist
    $checkColumn = $pdo->query("SHOW COLUMNS FROM transactions LIKE 'card_id'");
    if ($checkColumn->rowCount() === 0) {
        $sql = "ALTER TABLE transactions ADD COLUMN card_id INT DEFAULT NULL,
                ADD CONSTRAINT fk_transaction_card FOREIGN KEY (card_id) REFERENCES credit_cards(id) ON DELETE SET NULL;";
        $pdo->exec($sql);
        echo "Column card_id added to transactions successfully.\n";
    } else {
        echo "Column card_id already exists in transactions.\n";
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>