<?php
require 'config.php';
header('Content-Type: application/json');

// Garante suporte a GET (Health check / Meta Webhook verify)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hubVerifyToken = $_GET['hub_verify_token'] ?? '';
    $hubChallenge   = $_GET['hub_challenge'] ?? '';
    if ($hubChallenge) {
        echo $hubChallenge;
        exit;
    }
    echo json_encode([
        'status' => 'online',
        'service' => 'PriceXP WhatsApp Webhook Service',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Garante que a tabela de rascunhos/conversas pendentes do Patrick exista no banco
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_pending_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        phone VARCHAR(50) NOT NULL,
        type ENUM('receita', 'despesa') NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        bank_name VARCHAR(100) DEFAULT '',
        description VARCHAR(255) DEFAULT '',
        category VARCHAR(100) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

$senderPhone = '';
$rawText     = '';

if (!empty($data['phone'])) {
    $senderPhone = $data['phone'];
} elseif (!empty($data['sender'])) {
    $senderPhone = $data['sender'];
} elseif (!empty($data['from'])) {
    $senderPhone = $data['from'];
} elseif (!empty($data['data']['key']['remoteJid'])) {
    $senderPhone = $data['data']['key']['remoteJid'];
} elseif (!empty($data['entry'][0]['changes'][0]['value']['messages'][0]['from'])) {
    $senderPhone = $data['entry'][0]['changes'][0]['value']['messages'][0]['from'];
}

if (!empty($data['body'])) {
    $rawText = is_array($data['body']) ? ($data['body']['text'] ?? '') : $data['body'];
} elseif (!empty($data['text'])) {
    $rawText = is_array($data['text']) ? ($data['text']['message'] ?? '') : $data['text'];
} elseif (!empty($data['message'])) {
    $rawText = is_array($data['message']) ? ($data['message']['conversation'] ?? $data['message']['text'] ?? '') : $data['message'];
} elseif (!empty($data['data']['message']['conversation'])) {
    $rawText = $data['data']['message']['conversation'];
} elseif (!empty($data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'])) {
    $rawText = $data['entry'][0]['changes'][0]['value']['messages'][0]['text']['body'];
}

$cleanPhone = preg_replace('/\D/', '', $senderPhone);
$shortPhone = (strlen($cleanPhone) >= 12 && substr($cleanPhone, 0, 2) === '55') ? substr($cleanPhone, 2) : $cleanPhone;

if (empty($cleanPhone) || empty($rawText)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nenhum número de telefone ou mensagem de texto recebida no payload.'
    ]);
    exit;
}

// Busca o usuário no banco de dados PriceXP pelo número do WhatsApp ou LID
$stmtUser = $pdo->prepare("SELECT id, first_name, email, shared_owner_id, whatsapp FROM users WHERE whatsapp IS NOT NULL AND TRIM(whatsapp) != ''");
$stmtUser->execute();
$allUsers = $stmtUser->fetchAll();

$user = null;
if (count($allUsers) === 1) {
    $user = $allUsers[0];
} else {
    foreach ($allUsers as $u) {
        $uPhone = preg_replace('/\D/', '', $u['whatsapp']);
        if (empty($uPhone)) continue;
        
        $uShort = (strlen($uPhone) >= 11 && substr($uPhone, 0, 2) === '55') ? substr($uPhone, 2) : $uPhone;
        $cleanShort = (strlen($cleanPhone) >= 11 && substr($cleanPhone, 0, 2) === '55') ? substr($cleanPhone, 2) : $cleanPhone;

        if (
            $cleanPhone === $uPhone ||
            $cleanShort === $uShort ||
            substr($cleanPhone, -8) === substr($uPhone, -8) ||
            substr($cleanPhone, -9) === substr($uPhone, -9) ||
            strpos($cleanPhone, $uPhone) !== false
        ) {
            $user = $u;
            break;
        }
    }
}

if (!$user) {
    $stmtFallback = $pdo->prepare("SELECT id, first_name, email, shared_owner_id FROM users WHERE whatsapp IS NOT NULL AND TRIM(whatsapp) != '' ORDER BY id ASC LIMIT 1");
    $stmtFallback->execute();
    $user = $stmtFallback->fetch();
}

if (!$user) {
    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\nOlá! Eu sou o Patrick, seu assistente financeiro do PriceXP! 💼\n\nAinda não encontrei o seu número (`{$cleanPhone}`) vinculado a uma conta no site.\n\n👉 *Como ativar:*\nAcesse o site *PriceXP*, vá na aba *Minha Conta* e salve o seu número de WhatsApp!";
    echo json_encode([
        'success' => false,
        'reply' => $replyMsg,
        'phone' => $cleanPhone
    ]);
    exit;
}

$user_id      = (int)$user['id'];
$workspace_id = getWorkspaceUserId($pdo, $user_id);
$userName     = !empty($user['first_name']) ? $user['first_name'] : 'Usuário';
$lowerText    = mb_strtolower($rawText, 'UTF-8');

// --- VERIFICA SE HÁ RASCUNHO PENDENTE DO PATRICK (Últimos 10 minutos) ---
$stmtPending = $pdo->prepare("SELECT * FROM whatsapp_pending_sessions WHERE user_id = ? AND created_at >= NOW() - INTERVAL 10 MINUTE ORDER BY id DESC LIMIT 1");
$stmtPending->execute([$user_id]);
$pending = $stmtPending->fetch();

if ($pending) {
    $type = $pending['type'];
    $amount = (float)$pending['amount'];
    
    // Tenta extrair banco da resposta
    $bank_name = $pending['bank_name'];
    if (empty($bank_name) || $bank_name === 'Geral') {
        if (preg_match('/(nubank|itau|itaú|bradesco|santander|inter|c6|caixa|bb|banco do brasil|sicoob|sicredi|pagbank|picpay)/i', $lowerText, $bMatches)) {
            $bank_name = ucfirst(strtolower($bMatches[1]));
            if (strtolower($bank_name) === 'itau') $bank_name = 'Itaú';
            if (strtolower($bank_name) === 'bb') $bank_name = 'Banco do Brasil';
        }
    }
    
    // Extração da descrição da resposta
    $cleanDesc = preg_replace('/(r\$\s*\d+[\.,]?\d*|\d+[\.,]?\d*|reais|real|caiu|foi|no|na|do|da|de|em|para|com|banco|cartão|cartao|nubank|itau|itaú|bradesco|santander|inter|c6|caixa|sicoob|sicredi)/i', ' ', $rawText);
    $cleanDesc = trim(preg_replace('/\s+/', ' ', $cleanDesc));
    
    $description = !empty($cleanDesc) ? ucfirst($cleanDesc) : (!empty($pending['description']) ? $pending['description'] : ($type === 'receita' ? 'Receita via WhatsApp' : 'Despesa via WhatsApp'));

    // Categoria Inteligente
    $category = ($type === 'receita') ? 'Outras Receitas' : 'Outras Despesas';
    if ($type === 'receita') {
        if (preg_match('/(site|venda|freelance|servico|serviço|comissao|comissão|bonus|bônus|plr|renda)/i', $lowerText)) {
            $category = 'Renda Extra Líquida';
        } elseif (preg_match('/(salario|salário|holerite|férias|ferias)/i', $lowerText)) {
            $category = 'Salário Líquido';
        }
    } else {
        if (preg_match('/(mercado|supermercado|feira|comida|ifood)/i', $lowerText)) {
            $category = 'Casa';
        } elseif (preg_match('/(gasolina|combustivel|uber)/i', $lowerText)) {
            $category = 'Transporte';
        }
    }

    $date = date('Y-m-d');
    
    // Grava lançamento final e remove o rascunho
    $stmtIns = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, bank_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtIns->execute([$workspace_id, $user_id, $type, $category, $description, $amount, $date, $bank_name ?: 'Geral']);
    $insertedId = $pdo->lastInsertId();

    $stmtDel = $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE id = ?");
    $stmtDel->execute([$pending['id']]);

    $formattedAmount = number_format($amount, 2, ',', '.');
    $emojiType = ($type === 'receita') ? '🟢 Receita' : '🔴 Despesa';

    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\n"
              . "✅ *Lançamento Completado e Registrado com Sucesso!*\n\n"
              . "👤 *Usuário:* {$userName}\n"
              . "📊 *Tipo:* {$emojiType}\n"
              . "💰 *Valor:* R$ {$formattedAmount}\n"
              . "📝 *Descrição:* {$description}\n"
              . "📁 *Categoria:* {$category}\n"
              . "🏦 *Banco:* " . ($bank_name ?: 'Geral') . "\n"
              . "📅 *Data:* " . date('d/m/Y') . "\n\n"
              . "🚀 _Já disponível no seu painel PriceXP!_";

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// --- PARSER NORMAL DE NOVA MENSAGEM ---
$type = 'despesa';
if (preg_match('/(receb|ganh|salari|salário|pix|entrada|receita|deposit|depósito|venda|caiu|renda|reembolso|lucro|faturamento)/i', $lowerText)) {
    $type = 'receita';
}

$amount = 0.0;
if (preg_match('/(?:r\$\s*|valor\s*|de\s*)?(\d+(?:[\.,]\d{1,2})?)/i', $lowerText, $matches)) {
    $rawVal = str_replace(',', '.', $matches[1]);
    $amount = (float)$rawVal;
}

if ($amount <= 0) {
    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\nOlá {$userName}! Me envie um valor e onde você gastou ou recebeu!\n\n💡 *Exemplo:* _'Gastei 50 no mercado no Nubank'_ ou _'Recebi 3500 de salário no Itaú'_.";
    echo json_encode([
        'success' => false,
        'reply' => $replyMsg,
        'phone' => $cleanPhone
    ]);
    exit;
}

// 3. Extração do Banco
$bank_name = 'Geral';
if (preg_match('/(nubank|itau|itaú|bradesco|santander|inter|c6|caixa|bb|banco do brasil|sicoob|sicredi|pagbank|picpay)/i', $lowerText, $bMatches)) {
    $bank_name = ucfirst(strtolower($bMatches[1]));
    if (strtolower($bank_name) === 'itau') $bank_name = 'Itaú';
    if (strtolower($bank_name) === 'bb') $bank_name = 'Banco do Brasil';
}

// 4. Extração de Descrição
$cleanDesc = preg_replace('/^(patrick[,\s]*|oi\s+patrick[,\s]*|olá\s+patrick[,\s]*)/i', '', $rawText);
$cleanDesc = preg_replace('/(r\$\s*\d+[\.,]?\d*|\d+[\.,]?\d*|reais|real|gastei|gastamos|paguei|pagamos|comprei|compramos|recebi|recebemos|ganhei|ganhamos|depositei|depositamos|no|na|do|da|de|em|para|com|banco|cartão|cartao|nubank|itau|itaú|bradesco|santander|inter|c6|caixa|sicoob|sicredi)/i', ' ', $cleanDesc);
$cleanDesc = trim(preg_replace('/\s+/', ' ', $cleanDesc));

if (empty($cleanDesc) || strlen($cleanDesc) < 2) {
    if (preg_match('/(mercado|supermercado)/i', $lowerText)) $cleanDesc = 'Mercado';
    elseif (preg_match('/(gasolina|combustivel|combustível)/i', $lowerText)) $cleanDesc = 'Gasolina';
    elseif (preg_match('/(almoço|almoco|jantar|lanche|comida|restaurante|ifood)/i', $lowerText)) $cleanDesc = 'Ifood / Alimentação';
    elseif (preg_match('/(farmacia|farmácia|remedio|remédio)/i', $lowerText)) $cleanDesc = 'Farmácia';
    elseif (preg_match('/(uber|99|taxi)/i', $lowerText)) $cleanDesc = 'Uber / Transporte';
    elseif (preg_match('/(site|venda|cliente)/i', $lowerText)) $cleanDesc = 'Venda Site';
    else $cleanDesc = ($type === 'despesa') ? 'Despesa via WhatsApp' : 'Receita via WhatsApp';
}
$description = ucfirst($cleanDesc);

// Mapeamento Inteligente de Categorias
$category = ($type === 'receita') ? 'Outras Receitas' : 'Outras Despesas';
if ($type === 'receita') {
    if (preg_match('/(site|venda|freelance|servico|serviço|comissao|comissão|bonus|bônus|plr|renda)/i', $lowerText)) {
        $category = 'Renda Extra Líquida';
    } elseif (preg_match('/(salario|salário|holerite|férias|ferias)/i', $lowerText)) {
        $category = 'Salário Líquido';
    }
} else {
    if (preg_match('/(mercado|supermercado|feira|açougue|padaria|comida|ifood)/i', $lowerText)) {
        $category = 'Casa';
    } elseif (preg_match('/(gasolina|combustivel|combustível|uber|estacionamento|pedagio|mecanico)/i', $lowerText)) {
        $category = 'Transporte';
    } elseif (preg_match('/(remedio|remédio|farmacia|farmácia|medico|médico|consulta)/i', $lowerText)) {
        $category = 'Saúde';
    } elseif (preg_match('/(cinema|show|festa|bar|restaurante|viagem|jogos|lazer)/i', $lowerText)) {
        $category = 'Lazer';
    }
}

$date = date('Y-m-d');

// Grava o lançamento diretamente na tabela transactions do banco financas_db com segurança total
try {
    $stmtIns = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, bank_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtIns->execute([$workspace_id, $user_id, $type, $category, $description, $amount, $date, $bank_name]);
    $insertedId = $pdo->lastInsertId();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'reply' => "🟢 *Patrick — Assistente PriceXP*\n\n⚠️ Ops! Tive um contratempo ao gravar no banco: " . $e->getMessage()
    ]);
    exit;
}

logUserActivity($pdo, $user_id, 'WHATSAPP_LANCAMENTO', "Lançamento via WhatsApp #{$insertedId}: {$type} - {$description} (R$ {$amount})", $amount, ['bank' => $bank_name, 'phone' => $cleanPhone]);

$formattedAmount = number_format($amount, 2, ',', '.');
$emojiType = ($type === 'receita') ? '🟢 Receita' : '🔴 Despesa';

$replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\n"
          . "✅ *Lançamento Registrado com Sucesso!*\n\n"
          . "👤 *Usuário:* {$userName}\n"
          . "📊 *Tipo:* {$emojiType}\n"
          . "💰 *Valor:* R$ {$formattedAmount}\n"
          . "📝 *Descrição:* {$description}\n"
          . "📁 *Categoria:* {$category}\n"
          . "🏦 *Banco:* {$bank_name}\n"
          . "📅 *Data:* " . date('d/m/Y') . "\n\n"
          . "🚀 _Já atualizado no seu painel PriceXP!_";

echo json_encode([
    'success' => true,
    'id' => $insertedId,
    'user' => $userName,
    'reply' => $replyMsg
]);
exit;
