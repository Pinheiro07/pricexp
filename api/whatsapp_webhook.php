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

// Tabela de rascunhos / sessão incremental de conversa do Patrick
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS whatsapp_pending_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        phone VARCHAR(50) NOT NULL,
        type VARCHAR(20) DEFAULT 'despesa',
        amount DECIMAL(10, 2) DEFAULT 0.00,
        description VARCHAR(255) DEFAULT '',
        category VARCHAR(100) DEFAULT '',
        bank_name VARCHAR(100) DEFAULT '',
        payment_method VARCHAR(50) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    @$pdo->exec("ALTER TABLE whatsapp_pending_sessions ADD COLUMN payment_method VARCHAR(50) DEFAULT ''");
} catch (Exception $e) {}

// Limpeza automática de bancos legados poluídos no banco de dados
try {
    $pdo->exec("UPDATE transactions SET bank_name = 'Nubank' WHERE bank_name LIKE 'Nubank (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'Banco Inter' WHERE bank_name LIKE 'Banco Inter (%' OR bank_name LIKE 'Inter (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'C6 Bank' WHERE bank_name LIKE 'C6 Bank (%' OR bank_name LIKE 'C6 (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'Sicredi' WHERE bank_name LIKE 'Sicredi (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'Itaú' WHERE bank_name LIKE 'Itaú (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'Bradesco' WHERE bank_name LIKE 'Bradesco (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'Santander' WHERE bank_name LIKE 'Santander (%'");
    $pdo->exec("UPDATE transactions SET bank_name = 'Caixa' WHERE bank_name LIKE 'Caixa (%'");
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

if (empty($cleanPhone) || empty($rawText)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

// Identifica usuário
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
        if (strpos($cleanPhone, $uPhone) !== false || strpos($uPhone, substr($cleanPhone, -8)) !== false) {
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
    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\nOlá! Eu sou o Patrick, assistente do PriceXP! 💼\n\nCadastre seu número de WhatsApp no site *PriceXP* em *Minha Conta* para começarmos!";
    echo json_encode(['success' => false, 'reply' => $replyMsg]);
    exit;
}

$user_id      = (int)$user['id'];
$workspace_id = getWorkspaceUserId($pdo, $user_id);
$userName     = !empty($user['first_name']) ? $user['first_name'] : 'Usuário';
$lowerText    = mb_strtolower($rawText, 'UTF-8');

// --- HELPER DE PARSER INTELIGENTE DE VALORES ---
function parseAmount($text) {
    // Remove marcas de banco como "C6" ou "C6 Bank" para não confundir o "6" com R$ 6,00
    $cleanText = preg_replace('/\b(c6\s*bank|c6)\b/i', ' ', $text);

    if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(mil|k)\b/i', $cleanText, $m)) {
        $val = (float)str_replace(',', '.', $m[1]);
        return $val * 1000;
    }
    if (preg_match('/(?:r\$\s*|valor\s*|de\s*)?(\b\d+(?:[\.,]\d{1,2})?\b)(?:\s*reais|\s*real)?/i', $cleanText, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }
    return 0.0;
}

// --- HELPER DE EXTRAÇÃO DE BANCO LIMPO ---
function parseBank($text, $workspace_id = null, $pdo = null) {
    $lower = mb_strtolower($text, 'UTF-8');

    $userBankMap = [];
    if ($pdo && $workspace_id) {
        try {
            $stmtUserBanks = $pdo->prepare("SELECT DISTINCT bank_name FROM transactions WHERE user_id = ? AND bank_name IS NOT NULL AND bank_name != ''");
            $stmtUserBanks->execute([$workspace_id]);
            $userBanks = $stmtUserBanks->fetchAll(PDO::FETCH_COLUMN);

            foreach ($userBanks as $ub) {
                // Remove qualquer parêntese legado do nome do banco
                $cleanUb = trim(preg_replace('/\(.*?\)/', '', $ub));
                if (!empty($cleanUb) && strtolower($cleanUb) !== 'geral' && strtolower($cleanUb) !== 'dinheiro') {
                    $userBankMap[mb_strtolower($cleanUb, 'UTF-8')] = $cleanUb;
                }
            }
        } catch (Exception $e) {}
    }

    $defaultBanks = [
        'nubank' => 'Nubank', 'nu bank' => 'Nubank', 'nu' => 'Nubank',
        'itau' => 'Itaú', 'itaú' => 'Itaú', 'bradesco' => 'Bradesco', 
        'santander' => 'Santander', 'inter' => 'Banco Inter', 'banco inter' => 'Banco Inter',
        'c6' => 'C6 Bank', 'c6 bank' => 'C6 Bank', 'caixa' => 'Caixa', 
        'bb' => 'Banco do Brasil', 'banco do brasil' => 'Banco do Brasil', 
        'sicoob' => 'Sicoob', 'secob' => 'Sicoob', 
        'sicredi' => 'Sicredi', 'secredi' => 'Sicredi', 'sicrede' => 'Sicredi', 'si credi' => 'Sicredi', 'se credi' => 'Sicredi', 'secret' => 'Sicredi',
        'pagbank' => 'PagBank', 'pag bank' => 'PagBank', 'picpay' => 'PicPay', 'pic pay' => 'PicPay', 
        'mercado pago' => 'Mercado Pago', 'mercado livre' => 'Mercado Pago',
        'btg' => 'BTG Pactual', 'btg pactual' => 'BTG Pactual', 'will' => 'Will Bank', 'will bank' => 'Will Bank', 'neon' => 'Neon',
        'caju' => 'Caju', 'carteira' => 'Outro / Carteira', 'outro' => 'Outro / Carteira'
    ];

    $allBanks = array_merge($defaultBanks, $userBankMap);

    foreach ($allBanks as $key => $val) {
        if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $lower)) {
            return $val;
        }
    }
    return null;
}

