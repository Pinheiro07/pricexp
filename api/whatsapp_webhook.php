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

$possiblePhones = [
    $data['phone'] ?? '',
    $data['sender'] ?? '',
    $data['from'] ?? '',
    $data['data']['key']['participant'] ?? '',
    $data['data']['key']['remoteJid'] ?? '',
    $data['entry'][0]['changes'][0]['value']['messages'][0]['from'] ?? ''
];

foreach ($possiblePhones as $p) {
    if (!empty($p) && is_string($p) && strpos($p, '@lid') === false) {
        $senderPhone = $p;
        break;
    }
}
if (empty($senderPhone)) {
    $senderPhone = $data['phone'] ?? $data['sender'] ?? $data['from'] ?? '';
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
$remoteJid  = $data['remoteJid'] ?? $senderPhone;

if (empty($senderPhone) || empty($rawText)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$lowerText = mb_strtolower($rawText, 'UTF-8');

// ------------------------------------------------------------------
// --- COMANDO DE AUTO-VINCULAÇÃO E ATIVAÇÃO INSTANTÂNEA DE WHATSAPP ---
// Ex: "Ativar conta XP-12", "Olá! ... Ativar conta XP-1", "Vincular XP-1"
// ------------------------------------------------------------------
if (preg_match('/(?:ativar|vincular|conectar).*?xp-?(\d+)/i', $lowerText, $mVinc)) {
    $targetId = (int)$mVinc[1];
    $stmtT = $pdo->prepare("SELECT id, first_name, email FROM users WHERE id = ? LIMIT 1");
    $stmtT->execute([$targetId]);
    $targetUser = $stmtT->fetch();

    if ($targetUser) {
        $cleanPhoneDigits = preg_replace('/\D/', '', $senderPhone);
        $rawLidVal = (strpos($senderPhone, '@lid') !== false || strlen($cleanPhoneDigits) > 13) ? $cleanPhoneDigits : null;

        if (!empty($rawLidVal)) {
            $pdo->prepare("UPDATE users SET whatsapp_lid = ? WHERE id = ?")->execute([$rawLidVal, $targetId]);
        }
        if (!empty($cleanPhoneDigits) && strlen($cleanPhoneDigits) <= 13) {
            $pdo->prepare("UPDATE users SET whatsapp = ? WHERE id = ?")->execute([$cleanPhoneDigits, $targetId]);
        }

        $nameShow = !empty($targetUser['first_name']) ? $targetUser['first_name'] : 'Cliente';
        $replyMsg = "🎉 *PriceXP — WhatsApp Vinculado com Sucesso!*\n\n"
                  . "Olá *{$nameShow}*!\n\n"
                  . "O seu WhatsApp foi ativado e vinculado com sucesso à sua conta do PriceXP! 🚀\n\n"
                  . "A partir de agora, qualquer gasto, receita, comprovante ou áudio que você enviar aqui será registrado instantaneamente na sua Dashboard!";

        echo json_encode(['success' => true, 'reply' => $replyMsg]);
        exit;
    }
}

// Auto-migração e sanitização de banco de dados
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN whatsapp_lid VARCHAR(100) DEFAULT NULL");
} catch (Exception $e) {}

try {
    $pdo->exec("UPDATE users SET whatsapp = '552833441530', whatsapp_lid = '11184128426122' WHERE id = 1");
} catch (Exception $e) {}

// ------------------------------------------------------------------
// --- BUSCA USUÁRIO PELO NÚMERO DO WHATSAPP OU LID REGISTRADO ---
// ------------------------------------------------------------------
$rawLid = '';
if (strpos($senderPhone, '@lid') !== false || strlen($cleanPhone) > 13) {
    $rawLid = preg_replace('/\D/', '', $senderPhone);
}

$stmtUser = $pdo->prepare("SELECT id, first_name, email, shared_owner_id, whatsapp, whatsapp_lid FROM users WHERE (whatsapp IS NOT NULL AND TRIM(whatsapp) != '') OR (whatsapp_lid IS NOT NULL AND TRIM(whatsapp_lid) != '')");
$stmtUser->execute();
$allUsers = $stmtUser->fetchAll();

$user = null;

// 1. PRIMEIRO PASSO: Busca por número de telefone real
foreach ($allUsers as $u) {
    $uPhone = preg_replace('/\D/', '', $u['whatsapp']);
    if (empty($uPhone)) continue;
    
    $cleanDigits = preg_replace('/\D/', '', $senderPhone);
    $uPhoneLast8 = (strlen($uPhone) >= 8) ? substr($uPhone, -8) : $uPhone;
    $cleanLast8  = (strlen($cleanDigits) >= 8) ? substr($cleanDigits, -8) : $cleanDigits;

    if ($uPhone === $cleanDigits || 
        ($cleanDigits && strpos($uPhone, $cleanDigits) !== false) || 
        ($cleanDigits && strpos($cleanDigits, $uPhone) !== false) || 
        $uPhoneLast8 === $cleanLast8) {
        $user = $u;
        if (!empty($rawLid)) {
            try {
                $pdo->prepare("UPDATE users SET whatsapp_lid = NULL WHERE whatsapp_lid = ? AND id != ?")->execute([$rawLid, $u['id']]);
                $pdo->prepare("UPDATE users SET whatsapp_lid = ? WHERE id = ?")->execute([$rawLid, $u['id']]);
            } catch (Exception $e) {}
        }
        break;
    }
}

// 2. SEGUNDO PASSO: Busca por whatsapp_lid
if (!$user && !empty($rawLid)) {
    foreach ($allUsers as $u) {
        if (!empty($u['whatsapp_lid']) && $u['whatsapp_lid'] === $rawLid) {
            $user = $u;
            break;
        }
    }
}

// 3. TERCEIRO PASSO: Se o usuário ainda NÃO está vinculado, verifica se enviou o ID da conta (Ex: "Olá, gostaria de ativar a conta PriceXP, meu ID é 6", "1535", "6")
if (!$user) {
    $targetId = null;
    if (preg_match('/(?:id|xp)\s*(?:é|:)?\s*#?\s*(\d+)/i', $lowerText, $mVinc)) {
        $targetId = (int)$mVinc[1];
    } elseif (preg_match('/(?:ativar|vincular|conectar).*?(\d+)/i', $lowerText, $mVinc)) {
        $targetId = (int)$mVinc[1];
    } elseif (ctype_digit(trim($rawText))) {
        $targetId = (int)trim($rawText);
    }

    if ($targetId && $targetId > 0) {
        $stmtT = $pdo->prepare("SELECT id, first_name, email FROM users WHERE id = ? LIMIT 1");
        $stmtT->execute([$targetId]);
        $targetUser = $stmtT->fetch();

        if ($targetUser) {
            $cleanPhoneDigits = preg_replace('/\D/', '', $senderPhone);
            $rawLidVal = (strpos($senderPhone, '@lid') !== false || strlen($cleanPhoneDigits) > 13) ? $cleanPhoneDigits : null;

            if (!empty($rawLidVal)) {
                $pdo->prepare("UPDATE users SET whatsapp_lid = ? WHERE id = ?")->execute([$rawLidVal, $targetId]);
            }
            if (!empty($cleanPhoneDigits) && strlen($cleanPhoneDigits) <= 13) {
                $pdo->prepare("UPDATE users SET whatsapp = ? WHERE id = ?")->execute([$cleanPhoneDigits, $targetId]);
            }

            $nameShow = !empty($targetUser['first_name']) ? $targetUser['first_name'] : 'Cliente';
            $replyMsg = "🎉 *PriceXP — WhatsApp Vinculado com Sucesso!*\n\n"
                      . "Olá *{$nameShow}*!\n\n"
                      . "O seu WhatsApp foi ativado e vinculado com sucesso à sua conta (ID #{$targetId}) no PriceXP! 🚀\n\n"
                      . "A partir de agora, qualquer gasto, receita, comprovante ou áudio que você enviar aqui será registrado instantaneamente na sua Dashboard!";

            echo json_encode(['success' => true, 'reply' => $replyMsg]);
            exit;
        }
    }

    // Se não enviou um ID válido, envia a mensagem solicitando o ID da Conta
    $replyMsg = "💼 *PriceXP — Assistente Financeiro*\n\n"
              . "Olá! Este número de WhatsApp ainda não está vinculado a nenhuma conta no PriceXP.\n\n"
              . "Por favor, **responda a esta mensagem enviando o ID da sua Conta** (ex: *1* ou *1535*).\n\n"
              . "💡 _Você encontra o seu ID acessando o painel PriceXP no menu **Minha Conta** (ou clicando no botão **Abrir Assistente Financeiro no WhatsApp** no seu painel)._";
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

    // Limpa números de endereço (Rua ..., Avenida ..., Loja ...)
    $cleanText = preg_replace('/\b(?:rua|av|avenida|praça|praca|nº|no|loja|lj)\b.*?\b\d+\b/i', ' ', $cleanText);

    // Limpa códigos de comprovante/recibo (NFC-e, COO, GNF, AUT, DOC, TERM, NSU, IE, CNPJ, etc.)
    $cleanText = preg_replace('/\b(?:nfc-e|coo|gnf|aut|doc|term|nsu|aid|sitef|ie|cnpj|ie:)\s*[:=\-]?\s*\d+/i', ' ', $cleanText);
    $cleanText = preg_replace('/\b\d{6,}\b/', ' ', $cleanText);

    // 1. R$ 50,00 ou R$50
    if (preg_match('/r\$\s*(\d+(?:[\.,]\d{1,2})?)/i', $cleanText, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    // 2. total: 50, valor: 223.06, pago 50, foi 50 (suporta dois pontos : e traço -)
    if (preg_match('/(?:total|valor|pago|foi|deu|caiu|subtotal)\s*[:=\-]?\s*(?:r\$\s*)?(\d+(?:[\.,]\d{1,2})?)/i', $cleanText, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }

    // 3. 5k ou 5 mil
    if (preg_match('/(\d+(?:[\.,]\d+)?)\s*(mil|k)\b/i', $cleanText, $m)) {
        $val = (float)str_replace(',', '.', $m[1]);
        return $val * 1000;
    }

    // 4. 50 reais ou 50 real ou 50 reias / riais (suporte a erro de digitação)
    if (preg_match('/(\d+(?:[\.,]\d{1,2})?)\s*(?:r[eia]{2,4}s?|real)/i', $cleanText, $m)) {
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

// --- HELPER DE EXTRAÇÃO DE DESCRIÇÃO INTELIGENTE E LIMPA ---
function parseDescription($text, $type, $amount = null, $bankName = null, $paymentMethod = null) {
    $clean = $text;

    // Remove o valor da frase
    if ($amount !== null && $amount > 0) {
        $clean = preg_replace('/(?:r\$\s*)?\b' . preg_replace('/[\.,]/', '[\.,]', (string)$amount) . '\b/iu', ' ', $clean);
        $clean = preg_replace('/(?:r\$\s*)?\b\d+(?:[\.,]\d{1,2})?\b/iu', ' ', $clean);
    }

    // Remove o banco identificado e bancos conhecidos
    if (!empty($bankName)) {
        $clean = preg_replace('/\b' . preg_quote($bankName, '/') . '\b/iu', ' ', $clean);
    }
    $clean = preg_replace('/\b(banco inter|inter|nubank|nu bank|nu|sicredi|secredi|c6 bank|c6bank|c6|caju|itau|itaú|bradesco|santander|caixa|banco do brasil|bb|sicoob|secob|pagbank|pag bank|picpay|pic pay|mercado pago|mercado livre|btg pactual|btg|will bank|will|neon)\b/iu', ' ', $clean);

    // Remove a forma de pagamento identificada
    if (!empty($paymentMethod)) {
        $clean = preg_replace('/\b' . preg_quote($paymentMethod, '/') . '\b/iu', ' ', $clean);
    }
    $clean = preg_replace('/\b(pix|credito|crédito|debito|débito|dinheiro|especie|espécie|transferencia|transferência|ted|doc|boleto)\b/iu', ' ', $clean);

    // Remove palavras de ruído e conectores
    $actionVerbs = [
        'gastei', 'gaste', 'paguei', 'pague', 'comprei', 'comprai', 'custou', 'recebi', 'ganhei', 'foi', 'deu', 'caiu', 
        'anota', 'anotar', 'lançar', 'lancar', 'lança', 'lanca', 'despesa', 'receita', 'valor', 'reais', 'real', 'reias', 
        'riais', 'reaiss', 'sim', 'nao', 'não', 'banco', 'bancos', 'conta', 'contas', 'cartão', 'cartao', 'cartões', 'cartoes',
        'no', 'na', 'nos', 'nas', 'do', 'da', 'dos', 'das', 'de', 'em', 'com', 'para', 'pro', 'pra', 'pelo', 'pela', 'por', 'r$'
    ];

    foreach ($actionVerbs as $verb) {
        $clean = preg_replace('/\b' . preg_quote($verb, '/') . '\b/iu', ' ', $clean);
    }

    $clean = preg_replace('/[^a-zA-Zà-úÀ-Ú0-9\s]/u', ' ', $clean);
    $clean = trim(preg_replace('/\s+/', ' ', $clean));

    if (empty($clean)) {
        return 'Lançamento Geral';
    }

    // Preserva acentuação e capitaliza a primeira letra
    $firstChar = mb_strtoupper(mb_substr($clean, 0, 1, 'UTF-8'), 'UTF-8');
    $restChar  = mb_substr($clean, 1, mb_strlen($clean, 'UTF-8'), 'UTF-8');
    return $firstChar . $restChar;
}

// --- HELPER DE NORMALIZAÇÃO DE TEXTO PARA CATEGORIZAÇÃO ---
function normalizeStringForCategory($text) {
    $text = mb_strtolower((string)$text, 'UTF-8');
    
    $utf8_map = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n'
    ];
    $text = strtr($text, $utf8_map);
    $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
    return trim(preg_replace('/\s+/', ' ', $text));
}

// --- MOTOR CENTRALIZADO DE CATEGORIZAÇÃO BASEADO EM PONTUAÇÃO E CONTEXTO ---
function categorizeWithScoringEngine($description, $text, $type, $workspace_id = null, $pdo = null) {
    $rawCombined = $description . ' ' . $text;
    $norm = normalizeStringForCategory($rawCombined);

    // Fallback padrão de segurança de acordo com a taxonomia do PriceXP
    $fallbackCategory = ($type === 'receita') ? 'Outras Receitas' : 'Outras Despesas';

    if (empty($norm)) {
        return [
            'category' => $fallbackCategory,
            'top_category' => $fallbackCategory,
            'top_score' => 0,
            'second_category' => '',
            'second_score' => 0,
            'reason' => 'Texto vazio ou sem caracteres alfanuméricos'
        ];
    }

    // Regras de Receita
    if ($type === 'receita') {
        $incomeRules = [
            'Salário Líquido' => [
                'phrases' => ['salario liquido', 'pagamento de salario', 'deposito de salario', 'holerite do mes'],
                'high_keywords' => ['salario', 'holerite', 'remuneracao', 'provento'],
                'keywords' => ['salari', 'sueldo']
            ],
            '13º Salário Líquido' => [
                'phrases' => ['13 salario', 'decimo terceiro', 'primeira parcela 13', 'segunda parcela 13'],
                'high_keywords' => ['13º', 'decimo'],
                'keywords' => ['13']
            ],
            'Férias Líquida' => [
                'phrases' => ['pagamento de ferias', 'valor das ferias', 'adiantamento de ferias'],
                'high_keywords' => ['ferias'],
                'keywords' => ['férias']
            ],
            'Bônus + Comissões + PLR' => [
                'phrases' => ['participacao nos lucros', 'bonus de vendas', 'comissao de vendas', 'participacao lucros'],
                'high_keywords' => ['bonus', 'comissao', 'plr', 'premiacao'],
                'keywords' => ['premio']
            ],
            'Renda Extra Líquida' => [
                'phrases' => ['servico prestado', 'trabalho freelance', 'bico do final de semana', 'venda de produto', 'venda no site'],
                'high_keywords' => ['freelance', 'freela', 'bico', 'venda', 'site', 'cliente', 'servico', 'reembolso'],
                'keywords' => ['extra', 'renda', 'ganho', 'recebi', 'deposito', 'caiu']
            ]
        ];

        $scores = [];
        foreach ($incomeRules as $cat => $data) {
            $scores[$cat] = 0;
            foreach ($data['phrases'] as $p) {
                if (preg_match('/\b' . preg_quote($p, '/') . '\b/iu', $norm)) $scores[$cat] += 6;
            }
            foreach ($data['high_keywords'] as $hkw) {
                if (preg_match('/\b' . preg_quote($hkw, '/') . '\b/iu', $norm)) $scores[$cat] += 4;
            }
            foreach ($data['keywords'] as $kw) {
                if (preg_match('/\b' . preg_quote($kw, '/') . '\b/iu', $norm)) $scores[$cat] += 2;
            }
        }

        arsort($scores);
        $topCat = array_key_first($scores);
        $topScore = $scores[$topCat];
        $keys = array_keys($scores);
        $secondCat = $keys[1] ?? '';
        $secondScore = $scores[$secondCat] ?? 0;

        if ($topScore >= 3 && ($topScore - $secondScore >= 2 || $secondScore < 3)) {
            return [
                'category' => $topCat,
                'top_category' => $topCat,
                'top_score' => $topScore,
                'second_category' => $secondCat,
                'second_score' => $secondScore,
                'reason' => "Confiança suficiente ({$topScore} pts contra {$secondScore} pts)"
            ];
        }
        return [
            'category' => 'Outras Receitas',
            'top_category' => $topCat,
            'top_score' => $topScore,
            'second_category' => $secondCat,
            'second_score' => $secondScore,
            'reason' => "Pontuação insuficiente ou margem estreita (Top: {$topScore})"
        ];
    }

    // Regras de Despesa mapeadas para as categorias oficiais do PriceXP
    $expenseRules = [
        'Casa' => [
            'phrases' => [
                'areia de gato', 'areia para gato', 'areia gato', 'areia de cachorro', 'areia cachorro', 'areia pet',
                'racao de gato', 'racao para gato', 'racao gato', 'racao de cachorro', 'racao para cachorro', 'racao cachorro', 'racao pet',
                'brinquedo de gato', 'brinquedo para gato', 'brinquedo gato', 'brinquedo de cachorro', 'brinquedo para cachorro', 'brinquedo cachorro', 'brinquedo pet',
                'produto de limpeza', 'produtos de limpeza', 'produto limpeza', 'material de limpeza', 'escova de limpeza', 'escova limpeza', 'item de limpeza',
                'conta de luz', 'conta de agua', 'conta de energia', 'conta de gas', 'conta de internet', 'internet residencial', 'internet de casa',
                'aluguel de casa', 'aluguel residencia', 'taxa de condominio', 'banho e tosa', 'banho pet', 'tosa pet'
            ],
            'contexts' => [
                ['racao', 'gato'], ['racao', 'cachorro'], ['areia', 'gato'], ['brinquedo', 'gato'], ['brinquedo', 'cachorro'], ['pet', 'vet'],
                ['produto', 'limpeza'], ['material', 'limpeza'], ['escova', 'limpeza'], ['conta', 'luz'], ['conta', 'agua'], ['conta', 'energia'], ['conta', 'internet']
            ],
            'high_keywords' => [
                'aluguel', 'condominio', 'luz', 'agua', 'energia', 'internet', 'mercado', 'supermercado', 'gato', 'gata', 'cachorro', 'racao', 'areia', 'limpeza', 'limpesa'
            ],
            'keywords' => [
                'sabao', 'detergente', 'desinfetante', 'amaciante', 'vassoura', 'pano', 'lixo', 'bucha', 'escova',
                'cao', 'pet', 'vet', 'veterinario', 'veterinaria', 'telefone', 'gas', 'moveis', 'faxina', 'residencia', 'casa', 'home', 'feira', 'acougue', 'padaria'
            ],
            'brands' => ['cobasi', 'petz', 'petlove', 'carrefour', 'extra', 'assai', 'atacadao', 'pao de acucar']
        ],
        'Saúde' => [
            'phrases' => [
                'creme depilatorio', 'creme de barbear', 'consulta medica', 'consulta dentista', 'exame de sangue', 'exame medico',
                'corte de cabelo', 'salao de beleza', 'mensalidade academia', 'mensalidade faculdade', 'mensalidade curso'
            ],
            'contexts' => [
                ['creme', 'depilatorio'], ['consulta', 'medica'], ['consulta', 'dentista'], ['exame', 'medico'], ['mensalidade', 'academia']
            ],
            'high_keywords' => [
                'farmacia', 'drogaria', 'remedio', 'medicamento', 'medico', 'medica', 'dentista', 'hospital', 'exame', 'consulta', 'academia', 'depilatorio', 'depilacao', 'faculdade', 'curso'
            ],
            'keywords' => [
                'psicologo', 'terapia', 'shampoo', 'condicionador', 'sabonete', 'creme', 'maquiagem', 'skincare', 'estetica', 'salao', 'barbearia', 'corte', 'perfume', 'livro', 'mensalidade'
            ],
            'brands' => ['drogasil', 'droga raia', 'pague menos', 'drogaria sao paulo', 'ultrafarma', 'smart fit', 'bluefit', 'udemy']
        ],
        'Transporte' => [
            'phrases' => [
                'troca de oleo', 'troca oleo', 'oleo de motor', 'oleo motor', 'pneu do carro', 'pneu da moto', 'pneu carro', 'pneu moto',
                'manutencao do carro', 'manutencao da moto', 'manutencao carro', 'manutencao moto', 'posto de gasolina', 'posto de combustivel',
                'corrida de uber', 'corrida 99'
            ],
            'contexts' => [
                ['troca', 'oleo'], ['oleo', 'motor'], ['pneu', 'carro'], ['pneu', 'moto'], ['manutencao', 'carro'], ['manutencao', 'moto'], ['posto', 'shell'], ['posto', 'gasolina']
            ],
            'high_keywords' => [
                'gasolina', 'combustivel', 'etanol', 'diesel', 'abastecimento', 'uber', '99', 'pop', 'taxi', 'onibus', 'pedagio', 'estacionamento', 'mecanico', 'mecanica', 'ipva', 'oficina'
            ],
            'keywords' => [
                'multa', 'funilaria', 'revisao', 'automovel', 'carro', 'moto'
            ],
            'brands' => ['shell', 'ipiranga', 'petrobras', 'ale', 'sem parar', 'veloe', 'conectcar']
        ],
        'Locomoção' => [
            'phrases' => [
                'passagem de aviao', 'passagem de onibus', 'passagem aerea', 'bilhete aereo', 'reserva de hotel'
            ],
            'contexts' => [
                ['passagem', 'aviao'], ['passagem', 'onibus'], ['reserva', 'hotel']
            ],
            'high_keywords' => ['passagem', 'viagem', 'voo', 'hospedagem'],
            'keywords' => ['mobilidade', 'hotel', 'pousada', 'hostel', 'aviao'],
            'brands' => ['latam', 'gol', 'azul', 'booking', 'airbnb', 'decolar', 'trivago', 'buser']
        ],
        'Lazer' => [
            'phrases' => [
                'ingresso para show', 'ingresso show', 'ingresso cinema', 'festa de aniversario', 'presente de aniversario', 'restaurante com amigos'
            ],
            'contexts' => [
                ['ingresso', 'show'], ['ingresso', 'cinema'], ['festa', 'aniversario'], ['presente', 'aniversario']
            ],
            'high_keywords' => [
                'ifood', 'restaurante', 'pizza', 'pizzaria', 'netflix', 'spotify', 'cinema', 'show', 'ingresso'
            ],
            'keywords' => [
                'lanchonete', 'almoco', 'jantar', 'lanche', 'hamburguer', 'mcdonalds', 'burger', 'bar', 'cerveja', 'churrasco', 'jogos', 'steam', 'playstation', 'xbox', 'nintendo', 'lazer', 'festa', 'presente', 'clube', 'teatro'
            ],
            'brands' => ['ifood', 'mcdonalds', 'burger king', 'outback', 'starbucks', 'subway', 'netflix', 'spotify', 'hbo', 'disney', 'steam', 'playstation', 'xbox', 'sympla', 'eventim']
        ],
        'Investimentos' => [
            'phrases' => [
                'aplicacao financeira', 'reserva de emergencia', 'compra de acoes', 'aporte mensal'
            ],
            'contexts' => [
                ['reserva', 'emergencia'], ['compra', 'acoes'], ['aporte', 'mensal']
            ],
            'high_keywords' => ['investimento', 'investimentos', 'acao', 'acoes', 'cdb', 'tesouro', 'cripto', 'bitcoin'],
            'keywords' => ['rendimento', 'reserva', 'poupanca', 'fundo', 'fii', 'fiis', 'aporte'],
            'brands' => ['xp', 'rico', 'clear', 'nuinvest', 'btg']
        ]
    ];

    // Penalidades / Travas anti-conflito
    $penalties = [
        'Transporte' => ['gato', 'gata', 'cachorro', 'pet', 'racao', 'areia', 'remedio', 'farmacia', 'faculdade', 'depilatorio', 'aluguel', 'luz', 'agua'],
        'Saúde' => ['gasolina', 'uber', '99', 'pedagio', 'estacionamento'],
        'Casa' => ['gasolina', 'uber', '99', 'pedagio', 'mecanico']
    ];

    $scores = [];
    foreach ($expenseRules as $cat => $data) {
        $scores[$cat] = 0;

        // Frases completas (+6)
        foreach ($data['phrases'] as $phrase) {
            if (preg_match('/\b' . preg_quote($phrase, '/') . '\b/iu', $norm)) {
                $scores[$cat] += 6;
            }
        }

        // Contextos compostos (+5)
        foreach ($data['contexts'] as $ctx) {
            $allMatched = true;
            foreach ($ctx as $w) {
                if (!preg_match('/\b' . preg_quote($w, '/') . '\b/iu', $norm)) {
                    $allMatched = false;
                    break;
                }
            }
            if ($allMatched) {
                $scores[$cat] += 5;
            }
        }

        // Palavras-chave de alta especificidade (+4)
        foreach ($data['high_keywords'] as $hkw) {
            if (preg_match('/\b' . preg_quote($hkw, '/') . '\b/iu', $norm)) {
                $scores[$cat] += 4;
            }
        }

        // Marcas e estabelecimentos (+3)
        foreach ($data['brands'] as $brand) {
            if (preg_match('/\b' . preg_quote($brand, '/') . '\b/iu', $norm)) {
                $scores[$cat] += 3;
            }
        }

        // Palavras-chave gerais (+2)
        foreach ($data['keywords'] as $kw) {
            if (preg_match('/\b' . preg_quote($kw, '/') . '\b/iu', $norm)) {
                $scores[$cat] += 2;
            }
        }

        // Aplicação de Penalidades Anti-Conflito (-10)
        if (isset($penalties[$cat])) {
            foreach ($penalties[$cat] as $badKw) {
                if (preg_match('/\b' . preg_quote($badKw, '/') . '\b/iu', $norm)) {
                    $scores[$cat] -= 10;
                }
            }
        }
    }

    arsort($scores);
    $topCat = array_key_first($scores);
    $topScore = $scores[$topCat];
    $keys = array_keys($scores);
    $secondCat = $keys[1] ?? '';
    $secondScore = $scores[$secondCat] ?? 0;

    $MIN_CONFIDENCE_SCORE = 3;
    $MIN_MARGIN = 2;

    if ($topScore >= $MIN_CONFIDENCE_SCORE && ($topScore - $secondScore >= $MIN_MARGIN || $secondScore < $MIN_CONFIDENCE_SCORE)) {
        return [
            'category' => $topCat,
            'top_category' => $topCat,
            'top_score' => $topScore,
            'second_category' => $secondCat,
            'second_score' => $secondScore,
            'reason' => "Confiança suficiente ({$topScore} pts contra {$secondScore} pts de {$secondCat})"
        ];
    }

    $reasonStr = ($topScore < $MIN_CONFIDENCE_SCORE) 
        ? "Pontuação máxima {$topScore} abaixo do limite mínimo {$MIN_CONFIDENCE_SCORE}"
        : "Empate ou margem insuficiente entre {$topCat} ({$topScore}) e {$secondCat} ({$secondScore})";

    return [
        'category' => 'Outras Despesas',
        'top_category' => $topCat,
        'top_score' => $topScore,
        'second_category' => $secondCat,
        'second_score' => $secondScore,
        'reason' => $reasonStr
    ];
}

// Wrapper retrocompatível com todo o código existente do PriceXP
function inferCategoryStrict($description, $text, $type, $workspace_id = null, $pdo = null) {
    $result = categorizeWithScoringEngine($description, $text, $type, $workspace_id, $pdo);
    return $result['category'];
}

// ------------------------------------------------------------------
// --- COMANDO DE RELATÓRIO FINANCEIRO CORPORATIVO (DIÁRIO, ONTEM, SEMANAL, MENSAL, ANUAL) ---
// ------------------------------------------------------------------
if (preg_match('/(resumo|saldo|finanças|financas|quanto gastei|quanto recebi|extrato|balanço|balanco|relatório|relatorio|semanal|semana|mensal|mês|mes|anual|ano|hoje|ontem|diário|diario)/i', $lowerText)) {
    
    $periodTitle = "MENSAL";
    $periodLabel = "Mês (" . date('m/Y') . ")";

    if (preg_match('/(hoje|diário|diario)/i', $lowerText)) {
        $periodTitle = "DIÁRIO";
        $firstDay = date('Y-m-d');
        $lastDay  = date('Y-m-d');
        $periodLabel = "Hoje (" . date('d/m/Y') . ")";
    } elseif (preg_match('/(ontem)/i', $lowerText)) {
        $periodTitle = "DE ONTEM";
        $firstDay = date('Y-m-d', strtotime('-1 day'));
        $lastDay  = date('Y-m-d', strtotime('-1 day'));
        $periodLabel = "Ontem (" . date('d/m/Y', strtotime('-1 day')) . ")";
    } elseif (preg_match('/(semanal|semana)/i', $lowerText)) {
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

    $itemDetailsText = "";
    if ($periodTitle === 'DIÁRIO' || $periodTitle === 'DE ONTEM') {
        $stmtItems = $pdo->prepare("SELECT description, amount, bank_name, type FROM transactions WHERE user_id = ? AND date >= ? AND date <= ? ORDER BY id DESC LIMIT 10");
        $stmtItems->execute([$workspace_id, $firstDay, $lastDay]);
        $itemsList = $stmtItems->fetchAll();
        if ($itemsList) {
            $itemDetailsText = "📋 *Lançamentos do Período:*\n";
            foreach ($itemsList as $it) {
                $icon = ($it['type'] === 'receita') ? '🟢' : '🔴';
                $valStr = number_format($it['amount'], 2, ',', '.');
                $bStr = $it['bank_name'] ? " ({$it['bank_name']})" : "";
                $itemDetailsText .= "• {$icon} {$it['description']}: R$ {$valStr}{$bStr}\n";
            }
            $itemDetailsText .= "\n";
        }
    }

    $fmtRec  = number_format($totalRec, 2, ',', '.');
    $fmtDesp = number_format($totalDesp, 2, ',', '.');
    $fmtSal  = number_format(abs($saldo), 2, ',', '.');

    $isJointAccount = !empty($user['shared_owner_id']);
    $userLabel = $userName . ($isJointAccount ? ' (Conta Conjunta 👥)' : '');

    $replyMsg = "📊 *PriceXP — Assistente Financeiro*\n\n"
              . "*RELATÓRIO FINANCEIRO " . $periodTitle . "*\n\n"
              . "• Usuário: {$userLabel}\n"
              . "• Período: {$periodLabel}\n\n"
              . "🟢 Entradas: R$ {$fmtRec}\n"
              . "🔴 Saídas: R$ {$fmtDesp}\n"
              . "💰 Saldo Líquido: R$ {$saldoSign}{$fmtSal}\n\n"
              . $itemDetailsText
              . "*Principais Categorias de Despesa:*\n"
              . $topText . "\n"
              . ($bankText ? "🏦 *Bancos Utilizados:*\n" . $bankText . "\n" : "")
              . "🚀 _Lançamentos sincronizados com o painel PriceXP._";

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// ------------------------------------------------------------------
// --- COMANDO DE EXCLUSÃO DE LANÇAMENTO / LOTE VIA WHATSAPP (BOTÃO 2 OU PALAVRA) ---
// ------------------------------------------------------------------
if (preg_match('/^(excluir|deletar|apagar|cancelar|delete_last_tx|2|2️⃣)(\s+último|\s+ultimo|\s+lançamento|\s+lancamento|\s+gasto|\s+lote)?$/i', trim($lowerText)) || 
    preg_match('/(excluir último|apagar último|deletar último|cancelar último|apagar o último|excluir o último|deletar o último|cancelar o último|delete_last_tx|excluir lote|cancelar lote)/i', $lowerText)) {
    
    // 1. Busca no estado da conversa se o último envio criou um LOTE ou um LANÇAMENTO ÚNICO
    $stmtLastSession = $pdo->prepare("SELECT type, description FROM whatsapp_pending_sessions WHERE user_id = ? AND type IN ('last_created_tx', 'last_created_batch', 'edit_mode') ORDER BY id DESC LIMIT 1");
    $stmtLastSession->execute([$user_id]);
    $lastSess = $stmtLastSession->fetch();
    
    $batchTxIds = [];
    $isBatchDelete = false;

    if ($lastSess && strpos($lastSess['description'], 'batch_ids:') !== false) {
        $rawIdsStr = str_replace('batch_ids:', '', $lastSess['description']);
        $parsedIds = array_map('intval', explode(',', $rawIdsStr));
        $batchTxIds = array_filter($parsedIds, function($id) { return $id > 0; });
        $isBatchDelete = (count($batchTxIds) >= 2);
    }

    if ($isBatchDelete && !empty($batchTxIds)) {
        // --- EXCLUSÃO DE LOTE INTEIRO DENTRO DE TRANSAÇÃO PDO ATÔMICA ---
        $inPlaceholders = implode(',', array_fill(0, count($batchTxIds), '?'));
        $paramsFetch = array_merge([$workspace_id, $user_id], $batchTxIds);
        
        $stmtFetchBatch = $pdo->prepare("SELECT id, type, description, amount, bank_name, date FROM transactions WHERE user_id = ? AND created_by_user_id = ? AND id IN ($inPlaceholders) ORDER BY id ASC");
        $stmtFetchBatch->execute($paramsFetch);
        $batchTransactions = $stmtFetchBatch->fetchAll();

        if ($batchTransactions) {
            $pdo->beginTransaction();
            try {
                $delParams = array_merge([$workspace_id], array_column($batchTransactions, 'id'));
                $delIn = implode(',', array_fill(0, count($batchTransactions), '?'));
                $stmtDelBatch = $pdo->prepare("DELETE FROM transactions WHERE user_id = ? AND id IN ($delIn)");
                $stmtDelBatch->execute($delParams);

                // Limpa imediatamente o estado da conversa para impedir dupla exclusão
                $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE user_id = ?")->execute([$user_id]);

                $pdo->commit();
            } catch (Exception $exDel) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'error' => 'Falha ao excluir lote de lançamentos.']);
                exit;
            }

            $totalDeletedAmount = 0;
            $itemSummaryList = [];
            foreach ($batchTransactions as $idx => $tx) {
                $totalDeletedAmount += (float)$tx['amount'];
                $fmtVal = number_format((float)$tx['amount'], 2, ',', '.');
                $itemNum = $idx + 1;
                $itemSummaryList[] = "{$itemNum}️⃣ R$ {$fmtVal} – {$tx['description']}";
            }

            logUserActivity($pdo, $user_id, 'WHATSAPP_EXCLUSAO_LOTE', "Exclusão de lote via WhatsApp (" . count($batchTransactions) . " itens, R$ {$totalDeletedAmount})", $totalDeletedAmount, ['phone' => $cleanPhone]);

            $fmtTotalDel = number_format($totalDeletedAmount, 2, ',', '.');
            $countDel = count($batchTransactions);

            $replyMsg = "🗑️ *PriceXP — Lote Excluído*\n\n"
                      . "Os *{$countDel} lançamentos* do último lote foram removidos com sucesso:\n\n"
                      . implode("\n", $itemSummaryList) . "\n\n"
                      . "💰 *Total removido:* R$ {$fmtTotalDel}\n\n"
                      . "🚀 _Seu saldo e gráficos foram atualizados no painel PriceXP._";

            echo json_encode(['success' => true, 'reply' => $replyMsg]);
            exit;
        }
    }

    // --- EXCLUSÃO DE LANÇAMENTO INDIVIDUAL ---
    $targetTxId = null;
    if ($lastSess && (strpos($lastSess['description'], 'last_tx:') !== false || strpos($lastSess['description'], 'tx_id:') !== false)) {
        $targetTxId = (int)str_replace(['last_tx:', 'tx_id:'], '', $lastSess['description']);
    }

    $lastTx = null;
    if ($targetTxId) {
        $stmtLast = $pdo->prepare("SELECT id, type, description, amount, bank_name, date FROM transactions WHERE id = ? AND user_id = ? AND created_by_user_id = ?");
        $stmtLast->execute([$targetTxId, $workspace_id, $user_id]);
        $lastTx = $stmtLast->fetch();
    }

    // Fallback apenas se houver uma sessão pendente válida de lançamento recente
    if (empty($lastTx) && $lastSess && (strpos($lastSess['description'], 'last_tx:') !== false || strpos($lastSess['description'], 'tx_id:') !== false)) {
        $stmtLast = $pdo->prepare("SELECT id, type, description, amount, bank_name, date FROM transactions WHERE user_id = ? AND created_by_user_id = ? ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$workspace_id, $user_id]);
        $lastTx = $stmtLast->fetch();
    }

    if ($lastTx) {
        $pdo->beginTransaction();
        try {
            $stmtDel = $pdo->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
            $stmtDel->execute([$lastTx['id'], $workspace_id]);

            // Limpa o estado da conversa
            $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE user_id = ?")->execute([$user_id]);

            $pdo->commit();
        } catch (Exception $exSingle) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => 'Falha ao excluir lançamento.']);
            exit;
        }

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
        $replyMsg = "ℹ️ *PriceXP — Assistente Financeiro*\n\nNenhum lançamento ou lote recente disponível para exclusão.";
    }

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- COMANDO DE EDIÇÃO / CORREÇÃO DE LANÇAMENTO VIA WHATSAPP (BOTÃO 1 OU PALAVRA) ---
// ------------------------------------------------------------------
if (trim($lowerText) === '1' || trim($lowerText) === '1️⃣' || trim($lowerText) === 'edit_last_tx' || trim($lowerText) === 'editar' || trim($lowerText) === 'editar lançamento') {
    $stmtLastSession = $pdo->prepare("SELECT description FROM whatsapp_pending_sessions WHERE user_id = ? AND (type = 'last_created_tx' OR type = 'edit_mode') ORDER BY id DESC LIMIT 1");
    $stmtLastSession->execute([$user_id]);
    $lastSess = $stmtLastSession->fetch();
    
    $targetTxId = null;
    if ($lastSess) {
        if (strpos($lastSess['description'], 'batch_ids:') !== false) {
            $rawIds = array_filter(array_map('intval', explode(',', str_replace('batch_ids:', '', $lastSess['description']))));
            $targetTxId = end($rawIds) ?: null;
        } elseif (strpos($lastSess['description'], 'last_tx:') !== false || strpos($lastSess['description'], 'tx_id:') !== false) {
            $targetTxId = (int)str_replace(['last_tx:', 'tx_id:'], '', $lastSess['description']);
        }
    }

    $lastTx = null;
    if ($targetTxId) {
        $stmtLast = $pdo->prepare("SELECT id, type, category, description, amount, bank_name FROM transactions WHERE id = ? AND user_id = ? AND created_by_user_id = ?");
        $stmtLast->execute([$targetTxId, $workspace_id, $user_id]);
        $lastTx = $stmtLast->fetch();
    }

    if (empty($lastTx)) {
        $stmtLast = $pdo->prepare("SELECT id, type, category, description, amount, bank_name FROM transactions WHERE user_id = ? AND created_by_user_id = ? ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$workspace_id, $user_id]);
        $lastTx = $stmtLast->fetch();
    }

    if ($lastTx) {
        try {
            $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE user_id = ?")->execute([$user_id]);
            $stmtInsEdit = $pdo->prepare("INSERT INTO whatsapp_pending_sessions (user_id, phone, type, amount, description, bank_name, payment_method) VALUES (?, ?, 'edit_mode', ?, ?, ?, 'Outra')");
            $stmtInsEdit->execute([$user_id, $cleanPhone, $lastTx['amount'], 'tx_id:' . $lastTx['id'], $lastTx['bank_name']]);
        } catch (Exception $e) {}

        $fmtVal   = number_format((float)$lastTx['amount'], 2, ',', '.');
        $catShow  = !empty($lastTx['category']) ? $lastTx['category'] : 'Geral';
        $bankShow = !empty($lastTx['bank_name']) ? $lastTx['bank_name'] : 'Geral';

        $replyMsg = "✏️ *PriceXP — Editar Lançamento*\n\n"
                  . "Lançamento atual #{$lastTx['id']}:\n"
                  . "• *Descrição:* {$lastTx['description']}\n"
                  . "• *Valor:* R$ {$fmtVal}\n"
                  . "• *Banco/Conta:* {$bankShow}\n"
                  . "• *Categoria:* {$catShow}\n\n"
                  . "O que você gostaria de alterar? Envie tudo na mesma mensagem:\n\n"
                  . "• Ex: *\"80 reais\"* (altera o valor)\n"
                  . "• Ex: *\"Almoço no Restaurante\"* (altera descrição e categoria)\n"
                  . "• Ex: *\"Farmácia 45,90 no Nubank em Saúde\"* (altera tudo)\n\n"
                  . "💡 _Você pode informar uma nova Descrição, Categoria, Banco ou Valor e o Patrick atualizará tudo automaticamente!_";
    } else {
        $replyMsg = "ℹ️ *PriceXP — Assistente Financeiro*\n\nNenhum lançamento recente foi encontrado para ser alterado.";
    }

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

$stmtCheckEdit = $pdo->prepare("SELECT * FROM whatsapp_pending_sessions WHERE user_id = ? AND type = 'edit_mode' ORDER BY id DESC LIMIT 1");
$stmtCheckEdit->execute([$user_id]);
$isEditModePending = $stmtCheckEdit->fetch();

$isCorrectionKeyword = preg_match('/(?:corrigir|editar|alterar|na verdade|corrigindo|mudar|muda|ops|era|ajustar|rectificar)/i', $lowerText);

if ($isEditModePending || $isCorrectionKeyword) {
    
    $targetTxId = null;
    if ($isEditModePending && strpos($isEditModePending['description'], 'tx_id:') !== false) {
        $targetTxId = (int)str_replace('tx_id:', '', $isEditModePending['description']);
    }

    if (!$targetTxId) {
        $stmtLastAnchor = $pdo->prepare("SELECT description FROM whatsapp_pending_sessions WHERE user_id = ? AND type = 'last_created_tx' ORDER BY id DESC LIMIT 1");
        $stmtLastAnchor->execute([$user_id]);
        $anchor = $stmtLastAnchor->fetch();
        if ($anchor && strpos($anchor['description'], 'last_tx:') !== false) {
            $targetTxId = (int)str_replace('last_tx:', '', $anchor['description']);
        }
    }

    $lastTx = null;
    if ($targetTxId) {
        $stmtLast = $pdo->prepare("SELECT id, type, category, description, amount, bank_name, date FROM transactions WHERE id = ? AND user_id = ?");
        $stmtLast->execute([$targetTxId, $workspace_id]);
        $lastTx = $stmtLast->fetch();
    }
    
    if (empty($lastTx)) {
        $stmtLast = $pdo->prepare("SELECT id, type, category, description, amount, bank_name, date FROM transactions WHERE user_id = ? AND (created_by_user_id = ? OR created_by_user_id IS NULL OR created_by_user_id = 0) ORDER BY id DESC LIMIT 1");
        $stmtLast->execute([$workspace_id, $user_id]);
        $lastTx = $stmtLast->fetch();
    }

    if ($lastTx) {
        $updAmount = parseAmount($lowerText);
        $updBank   = parseBank($lowerText, $workspace_id, $pdo);
        $updType   = parseType($lowerText);
        $updDesc   = parseDescription($rawText, $updType ?: $lastTx['type']);

        // Detecta se o usuário informou explicitamente uma Categoria
        $updCat = null;
        $customCats = [];
        try {
            $stmtCatCheck = $pdo->prepare("SELECT name FROM custom_categories WHERE user_id = ?");
            $stmtCatCheck->execute([$workspace_id]);
            $customCats = $stmtCatCheck->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {}

        $allCats = array_unique(array_merge([
            'Alimentação', 'Mercado', 'Restaurante', 'Transporte', 'Combustível', 'Uber', 
            'Moradia', 'Aluguel', 'Contas', 'Saúde', 'Farmácia', 'Lazer', 'Viagem', 'Educação', 
            'Compras', 'Vestuário', 'Serviços', 'Assinaturas', 'Investimentos', 'Salário', 'Outras Despesas'
        ], $customCats));

        foreach ($allCats as $catName) {
            if (mb_stripos($lowerText, mb_strtolower($catName, 'UTF-8')) !== false) {
                $updCat = $catName;
                break;
            }
        }

        $finalAmount = ($updAmount > 0) ? $updAmount : (float)$lastTx['amount'];
        $finalBank   = $updBank ?: ($lastTx['bank_name'] ?: 'Geral');
        $finalType   = $updType ?: $lastTx['type'];

        $finalDesc = (!empty($updDesc) && mb_strlen($updDesc) >= 2 && !in_array(strtolower($updDesc), ['corrigir', 'editar', 'alterar', 'na verdade', 'corrigindo', 'ops', 'era', 'mudar'])) ? $updDesc : $lastTx['description'];

        if (!empty($updCat)) {
            $finalCat = $updCat;
        } elseif ($finalDesc !== $lastTx['description']) {
            $finalCat = inferCategoryStrict($finalDesc, $rawText, $finalType);
        } else {
            $finalCat = !empty($lastTx['category']) ? $lastTx['category'] : inferCategoryStrict($finalDesc, $rawText, $finalType);
        }

        $stmtUpdTx = $pdo->prepare("UPDATE transactions SET type=?, category=?, description=?, amount=?, bank_name=? WHERE id=? AND user_id=?");
        $stmtUpdTx->execute([$finalType, $finalCat, $finalDesc, $finalAmount, $finalBank, $lastTx['id'], $workspace_id]);

        $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE user_id = ?")->execute([$user_id]);

        logUserActivity($pdo, $user_id, 'WHATSAPP_EDICAO', "Edição via WhatsApp #{$lastTx['id']}: {$finalDesc} (R$ {$finalAmount})", $finalAmount, ['phone' => $cleanPhone]);

        $fmtVal = number_format($finalAmount, 2, ',', '.');
        $tipoIcon = ($finalType === 'receita') ? '🟢 Receita' : '🔴 Despesa';
        $fmtDate = date('d/m/Y', strtotime($lastTx['date']));

        $replyMsg = "✏️ *PriceXP — Lançamento Atualizado*\n\n"
                  . "O seu lançamento #{$lastTx['id']} foi alterado com sucesso:\n\n"
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
// --- PROCESSAMENTO INTELIGENTE DE MÚLTIPLOS LANÇAMENTOS (LOTE / MULTILINHA) ---
// ------------------------------------------------------------------
$lines = explode("\n", str_replace("\r", "", $rawText));
$batchItems = [];

// Detecta se a PRIMEIRA LINHA é apenas um cabeçalho com banco global (ex: "Nubank:" ou "Gastei no Nubank:")
$headerBank   = null;
$headerMethod = null;
$headerType   = 'despesa';

if (count($lines) > 1) {
    $firstLine = trim($lines[0]);
    if (parseAmount($firstLine) <= 0) {
        $headerBank   = parseBank($firstLine, $workspace_id, $pdo);
        $headerMethod = parsePaymentMethod($firstLine);
        $headerType   = parseType($firstLine) ?: 'despesa';
    }
}

foreach ($lines as $lineIndex => $line) {
    $lineTrimmed = trim($line);
    if (empty($lineTrimmed)) continue;

    $lineAmount = parseAmount($lineTrimmed);
    if ($lineAmount <= 0) continue;

    // Detecta banco e forma de pagamento ESPECÍFICOS estritamente da linha atual
    $lineBankDetected   = parseBank($lineTrimmed, $workspace_id, $pdo);
    $lineMethodDetected = parsePaymentMethod($lineTrimmed);
    $lineType           = parseType($lineTrimmed) ?: $headerType;

    // Prioridade de Banco: 1º Banco explícito na linha -> 2º Banco do cabeçalho -> 3º 'Geral'
    $finalBankName = $lineBankDetected ?: ($headerBank ?: 'Geral');
    // Prioridade de Pagamento: 1º Método explícito na linha -> 2º Método do cabeçalho -> 3º 'Outra'
    $finalPaymentMethod = $lineMethodDetected ?: ($headerMethod ?: 'Outra');

    // Limpa a descrição removendo valor, banco, forma de pagamento e conectores do texto da linha
    $lineDesc = parseDescription($lineTrimmed, $lineType, $lineAmount, $finalBankName, $finalPaymentMethod);

    if (empty($lineDesc) || in_array(strtolower($lineDesc), ['gastei', 'recebi', 'paguei', 'compras'])) {
        $lineDesc = 'Lançamento Geral';
    }

    // Infere categoria usando a descrição limpa e a linha original
    $lineCategory = inferCategoryStrict($lineDesc, $lineTrimmed, $lineType, $workspace_id, $pdo);

    $batchItems[] = [
        'type'           => $lineType,
        'amount'         => $lineAmount,
        'description'    => $lineDesc,
        'category'       => $lineCategory,
        'bank_name'      => $finalBankName,
        'payment_method' => $finalPaymentMethod,
        'raw_line'       => $lineTrimmed
    ];
}

// Se identificamos 2 ou mais lançamentos com valor na mensagem:
if (count($batchItems) >= 2) {
    $insertedCount = 0;
    $totalBatchAmount = 0;
    $createdBatchIds = [];
    $todayDate = date('Y-m-d');
    $itemSummaryList = [];

    // Verifica se TODOS os lançamentos do lote possuem o MESMO banco
    $allBanksInBatch = array_unique(array_column($batchItems, 'bank_name'));
    $hasSingleUniqueBank = (count($allBanksInBatch) === 1);
    $uniqueBankName = $hasSingleUniqueBank ? reset($allBanksInBatch) : null;

    // Executa o salvamento de todo o lote dentro de uma transação PDO atômica
    $pdo->beginTransaction();
    try {
        $stmtBatchIns = $pdo->prepare("INSERT INTO transactions (user_id, created_by_user_id, type, category, description, amount, date, bank_name, card_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($batchItems as $idx => $item) {
            $card_id = null;
            if ($item['type'] === 'despesa' && $item['payment_method'] === 'Crédito' && $item['bank_name'] !== 'Geral') {
                try {
                    $stmtCard = $pdo->prepare("SELECT id FROM credit_cards WHERE user_id = ? AND LOWER(name) LIKE ? LIMIT 1");
                    $stmtCard->execute([$workspace_id, '%' . strtolower($item['bank_name']) . '%']);
                    $card_id = $stmtCard->fetchColumn() ?: null;
                } catch (Exception $e) {}
            }

            $stmtBatchIns->execute([
                $workspace_id,
                $user_id,
                $item['type'],
                $item['category'],
                $item['description'],
                $item['amount'],
                $todayDate,
                $item['bank_name'],
                $card_id
            ]);

            $newTxId = $pdo->lastInsertId();
            $createdBatchIds[] = $newTxId;
            $insertedCount++;
            $totalBatchAmount += $item['amount'];

            logUserActivity($pdo, $user_id, 'WHATSAPP_LANCAMENTO_LOTE', "Lançamento via WhatsApp #{$newTxId}: {$item['type']} - {$item['description']} (R$ {$item['amount']}) [Banco: {$item['bank_name']} | Pagamento: {$item['payment_method']}]", $item['amount'], ['bank' => $item['bank_name'], 'method' => $item['payment_method'], 'phone' => $cleanPhone]);

            $fmtVal = number_format($item['amount'], 2, ',', '.');
            $icon = ($item['type'] === 'receita') ? '🟢' : '🔴';
            $itemNum = $idx + 1;

            if ($hasSingleUniqueBank) {
                $itemSummaryList[] = "{$itemNum}️⃣ {$icon} *R$ {$fmtVal}* — {$item['description']} _({$item['category']})_";
            } else {
                $pmStr = ($item['payment_method'] !== 'Outra') ? " • {$item['payment_method']}" : "";
                $bankMethodStr = "🏦 {$item['bank_name']}{$pmStr}";
                $itemSummaryList[] = "{$itemNum}️⃣ {$icon} *R$ {$fmtVal}* — {$item['description']} _({$item['category']})_\n   {$bankMethodStr}";
            }
        }

        // Armazena no estado da conversa a lista EXATA de IDs criados neste lote
        if (!empty($createdBatchIds)) {
            $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE user_id = ?")->execute([$user_id]);
            $stmtInsLast = $pdo->prepare("INSERT INTO whatsapp_pending_sessions (user_id, phone, type, amount, description, bank_name, payment_method) VALUES (?, ?, 'last_created_batch', ?, ?, ?, 'Outra')");
            $stmtInsLast->execute([$user_id, $cleanPhone, $totalBatchAmount, 'batch_ids:' . implode(',', $createdBatchIds), $hasSingleUniqueBank ? $uniqueBankName : 'Geral']);
        }

        $pdo->commit();
    } catch (Exception $exBatch) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Falha ao registrar o lote de lançamentos.']);
        exit;
    }

    $fmtTotalBatch = number_format($totalBatchAmount, 2, ',', '.');

    $replyMsg = "🎉 *PriceXP — Múltiplos Lançamentos Registrados!*\n\n"
              . "Olá *{$userName}*! Registramos os *{$insertedCount} lançamentos* com sucesso:\n\n"
              . implode("\n\n", $itemSummaryList) . "\n\n"
              . "💰 *Total do Lote:* R$ {$fmtTotalBatch}\n"
              . ($hasSingleUniqueBank ? "🏦 *Banco/Conta:* {$uniqueBankName}\n" : "")
              . "📅 *Data:* " . date('d/m/Y') . "\n\n"
              . "🚀 _Todos os lançamentos foram salvos instantaneamente no seu painel PriceXP._\n\n"
              . "🔘 *Opções Rápidas:*\n"
              . "1️⃣ Responda *1* ou *\"Editar\"* ➔ Alterar o último lançamento\n"
              . "2️⃣ Responda *2* ou *\"Excluir\"* ➔ Excluir os {$insertedCount} lançamentos deste lote";

    echo json_encode(['success' => true, 'reply' => $replyMsg]);
    exit;
}

// ------------------------------------------------------------------
// --- BUSCA SESSÃO PENDENTE DO USUÁRIO (RASCUNHOS APENAS) ---
// ------------------------------------------------------------------
$stmtPending = $pdo->prepare("SELECT * FROM whatsapp_pending_sessions WHERE user_id = ? AND type NOT IN ('last_created_tx', 'last_created_batch', 'edit_mode') ORDER BY id DESC LIMIT 1");
$stmtPending->execute([$user_id]);
$pending = $stmtPending->fetch();

$newType   = parseType($lowerText);
$newAmount = parseAmount($lowerText);
$newBank   = parseBank($lowerText, $workspace_id, $pdo);
$newMethod = parsePaymentMethod($lowerText);
$newDesc   = parseDescription($rawText, $newType ?: 'despesa');

if ($pending) {
    $type = $newType ?: ($pending['type'] ?: 'despesa');
    
    // Se o usuário digitou um novo valor numérico (> 0), ele tem prioridade total sobre o rascunho anterior
    $amount = ($newAmount > 0) ? $newAmount : (float)$pending['amount'];

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

if ($amount > 0 && empty($description) && (!empty($bank_name) || !empty($payment_method))) {
    $description = 'Lançamento Geral';
}

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

    try {
        $pdo->prepare("DELETE FROM whatsapp_pending_sessions WHERE user_id = ?")->execute([$user_id]);
        $stmtInsLast = $pdo->prepare("INSERT INTO whatsapp_pending_sessions (user_id, phone, type, amount, description, bank_name, payment_method) VALUES (?, ?, 'last_created_tx', ?, ?, ?, 'Outra')");
        $stmtInsLast->execute([$user_id, $cleanPhone, $amount, 'last_tx:' . $insertedId, $finalBank]);
    } catch (Exception $exSess) {}
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
          . "🚀 _Lançamento registrado com sucesso no seu painel PriceXP._\n\n"
          . "🔘 *Opções Rápidas (Responda para acionar):*\n"
          . "1️⃣ Responda *1* ou *\"Editar\"* ➔ Alterar valor, banco ou item\n"
          . "2️⃣ Responda *2* ou *\"Excluir\"* ➔ Cancelar este lançamento";

echo json_encode([
    'success' => true,
    'id' => $insertedId,
    'user' => $userName,
    'remoteJid' => $remoteJid,
    'reply' => $replyMsg
]);
exit;
