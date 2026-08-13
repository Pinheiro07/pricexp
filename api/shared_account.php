<?php
require 'config.php';
$action = $_GET['action'] ?? '';

if ($action === 'check_invite') {
    header('Content-Type: application/json');
    $token = $_GET['token'] ?? '';
    if ($token) {
        $token_hash = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT ai.invite_email, u.first_name AS owner_name FROM account_invites ai JOIN users u ON ai.owner_user_id = u.id WHERE ai.token_hash = ?");
        $stmt->execute([$token_hash]);
        $row = $stmt->fetch();
        if ($row) {
            echo json_encode(['valid' => true, 'email' => $row['invite_email'], 'owner_name' => $row['owner_name']]);
            exit;
        }
    }
    echo json_encode(['valid' => false]);
    exit;
}

requireLogin();
header('Content-Type: application/json');

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];

if ($method === 'GET') {
    // 1. Verificar se o próprio usuário é membro de uma conta cujo dono é outro usuário
    $stmt = $pdo->prepare("SELECT shared_owner_id FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    
    $partner = null;
    $is_owner = true;

    if ($u && !empty($u['shared_owner_id'])) {
        // Sou membro conectado ao workspace do owner
        $is_owner = false;
        $stmtP = $pdo->prepare("SELECT id, email, first_name, last_name, profile_picture FROM users WHERE id = ?");
        $stmtP->execute([$u['shared_owner_id']]);
        $partner = $stmtP->fetch();
    } else {
        // Sou owner. Verificar se algum outro usuário me tem como shared_owner_id
        $stmtP = $pdo->prepare("SELECT id, email, first_name, last_name, profile_picture FROM users WHERE shared_owner_id = ? LIMIT 1");
        $stmtP->execute([$user_id]);
        $partner = $stmtP->fetch();
    }

    if ($partner) {
        $workspaceOwnerId = getWorkspaceUserId($pdo, $user_id);
        $partnerId        = (int)$partner['id'];

        // Buscar lançamentos criados especificamente pelo parceiro(a)
        $stmtT = $pdo->prepare("
            SELECT id, type, category, description, amount, date, bank_name 
            FROM transactions 
            WHERE user_id = ? AND created_by_user_id = ?
            ORDER BY id DESC 
            LIMIT 20
        ");
        $stmtT->execute([$workspaceOwnerId, $partnerId]);
        $partnerTx = $stmtT->fetchAll();

        foreach ($partnerTx as &$tx) {
            $tx['id']     = (int)$tx['id'];
            $tx['amount'] = (float)$tx['amount'];
        }

        echo json_encode([
            'is_connected'         => true,
            'is_owner'             => $is_owner,
            'partner'              => [
                'id'              => $partnerId,
                'email'           => $partner['email'],
                'first_name'      => $partner['first_name'] ?? 'Parceiro(a)',
                'last_name'       => $partner['last_name'] ?? '',
                'profile_picture' => $partner['profile_picture'] ?? 'default.png'
            ],
            'partner_transactions' => $partnerTx
        ]);
    } else {
        echo json_encode(['is_connected' => false]);
    }
    exit;
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    if ($action === 'connect') {
        $partner_email = trim($data['email'] ?? '');

        if (!$partner_email) {
            echo json_encode(['success' => false, 'error' => 'Informe o e-mail do seu parceiro(a).']);
            exit;
        }

        if (strtolower($partner_email) === strtolower($_SESSION['email'])) {
            echo json_encode(['success' => false, 'error' => 'Você não pode conectar a sua própria conta a você mesmo.']);
            exit;
        }

        // Buscar usuário parceiro pelo e-mail
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, shared_owner_id FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$partner_email]);
        $targetUser = $stmt->fetch();

        require_once __DIR__ . '/mail_helper.php';
        $workspaceOwnerId = getWorkspaceUserId($pdo, $user_id);

        if (!$targetUser) {
            // E-mail não cadastrado: Gerar convite por e-mail com token seguro
            $token      = bin2hex(random_bytes(16));
            $token_hash = hash('sha256', $token);

            // Remover convites antigos para o mesmo e-mail
            $stmtDel = $pdo->prepare("DELETE FROM account_invites WHERE LOWER(invite_email) = LOWER(?)");
            $stmtDel->execute([$partner_email]);

            // Salvar novo convite
            $stmtIns = $pdo->prepare("INSERT INTO account_invites (owner_user_id, invite_email, token_hash) VALUES (?, ?, ?)");
            $stmtIns->execute([$workspaceOwnerId, $partner_email, $token_hash]);

            // Nome do inviter
            $stmtOwner = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
            $stmtOwner->execute([$user_id]);
            $ownerRow = $stmtOwner->fetch();
            $ownerName = $ownerRow['first_name'] ?? 'Seu parceiro(a)';

            // Montar link de convite
            $protocol   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $domain     = $_SERVER['HTTP_HOST'] ?? 'pricexp.com';
            $inviteLink = "{$protocol}://{$domain}/?invite={$token}";

            $subject = "Convite para Conta Conjunta - PriceXP";
            $messageHtml = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #10b981; text-align: center;'>PriceXP</h2>
                <p>Olá!</p>
                <p><strong>{$ownerName}</strong> convidou você para compartilharem o gerenciamento financeiro juntos na <strong>Conta Conjunta do PriceXP</strong>!</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$inviteLink}' style='background: linear-gradient(135deg, #1e3a8a, #10b981); color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>
                        🚀 Aceitar Convite & Cadastrar
                    </a>
                </div>
                <p style='font-size: 0.85rem; color: #64748b;'>Ou copie e cole este link no seu navegador:<br><a href='{$inviteLink}' style='color: #2563eb;'>{$inviteLink}</a></p>
                <p style='color: #718096; font-size: 12px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;'>
                    Se você não conhece a pessoa que enviou este convite, ignore este e-mail.
                </p>
            </div>";

            sendEmail($partner_email, $subject, $messageHtml);
            logUserActivity($pdo, $user_id, 'CONECTAR_CONTA_CONJUNTA', "Convite de conta conjunta enviado por e-mail para {$partner_email}");

            echo json_encode([
                'success' => true, 
                'message' => 'Convite enviado por e-mail! Seu parceiro(a) poderá se cadastrar direto pelo link.'
            ]);
            exit;
        }

        // Verificar se o parceiro já está conectado a outra conta
        if (!empty($targetUser['shared_owner_id'])) {
            echo json_encode(['success' => false, 'error' => 'Esta conta já está vinculada a outro espaço de conta conjunta.']);
            exit;
        }

        // Conectar: O usuário informado passa a ter o $workspaceOwnerId como shared_owner_id
        $stmtUp = $pdo->prepare("UPDATE users SET shared_owner_id = ? WHERE id = ?");
        $stmtUp->execute([$workspaceOwnerId, $targetUser['id']]);
        logUserActivity($pdo, $user_id, 'CONECTAR_CONTA_CONJUNTA', "Conta conjunta conectada com {$partner_email}");

        echo json_encode(['success' => true, 'message' => 'Conta conjunta conectada com sucesso!']);
        exit;
    }

    if ($action === 'disconnect') {
        // Desconectar o vínculo da conta conjunta
        $stmt = $pdo->prepare("SELECT shared_owner_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();

        if ($u && !empty($u['shared_owner_id'])) {
            // Eu sou membro, removo meu vínculo
            $stmtUp = $pdo->prepare("UPDATE users SET shared_owner_id = NULL WHERE id = ?");
            $stmtUp->execute([$user_id]);
        } else {
            // Eu sou owner, desvinculo membros que me apontam
            $stmtUp = $pdo->prepare("UPDATE users SET shared_owner_id = NULL WHERE shared_owner_id = ?");
            $stmtUp->execute([$user_id]);
        }

        logUserActivity($pdo, $user_id, 'DESCONECTAR_CONTA_CONJUNTA', "Conta conjunta desconectada");

        echo json_encode(['success' => true, 'message' => 'Conta conjunta desconectada.']);
        exit;
    }
}
?>