// --- HELPER DE EXTRAÇÃO DE FORMA DE PAGAMENTO ---
function parsePaymentMethod($text) {
    if (preg_match('/\b(pix)\b/i', $text)) return 'PIX';
    if (preg_match('/\b(débito|debito)\b/i', $text)) return 'Débito';
    if (preg_match('/\b(crédito|credito|cartão|cartao)\b/i', $text)) return 'Crédito';
    if (preg_match('/\b(dinheiro|especie|espécie)\b/i', $text)) return 'Dinheiro';
    if (preg_match('/\b(transferência|transferencia|ted|doc)\b/i', $text)) return 'Transferência';
    if (preg_match('/\b(boleto)\b/i', $text)) return 'Boleto';
    return null;
}

// --- HELPER DE IDENTIFICAÇÃO DE TIPO ---
function parseType($text) {
    if (preg_match('/(receb|ganh|salari|salário|pix receb|entrada|receita|deposit|depósito|venda|caiu|renda|reembolso|lucro)/i', $text)) {
        return 'receita';
    }
    if (preg_match('/(gast|pagu|compr|saiu|débito|debito|cobrad|custou|despesa)/i', $text)) {
        return 'despesa';
    }
    return null;
}

// --- HELPER DE EXTRAÇÃO DE DESCRIÇÃO INTELIGENTE ---
function parseDescription($text, $type) {
    $lower = mb_strtolower($text, 'UTF-8');

    // 1. Captura direta da estrutura natural: "gastei X [em/no/na/de/com] [DESCRIÇÃO]"
    if (preg_match('/(?:gastei|paguei|comprei|saiu|custou|recebi|ganhei)\s+(?:r\$\s*)?\d+(?:[\.,]\d+)?\s*(?:reais|real|mil|k)?\s+(?:no|na|do|da|de|em|com|para)\s+([a-zà-ú0-9\s]{2,35})/i', $text, $mStruct)) {
        $extracted = $mStruct[1];
        $extracted = preg_replace('/\b(nubank|nu bank|itau|itaú|bradesco|santander|inter|c6|caixa|bb|banco do brasil|sicoob|secob|sicredi|secredi|sicrede|si credi|se credi|pagbank|picpay|mercado pago|pix|débito|debito|crédito|credito|dinheiro|boleto)\b/i', ' ', $extracted);
        $extracted = trim(preg_replace('/\s+/', ' ', $extracted));
        if (mb_strlen($extracted, 'UTF-8') >= 2) {
            return ucfirst($extracted);
        }
    }

    // 2. Dicionário de Palavras-Chave Conhecidas
    $expenseKeywords = [
        'mercado' => 'Mercado', 'supermercado' => 'Mercado', 'feira' => 'Feira', 'açougue' => 'Açougue', 'padaria' => 'Padaria',
        'ifood' => 'iFood', 'restaurante' => 'Restaurante', 'almoço' => 'Almoço', 'almoco' => 'Almoço', 'jantar' => 'Jantar',
        'lanche' => 'Lanche', 'pizza' => 'Pizza', 'comida' => 'Alimentação', 'mcdonald' => 'McDonalds', 'burger' => 'Burger King',
        'gasolina' => 'Gasolina', 'combustivel' => 'Gasolina', 'combustível' => 'Gasolina', 'uber' => 'Uber', '99' => 'Uber / 99', 'taxi' => 'Táxi', 'táxi' => 'Táxi',
        'cinema' => 'Cinema', 'movie' => 'Cinema', 'netflix' => 'Netflix', 'spotify' => 'Spotify', 'jogos' => 'Jogos', 'bar' => 'Bar / Lazer',
        'farmacia' => 'Farmácia', 'farmácia' => 'Farmácia', 'remedio' => 'Farmácia', 'remédio' => 'Farmácia', 'medico' => 'Consulta Médica', 'médico' => 'Consulta Médica',
        'aluguel' => 'Aluguel', 'condominio' => 'Condomínio', 'condomínio' => 'Condomínio', 'luz' => 'Conta de Luz', 'energia' => 'Conta de Luz', 'água' => 'Conta de Água', 'agua' => 'Conta de Água', 'internet' => 'Internet', 'telefone' => 'Telefone',
        'escola' => 'Escola', 'faculdade' => 'Faculdade', 'curso' => 'Curso', 'livro' => 'Livro / Estudo'
    ];

    $incomeKeywords = [
        'salario' => 'Salário', 'salário' => 'Salário', 'holerite' => 'Salário', 'férias' => 'Férias', 'ferias' => 'Férias', '13' => '13º Salário',
        'venda' => 'Venda', 'site' => 'Venda no Site', 'cliente' => 'Pagamento de Cliente', 'freelance' => 'Freelance', 'servico' => 'Serviço Prestado', 'serviço' => 'Serviço Prestado',
        'comissao' => 'Comissão', 'comissão' => 'Comissão', 'bonus' => 'Bônus', 'bônus' => 'Bônus', 'plr' => 'PLR', 'rendimento' => 'Rendimento', 'investimento' => 'Investimento'
    ];

    $dict = ($type === 'receita') ? array_merge($incomeKeywords, $expenseKeywords) : array_merge($expenseKeywords, $incomeKeywords);
    foreach ($dict as $key => $label) {
        if (preg_match('/\b' . preg_quote($key, '/') . '\b/i', $lower)) {
            return $label;
        }
    }

    if (preg_match('/(?:pix|receb\w+|veio|pagamento)\s+(?:do|da|de)\s+([a-zà-ú]{3,15})/i', $lower, $mPerson)) {
        return 'Pix de ' . ucfirst($mPerson[1]);
    }

    // 3. Limpeza Geral
    $clean = preg_replace('/^(patrick[,\s]*|oi\s+patrick[,\s]*|olá\s+patrick[,\s]*)/i', '', $text);
    $clean = preg_replace('/(r\$\s*\d+[\.,]?\d*|\d+[\.,]?\d*\s*(mil|k)?|\b(reais|real|gastei|gastamos|paguei|pagamos|comprei|compramos|recebi|recebemos|ganhei|ganhamos|depositei|foi|caiu|entrou|tava|estava|ficou)\b)/i', ' ', $clean);
    $clean = preg_replace('/\b(no|na|do|da|de|em|para|com|pelo|pela|por)\b/i', ' ', $clean);
    $clean = preg_replace('/\b(nubank|nu bank|itau|itaú|bradesco|santander|inter|c6|caixa|bb|banco do brasil|sicoob|secob|sicredi|secredi|sicrede|si credi|se credi|pagbank|picpay|mercado pago|pix|débito|debito|crédito|credito|dinheiro|boleto)\b/i', ' ', $clean);
    $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $clean);
    $clean = trim(preg_replace('/\s+/', ' ', $clean));

    if (empty($clean) || mb_strlen($clean, 'UTF-8') < 2) {
        return null;
    }
    return ucfirst($clean);
}

