<?php
require 'config.php';
requireLogin();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM credit_cards WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$user_id]);
    $cards = $stmt->fetchAll();
    
    // Convert limits to float
    foreach($cards as &$card) {
        $card['credit_limit'] = (float)$card['credit_limit'];
    }
    
    echo json_encode($cards);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $credit_limit = $data['credit_limit'] ?? 0;
    $closing_day = intval($data['closing_day'] ?? 5);
    $due_day = intval($data['due_day'] ?? 15);
    
    if (empty($name) || $credit_limit <= 0 || $closing_day < 1 || $closing_day > 31 || $due_day < 1 || $due_day > 31) {
        header('HTTP/1.1 400 Bad Request');
        echo json_encode(['error' => 'Dados inválidos. Verifique os campos.']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO credit_cards (user_id, name, credit_limit, closing_day, due_day) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $name, $credit_limit, $closing_day, $due_day]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM credit_cards WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    
    echo json_encode(['success' => true]);
    exit;
}
?>
