<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testando Conectividade com o Host do Mailcow na Porta HTTPS (443)</h2>";

$host = "mail.bitdesksupport.com.br";
$port = 443;

echo "Resolvendo IP de $host... ";
$resolved = gethostbyname($host);
echo "<strong>$resolved</strong><br>";

echo "Conectando em $resolved:$port... ";
$errno = 0;
$errstr = '';
$socket = @fsockopen($resolved, $port, $errno, $errstr, 5);

if ($socket) {
    echo "<span style='color:green;'><b>SUCESSO! Conectado na porta 443 do Host.</b></span><br>";
    fclose($socket);
} else {
    echo "<span style='color:red;'><b>FALHA:</b> $errstr ($errno)</span><br><br>";
}

echo "<h4>Fim do teste.</h4>";
?>