<?php
error_reporting(0);
ini_set('display_errors', '0');
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
$remoteJid  = $senderPhone;

if (empty($senderPhone) || empty($rawText)) {
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
    $replyMsg = "*PriceXP — Assistente Financeiro*\n\nOlá! Para utilizar o assistente por WhatsApp, você precisa cadastrar o seu número de telefone em *Minha Conta* no painel PriceXP.";
    echo json_encode(['success' => false, 'reply' => $replyMsg]);
    exit;
}

$user_id      = (int)$user['id'];
$workspace_id = getWorkspaceUserId($pdo, $user_id);
$userName     = !empty($user['first_name']) ? $user['first_name'] : 'Usuário';
$lowerText    = mb_strtolower($rawText, 'UTF-8');

// --- HELPER DE PARSER INTELIGENTE DE VALORES (COM STRIP DE CNPJ E HORAS) ---
function parseAmount($text) {
    $cleanText = preg_replace('/\b\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2}\b/', ' ', $text);
    $cleanText = preg_replace('/\b\d{1,2}:\d{2}(?::\d{2})?\b/', ' ', $cleanText);
    $cleanText = preg_replace('/\b\d{1,2}\/\d{1,2}\/\d{2,4}\b/', ' ', $cleanText);
    $cleanText = preg_replace('/\b(c6\s*bank|c6)\b/i', ' ', $cleanText);

    // 1. R$ 50,00 ou R$50
    if (preg_match('/r\$\s*(\d+(?:[\.,]\d{1,2})?)/i', $cleanText, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    // 2. total 50, valor 50, pago 50, foi 50
    if (preg_match('/(?:total|valor|pago|foi|deu|caiu)\s*(?:r\$\s*)?(\d+(?:[\.,]\d{1,2})?)/i', $cleanText, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    // 3. 5k ou 5 mil
    if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(mil|k)\b/i', $cleanText, $m)) {
        $val = (float)str_replace(',', '.', $m[1]);
        return $val * 1000;
    }

    // 4. 50 reais ou 50 real
    if (preg_match('/(\d+(?:[\.,]\d{1,2})?)\s*(?:reais|real)/i', $cleanText, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    // 5. Número puro (ex: "5", "100", "50,90", "45.00")
    if (preg_match('/^\s*(\d+(?:[\.,]\d{1,2})?)\s*$/', trim($cleanText), $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    // 6. Número isolado em qualquer frase (ex: "50 no inter", "gastou 100")
    if (preg_match('/(?:\b|^)(\d+(?:[\.,]\d{1,2})?)(?:\b|$)/', $cleanText, $m)) {
        $num = (float)str_replace(',', '.', $m[1]);
        if ($num > 0) return $num;
    }

    // 7. Números por extenso em português
    $wordMap = [
        'um' => 1, 'uma' => 1, 'dois' => 2, 'duas' => 2, 'três' => 3, 'tres' => 3,
        'quatro' => 4, 'cinco' => 5, 'seis' => 6, 'sete' => 7, 'oito' => 8, 'nove' => 9,
        'dez' => 10, 'onze' => 11, 'doze' => 12, 'treze' => 13, 'quatorze' => 14, 'quinze' => 15,
        'dezesseis' => 16, 'dezessete' => 17, 'dezoito' => 18, 'dezenove' => 19, 'vinte' => 20,
        'trinta' => 30, 'quarenta' => 40, 'cinquenta' => 50, 'sessenta' => 60, 'setenta' => 70,
        'oitenta' => 80, 'noventa' => 90, 'cem' => 100, 'cento' => 100, 'duzentos' => 200, 'quinhentos' => 500
    ];

    $lowerWord = mb_strtolower(trim($text), 'UTF-8');
    if (isset($wordMap[$lowerWord])) {
        return (float)$wordMap[$lowerWord];
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
    if (preg_match('/(via\s*-\s*cliente|cnpj|cupom fiscal|autorizada|autenticação|supermercado|loja|compra pix)/i', $text)) {
        return 'despesa';
    }
    if (preg_match('/(receb|ganh|salari|salário|pix receb|entrada|receita|deposit|depósito|venda no site|caiu|renda|reembolso|lucro)/i', $text)) {
        return 'receita';
    }
    if (preg_match('/(gast|pagu|compr|saiu|débito|debito|cobrad|custou|despesa|venda|compra)/i', $text)) {
        return 'despesa';
    }
    return null;
}

// --- HELPER DE EXTRAÇÃO DE DESCRIÇÃO INTELIGENTE ---
function parseDescription($text, $type) {
    $lower = mb_strtolower($text, 'UTF-8');

    // 1. Dicionário de palavras-chave explícitas (ex: comida, mercado, gasolina, aluguel)
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

    // 2. Extrai de frases "comprei X" ou "gastei no X"
    if (preg_match('/(?:comprei|gastei|paguei|custou)\s+([a-zà-ú0-9\s]{2,30})/i', $text, $mComp)) {
        $extracted = preg_replace('/\b(banco|bancos|conta|contas|cartão|cartao|cartões|cartoes|nubank|nu bank|itau|itaú|bradesco|santander|inter|c6|c6bank|c6 bank|caixa|bb|banco do brasil|sicoob|secob|sicredi|secredi|sicrede|si credi|se credi|pagbank|picpay|mercado pago|pix|débito|debito|crédito|credito|dinheiro|boleto|reais|real)\b/i', ' ', $mComp[1]);
        $extracted = trim(preg_replace('/\s+/', ' ', $extracted));
        if (mb_strlen($extracted, 'UTF-8') >= 3) {
            return ucfirst($extracted);
        }
    }

    if (preg_match('/(?:pix|receb\w+|veio|pagamento)\s+(?:do|da|de)\s+([a-zà-ú]{3,15})/i', $lower, $mPerson)) {
        return 'Pix de ' . ucfirst($mPerson[1]);
    }

    return null;
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
        if (preg_match('/(comida|mercado|supermercado|feira|açougue|padaria|aluguel|condomínio|condominio|luz|água|agua|internet|telefone|energia|móveis|moveis|faxina)/i', $combined)) return 'Casa';
        if (preg_match('/(ifood|restaurante|lanchonete|pizza|almoço|almoco|jantar|mcdonald)/i', $combined)) return 'Casa';
        if (preg_match('/(farmacia|farmácia|médico|medico|consulta|hospital|remedio|remédio|dentista|exame)/i', $combined)) return 'Saúde';
        if (preg_match('/(gasolina|combustivel|combustível|uber|99|táxi|taxi|ônibus|onibus|pedagio|estacionamento|mecanico|mecânico)/i', $combined)) return 'Transporte';
        if (preg_match('/(passagem|viagem|mobilidade)/i', $combined)) return 'Locomoção';
        if (preg_match('/(netflix|spotify|cinema|jogos|lazer|show|bar|festa|presente)/i', $combined)) return 'Lazer';
        if (preg_match('/(investimento|rendimento|ação|ações|cdb|tesouro|reserva)/i', $combined)) return 'Investimentos';
        return 'Outras Despesas';
    }
}

// ------------------------------------------------------------------
// --- COMANDO DE RELATÓRIO FINANCEIRO CORPORATIVO (SEMANAL, MENSAL, ANUAL) ---
// ------------------------------------------------------------------
if (preg_match('/(resumo|saldo|finanças|financas|quanto gastei|quanto recebi|extrato|balanço|balanco|relatório|relatorio|semanal|semana|mensal|mês|mes|anual|ano)/i', $lowerText)) {
    
    $periodTitle = "MENSAL";
    $periodLabel = "Mês (" . date('m/Y') . ")";

    if (preg_match('/(semanal|semana)/i', $lowerText)) {
        $periodTitle = "SEMANAL";
        $firstDay = date('Y-m-d', strtotime('monday this week'));
        $lastDay  = date('Y-m-d', strtotime('sunday this week'));
        $periodLabel = "Semana (" . date('d/m', strtotime($firstDay)) . " a " . date('d/m', strtotime($lastDay)) . ")";
    } elseif (preg_match('/(anual|ano)/i', $lowerText)) {
        $periodTitle = "ANUAL";
        $firstDay = date('Y-01-01');
        $lastDay  = date('Y-12-31');
        $periodLabel = "Ano (" . date('Y') . ")";
    } else {
        $periodTitle = "MENSAL";
        $firstDay = date('Y-m-01');
        $lastDay  = date('Y-m-t');
        $periodLabel = "Mês (" . date('m/Y') . ")";
    }

    $stmtRec = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'receita' AND date >= ? AND date <= ?");
    $stmtRec->execute([$workspace_id, $firstDay, $lastDay]);
    $totalRec = (float)($stmtRec->fetchColumn() ?? 0);

    $stmtDesp = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE user_id = ? AND type = 'despesa' AND date >= ? AND date <= ?");
    $stmtDesp->execute([$workspace_id, $firstDay, $lastDay]);
    $totalDesp = (float)($stmtDesp->fetchColumn() ?? 0);

    $saldo = $totalRec - $totalDesp;
    $saldoSign = ($saldo >= 0) ? '+' : '-';

    $stmtTop = $pdo->prepare("SELECT category, SUM(amount) AS total FROM transactions WHERE user_id = ? AND type = 'despesa' AND date >= ? AND date <= ? GROUP BY category ORDER BY total DESC LIMIT 5");
    $stmtTop->execute([$workspace_id, $firstDay, $lastDay]);
    $topCategories = $stmtTop->fetchAll();

    $topText = "";
    if ($topCategories) {
        foreach ($topCategories as $idx => $cat) {
            $n = $idx + 1;
            $topText .= "{$n}. {$cat['category']}: R$ " . number_format($cat['total'], 2, ',', '.') . "\n";
        }
    } else {
        $topText = "Sem despesas registradas no período.\n";
    }

    $stmtBanks = $pdo->prepare("SELECT bank_name, SUM(amount) AS total FROM transactions WHERE user_id = ? AND date >= ? AND date <= ? AND bank_name IS NOT NULL AND bank_name != '' AND bank_name != 'Geral' GROUP BY bank_name ORDER BY total DESC LIMIT 6");
    $stmtBanks->execute([$workspace_id, $firstDay, $lastDay]);
    $topBanks = $stmtBanks->fetchAll();

    $bankText = "";
    if ($topBanks) {
        foreach ($topBanks as $b) {
            $bankText .= "• {$b['bank_name']}: R$ " . number_format($b['total'], 2, ',', '.') . "\n";
        }
    }

    $fmtRec  = number_format($totalRec, 2, ',', '.');
    $fmtDesp = number_format($totalDesp, 2, ',', '.');
    $fmtSal  = number_format(abs($saldo), 2, ',', '.');

    $replyMsg = "📊 *PriceXP — Assistente Financeiro*\n\n"
              . "*RELATÓRIO FINANCEIRO " . $periodTitle . "*\n\n"
              . "• Usuário: {$userName}\n"
              . "• Período: {$periodLabel}\n\n"
              . "🟢 Entradas: R$ {$fmtRec}\n"
              . "🔴 Saídas: R$ {$fmtDesp}\n"
              . "💰 Saldo Líquido: R$ {$saldoSign}{$fmtSal}\n\n"
              . "*Principais Categorias de Despesa:*\n"
              . $topText . "\n"
              . ($bankText ? "🏦 *Bancos Utilizados:*\n" . $bankText . "\n" : "")
              . "🚀 _Lançamentos sincronizados com o painel PriceXP._";

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- COMANDO DE EXCLUSÃO DE LANÇAMENTO VIA WHATSAPP (BOTÃO 2 OU PALAVRA) ---
// ------------------------------------------------------------------
if (preg_match('/^(excluir|deletar|apagar|cancelar|delete_last_tx|2|2️⃣)(\s+último|\s+ultimo|\s+lançamento|\s+lancamento|\s+gasto)?$/i', trim($lowerText)) || 
    preg_match('/(excluir último|apagar último|deletar último|cancelar último|apagar o último|excluir o último|deletar o último|cancelar o último|delete_last_tx)/i', $lowerText)) {
    
    $stmtLast = $pdo->prepare("SELECT id, type, description, amount, bank_name, date FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$workspace_id]);
    $lastTx = $stmtLast->fetch();

    if ($lastTx) {
        $stmtDel = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
        $stmtDel->execute([$lastTx['id'], $workspace_id]);

        logUserActivity($pdo, $user_id, 'WHATSAPP_EXCLUSAO', "Exclusão via WhatsApp #{$lastTx['id']}: {$lastTx['description']} (R$ {$lastTx['amount']})", $lastTx['amount'], ['phone' => $cleanPhone]);

        $fmtVal = number_format((float)$lastTx['amount'], 2, ',', '.');
        $tipoIcon = ($lastTx['type'] === 'receita') ? '🟢 Receita' : '🔴 Despesa';
        $fmtDate = date('d/m/Y', strtotime($lastTx['date']));

        $replyMsg = "🗑️ *PriceXP — Lançamento Excluído*\n\n"
                  . "O seu último lançamento foi removido com sucesso:\n\n"
                  . "• Tipo: {$tipoIcon}\n"
                  . "• Descrição: {$lastTx['description']}\n"
                  . "• Valor: R$ {$fmtVal}\n"
                  . "• Banco: " . ($lastTx['bank_name'] ?: 'Geral') . "\n"
                  . "• Data: {$fmtDate}\n\n"
                  . "🚀 _Seu saldo e gráficos foram atualizados no painel PriceXP._";
    } else {
        $replyMsg = "ℹ️ *PriceXP — Assistente Financeiro*\n\nNenhum lançamento recente foi encontrado para ser excluído.";
    }

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- COMANDO DE EDIÇÃO / CORREÇÃO DE LANÇAMENTO VIA WHATSAPP (BOTÃO 1 OU PALAVRA) ---
// ------------------------------------------------------------------
if (trim($lowerText) === '1' || trim($lowerText) === '1️⃣' || trim($lowerText) === 'edit_last_tx' || trim($lowerText) === 'editar' || trim($lowerText) === 'editar lançamento') {
    $stmtLast = $pdo->prepare("SELECT id, type, description, amount, bank_name FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$workspace_id]);
    $lastTx = $stmtLast->fetch();

    if ($lastTx) {
        $fmtVal = number_format((float)$lastTx['amount'], 2, ',', '.');
        $replyMsg = "✏️ *PriceXP — Editar Lançamento*\n\n"
                  . "Lançamento atual: *{$lastTx['description']}* de *R$ {$fmtVal}* (" . ($lastTx['bank_name'] ?: 'Geral') . ").\n\n"
                  . "Digite como deseja alterar:\n"
                  . "• Ex: *\"80 no Itaú\"*\n"
                  . "• Ex: *\"Farmácia 45,90 no Nubank\"*\n"
                  . "• Ex: *\"Foi 150 na gasolina\"*\n\n"
                  . "O Patrick atualizará o lançamento instantaneamente!";
    } else {
        $replyMsg = "ℹ️ *PriceXP — Assistente Financeiro*\n\nNenhum lançamento recente foi encontrado para ser alterado.";
    }

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

if (preg_match('/^(corrigir|editar|alterar|na verdade|corrigindo|mudar para|muda para)/i', trim($lowerText)) || 
    preg_match('/(na verdade foi|na verdade era|corrigindo valor|corrigir valor)/i', $lowerText)) {
    
    $stmtLast = $pdo->prepare("SELECT id, type, category, description, amount, bank_name, date FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $stmtLast->execute([$workspace_id]);
    $lastTx = $stmtLast->fetch();

    if ($lastTx) {
        $updAmount = parseAmount($lowerText);
        $updBank   = parseBank($lowerText, $workspace_id, $pdo);
        $updType   = parseType($lowerText);
        $updDesc   = parseDescription($rawText, $updType ?: $lastTx['type']);

        $finalAmount = ($updAmount > 0) ? $updAmount : (float)$lastTx['amount'];
        $finalBank   = $updBank ?: ($lastTx['bank_name'] ?: 'Geral');
        $finalType   = $updType ?: $lastTx['type'];
        $finalDesc   = (!empty($updDesc) && mb_strlen($updDesc) >= 3 && !in_array(strtolower($updDesc), ['corrigir', 'editar', 'alterar', 'na verdade', 'corrigindo'])) ? $updDesc : $lastTx['description'];
        $finalCat    = inferCategoryStrict($finalDesc, $rawText, $finalType);

        $stmtUpdTx = $pdo->prepare("UPDATE transactions SET type=?, category=?, description=?, amount=?, bank_name=? WHERE id=? AND user_id=?");
        $stmtUpdTx->execute([$finalType, $finalCat, $finalDesc, $finalAmount, $finalBank, $lastTx['id'], $workspace_id]);

        logUserActivity($pdo, $user_id, 'WHATSAPP_EDICAO', "Edição via WhatsApp #{$lastTx['id']}: {$finalDesc} (R$ {$finalAmount})", $finalAmount, ['phone' => $cleanPhone]);

        $fmtVal = number_format($finalAmount, 2, ',', '.');
        $tipoIcon = ($finalType === 'receita') ? '🟢 Receita' : '🔴 Despesa';
        $fmtDate = date('d/m/Y', strtotime($lastTx['date']));

        $replyMsg = "✏️ *PriceXP — Lançamento Atualizado*\n\n"
                  . "O seu último lançamento foi alterado com sucesso:\n\n"
                  . "• Tipo: {$tipoIcon}\n"
                  . "• Descrição: {$finalDesc}\n"
                  . "• Novo Valor: R$ {$fmtVal}\n"
                  . "• Novo Banco: {$finalBank}\n"
                  . "• Categoria: {$finalCat}\n"
                  . "• Data: {$fmtDate}\n\n"
                  . "🚀 _Seu saldo e relatórios foram atualizados automaticamente._";
    } else {
        $replyMsg = "ℹ️ *PriceXP — Assistente Financeiro*\n\nNenhum lançamento recente foi encontrado para ser alterado.";
    }

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- BUSCA SESSÃO PENDENTE DO USUÁRIO ---
// ------------------------------------------------------------------
$stmtPending = $pdo->prepare("SELECT * FROM whatsapp_pending_sessions WHERE user_id = ? AND created_at >= NOW() - INTERVAL 15 MINUTE ORDER BY id DESC LIMIT 1");
$stmtPending->execute([$user_id]);
$pending = $stmtPending->fetch();

$newType   = parseType($lowerText);
$newAmount = parseAmount($lowerText);
$newBank   = parseBank($lowerText, $workspace_id, $pdo);
$newMethod = parsePaymentMethod($lowerText);
$newDesc   = parseDescription($rawText, $newType ?: 'despesa');

if ($pending) {
    $type = $newType ?: ($pending['type'] ?: 'despesa');
    
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
    $type           = $newType ?: 'despesa';
    $amount         = $newAmount;
    $bank_name      = $newBank;
    $payment_method = $newMethod;
    $description    = $newDesc;
    $pendingId      = null;
}

$missing = [];
if ($amount <= 0) $missing[] = 'valor';
if (empty($description)) $missing[] = 'descricao';
if (empty($bank_name) && $payment_method !== 'Dinheiro') $missing[] = 'banco';

if ($type === 'despesa' && empty($payment_method)) {
    $missing[] = 'forma_pagamento';
}

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
    $tipoLabel = ($type === 'receita') ? 'Receita 🟢' : 'Despesa 🔴';

    $questions = [];
    if (in_array('valor', $missing)) {
        $questions[] = "qual foi o valor";
    }
    if (in_array('descricao', $missing)) {
        $questions[] = ($type === 'receita') ? "de onde veio essa receita" : "com o que você gastou";
    }
    if (in_array('banco', $missing) && in_array('forma_pagamento', $missing)) {
        $questions[] = "qual conta/banco utilizou e a forma de pagamento (PIX, débito ou crédito)";
    } elseif (in_array('banco', $missing)) {
        $questions[] = ($type === 'receita') ? "em qual banco/conta caiu" : "qual banco ou conta utilizou";
    } elseif (in_array('forma_pagamento', $missing)) {
        $questions[] = "qual foi a forma de pagamento (PIX, débito ou crédito)";
    }

    $askText = implode(", ", $questions);
    $introText = $formattedAmount ? "Anotado o lançamento de {$formattedAmount} ({$tipoLabel}).\n\nPor favor, informe: " : "Por favor, informe: ";

    $replyMsg = "💼 *PriceXP — Assistente Financeiro*\n\n" . $introText . $askText . "?";
    
    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- REGISTRO DO LANÇAMENTO COMPLETO ---
// ------------------------------------------------------------------
$category = inferCategoryStrict($description, $rawText, $type);
$date = date('Y-m-d');
$finalBank = $bank_name ?: 'Geral';

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
        'reply' => "⚠️ *PriceXP — Assistente Financeiro*\n\nOcorreu um erro ao registrar a transação: " . $e->getMessage()
    ]);
    exit;
}

logUserActivity($pdo, $user_id, 'WHATSAPP_LANCAMENTO', "Lançamento via WhatsApp #{$insertedId}: {$type} - {$description} (R$ {$amount})", $amount, ['bank' => $finalBank, 'phone' => $cleanPhone]);

$formattedAmount = number_format($amount, 2, ',', '.');
$tipoLabel = ($type === 'receita') ? 'Receita 🟢' : 'Despesa 🔴';

$replyMsg = "✅ *PriceXP — Confirmação de Lançamento*\n\n"
          . "• Tipo: {$tipoLabel}\n"
          . "• Valor: R$ {$formattedAmount}\n"
          . "• Descrição: {$description}\n"
          . "• Categoria: {$category}\n"
          . "• Banco: {$finalBank}\n"
          . ($type === 'despesa' ? "• Forma de Pagamento: " . ($payment_method ?: 'Outra') . "\n" : "")
          . "• Data: " . date('d/m/Y') . "\n\n"
          . "🚀 _Lançamento registrado com sucesso no seu painel PriceXP._\n"
          . "💡 _Dica: Digite *\"excluir\"* para apagar ou *\"na verdade foi 80 no Itaú\"* para corrigir._";

echo json_encode([
    'success' => true,
    'id' => $insertedId,
    'user' => $userName,
    'remoteJid' => $remoteJid,
    'reply' => $replyMsg
]);
exit;
