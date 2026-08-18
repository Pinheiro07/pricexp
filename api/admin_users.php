<?php
require 'config.php';
requireLogin();

// Restringe acesso exclusivamente à conta admin do Lucas Pinheiro
$stmtUser = $pdo->prepare("SELECT id, email, first_name, last_name FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$loggedUser = $stmtUser->fetch();

if (!$loggedUser || strtolower($loggedUser['email']) !== 'lucassilvapinheiro07@gmail.com') {
    header('HTTP/1.1 403 Forbidden');
    echo '<!DOCTYPE html><html><head><title>Acesso Negado</title><style>body{font-family:sans-serif;background:#0b0f19;color:#fff;text-align:center;padding:50px;}a{color:#60a5fa;}</style></head><body><h1>403 - Acesso Negado</h1><p>Apenas o administrador do sistema tem acesso a esta página.</p><a href="../">Voltar ao aplicativo</a></body></html>';
    exit;
}

// Garante a existência da tabela user_activity_logs no MySQL da VPS
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        amount DECIMAL(10, 2) DEFAULT NULL,
        meta_json TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (\Exception $e) {}

$msg = '';
$msgType = '';

// Gerenciamento da Chave de Acesso para Desocultar Valores Financeiros
define('ADMIN_SECRET_KEY', 'pricexp2026');

if (isset($_POST['action']) && $_POST['action'] === 'toggle_unlock') {
    $providedKey = trim($_POST['secret_key'] ?? '');
    if ($providedKey === ADMIN_SECRET_KEY) {
        $_SESSION['admin_unlocked'] = true;
        $msg = 'Valores financeiros desocultados com sucesso!';
        $msgType = 'success';
    } else {
        $_SESSION['admin_unlocked'] = false;
        $msg = 'Chave de acesso incorreta. Os valores continuam ocultados por segurança.';
        $msgType = 'danger';
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'lock_privacy') {
    $_SESSION['admin_unlocked'] = false;
    $msg = 'Valores financeiros ocultados novamente com sucesso.';
    $msgType = 'success';
}

$isUnlocked = !empty($_SESSION['admin_unlocked']);

// Ação 1: Exclusão de Usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $deleteId = intval($_POST['user_id'] ?? 0);
    
    if ($deleteId > 0) {
        if ($deleteId == $_SESSION['user_id']) {
            $msg = 'Você não pode excluir sua própria conta por este painel.';
            $msgType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$deleteId]);
                logUserActivity($pdo, $_SESSION['user_id'], 'ADMIN_EXCLUIR_USUARIO', "Administrador excluiu o usuário #{$deleteId}");
                $msg = 'Usuário #' . $deleteId . ' e seus dados foram excluídos com sucesso!';
                $msgType = 'success';
            } catch (\PDOException $e) {
                $msg = 'Erro ao excluir usuário: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

// Ação 2: Exclusão Direta de Lançamento de Qualquer Usuário pelo Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_tx') {
    $txId = intval($_POST['tx_id'] ?? 0);
    if ($txId > 0) {
        try {
            // Busca dados do lançamento para o log antes de excluir
            $stmtFind = $pdo->prepare("SELECT t.*, u.email FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.id = ?");
            $stmtFind->execute([$txId]);
            $tx = $stmtFind->fetch();

            if ($tx) {
                $stmtDel = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
                $stmtDel->execute([$txId]);

                $logMsg = "Admin excluiu lançamento #{$txId} ('{$tx['description']}', R$ {$tx['amount']}) do usuário {$tx['email']}";
                logUserActivity($pdo, $_SESSION['user_id'], 'ADMIN_EXCLUIR_LANCAMENTO', $logMsg, $tx['amount']);

                $msg = "Lançamento #{$txId} ('" . htmlspecialchars($tx['description']) . "') excluído do banco com sucesso!";
                $msgType = 'success';
            } else {
                $msg = "Lançamento #{$txId} não foi encontrado no banco de dados.";
                $msgType = 'danger';
            }
        } catch (\PDOException $e) {
            $msg = 'Erro ao excluir lançamento do banco: ' . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Ação 3: Criação Direta de Usuário/Cliente pelo Administrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim(mb_strtolower($_POST['email'] ?? '', 'UTF-8'));
    $whatsapp  = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (!$firstName || !$email || !$password) {
        $msg = 'Preencha todos os campos obrigatórios (Nome, E-mail e Senha).';
        $msgType = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Digite um e-mail válido.';
        $msgType = 'danger';
    } elseif ($password !== $confirmPw) {
        $msg = 'A senha e a confirmação de senha não coincidem.';
        $msgType = 'danger';
    } else {
        try {
            // Validação de E-mail Único no backend
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                $msg = 'Este e-mail já possui uma conta no PriceXP.';
                $msgType = 'danger';
            } else {
                // Transação SQL Segura
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_DEFAULT);
                $profilePicture = 'default.png';

                // Cadastro administrativo nasce ativo (is_active = 1, sem código de confirmação)
                $stmtIns = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, profile_picture, is_active, whatsapp, verification_code, code_expires_at) VALUES (?, ?, ?, ?, ?, 1, ?, NULL, NULL)");
                $stmtIns->execute([$firstName, $lastName, $email, $hash, $profilePicture, $whatsapp]);
                $newUserId = $pdo->lastInsertId();

                logUserActivity($pdo, $_SESSION['user_id'], 'ADMIN_CRIAR_USUARIO', "Administrador criou a conta de cliente #{$newUserId} ({$email})");

                $pdo->commit();

                $msg = "Cliente {$firstName} ({$email}) cadastrado com sucesso! A conta já está ativa para login.";
                $msgType = 'success';
            }
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Erro PDO ao cadastrar cliente pelo admin: " . $e->getMessage());
            $msg = 'Não foi possível cadastrar o cliente. Tente novamente.';
            $msgType = 'danger';
        }
    }
}

