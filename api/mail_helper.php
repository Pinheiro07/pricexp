<?php
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $messageHtml) {
    // Se os arquivos do PHPMailer não existirem ainda, faz um fallback silencioso
    if (!file_exists(__DIR__ . '/PHPMailer/PHPMailer.php')) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM . "\r\n";
        return @mail($to, $subject, $messageHtml, $headers);
    }

    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    $mail = new PHPMailer(true);

    try {
        // Configurações do Servidor SMTP
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';
        
        // Se o host for padrão ou vazio, tenta usar o e-mail local do PHP
        if (SMTP_HOST === 'mail.seudominio.com' || SMTP_HOST === '') {
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . SMTP_FROM . "\r\n";
            return @mail($to, $subject, $messageHtml, $headers);
        }

        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->Port       = SMTP_PORT;

        // Criptografia TLS ou SSL
        if (SMTP_SECURE === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (SMTP_SECURE === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        // Opções extras para evitar falhas de certificado autoassinado na VPS
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Remetente e Destinatário
        $mail->setFrom(SMTP_FROM, 'PriceXP');
        $mail->addAddress($to);

        // Conteúdo do e-mail (HTML)
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $messageHtml;
        $mail->AltBody = strip_tags($messageHtml);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        // Fallback final para mail() nativo se der erro
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM . "\r\n";
        return @mail($to, $subject, $messageHtml, $headers);
    }
}
?>
