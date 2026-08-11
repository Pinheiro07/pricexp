<?php
require 'config.php';
requireLogin();

// Restringe acesso exclusivamente à conta admin do Lucas Pinheiro
$stmtUser = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtUser->execute([$_SESSION['user_id']]);
$loggedUser = $stmtUser->fetch();

if (!$loggedUser || strtolower($loggedUser['email']) !== 'lucassilvapinheiro07@gmail.com') {
    header('HTTP/1.1 403 Forbidden');
    echo '<!DOCTYPE html><html><head><title>Acesso Negado</title><style>body{font-family:sans-serif;background:#0b0f19;color:#fff;text-align:center;padding:50px;}a{color:#60a5fa;}</style></head><body><h1>403 - Acesso Negado</h1><p>Apenas o administrador do sistema tem acesso a esta página.</p><a href="../">Voltar ao aplicativo</a></body></html>';
    exit;
}

$msg = '';
$msgType = '';

// Ação de Exclusão
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $deleteId = intval($_POST['user_id'] ?? 0);
    
    if ($deleteId > 0) {
        if ($deleteId == $_SESSION['user_id']) {
            $msg = 'Você não pode excluir sua própria conta por este painel.';
            $msgType = 'danger';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$deleteId]);
                $msg = 'Usuário #' . $deleteId . ' excluído com sucesso!';
                $msgType = 'success';
            } catch (\PDOException $e) {
                $msg = 'Erro ao excluir usuário: ' . $e->getMessage();
                $msgType = 'danger';
            }
        }
    }
}

$stmt = $pdo->query("SELECT id, first_name, last_name, email, is_active, created_at FROM users ORDER BY id DESC");
$users = $stmt->fetchAll();

if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários - PriceXP Admin</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0b0f19;
            color: #f8fafc;
            padding: 2rem;
            margin: 0;
        }
        .container {
            max-width: 960px;
            margin: 0 auto;
            background: #1c263e;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        h1 { margin: 0; color: #fff; font-size: 1.5rem; }
        p { color: #94a3b8; font-size: 0.9rem; margin-top: 0.25rem; }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        .alert-success { background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }
        .alert-danger { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        th {
            color: #94a3b8;
            font-weight: 600;
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
        .badge-me { background: rgba(59, 130, 246, 0.15); color: #60a5fa; margin-left: 0.5rem; }
        .btn-delete {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.35rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-bar">
            <div>
                <h1>🛡️ Painel Admin - Gerenciamento de Usuários</h1>
                <p>Total de cadastros: <strong><?= count($users); ?></strong></p>
            </div>
            <a href="../" style="color: #60a5fa; text-decoration: none; font-size: 0.85rem; font-weight: 600;">← Voltar ao App</a>
        </div>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msgType; ?>"><?= htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Status</th>
                    <th>Data de Cadastro</th>
                    <th style="text-align: center;">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id']; ?></td>
                        <td>
                            <?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'Sem nome'); ?>
                            <?php if ($u['id'] == $_SESSION['user_id']): ?>
                                <span class="badge badge-me">Você</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($u['email']); ?></strong></td>
                        <td>
                            <?php if (!isset($u['is_active']) || $u['is_active'] == 1): ?>
                                <span class="badge badge-success">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                        <td style="text-align: center;">
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir o usuário <?= htmlspecialchars(addslashes($u['email'])); ?>? Esta ação não pode ser desfeita e apagará todos os dados deste usuário.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $u['id']; ?>">
                                    <button type="submit" class="btn-delete">🗑️ Excluir</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #64748b; font-size: 0.8rem;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