// --- HELPER DE CATEGORIAS OFICIAIS DO PRICEXP ---
function inferCategoryStrict($description, $text, $type) {
    $combined = mb_strtolower($description . ' ' . $text, 'UTF-8');

    if ($type === 'receita') {
        if (preg_match('/(salario|salário|holerite)/i', $combined)) return 'Salário Líquido';
        if (preg_match('/(13|décimo terceiro|decimo terceiro)/i', $combined)) return '13º Salário Líquido';
        if (preg_match('/(férias|ferias)/i', $combined)) return 'Férias Líquida';
        if (preg_match('/(bônus|bonus|comissão|comissao|plr|prêmio|premio)/i', $combined)) return 'Bônus + Comissões + PLR';
        if (preg_match('/(freelance|serviço|servico|venda|site|bico|cliente)/i', $combined)) return 'Renda Extra Líquida';
        return 'Outras Receitas';
    } else {
        if (preg_match('/(mercado|supermercado|feira|açougue|padaria|aluguel|condomínio|condominio|luz|água|agua|internet|telefone|energia|móveis|moveis|faxina)/i', $combined)) return 'Casa';
        if (preg_match('/(ifood|restaurante|lanchonete|pizza|comida|almoço|almoco|jantar|mcdonald)/i', $combined)) return 'Casa';
        if (preg_match('/(farmacia|farmácia|médico|medico|consulta|hospital|remedio|remédio|dentista|exame)/i', $combined)) return 'Saúde';
        if (preg_match('/(gasolina|combustivel|combustível|uber|99|táxi|taxi|ônibus|onibus|pedagio|estacionamento|mecanico|mecânico)/i', $combined)) return 'Transporte';
        if (preg_match('/(passagem|viagem|mobilidade)/i', $combined)) return 'Locomoção';
        if (preg_match('/(netflix|spotify|cinema|jogos|lazer|show|bar|festa|presente)/i', $combined)) return 'Lazer';
        if (preg_match('/(investimento|rendimento|ação|ações|cdb|tesouro|reserva)/i', $combined)) return 'Investimentos';
        return 'Outras Despesas';
    }
}

