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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true) ?? $_POST;

// Tenta extrair telefone do remetente e mensagem de múltiplos formatos de API (Evolution, Z-API, Meta, Baileys)
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

// Higieniza o número de telefone (somente dígitos)
$cleanPhone = preg_replace('/\D/', '', $senderPhone);
// Remove DDI 55 se o número armazenado no banco estiver sem DDI
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
    // Se só existe 1 usuário com WhatsApp cadastrado no PriceXP, vincula direto!
    $user = $allUsers[0];
} else {
    foreach ($allUsers as $u) {
        $uPhone = preg_replace('/\D/', '', $u['whatsapp']);
        if (empty($uPhone)) continue;
        
        // Compara variações com e sem DDI 55, e pelos últimos 8 e 9 dígitos do celular
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
    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\nOlá! Eu sou o Patrick, seu assistente financeiro do PriceXP! 💼\n\nAinda não encontrei o seu número (`{$cleanPhone}`) vinculado a uma conta no site.\n\n👉 *Como ativar:*\nAcesse o site *PriceXP*, vá na aba *Minha Conta* e salve o seu número de WhatsApp! Depois disso você já poderá mandar mensagens e áudios pra mim para cadastrar seus gastos e ganhos automaticamente!";
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

// PARSER INTELIGENTE DE MENSAGENS FINANCEIRAS (Áudio/Texto)
$lowerText = strtolower($rawText);

// 1. Tipo (Receita vs Despesa)
$type = 'despesa';
if (preg_match('/(recebi|ganhei|salario|salário|pix recebido|entrada|receita|deposito|depósito)/i', $lowerText)) {
    $type = 'receita';
}

// 2. Extração de Valor Numérico (ex: R$ 50,00 | 50.50 | 50 | 120,00)
$amount = 0.0;
if (preg_match('/(?:r\$\s*|valor\s*|de\s*)?(\d+(?:[\.,]\d{1,2})?)/i', $lowerText, $matches)) {
    $rawVal = str_replace(',', '.', $matches[1]);
    $amount = (float)$rawVal;
}

if ($amount <= 0) {
    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\nOlá {$userName}! Para cadastrar uma despesa ou receita, me envie um texto ou áudio dizendo o valor e onde foi!\n\n💡 *Exemplo:* _'Gastei 50 no mercado no Nubank'_ ou _'Recebi 3500 de salário no Itaú'_.";
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

// 4. Extração de Categoria & Descrição
$category = ($type === 'despesa') ? 'Outras Despesas' : 'Outras Receitas';
$description = trim(preg_replace('/(r\$\s*\d+[\.,]?\d*|\d+[\.,]?\d*|gastei|paguei|comprei|recebi|no|na|do|da|banco|nubank|itau|itaú|bradesco|santander|inter|c6|caixa)/i', '', $lowerText));
$description = trim(preg_replace('/\s+/', ' ', $description));
if (empty($description)) {
    $description = ($type === 'despesa') ? 'Despesa via WhatsApp' : 'Receita via WhatsApp';
}
$description = ucfirst($description);

// Mapeamento automático de categorias por palavras-chave
if (preg_match('/(mercado|supermercado|feira|açougue|padaria|comida|ifood)/i', $lowerText)) {
    $category = 'Casa';
} elseif (preg_match('/(gasolina|combustivel|combustível|uber|uber|estacionamento|pedagio|pedágio|mecanico)/i', $lowerText)) {
    $category = 'Transporte';
} elseif (preg_match('/(remedio|remédio|farmacia|farmácia|medico|médico|consulta|exame)/i', $lowerText)) {
    $category = 'Saúde';
} elseif (preg_match('/(cinema|show|festa|bar|restaurante|viagem|jogos|lazer)/i', $lowerText)) {
    $category = 'Lazer';
} elseif (preg_match('/(salario|salário|férias|ferias|bonus|bônus|plr|renda extra)/i', $lowerText)) {
    $category = 'Salário Líquido';
}

$date = date('Y-m-d');

// Grava o lançamento diretamente na tabela transactions do banco financas_db
$stmtIns = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, bank_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtIns->execute([$workspace_id, $user_id, $type, $category, $description, $amount, $date, $bank_name]);
$insertedId = $pdo->lastInsertId();

// Grava log de auditoria
logUserActivity($pdo, $user_id, 'WHATSAPP_LANCAMENTO', "Lançamento via WhatsApp #{$insertedId}: {$type} - {$description} (R$ {$amount})", $amount, ['bank' => $bank_name, 'phone' => $cleanPhone]);

// Resposta formatada para o WhatsApp
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
    'reply' => $replyMsg,
    'data' => [
        'type' => $type,
        'amount' => $amount,
        'description' => $description,
        'category' => $category,
        'bank_name' => $bank_name,
        'date' => $date
    ]
]);
exit;
