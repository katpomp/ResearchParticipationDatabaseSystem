<?php

require_once __DIR__ . "/lib/PHPMailer/Exception.php";
require_once __DIR__ . "/lib/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/lib/PHPMailer/SMTP.php";

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP settings are never hardcoded. Load order (later wins):
 * 1) Optional PHP file: getenv('SONA_MAIL_CONFIG') or config/mail.local.php
 * 2) Environment variables SONA_SMTP_* (good for production / containers)
 *
 * @return array{user:string,pass:string,host:string,port:int,secure:string,from_email:string,from_name:string}
 */
function sona_get_smtp_config(): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cfg = [
        'user' => '',
        'pass' => '',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'secure' => 'tls',
        'from_email' => '',
        'from_name' => 'CNU Research Participation System',
    ];

    $configPath = getenv('SONA_MAIL_CONFIG');
    if ($configPath === false || $configPath === '') {
        $configPath = dirname(__DIR__) . '/config/mail.local.php';
    }

    if (is_string($configPath) && is_readable($configPath)) {
        $loaded = require $configPath;
        if (is_array($loaded)) {
            foreach (['user', 'pass', 'host', 'secure', 'from_email', 'from_name'] as $k) {
                if (isset($loaded[$k]) && $loaded[$k] !== '') {
                    $cfg[$k] = (string) $loaded[$k];
                }
            }
            if (isset($loaded['port'])) {
                $cfg['port'] = (int) $loaded['port'];
            }
        }
    }

    $envPairs = [
        'SONA_SMTP_USER' => 'user',
        'SONA_SMTP_PASS' => 'pass',
        'SONA_SMTP_HOST' => 'host',
        'SONA_SMTP_PORT' => 'port',
        'SONA_SMTP_SECURE' => 'secure',
        'SONA_SMTP_FROM_EMAIL' => 'from_email',
        'SONA_SMTP_FROM_NAME' => 'from_name',
    ];
    foreach ($envPairs as $envKey => $cfgKey) {
        $v = getenv($envKey);
        if ($v !== false && $v !== '') {
            $cfg[$cfgKey] = $cfgKey === 'port' ? (int) $v : $v;
        }
    }

    if ($cfg['from_email'] === '' && $cfg['user'] !== '') {
        $cfg['from_email'] = $cfg['user'];
    }

    $cached = $cfg;
    return $cached;
}

function sona_send_plain_email(string $toEmail, string $subject, string $bodyText): bool
{
    $cfg = sona_get_smtp_config();
    if ($cfg['user'] === '' || $cfg['pass'] === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['user'];
        $mail->Password = $cfg['pass'];
        $mail->Port = $cfg['port'];
        $mail->CharSet = 'UTF-8';

        if (strtolower($cfg['secure']) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body = $bodyText;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send multipart email (HTML + plain-text fallback). Better rendering on mobile clients.
 */
function sona_send_html_email(string $toEmail, string $subject, string $htmlBody, string $plainBody): bool
{
    $cfg = sona_get_smtp_config();
    if ($cfg['user'] === '' || $cfg['pass'] === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $cfg['user'];
        $mail->Password = $cfg['pass'];
        $mail->Port = $cfg['port'];
        $mail->CharSet = 'UTF-8';

        if (strtolower($cfg['secure']) === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