// Aba ativa
$activeTab = $_GET['tab'] ?? 'users';
if (!in_array($activeTab, ['users', 'logs', 'transactions'])) {
    $activeTab = 'users';
}

// Dados: Lista de Usuários
$stmtUsers = $pdo->query("SELECT id, first_name, last_name, email, is_active, created_at FROM users ORDER BY id DESC");
$users = $stmtUsers->fetchAll();

// Dados: Logs de Atividades (Filtros: Usuário, Ação, Data Inicial, Data Final)
$filterLogUser   = intval($_GET['log_user'] ?? 0);
$filterLogAction = trim($_GET['log_action'] ?? '');
$filterLogStart  = trim($_GET['log_start'] ?? '');
$filterLogEnd    = trim($_GET['log_end'] ?? '');

$logWhere = [];
$logParams = [];

if ($filterLogUser > 0) {
    $logWhere[] = "l.user_id = ?";
    $logParams[] = $filterLogUser;
}
if (!empty($filterLogAction)) {
    $logWhere[] = "l.action_type = ?";
    $logParams[] = $filterLogAction;
}
if (!empty($filterLogStart)) {
    $logWhere[] = "l.created_at >= ?";
    $logParams[] = $filterLogStart . ' 00:00:00';
}
if (!empty($filterLogEnd)) {
    $logWhere[] = "l.created_at <= ?";
    $logParams[] = $filterLogEnd . ' 23:59:59';
}

$logSql = "SELECT l.*, u.email, u.first_name, u.last_name 
           FROM user_activity_logs l 
           JOIN users u ON l.user_id = u.id";
if (count($logWhere) > 0) {
    $logSql .= " WHERE " . implode(" AND ", $logWhere);
}
$logSql .= " ORDER BY l.id DESC LIMIT 300";

$stmtLogs = $pdo->prepare($logSql);
$stmtLogs->execute($logParams);
$activityLogs = $stmtLogs->fetchAll();

// Tipos de ações disponíveis nos logs para o filtro
$stmtActionTypes = $pdo->query("SELECT DISTINCT action_type FROM user_activity_logs ORDER BY action_type ASC");
$actionTypes = $stmtActionTypes->fetchAll(PDO::FETCH_COLUMN);

// Dados: Gerenciamento de Lançamentos dos Usuários (Filtros: Usuário, Pesquisa)
$filterTxUser   = intval($_GET['tx_user'] ?? 0);
$filterTxSearch = trim($_GET['tx_search'] ?? '');

$txWhere = [];
$txParams = [];

