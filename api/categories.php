<?php
require_once __DIR__ . '/config.php';
requireLogin();
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// Default built-in categories matching app.js
$system_categories = [
    'receita' => ["Salário Líquido", "13º Salário Líquido", "Férias Líquida", "Bônus + Comissões + PLR", "Renda Extra Líquida", "Outras Receitas"],
    'despesa' => ["Casa", "Saúde", "Locomoção", "Lazer", "Transporte", "Investimentos", "Outras Despesas"]
];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT name, type FROM custom_categories WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$user_id]);
        $custom = $stmt->fetchAll();
        
        $categories = $system_categories;
        foreach ($custom as $c) {
            $type = $c['type'];
            if (isset($categories[$type]) && !in_array($c['name'], $categories[$type])) {
                $categories[$type][] = $c['name'];
            }
        }
        
        echo json_encode($categories);
    } catch (\Exception $e) {
        echo json_encode($system_categories); // Fallback
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $name = trim($data['name'] ?? '');
    $type = $data['type'] ?? '';

    if (empty($name) || !in_array($type, ['receita', 'despesa'])) {
        echo json_encode(['success' => false, 'error' => 'Nome e tipo inválidos.']);
        exit;
    }

    // Check if it already exists in system categories (case-insensitive)
    if (in_array(strtolower($name), array_map('strtolower', $system_categories[$type]))) {
        echo json_encode(['success' => false, 'error' => 'Esta categoria padrão já existe.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO custom_categories (user_id, name, type) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $name, $type]);
        echo json_encode(['success' => true, 'name' => $name]);
    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'error' => 'Você já adicionou esta categoria.']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao salvar categoria: ' . $e->getMessage()]);
        }
    }
    exit;
}
?>
