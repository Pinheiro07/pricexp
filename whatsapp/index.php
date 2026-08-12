<?php
// Portal de Conexão WhatsApp PriceXP
$api_key = 'pricexp_evo_api_key_2833441530';
$instance = 'pricexp-bot';

// Auto-detecta o IP exato da VPS/Docker container
$possible_urls = [
    'http://172.17.0.1:8085',
    'http://172.18.0.1:8085',
    'http://172.19.0.1:8085',
    'http://172.20.0.1:8085',
    'http://172.21.0.1:8085',
    'http://172.22.0.1:8085',
    'http://evolution-api:8080',
    'http://localhost:8085'
];

$api_url = 'http://172.17.0.1:8085';
$debug_log = [];

foreach ($possible_urls as $url) {
    $ch = curl_init($url . '/instance/fetchInstances');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $api_key]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $debug_log[] = "$url => HTTP $http_code " . ($err ? "($err)" : "");
    if ($http_code === 200) {
        $api_url = $url;
        break;
    }
}

function evo_request($url, $method = 'GET', $data = null) {
    global $api_key;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $api_key
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Ações do botão
$action = $_GET['action'] ?? '';
if ($action === 'recreate') {
    evo_request($api_url . '/instance/delete/' . $instance, 'DELETE');
    sleep(1);
    evo_request($api_url . '/instance/create', 'POST', [
        'instanceName' => $instance,
        'qrcode' => true,
        'number' => '552833441530',
        'integration' => 'WHATSAPP-BAILEYS'
    ]);
    sleep(1);
    evo_request($api_url . '/instance/restart/' . $instance, 'POST');
    sleep(1);
    evo_request($api_url . '/instance/connect/' . $instance, 'GET');
    header('Location: index.php');
    exit;
}

// Consulta instâncias
$instances = evo_request($api_url . '/instance/fetchInstances');
$status = 'desconectado';
$is_open = false;

if (is_array($instances) && !empty($instances)) {
    foreach ($instances as $inst) {
        if (($inst['name'] ?? '') === $instance) {
            $status = $inst['connectionStatus'] ?? 'close';
            if ($status === 'open') $is_open = true;
        }
    }
}

// Acorda a instância se estiver em estado 'close'
if ($status === 'close' && empty($action)) {
    evo_request($api_url . '/instance/restart/' . $instance, 'POST');
}

// Se não houver instância, cria automaticamente
if (empty($instances) || (is_array($instances) && count($instances) === 0)) {
    evo_request($api_url . '/instance/create', 'POST', [
        'instanceName' => $instance,
        'qrcode' => true,
        'number' => '552833441530',
        'integration' => 'WHATSAPP-BAILEYS'
    ]);
}

// Busca QR Code
$qr_data = null;
$pairing_code = null;
$qr_debug = null;

if (!$is_open) {
    $qr_resp = evo_request($api_url . '/instance/connect/' . $instance);
    $qr_debug = json_encode($qr_resp);
    
    if (!empty($qr_resp['base64'])) {
        $qr_data = $qr_resp['base64'];
    } elseif (!empty($qr_resp['qrcode']['base64'])) {
        $qr_data = $qr_resp['qrcode']['base64'];
    } elseif (!empty($qr_resp['code'])) {
        $qr_data = $qr_resp['code'];
    }
    
    // Tenta também pegar o código de pareamento
    $pair_resp = evo_request($api_url . '/instance/connect/' . $instance . '?number=552833441530');
    $pairing_code = $pair_resp['code'] ?? $pair_resp['pairingCode'] ?? null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PriceXP — Conexão WhatsApp</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0f19;
            --card-bg: #151c2c;
            --primary: #25D366;
            --primary-dark: #128C7E;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --border: #232d42;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem; }
        .card { background: var(--card-bg); border: 1px solid var(--border); border-radius: 24px; max-width: 480px; width: 100%; padding: 2.2rem; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); }
        .logo-wrap { margin-bottom: 1.5rem; display: flex; justify-content: center; }
        .logo-wrap img { height: 55px; width: auto; object-fit: contain; }
        h1 { font-size: 1.45rem; font-weight: 800; margin-bottom: 0.4rem; color: #fff; }
        p.desc { font-size: 0.88rem; color: var(--muted); margin-bottom: 1.5rem; }
        .status-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.45rem 1.2rem; border-radius: 30px; font-weight: 700; font-size: 0.85rem; margin-bottom: 1.5rem; }
        .status-open { background: rgba(37, 211, 102, 0.15); color: #25D366; border: 1px solid rgba(37, 211, 102, 0.3); }
        .status-connecting { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .status-close { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .qr-box { background: #fff; padding: 1.2rem; border-radius: 20px; display: inline-block; margin-bottom: 1.5rem; box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .qr-box img { width: 220px; height: 220px; display: block; border-radius: 8px; }
        .pairing-box { background: #1a2336; border: 1px solid var(--border); border-radius: 14px; padding: 1rem; margin-bottom: 1.5rem; }
        .pairing-code { font-family: monospace; font-size: 1.8rem; font-weight: 800; letter-spacing: 4px; color: #25D366; margin-top: 0.25rem; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.85rem; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; font-weight: 700; text-decoration: none; border: none; cursor: pointer; font-size: 0.95rem; transition: all 0.2s; }
        .btn:hover { opacity: 0.92; transform: translateY(-1px); }
        .btn-secondary { background: rgba(255,255,255,0.05); border: 1px solid var(--border); color: var(--muted); margin-top: 0.75rem; }
        .btn-secondary:hover { color: #fff; background: rgba(255,255,255,0.1); }
        .instructions { text-align: left; background: #0f1624; border: 1px solid var(--border); border-radius: 14px; padding: 1.1rem; margin-top: 1.5rem; font-size: 0.82rem; color: var(--muted); }
        .instructions ol { padding-left: 1.2rem; margin-top: 0.5rem; }
        .instructions li { margin-bottom: 0.4rem; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <img src="../assets/pricexp_horizontal.png" alt="PriceXP" onerror="this.parentElement.innerHTML='<h2 style=\'color:#25D366;\'>PriceXP</h2>'">
        </div>
        <h1>Conexão WhatsApp Business</h1>
        <p class="desc">Número Bot: <strong>(28) 3344-1530</strong></p>

        <?php if ($is_open): ?>
            <div class="status-badge status-open">
                🟢 BOT WHATSAPP CONECTADO E ATIVO
            </div>
            <p style="color: #4ade80; margin-bottom: 1.5rem; font-weight: 600; font-size: 0.95rem; line-height: 1.5;">
                ✅ O bot PriceXP já está pronto para receber mensagens e áudios de lançamentos financeiros!
            </p>
            <a href="index.php" class="btn">🔄 Verificar Status</a>
        <?php else: ?>
            <div class="status-badge <?= $status === 'connecting' ? 'status-connecting' : 'status-close' ?>">
                ⚡ Status: <?= strtoupper($status) ?>
            </div>

            <?php if ($qr_data): ?>
                <div class="qr-box">
                    <img src="<?= $qr_data ?>" alt="QR Code WhatsApp">
                </div>
            <?php else: ?>
                <div style="background: rgba(255,255,255,0.03); border: 1px dashed var(--border); border-radius: 16px; padding: 2rem 1rem; margin-bottom: 1.5rem;">
                    <p style="font-size: 0.88rem; color: var(--muted);">
                        📱 Gerando QR Code em tempo real...<br>Clique em atualizar abaixo em instantes.
                    </p>
                </div>
            <?php endif; ?>

            <?php if (!empty($pairing_code)): ?>
                <div class="pairing-box">
                    <div style="font-size: 0.78rem; color: var(--muted);">CÓDIGO DE PAREAMENTO</div>
                    <div class="pairing-code"><?= htmlspecialchars($pairing_code) ?></div>
                </div>
            <?php endif; ?>

            <a href="index.php" class="btn">🔄 Atualizar QR Code</a>
            <a href="index.php?action=recreate" class="btn btn-secondary">⚡ Reiniciar Conexão com WhatsApp</a>
        <?php endif; ?>

        <div class="instructions">
            <strong style="color: #fff;">📲 Como Conectar no Celular:</strong>
            <ol>
                <li>Abra o WhatsApp Business no celular do número <strong>(28) 3344-1530</strong>.</li>
                <li>Vá em <strong>⋮ Menu → Aparelhos Conectados → Conectar um aparelho</strong>.</li>
                <li>Aponta a câmera para o QR Code acima!</li>
            </ol>
        </div>

        <div style="margin-top: 1rem; font-size: 0.72rem; color: #6b7280; text-align: left; background: #000; padding: 0.75rem; border-radius: 8px; font-family: monospace;">
            <strong>Diagnostics:</strong> Active: <?= $api_url ?><br>
            <strong>Status:</strong> <?= htmlspecialchars($status) ?><br>
            <strong>QR Output:</strong> <?= htmlspecialchars($qr_debug ?? 'N/A') ?><br>
            <?php foreach ($debug_log as $log): ?>
                - <?= htmlspecialchars($log) ?><br>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
