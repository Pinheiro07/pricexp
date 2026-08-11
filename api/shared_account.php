<?php
require 'config.php';
requireLogin();
header('Content-Type: application/json');

$method  = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';

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
        echo json_encode([
            'is_connected' => true,
            'is_owner'     => $is_owner,
            'partner'      => [
                'id'              => (int)$partner['id'],
                'email'           => $partner['email'],
                'first_name'      => $partner['first_name'] ?? 'Parceiro(a)',
                'last_name'       => $partner['last_name'] ?? '',
                'profile_picture' => $partner['profile_picture'] ?? 'default.png'
            ]
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

        if (!$targetUser) {
            echo json_encode(['success' => false, 'error' => 'Nenhum usuário cadastrado foi encontrado com este e-mail. Peça para seu parceiro(a) se cadastrar no app primeiro!']);
            exit;
        }

        // Verificar se o parceiro já está conectado a outra conta
        if (!empty($targetUser['shared_owner_id'])) {
            echo json_encode(['success' => false, 'error' => 'Esta conta já está vinculada a outro espaço de conta conjunta.']);
            exit;
        }

        // Conectar: O usuário informado passa a ter o $user_id atual como shared_owner_id
        $workspaceOwnerId = getWorkspaceUserId($pdo, $user_id);
        $stmtUp = $pdo->prepare("UPDATE users SET shared_owner_id = ? WHERE id = ?");
        $stmtUp->execute([$workspaceOwnerId, $targetUser['id']]);

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

        echo json_encode(['success' => true, 'message' => 'Conta conjunta desconectada.']);
        exit;
    }
}
?>
