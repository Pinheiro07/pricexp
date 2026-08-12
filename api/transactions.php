<?php
require 'config.php';
requireLogin();
header('Content-Type: application/json');

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

// ID do workspace (owner da conta se for conta conjunta, ou o próprio user_id)
$workspace_id = getWorkspaceUserId($pdo, $user_id);

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT t.*, u.first_name AS created_by_name 
        FROM transactions t 
        LEFT JOIN users u ON t.created_by_user_id = u.id 
        WHERE t.user_id = ? 
        ORDER BY t.date DESC, t.id DESC
    ");
    $stmt->execute([$workspace_id]);
    $transactions = $stmt->fetchAll();
    
    // Convert types for JS
    foreach($transactions as &$tx) {
        $tx['id']      = (int)$tx['id'];
        $tx['amount']  = (float)$tx['amount'];
        $tx['card_id'] = $tx['card_id'] !== null ? (int)$tx['card_id'] : null;
    }
    
    echo json_encode($transactions);
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $type         = $data['type'] ?? '';
    $category     = $data['category'] ?? '';
    $description  = $data['description'] ?? '';
    $amount       = $data['amount'] ?? 0;
    $date         = $data['date'] ?? '';
    $installments = $data['installments'] ?? 1;
    $repeat_type  = $data['repeat_type'] ?? 'none';
    $card_id      = !empty($data['card_id']) ? intval($data['card_id']) : null;
    $bank_name    = !empty($data['bank_name']) ? trim($data['bank_name']) : 'Geral';

    $stmt = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, card_id, bank_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($repeat_type !== 'none' && $installments > 1) {
        $baseDate = new DateTime($date);
        for ($i = 0; $i < $installments; $i++) {
            $clonedDate = clone $baseDate;
            $clonedDate->modify("+$i month");
            $currentDateStr = $clonedDate->format('Y-m-d');
            
            $currentDesc = $description;
            if ($repeat_type === 'installment' && $installments <= 120) {
                $currentNum = $i + 1;
                $currentDesc .= " ($currentNum/$installments)";
            }
            
            $stmt->execute([$workspace_id, $user_id, $type, $category, $currentDesc, $amount, $currentDateStr, $card_id, $bank_name]);
        }
        logUserActivity($pdo, $user_id, 'CRIAR_LANCAMENTO', "Lançamento repetido ({$installments}x) de {$type}: {$description}", $amount, ['category' => $category, 'bank' => $bank_name]);
    } else {
        $stmt->execute([$workspace_id, $user_id, $type, $category, $description, $amount, $date, $card_id, $bank_name]);
        logUserActivity($pdo, $user_id, 'CRIAR_LANCAMENTO', "Lançamento de {$type}: {$description}", $amount, ['category' => $category, 'bank' => $bank_name]);
    }
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }

    $type        = $data['type']        ?? '';
    $category    = $data['category']    ?? '';
    $description = $data['description'] ?? '';
    $amount      = floatval($data['amount'] ?? 0);
    $date        = $data['date']        ?? '';
    $card_id     = !empty($data['card_id']) ? intval($data['card_id']) : null;
    $bank_name   = !empty($data['bank_name']) ? trim($data['bank_name']) : 'Geral';

    $stmt = $pdo->prepare("UPDATE transactions SET type=?, category=?, description=?, amount=?, date=?, card_id=?, bank_name=? WHERE id=? AND user_id=?");
    $stmt->execute([$type, $category, $description, $amount, $date, $card_id, $bank_name, $id, $workspace_id]);
    logUserActivity($pdo, $user_id, 'EDITAR_LANCAMENTO', "Edição do lançamento #{$id}: {$description}", $amount, ['category' => $category, 'bank' => $bank_name]);

    echo json_encode(['success' => true]);
    exit;
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $workspace_id]);
    logUserActivity($pdo, $user_id, 'EXCLUIR_LANCAMENTO', "Exclusão do lançamento #{$id}");
    
    echo json_encode(['success' => true]);
    exit;
}
?>
