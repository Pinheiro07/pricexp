<?php
error_reporting(0);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_helper.php';
header('Content-Type: application/json');

// --- CONTROLE DE SESSÃO E EXPIRAÇÃO (20 MINUTOS) ---
$session_expired = false;
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1200)) {
        // Sessão expirou (20 minutos de inatividade)
        session_unset();
        session_destroy();
        session_start();
        $session_expired = true;
    } else {
        // Atualiza o tempo da última atividade
        $_SESSION['last_activity'] = time();
    }
}

// --- TENTATIVA DE AUTO-LOGIN VIA REMEMBER_TOKEN (COOKIES DE 30 DIAS) ---
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $token_hash = hash('sha256', $token);
    
    $stmt = $pdo->prepare("SELECT ut.*, u.first_name, u.last_name, u.email, u.profile_picture FROM user_remember_tokens ut 
                            JOIN users u ON ut.user_id = u.id 
                            WHERE ut.token_hash = ? AND ut.expires_at > NOW()");
    $stmt->execute([$token_hash]);
    $remember = $stmt->fetch();
    
    if ($remember) {
        // Auto-login de 30 dias (ignora verificação por e-mail)
        $_SESSION['user_id'] = $remember['user_id'];
        $_SESSION['email'] = $remember['email'];
        $_SESSION['first_name'] = $remember['first_name'];
        $_SESSION['last_name'] = $remember['last_name'];
        $_SESSION['profile_picture'] = $remember['profile_picture'];
        $_SESSION['last_activity'] = time();
        
        // Rotacionar token por segurança
        $new_token = bin2hex(random_bytes(32));
        $new_token_hash = hash('sha256', $new_token);
        $new_expires = date('Y-m-d H:i:s', time() + 30 * 86400);
        
        $stmt = $pdo->prepare("UPDATE user_remember_tokens SET token_hash = ?, expires_at = ? WHERE id = ?");
        $stmt->execute([$new_token_hash, $new_expires, $remember['id']]);
        
        setcookie('remember_token', $new_token, time() + 30 * 86400, '/', '', true, true);
    } else {
        // Token inválido ou expirado - limpa cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1) CADASTRO DE USUÁRIO (COM SENHA E REGISTRO INATIVO)
    if ($action === 'register') {
        $first_name = $_POST['first_name'] ?? '';
        $last_name = $_POST['last_name'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$email || !$password || !$first_name) {
            echo json_encode(['success' => false, 'error' => 'Preencha todos os campos obrigatórios.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Digite um e-mail válido.']);
            exit;
        }

        try {
            // Validação de E-mail Único
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'E-mail já cadastrado.']);
                exit;
            }

            // Upload de Imagem de Perfil
            $profile_picture = 'default.png'; // Fallback
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                
                $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                
                if (in_array($file_ext, $allowed_exts)) {
                    $new_filename = uniqid('profile_') . '.' . $file_ext;
                    if (@move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                        $profile_picture = $new_filename;
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Formato de imagem inválido. Use JPG ou PNG.']);
                    exit;
                }
            }

            // Verificar se veio token de convite para Conta Conjunta
            $invite_token = $_POST['invite_token'] ?? $_GET['invite'] ?? '';
            $shared_owner_id = null;
            if (!empty($invite_token)) {
                $token_hash = hash('sha256', $invite_token);
                $stmtInv = $pdo->prepare("SELECT id, owner_user_id FROM account_invites WHERE token_hash = ?");
                $stmtInv->execute([$token_hash]);
                $invRow = $stmtInv->fetch();
                if ($invRow) {
                    $shared_owner_id = (int)$invRow['owner_user_id'];
                    // Excluir convite consumido
                    $stmtDelInv = $pdo->prepare("DELETE FROM account_invites WHERE id = ?");
                    $stmtDelInv->execute([$invRow['id']]);
                }
            }

            $whatsapp = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Se o usuário está se cadastrando por um convite válido, ativa a conta e faz auto-login imediato
            if ($shared_owner_id !== null) {
                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, profile_picture, is_active, shared_owner_id, whatsapp) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
                $stmt->execute([$first_name, $last_name, $email, $hash, $profile_picture, $shared_owner_id, $whatsapp]);
                $new_id = $pdo->lastInsertId();

                // Ativar sessão PHP imediatamente
                $_SESSION['user_id']         = $new_id;
                $_SESSION['email']           = $email;
                $_SESSION['first_name']      = $first_name;
                $_SESSION['last_name']       = $last_name;
                $_SESSION['profile_picture'] = $profile_picture;
                $_SESSION['last_activity']    = time();

                // Gravar token de 30 dias para auto-login futuro
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + 30 * 86400);
                $stmtTok = $pdo->prepare("INSERT INTO user_remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmtTok->execute([$new_id, $token_hash, $expires_at]);
                setcookie('remember_token', $token, time() + 30 * 86400, '/', '', true, true);

                echo json_encode([
                    'success'    => true,
                    'auto_login' => true,
                    'user'       => [
                        'email'           => $email,
                        'first_name'      => $first_name,
                        'last_name'       => $last_name,
                        'profile_picture' => $profile_picture
                    ]
                ]);
                exit;
            }

            // Gerar código de verificação seguro de 6 dígitos
            $code = (string)random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + 900); // 15 minutos

            // Inserir no Banco (Como inativo: is_active = 0)
            $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, profile_picture, is_active, verification_code, code_expires_at, whatsapp) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)");
            $stmt->execute([$first_name, $last_name, $email, $hash, $profile_picture, $code, $expires, $whatsapp]);

            // Enviar e-mail de confirmação usando PHPMailer
            $to = $email;
            $subject = "Codigo de Confirmacao - PriceXP";
            
            $messageHtml = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #3182ce; text-align: center;'>PriceXP</h2>
                <p>Olá, " . htmlspecialchars($first_name) . "!</p>
                <p>Seu código de confirmação para ativar sua conta no PriceXP é:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; background-color: #edf2f7; padding: 10px 20px; border-radius: 4px; border: 1px solid #cbd5e0; color: #2d3748;'>{$code}</span>
                </div>
                <p>Este código expira em <strong>15 minutos</strong>.</p>
                <p style='color: #718096; font-size: 12px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;'>
                    Se você não solicitou este acesso, ignore este e-mail.
                </p>
            </div>";

            sendEmail($to, $subject, $messageHtml);

            echo json_encode(['success' => true, 'require_verification' => true, 'email' => $email]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro interno ao cadastrar: ' . $e->getMessage()]);
        }
        exit;
    }

    // 2) VALIDAÇÃO DO CÓDIGO (ATIVAÇÃO DA CONTA E APLICAÇÃO DO LEMBRAR-ME)
    if ($action === 'verify') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');
        $code = trim($data['code'] ?? '');
        $remember = isset($data['remember']) && $data['remember'] == true;

        if (!$email || !$code) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && ($user['verification_code'] === $code && strtotime($user['code_expires_at']) > time())) {
            // Ativa o usuário
            $stmt = $pdo->prepare("UPDATE users SET is_active = 1, verification_code = NULL, code_expires_at = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Faz login na sessão
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['profile_picture'] = $user['profile_picture'];
            $_SESSION['last_activity'] = time();

            // Configurar Cookie de Lembrar-me por 30 Dias se selecionado
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', time() + 30 * 86400); // 30 dias

                // Deleta tokens antigos do usuário
                $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
                $stmt->execute([$user['id']]);

                // Insere novo token hash no banco
                $stmt = $pdo->prepare("INSERT INTO user_remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token_hash, $expires_at]);

                // Seta cookie httpOnly e Seguro (se HTTPS)
                setcookie('remember_token', $token, time() + 30 * 86400, '/', '', true, true);
            }

            logUserActivity($pdo, $user['id'], 'LOGIN', "Autenticação realizada com sucesso no sistema");

            echo json_encode([
                'success' => true, 
                'first_name' => $user['first_name'],
                'profile_picture' => $user['profile_picture']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Código de verificação inválido ou expirado.']);
        }
        exit;
    }

    // 3) LOGIN COM SENHA (SEMPRE GERA CÓDIGO POR E-MAIL)
    if ($action === 'login') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(['success' => false, 'error' => 'E-mail e senha são obrigatórios.']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Gera código de verificação para o Login (Válido por 15 min)
            $code = (string)random_int(100000, 999999);
            $expires = date('Y-m-d H:i:s', time() + 900);
            
            $stmt = $pdo->prepare("UPDATE users SET verification_code = ?, code_expires_at = ? WHERE id = ?");
            $stmt->execute([$code, $expires, $user['id']]);

            // Envia e-mail do código de login via PHPMailer
            $to = $email;
            $subject = "Codigo de Seguranca de Acesso - PriceXP";
            $messageHtml = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                <h2 style='color: #3182ce; text-align: center;'>PriceXP</h2>
                <p>Olá, " . htmlspecialchars($user['first_name']) . "!</p>
                <p>Você solicitou acesso ao PriceXP. Seu código de segurança de acesso é:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <span style='font-size: 32px; font-weight: bold; letter-spacing: 5px; background-color: #edf2f7; padding: 10px 20px; border-radius: 4px; border: 1px solid #cbd5e0; color: #2d3748;'>{$code}</span>
                </div>
                <p>Este código expira em <strong>15 minutos</strong>.</p>
                <p style='color: #718096; font-size: 12px; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 20px;'>
                    Se você não solicitou este acesso, ignore este e-mail.
                </p>
            </div>";

            sendEmail($to, $subject, $messageHtml);

            echo json_encode([
                'success' => true, 
                'require_verification' => true, 
                'email' => $email
            ]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'E-mail ou senha incorretos.']);
        }
        exit;
    }
}

// 4) VERIFICAÇÃO DE SESSÃO ATIVA
if ($action === 'check') {
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'logged_in' => true, 
            'email' => $_SESSION['email'],
            'first_name' => $_SESSION['first_name'] ?? 'Usuário',
            'last_name' => $_SESSION['last_name'] ?? '',
            'profile_picture' => $_SESSION['profile_picture'] ?? 'default.png'
        ]);
    } else {
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

// 5) ENCERRAMENTO DA SESSÃO
if ($action === 'logout') {
    if (isset($_SESSION['user_id'])) {
        // Limpar token do banco
        $stmt = $pdo->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }
    // Limpar cookie
    setcookie('remember_token', '', time() - 3600, '/');
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}
?>