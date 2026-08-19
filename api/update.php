<?php
header('HTTP/1.1 403 Forbidden');
header('Content-Type: application/json');
echo json_encode(['error' => 'Acesso negado. Atualização remota via web desativada por segurança.']);
exit;

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Script de Atualização Automática do PriceXP em 1 Clique (via GitHub Raw)
$baseDir = dirname(__DIR__);

$files = [
    'api/whatsapp_webhook.php' => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/api/whatsapp_webhook.php',
    'api/profile.php'          => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/api/profile.php',
    'api/login.php'            => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/api/login.php',
    'api/transactions.php'     => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/api/transactions.php',
    'api/shared_account.php'   => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/api/shared_account.php',
    'index.html'               => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/index.html',
    'app.html'                 => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/app.html',
    'landing.html'             => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/landing.html',
    'landing.css'              => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/landing.css',
    'landing.js'               => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/landing.js',
    'app.js'                   => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/app.js',
    'style.css'                => 'https://raw.githubusercontent.com/Pinheiro07/pricexp/main/style.css'
];

$results = [];
foreach ($files as $relativePath => $remoteUrl) {
    $fullPath = $baseDir . '/' . $relativePath;
    
    // Tenta baixar via file_get_contents ou curl
    $content = @file_get_contents($remoteUrl);
    if ($content === false && function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $remoteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        $content = curl_exec($ch);
        curl_close($ch);
    }

    if ($content !== false && strlen($content) > 10) {
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $written = @file_put_contents($fullPath, $content);
        if ($written !== false) {
            $results[$relativePath] = 'Atualizado (' . $written . ' bytes)';
        } else {
            $results[$relativePath] = 'Erro de escrita de arquivo';
        }
    } else {
        $results[$relativePath] = 'Erro ao baixar do GitHub';
    }
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Sistema PriceXP atualizado com sucesso a partir do GitHub!',
    'results' => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