if ($filterTxUser > 0) {
    $txWhere[] = "t.user_id = ?";
    $txParams[] = $filterTxUser;
}
if (!empty($filterTxSearch)) {
    $txWhere[] = "(t.description LIKE ? OR t.category LIKE ? OR t.bank_name LIKE ?)";
    $txParams[] = "%{$filterTxSearch}%";
    $txParams[] = "%{$filterTxSearch}%";
    $txParams[] = "%{$filterTxSearch}%";
}

$txSql = "SELECT t.*, u.email, u.first_name, u.last_name 
          FROM transactions t 
          JOIN users u ON t.user_id = u.id";
if (count($txWhere) > 0) {
    $txSql .= " WHERE " . implode(" AND ", $txWhere);
}
$txSql .= " ORDER BY t.date DESC, t.id DESC LIMIT 200";

$stmtTx = $pdo->prepare($txSql);
$stmtTx->execute($txParams);
$allTransactions = $stmtTx->fetchAll();

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode(['users' => $users]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin & Auditoria - PriceXP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            padding: 1.5rem;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #1e293b;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        h1 { margin: 0; color: #fff; font-size: 1.6rem; display: flex; align-items: center; gap: 0.5rem; }
        p { color: #94a3b8; font-size: 0.9rem; margin-top: 0.25rem; }
        
        /* Tabs */
        .admin-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 2px solid #334155;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        .admin-tab-btn {
            padding: 0.75rem 1.25rem;
            background: transparent;
            border: none;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .admin-tab-btn:hover { color: #f8fafc; }
        .admin-tab-btn.active {
            color: #10b981;
            border-bottom-color: #10b981;
        }

        /* Filter Box */
        .filter-card {
            background: #0f172a;
            border: 1px solid #334155;
            padding: 1.25rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            flex: 1;
            min-width: 160px;
        }
        .filter-group label {
            font-size: 0.8rem;
            color: #94a3b8;
            font-weight: 600;
        }
        .filter-group input, .filter-group select {
            background: #1e293b;
            border: 1px solid #475569;
            color: #fff;
            padding: 0.55rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.88rem;
            outline: none;
        }

        /* Privacy Shield / Blur */
        .financial-blur {
            filter: blur(5px);
            user-select: none;
            opacity: 0.6;
            transition: filter 0.3s ease;
        }
        .financial-blur:hover {
            filter: blur(3px);
        }

        .alert {
            padding: 0.85rem 1.25rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0.5rem;
            font-size: 0.88rem;
        }
        th, td {
            padding: 0.85rem 1rem;
            text-align: left;
            border-bottom: 1px solid #334155;
        }
        th {
            color: #94a3b8;
            font-weight: 600;
            background: #0f172a;
        }
        tr:hover { background: rgba(255, 255, 255, 0.02); }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
        .badge-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
        .badge-info { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: none;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary { background: linear-gradient(135deg, #1e3a8a, #10b981); color: #fff; }
        .btn-primary:hover { opacity: 0.9; }
        .btn-secondary { background: #334155; color: #f8fafc; border: 1px solid #475569; }
        .btn-secondary:hover { background: #475569; }
        .btn-delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.3); }

        @media print {
            body { background: #fff; color: #000; padding: 0; }
            .container { background: #fff; color: #000; box-shadow: none; border: none; max-width: 100%; }
            .admin-tabs, .filter-card, .btn-action, .no-print { display: none !important; }
            .financial-blur { filter: none !important; opacity: 1 !important; }
            th { background: #f1f5f9; color: #000; }
            td, th { border-bottom: 1px solid #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-bar">
            <div>
                <h1>🛡️ Painel Admin & Auditoria</h1>
                <p>Gerenciamento avançado de usuários, relatórios de auditoria e controle do banco de dados.</p>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button onclick="window.print()" class="btn-action btn-secondary no-print">🖨️ Imprimir Relatório</button>
                <a href="../" class="btn-action btn-secondary no-print">← Voltar ao App</a>
            </div>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msgType; ?>"><?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <!-- Navegação por Abas -->
        <div class="admin-tabs no-print">
            <a href="?tab=users" class="admin-tab-btn <?= $activeTab === 'users' ? 'active' : ''; ?>">
                👥 Usuários (<?= count($users); ?>)
            </a>
            <a href="?tab=logs" class="admin-tab-btn <?= $activeTab === 'logs' ? 'active' : ''; ?>">
                📋 Logs de Auditoria & Atividades (<?= count($activityLogs); ?>)
            </a>
            <a href="?tab=transactions" class="admin-tab-btn <?= $activeTab === 'transactions' ? 'active' : ''; ?>">
                💸 Lançamentos do Banco (<?= count($allTransactions); ?>)
            </a>
        </div>

        <!-- Barra de Segurança: Desocultar Valores Financeiros -->
        <div class="filter-card no-print" style="background: rgba(16, 185, 129, 0.05); border-color: rgba(16, 185, 129, 0.3);">
            <div style="flex: 1;">
                <strong style="color: #10b981; display: flex; align-items: center; gap: 0.4rem; font-size: 0.95rem;">
                    <?= $isUnlocked ? '🔓 Valores Financeiros Desocultados' : '🔒 Privacidade Financeira Ativa (Valores Borrados)'; ?>
                </strong>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.82rem; color: #94a3b8;">
                    <?= $isUnlocked ? 'Os valores numéricos estão visíveis para emissão de relatório ao cliente.' : 'Por segurança e privacidade dos usuários, os valores de R$ estão ocultados com borrado.'; ?>
                </p>
            </div>
            
            <?php if (!$isUnlocked): ?>
                <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                    <input type="hidden" name="action" value="toggle_unlock">
                    <input type="password" name="secret_key" placeholder="Digite a Chave de Acesso" required style="padding: 0.55rem 0.85rem; border-radius: 0.5rem; border: 1px solid #475569; background: #1e293b; color: #fff; outline: none; font-size: 0.85rem;">
                    <button type="submit" class="btn-action btn-primary">🔓 Desocultar Valores</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="lock_privacy">
                    <button type="submit" class="btn-action btn-secondary">🔒 Ocultar Valores Novamente</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- ABA 1: GERENCIAMENTO DE USUÁRIOS -->
        <?php if ($activeTab === 'users'): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="margin: 0; font-size: 1.1rem; color: #f8fafc; font-weight: 600;">Lista de Clientes & Usuários</h3>
                <button type="button" onclick="openCreateUserModal()" class="btn-action btn-primary no-print" style="display: flex; align-items: center; gap: 0.5rem; background: #10b981; border: none; font-weight: 600;">
                    <span>➕ Cadastrar Cliente</span>
                </button>
            </div>

            <!-- MODAL CADASTRAR CLIENTE -->
            <div id="modal-create-user" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 1rem; width: 100%; max-width: 500px; padding: 2rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5); position: relative; margin: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid #334155;">
                        <h3 style="margin: 0; color: #f8fafc; font-size: 1.2rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                            👤 Cadastrar Novo Cliente
                        </h3>
                        <button type="button" onclick="closeCreateUserModal()" style="background: transparent; border: none; color: #94a3b8; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
                    </div>

                    <form method="POST" id="form-create-user" onsubmit="return confirmCreateUser(this);">
                        <input type="hidden" name="action" value="create_user">

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div>
                                <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.35rem; font-weight: 500;">Nome *</label>
                                <input type="text" name="first_name" required placeholder="Ex: João" style="width: 100%; padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #fff; outline: none; font-size: 0.9rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.35rem; font-weight: 500;">Sobrenome</label>
                                <input type="text" name="last_name" placeholder="Ex: Silva" style="width: 100%; padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #fff; outline: none; font-size: 0.9rem;">
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.35rem; font-weight: 500;">E-mail do Cliente *</label>
                            <input type="email" name="email" required placeholder="exemplo@email.com" style="width: 100%; padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #fff; outline: none; font-size: 0.9rem;">
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.35rem; font-weight: 500;">WhatsApp (com DDD)</label>
                            <input type="tel" name="whatsapp" placeholder="Ex: 27999998888" style="width: 100%; padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #fff; outline: none; font-size: 0.9rem;">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.35rem; font-weight: 500;">Senha *</label>
                                <input type="password" name="password" id="admin-new-pw" required style="width: 100%; padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #fff; outline: none; font-size: 0.9rem;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.35rem; font-weight: 500;">Confirmar Senha *</label>
                                <input type="password" name="confirm_password" id="admin-confirm-pw" required style="width: 100%; padding: 0.6rem 0.8rem; background: #0f172a; border: 1px solid #334155; border-radius: 0.5rem; color: #fff; outline: none; font-size: 0.9rem;">
                            </div>
                        </div>

                        <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.82rem; color: #93c5fd;">
                            ℹ️ <strong>Conta Ativa Instantaneamente:</strong> Esta conta será criada com status <strong>Ativo (`is_active = 1`)</strong> para que o cliente possa fazer o login normal.
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                            <button type="button" onclick="closeCreateUserModal()" class="btn-action btn-secondary">Cancelar</button>
                            <button type="submit" id="btn-submit-create-user" class="btn-action btn-primary" style="background: #10b981;">Cadastrar cliente</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
            function openCreateUserModal() {
                document.getElementById('modal-create-user').style.display = 'flex';
            }
            function closeCreateUserModal() {
                document.getElementById('modal-create-user').style.display = 'none';
            }
            function confirmCreateUser(form) {
                var pw = document.getElementById('admin-new-pw').value;
                var confirmPw = document.getElementById('admin-confirm-pw').value;
                if (pw !== confirmPw) {
                    alert('A senha e a confirmação de senha não coincidem.');
                    return false;
                }
                var email = form.email.value;
                var name = form.first_name.value + ' ' + form.last_name.value;
                if (!confirm('Cadastrar este cliente?\n\n' + name + '\n' + email)) {
                    return false;
                }
                var btn = document.getElementById('btn-submit-create-user');
                btn.disabled = true;
                btn.innerText = 'Cadastrando...';
                return true;
            }
            </script>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Data de Cadastro</th>
                        <th style="text-align: center;">Ações do Administrador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>#<?= $u['id']; ?></td>
                            <td>
                                <strong><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'Sem nome'); ?></strong>
                                <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge badge-info">Você</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['email']); ?></td>
                            <td>
                                <?php if (!isset($u['is_active']) || $u['is_active'] == 1): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                    <a href="?tab=logs&log_user=<?= $u['id']; ?>" class="btn-action btn-secondary" style="font-size: 0.78rem; padding: 0.35rem 0.65rem;">📋 Ver Logs</a>
                                    <a href="?tab=transactions&tx_user=<?= $u['id']; ?>" class="btn-action btn-secondary" style="font-size: 0.78rem; padding: 0.35rem 0.65rem;">💸 Ver Lançamentos</a>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir o usuário <?= htmlspecialchars(addslashes($u['email'])); ?>? Esta ação não pode ser desfeita e apagará todos os lançamentos deste usuário do banco.');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete" style="font-size: 0.78rem; padding: 0.35rem 0.65rem;">🗑️ Excluir Conta</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- ABA 2: LOGS DE AUDITORIA & ATIVIDADES -->
        <?php if ($activeTab === 'logs'): ?>
            <!-- Form de Filtros -->
            <form method="GET" class="filter-card no-print">
                <input type="hidden" name="tab" value="logs">
                <div class="filter-group">
                    <label>Filtrar por Usuário</label>
                    <select name="log_user">
                        <option value="0">Todos os Usuários</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id']; ?>" <?= $filterLogUser == $u['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(($u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] . ' - ' : '') . $u['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Tipo de Ação</label>
                    <select name="log_action">
                        <option value="">Todas as Ações</option>
                        <?php foreach ($actionTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type); ?>" <?= $filterLogAction === $type ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($type); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Data Inicial</label>
                    <input type="date" name="log_start" value="<?= htmlspecialchars($filterLogStart); ?>">
                </div>

                <div class="filter-group">
                    <label>Data Final</label>
                    <input type="date" name="log_end" value="<?= htmlspecialchars($filterLogEnd); ?>">
                </div>

                <button type="submit" class="btn-action btn-primary">🔍 Filtrar Logs</button>
                <a href="?tab=logs" class="btn-action btn-secondary">Limpar</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Data / Hora</th>
                        <th>Usuário</th>
                        <th>Ação Registrada</th>
                        <th>Descrição dos Detalhes</th>
                        <th>Valor (R$)</th>
                        <th>Endereço IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($activityLogs) === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #94a3b8; padding: 2rem;">Nenhum log de atividade encontrado para os filtros selecionados.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td style="white-space: nowrap;"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <strong><?= htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?: 'Usuário'); ?></strong><br>
                                    <span style="font-size: 0.78rem; color: #94a3b8;"><?= htmlspecialchars($log['email']); ?></span>
                                </td>
                                <td><span class="badge badge-info"><?= htmlspecialchars($log['action_type']); ?></span></td>
                                <td>
                                    <span class="<?= !$isUnlocked ? 'financial-blur' : ''; ?>">
                                        <?= htmlspecialchars($log['description']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['amount'] !== null): ?>
                                        <span class="<?= !$isUnlocked ? 'financial-blur' : ''; ?>" style="font-weight: 600; color: #10b981;">
                                            R$ <?= number_format((float)$log['amount'], 2, ',', '.'); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #64748b;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.78rem; color: #94a3b8;"><?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- ABA 3: GERENCIAMENTO DE LANÇAMENTOS (EXCLUSÃO DIRETA DO BANCO) -->
        <?php if ($activeTab === 'transactions'): ?>
            <!-- Form de Filtros -->
            <form method="GET" class="filter-card no-print">
                <input type="hidden" name="tab" value="transactions">
                <div class="filter-group">
                    <label>Filtrar por Usuário</label>
                    <select name="tx_user">
                        <option value="0">Todos os Usuários</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id']; ?>" <?= $filterTxUser == $u['id'] ? 'selected' : ''; ?>>
                                <?= htmlspecialchars(($u['first_name'] ? $u['first_name'] . ' ' . $u['last_name'] . ' - ' : '') . $u['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group" style="flex: 2;">
                    <label>Buscar Lançamento (Descrição, Categoria ou Banco)</label>
                    <input type="text" name="tx_search" placeholder="Ex: Mercado, Salário, Nubank..." value="<?= htmlspecialchars($filterTxSearch); ?>">
                </div>

                <button type="submit" class="btn-action btn-primary">🔍 Buscar Lançamentos</button>
                <a href="?tab=transactions" class="btn-action btn-secondary">Limpar</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Categoria / Banco</th>
                        <th>Valor (R$)</th>
                        <th style="text-align: center;">Ação de Admin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($allTransactions) === 0): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #94a3b8; padding: 2rem;">Nenhum lançamento encontrado no banco de dados para a busca.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allTransactions as $tx): ?>
                            <tr>
                                <td>#<?= $tx['id']; ?></td>
                                <td>
                                    <strong><?= htmlspecialchars(trim(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? '')) ?: 'Usuário'); ?></strong><br>
                                    <span style="font-size: 0.78rem; color: #94a3b8;"><?= htmlspecialchars($tx['email']); ?></span>
                                </td>
                                <td style="white-space: nowrap;"><?= date('d/m/Y', strtotime($tx['date'])); ?></td>
                                <td>
                                    <?php if ($tx['type'] === 'receita'): ?>
                                        <span class="badge badge-success">Receita</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Despesa</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= !$isUnlocked ? 'financial-blur' : ''; ?>">
                                        <?= htmlspecialchars($tx['description']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= htmlspecialchars($tx['category']); ?></span>
                                    <span style="font-size: 0.78rem; color: #94a3b8; margin-left: 0.25rem;">(<?= htmlspecialchars($tx['bank_name'] ?? 'Geral'); ?>)</span>
                                </td>
                                <td>
                                    <span class="<?= !$isUnlocked ? 'financial-blur' : ''; ?>" style="font-weight: 600; color: <?= $tx['type'] === 'receita' ? '#10b981' : '#ef4444'; ?>;">
                                        R$ <?= number_format((float)$tx['amount'], 2, ',', '.'); ?>
                                    </span>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir o lançamento #<?= $tx['id']; ?> (\'<?= htmlspecialchars(addslashes($tx['description'])); ?>\') do usuário <?= htmlspecialchars(addslashes($tx['email'])); ?> diretamente do banco?');">
                                        <input type="hidden" name="action" value="delete_tx">
                                        <input type="hidden" name="tx_id" value="<?= $tx['id']; ?>">
                                        <button type="submit" class="btn-action btn-delete" style="font-size: 0.78rem; padding: 0.35rem 0.65rem;">🗑️ Excluir Lançamento</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        if (window.lucide) {
            lucide.createIcons();
        }
    </script>
</body>
</html>