// ------------------------------------------------------------------
// --- COMANDO DE RESUMO FINANCEIRO DO MÊS DO PATRICK ---
// ------------------------------------------------------------------
if (preg_match('/(resumo|saldo|finanças|financas|quanto gastei|quanto recebi|extrato|balanço|balanco|relatório|relatorio)/i', $lowerText)) {
    $firstDay  = date('Y-m-01');
    $lastDay   = date('Y-m-t');
    $monthYear = date('m/Y');

    $stmtRec = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'receita' AND date >= ? AND date <= ?");
    $stmtRec->execute([$workspace_id, $firstDay, $lastDay]);
    $totalRec = (float)($stmtRec->fetchColumn() ?? 0);

    $stmtDesp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'despesa' AND date >= ? AND date <= ?");
    $stmtDesp->execute([$workspace_id, $firstDay, $lastDay]);
    $totalDesp = (float)($stmtDesp->fetchColumn() ?? 0);

    $saldo = $totalRec - $totalDesp;
    $saldoEmoji = ($saldo >= 0) ? '🟢 +' : '🔴 -';

    $stmtTop = $pdo->prepare("SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'despesa' AND date >= ? AND date <= ? GROUP BY category ORDER BY total DESC LIMIT 3");
    $stmtTop->execute([$workspace_id, $firstDay, $lastDay]);
    $topCategories = $stmtTop->fetchAll();

    $topText = "";
    if ($topCategories) {
        $emojis = ['1️⃣', '2️⃣', '3️⃣'];
        foreach ($topCategories as $idx => $cat) {
            $e = $emojis[$idx] ?? '🔹';
            $topText .= "{$e} *{$cat['category']}:* R$ " . number_format($cat['total'], 2, ',', '.') . "\n";
        }
    } else {
        $topText = "_Nenhuma despesa registrada este mês._\n";
    }

    $fmtRec  = number_format($totalRec, 2, ',', '.');
    $fmtDesp = number_format($totalDesp, 2, ',', '.');
    $fmtSal  = number_format(abs($saldo), 2, ',', '.');

    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\n"
              . "📊 *RESUMO FINANCEIRO DO MÊS ({$monthYear})*\n\n"
              . "👤 *Usuário:* {$userName}\n\n"
              . "🟢 *Total de Entradas:* R$ {$fmtRec}\n"
              . "🔴 *Total de Saídas:* R$ {$fmtDesp}\n"
              . "💰 *Saldo do Mês:* {$saldoEmoji}R$ {$fmtSal}\n\n"
              . "🔥 *Maiores Gastos no Mês:*\n"
              . $topText . "\n"
              . "🚀 _Acesse o site PriceXP para o relatório completo!_";

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- BUSCA SESSÃO PENDENTE DO USUÁRIO (Últimos 15 minutos) ---
// ------------------------------------------------------------------
$stmtPending = $pdo->prepare("SELECT * FROM whatsapp_pending_sessions WHERE user_id = ? AND created_at >= NOW() - INTERVAL 15 MINUTE ORDER BY id DESC LIMIT 1");
$stmtPending->execute([$user_id]);
$pending = $stmtPending->fetch();

// Coleta tudo o que vier na mensagem atual
$newType   = parseType($lowerText);
$newAmount = parseAmount($lowerText);
$newBank   = parseBank($lowerText, $workspace_id, $pdo);
$newMethod = parsePaymentMethod($lowerText);
$newDesc   = parseDescription($rawText, $newType ?: 'despesa');

if ($pending) {
    // --- CONVERSA INCREMENTAL / CORREÇÃO ---
    $type = $newType ?: ($pending['type'] ?: 'despesa');
    
    // Só substitui o valor do rascunho se a nova mensagem trouxer explicitamente contexto financeiro
    $hasMoneyContext = preg_match('/(r\$|reais|real|\bvalor\b|na verdade|corrigindo)/i', $lowerText);
    if ((float)$pending['amount'] > 0) {
        $amount = ($newAmount > 0 && $hasMoneyContext) ? $newAmount : (float)$pending['amount'];
    } else {
        $amount = ($newAmount > 0) ? $newAmount : 0.0;
    }

    $bank_name      = $newBank ?: $pending['bank_name'];
    $payment_method = $newMethod ?: $pending['payment_method'];

    if (!empty($pending['description'])) {
        $description = (!empty($newDesc) && mb_strlen($newDesc) >= 3 && !in_array(strtolower($newDesc), ['foi', 'sim', 'nao', 'não'])) ? $newDesc : $pending['description'];
    } else {
        $description = $newDesc;
    }
    $pendingId = $pending['id'];
} else {
    // --- NOVO LANÇAMENTO ---
    $type           = $newType ?: 'despesa';
    $amount         = $newAmount;
    $bank_name      = $newBank;
    $payment_method = $newMethod;
    $description    = $newDesc;
    $pendingId      = null;
}

// --- VERIFICAÇÃO DE DADOS FALTANTES ---
$missing = [];
if ($amount <= 0) $missing[] = 'valor';
if (empty($description)) $missing[] = 'descricao';
if (empty($bank_name) && $payment_method !== 'Dinheiro') $missing[] = 'banco';

// FORMA DE PAGAMENTO É OBRIGATÓRIA APENAS PARA SAÍDAS (DESPESAS)!
if ($type === 'despesa' && empty($payment_method)) {
    $missing[] = 'forma_pagamento';
}

// Se AINDA FALTAM dados obrigatórios, atualiza a sessão e faz a PERGUNTA CONVERSACIONAL ÚNICA
if (!empty($missing)) {
    try {
        if ($pendingId) {
            $stmtUpd = $pdo->prepare("UPDATE whatsapp_pending_sessions SET type=?, amount=?, description=?, bank_name=?, payment_method=? WHERE id=?");
            $stmtUpd->execute([$type, $amount, $description, $bank_name, $payment_method, $pendingId]);
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO whatsapp_pending_sessions (user_id, phone, type, amount, description, bank_name, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$user_id, $cleanPhone, $type, $amount, $description, $bank_name, $payment_method]);
        }
    } catch (Exception $ex) {}

    $formattedAmount = ($amount > 0) ? "R$ " . number_format($amount, 2, ',', '.') : "";
    $tipoTxt = ($type === 'receita') ? 'receita 🟢' : 'despesa 🔴';

    $questions = [];
    if (in_array('valor', $missing)) {
        $questions[] = "qual foi o valor";
    }
    if (in_array('descricao', $missing)) {
        $questions[] = ($type === 'receita') ? "de onde veio essa receita" : "com o que você gastou";
    }
    if (in_array('banco', $missing) && in_array('forma_pagamento', $missing)) {
        $questions[] = "qual conta/banco usou e se foi no PIX, débito ou crédito";
    } elseif (in_array('banco', $missing)) {
        $questions[] = ($type === 'receita') ? "em qual banco/conta caiu" : "qual banco ou conta usou";
    } elseif (in_array('forma_pagamento', $missing)) {
        $questions[] = "foi no PIX, débito ou crédito";
    }

    $askText = implode(", ", $questions);
    $introText = $formattedAmount ? "Anotado {$formattedAmount} de {$tipoTxt}! 💰\n\nSó me fala: " : "Só me conta: ";

    $replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\n" . $introText . ucfirst($askText) . "?";
    
    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- REGISTRO DO LANÇAMENTO COMPLETO NO PRICEXP ---
// ------------------------------------------------------------------
$category = inferCategoryStrict($description, $rawText, $type);
$date = date('Y-m-d');

// Garania de Banco LIMPO (sem sufixos como (PIX) ou (Débito) que poluem o filtro do app!)
$finalBank = $bank_name ?: 'Geral';

// Tenta vincular ao Cartão de Crédito do Usuário no banco se a forma for crédito
$card_id = null;
if ($type === 'despesa' && $payment_method === 'Crédito') {
    try {
        $stmtCard = $pdo->prepare("SELECT id FROM credit_cards WHERE user_id = ? AND LOWER(name) LIKE ? LIMIT 1");
        $stmtCard->execute([$workspace_id, '%' . strtolower($finalBank) . '%']);
        $card_id = $stmtCard->fetchColumn() ?: null;
    } catch (Exception $e) {}
}

try {
    $stmtIns = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, bank_name, card_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtIns->execute([$workspace_id, $user_id, $type, $category, $description, $amount, $date, $finalBank, $card_id]);
    $insertedId = $pdo->lastInsertId();

    if ($pendingId) {
        $stmtDel = $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE id = ?");
        $stmtDel->execute([$pendingId]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'reply' => "🟢 *Patrick — Assistente PriceXP*\n\n⚠️ Tive um contratempo ao gravar no banco: " . $e->getMessage()
    ]);
    exit;
}

logUserActivity($pdo, $user_id, 'WHATSAPP_LANCAMENTO', "Lançamento via WhatsApp #{$insertedId}: {$type} - {$description} (R$ {$amount})", $amount, ['bank' => $finalBank, 'phone' => $cleanPhone]);

$formattedAmount = number_format($amount, 2, ',', '.');
$emojiType = ($type === 'receita') ? '🟢 Receita' : '🔴 Despesa';

$replyMsg = "🟢 *Patrick — Assistente PriceXP*\n\n"
          . "✅ *Lançamento Registrado com Sucesso!*\n\n"
          . "👤 *Usuário:* {$userName}\n"
          . "📊 *Tipo:* {$emojiType}\n"
          . "💰 *Valor:* R$ {$formattedAmount}\n"
          . "📝 *Descrição:* {$description}\n"
          . "📁 *Categoria:* {$category}\n"
          . "🏦 *Banco:* {$finalBank}\n"
          . ($type === 'despesa' ? "💳 *Forma:* " . ($payment_method ?: 'Outra') . "\n" : "")
          . "📅 *Data:* " . date('d/m/Y') . "\n\n"
          . "🚀 _Já disponível no seu painel PriceXP!_";

echo json_encode([
    'success' => true,
    'id' => $insertedId,
    'user' => $userName,
    'reply' => $replyMsg
]);
exit;
