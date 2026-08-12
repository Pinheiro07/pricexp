<?php
error_reporting(0);
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    
    if (!$first_name || !$email) {
        echo json_encode(['success' => false, 'error' => 'Nome e e-mail são obrigatórios.']);
        exit;
    }

    // Puxa o usuário atual para validar a senha atual e pegar a foto antiga
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Se ele quiser trocar senha ou email, precisa da senha atual
    if ($email !== $user['email'] || $password !== '') {
        if (!password_verify($current_password, $user['password_hash'])) {
            echo json_encode(['success' => false, 'error' => 'A senha atual está incorreta.']);
            exit;
        }
    }
    
    // Se o email mudou, verifica se já existe
    if ($email !== $user['email']) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este e-mail já está em uso.']);
            exit;
        }
    }

    $profile_picture = $user['profile_picture'];
    
    // Upload de Imagem de Perfil
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($file_ext, $allowed_exts)) {
            $new_filename = uniqid('profile_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                
                // Apaga a foto antiga se não for a padrão
                if ($profile_picture !== 'default.png' && file_exists($upload_dir . $profile_picture)) {
                    unlink($upload_dir . $profile_picture);
                }
                
                $profile_picture = $new_filename;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Formato de imagem inválido. Use JPG ou PNG.']);
            exit;
        }
    }

    // Prepara os dados para atualização
    $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, profile_picture = ?";
    $params = [$first_name, $last_name, $email, $profile_picture];
    
    if ($password !== '') {
        $query .= ", password_hash = ?";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }
    
    $query .= " WHERE id = ?";
    $params[] = $user_id;
    
    try {
        $stmt = $pdo->prepare($query);
        if ($stmt->execute($params)) {
            // Atualiza a sessão
            $_SESSION['email'] = $email;
            $_SESSION['first_name'] = $first_name;
            $_SESSION['last_name'] = $last_name;
            $_SESSION['profile_picture'] = $profile_picture;
            
            logUserActivity($pdo, $user_id, 'ALTERAR_PERFIL', "Atualização de perfil / dados cadastrais");

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Falha ao atualizar o perfil.']);
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erro interno ao salvar: ' . $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action === 'delete') {
    try {
        // Primeiro deleta todas as transações para não dar erro de Foreign Key ou deixar dados orfãos
        $stmt = $pdo->prepare("DELETE FROM transactions WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // Apaga a foto de perfil do servidor
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && $user['profile_picture'] !== 'default.png') {
            $file_path = '../uploads/' . $user['profile_picture'];
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        // Deleta o usuário
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            session_destroy();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Falha ao deletar a conta.']);
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Erro interno ao excluir: ' . $e->getMessage()]);
    }
    exit;
}

// Se for GET normal, devolve os dados atuais para preencher o form
$stmt = $pdo->prepare("SELECT first_name, last_name, email, profile_picture FROM users WHERE id = ?");
$stmt->execute([$user_id]);
echo json_encode($stmt->fetch());
?>
