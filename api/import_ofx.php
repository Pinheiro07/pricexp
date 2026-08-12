<?php
require 'config.php';
requireLogin();
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// ======================================================
// COMMIT MODE: Salva as transações já editadas pelo usuário
// Recebe JSON no body: { "mode": "commit", "transactions": [...] }
// ======================================================
$workspace_id = getWorkspaceUserId($pdo, $user_id);
$rawInput = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($rawInput)) {
    $payload = json_decode($rawInput, true);
    if ($payload && isset($payload['mode']) && $payload['mode'] === 'commit' && isset($payload['transactions'])) {
        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, bank_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $count = 0;
        foreach ($payload['transactions'] as $tx) {
            $type = $tx['type'] ?? 'despesa';
            $category = trim($tx['category'] ?? '');
            $description = trim($tx['description'] ?? 'Importado');
            $amount = abs((float)($tx['amount'] ?? 0));
            $date = $tx['date'] ?? date('Y-m-d');
            $bank_name = !empty($tx['bank_name']) ? trim($tx['bank_name']) : 'Geral';
            if ($amount <= 0 || empty($date)) continue;
            if (empty($category)) $category = ($type === 'despesa') ? 'Outras Despesas' : 'Outras Receitas';
            $stmt->execute([$workspace_id, $user_id, $type, $category, $description, $amount, $date, $bank_name]);
            $count++;
        }
        logUserActivity($pdo, $user_id, 'IMPORTAR_EXTRATO', "Importação de extrato bancário ({$count} lançamentos)");
        echo json_encode(['success' => true, 'imported_count' => $count]);
        exit;
    }
}

// ======================================================
// PREVIEW MODE: Faz o parse do arquivo e retorna as transações sem salvar
// ======================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['ofx_file'])) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file = $_FILES['ofx_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Erro no upload do arquivo.']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$content = file_get_contents($file['tmp_name']);
$parsedTransactions = [];

if ($ext === 'csv') {
    $lines = explode("\n", str_replace("\r", "", $content));

    foreach ($lines as $line) {
        $row = str_getcsv($line, ';');
        if (count($row) < 2) $row = str_getcsv($line, ',');
        if (count($row) < 2) continue;

        $date = ''; $amount = 0; $description = '';

        foreach ($row as $col) {
            $val = trim($col);
            if (empty($val)) continue;
            if (empty($date)) {
                if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $val, $m)) { $date = "{$m[3]}-{$m[2]}-{$m[1]}"; continue; }
                if (preg_match('/^(\d{4})[\/\-](\d{2})[\/\-](\d{2})$/', $val, $m)) { $date = "{$m[1]}-{$m[2]}-{$m[3]}"; continue; }
            }
            if ($amount === 0 && preg_match('/^[\-\+]?\s*(?:R\$)?\s*[0-9]+(?:[\.,][0-9]+)*$/', $val)) {
                $clean = preg_replace('/[^\d\.,\-]/', '', $val);
                if (strpos($clean, ',') !== false && strpos($clean, '.') !== false) {
                    $clean = str_replace('.', '', $clean); $clean = str_replace(',', '.', $clean);
                } elseif (strpos($clean, ',') !== false) {
                    $clean = preg_match('/,\d{2}$/', $clean) ? str_replace(',', '.', $clean) : str_replace(',', '', $clean);
                }
                $amtFloat = (float)$clean;
                if ($amtFloat != 0) { $amount = $amtFloat; continue; }
            }
            if (strlen($val) > 2 && !is_numeric(str_replace(['.', ','], '', $val))) {
                $description = empty($description) ? $val : $description . ' - ' . $val;
            }
        }

        if (!empty($date) && $amount != 0 && !empty($description)) {
            $type = ($amount < 0) ? 'despesa' : 'receita';
            $parsedTransactions[] = [
                'type' => $type,
                'category' => ($type === 'despesa') ? 'Outras Despesas' : 'Outras Receitas',
                'description' => $description,
                'amount' => abs($amount),
                'date' => $date,
            ];
        }
    }
} else {
    $chunks = explode('<STMTTRN>', $content);
    array_shift($chunks);
    if (empty($chunks)) {
        echo json_encode(['success' => false, 'error' => 'Nenhuma transação encontrada no arquivo OFX.']);
        exit;
    }
    foreach ($chunks as $chunk) {
        $txBlock = explode('</STMTTRN>', $chunk)[0];
        preg_match('/<DTPOSTED>([0-9]{8})/i', $txBlock, $dtMatch);
        $ofxDateStr = $dtMatch[1] ?? '';
        $date = date('Y-m-d');
        if (strlen($ofxDateStr) >= 8) {
            $date = substr($ofxDateStr, 0, 4) . '-' . substr($ofxDateStr, 4, 2) . '-' . substr($ofxDateStr, 6, 2);
        }
        preg_match('/<TRNAMT>([\-0-9\.]+)/i', $txBlock, $amtMatch);
        $amountStr = $amtMatch[1] ?? '0';
        $amount = abs((float)$amountStr);
        $type = ((float)$amountStr < 0) ? 'despesa' : 'receita';
        preg_match('/<MEMO>(.*?)(?:<|\r|\n)/i', $txBlock, $memoMatch);
        $description = trim(strip_tags($memoMatch[1] ?? 'Importado'));
        if ($amount > 0) {
            $parsedTransactions[] = [
                'type' => $type,
                'category' => ($type === 'despesa') ? 'Outras Despesas' : 'Outras Receitas',
                'description' => $description,
                'amount' => $amount,
                'date' => $date,
            ];
        }
    }
}

if (empty($parsedTransactions)) {
    echo json_encode(['success' => false, 'error' => 'Nenhuma transação válida encontrada no arquivo.']);
    exit;
}

echo json_encode(['success' => true, 'preview' => true, 'transactions' => $parsedTransactions]);
exit;
?>