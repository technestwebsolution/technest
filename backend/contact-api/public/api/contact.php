<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function locateProjectRoot(): ?string
{
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) && is_string($_SERVER['DOCUMENT_ROOT'])
        ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')
        : '';
    $candidates = array_filter(array_unique([
        dirname(__DIR__, 2),
        dirname(__DIR__, 3),
        $documentRoot,
        $documentRoot !== '' ? dirname($documentRoot) : '',
    ]));

    foreach ($candidates as $candidate) {
        if (is_file($candidate . '/vendor/autoload.php')) return $candidate;
    }
    return null;
}

function respond(bool $ok, string $status, string $message, int $httpStatus = 200): never
{
    http_response_code($httpStatus);
    echo json_encode(['ok' => $ok, 'status' => $status, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function postValue(string $key, int $maxLength): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) return '';
    $value = trim(str_replace("\0", '', $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function envValue(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return is_string($value) && trim($value) !== '' ? trim($value) : $default;
}

$projectRoot = locateProjectRoot();

if ($projectRoot === null) {
    error_log('[technest-contact] Composer autoload file was not found. Run composer install.');

    respond(
        false,
        'server_error',
        'The mail service is not configured yet.',
        500
    );
}

require $projectRoot . '/vendor/autoload.php';

Dotenv::createImmutable($projectRoot)->safeLoad();

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$allowedOrigins = array_values(array_filter([
    envValue(
        'ALLOWED_ORIGIN_PRIMARY',
        'https://technestwebsolution.com'
    ),
    envValue(
        'ALLOWED_ORIGIN_WWW',
        'https://www.technestwebsolution.com'
    ),
    envValue(
        'ALLOWED_ORIGIN_PREVIEW',
        ''
    ),
]));

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (is_string($origin) && $origin !== '') {
    if (!in_array($origin, $allowedOrigins, true)) {
        respond(
            false,
            'forbidden',
            'This form request was rejected.',
            403
        );
    }

    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
}

if ($requestMethod === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($requestMethod !== 'POST') {
    header('Allow: POST, OPTIONS');

    respond(
        false,
        'method_not_allowed',
        'Only POST requests are allowed.',
        405
    );
}

if (postValue('website', 200) !== '') {
    respond(true, 'success', 'Thank you! Your message has been sent successfully.');
}

$formStartedAt = (int) postValue('form_started_at', 20);
$elapsedSeconds = time() - $formStartedAt;
if ($formStartedAt <= 0 || $elapsedSeconds < 2 || $elapsedSeconds > 7200) {
    respond(false, 'validation_error', 'Please refresh the page and submit the form again.', 422);
}

$fullName = preg_replace('/[\r\n]+/', ' ', postValue('full_name', 120)) ?? '';
$email = postValue('email', 190);
$phone = preg_replace('/[\r\n]+/', ' ', postValue('phone', 40)) ?? '';
$message = postValue('message', 5000);

$isValid = $fullName !== ''
    && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
    && preg_match('/^[0-9+()\-.\s]{7,40}$/', $phone) === 1
    && $message !== '';
if (!$isValid) respond(false, 'validation_error', 'Please check the entered details and try again.', 422);

$clientIp = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
$rateLimitFile = sys_get_temp_dir() . '/technest-contact-' . hash('sha256', $clientIp) . '.lock';
$lastSubmissionAt = is_file($rateLimitFile) ? (int) file_get_contents($rateLimitFile) : 0;
if ($lastSubmissionAt > 0 && (time() - $lastSubmissionAt) < 60) {
    header('Retry-After: 60');
    respond(false, 'rate_limited', 'Please wait a minute before sending another message.', 429);
}
file_put_contents($rateLimitFile, (string) time(), LOCK_EX);

$smtpHost = envValue('MAIL_HOST', 'smtp.ionos.com');
$smtpPort = (int) envValue('MAIL_PORT', '587');
$smtpEncryption = strtolower(envValue('MAIL_ENCRYPTION', 'tls'));
$smtpUsername = envValue('MAIL_USERNAME');
$smtpPassword = envValue('MAIL_PASSWORD');
$fromAddress = envValue('MAIL_FROM_ADDRESS', $smtpUsername);
$fromName = envValue('MAIL_FROM_NAME', 'TechNest Web Solution');
$toAddress = envValue('MAIL_TO_ADDRESS');
$appTimezone = envValue('APP_TIMEZONE', 'Asia/Kolkata');
$smtpDebug = filter_var(envValue('MAIL_SMTP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

if ($smtpHost === '' || $smtpPort <= 0 || filter_var($smtpUsername, FILTER_VALIDATE_EMAIL) === false || $smtpPassword === '' || filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false || filter_var($toAddress, FILTER_VALIDATE_EMAIL) === false) {
    @unlink($rateLimitFile);
    error_log('[technest-contact] SMTP environment configuration is missing or invalid.');
    respond(false, 'server_error', 'The mail service is not configured correctly.', 500);
}

try { $timezone = new DateTimeZone($appTimezone); } catch (Throwable) { $timezone = new DateTimeZone('UTC'); }
$submittedAt = new DateTimeImmutable('now', $timezone);
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 500);
$safeName = escapeHtml($fullName);
$safeEmail = escapeHtml($email);
$safePhone = escapeHtml($phone);
$safeMessage = nl2br(escapeHtml($message));
$safeSubmittedAt = escapeHtml($submittedAt->format('d M Y, h:i A T'));
$safeIp = escapeHtml($clientIp);
$safeUserAgent = escapeHtml($userAgent);

$htmlBody = <<<HTML
<!doctype html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>New TechNest Contact Form Submission</title></head><body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#17213a;"><div style="max-width:680px;margin:0 auto;padding:28px 16px;"><div style="overflow:hidden;border:1px solid #dce5ef;border-radius:16px;background:#fff;"><div style="background:#087dcc;padding:22px 26px;color:#fff;"><h1 style="margin:0;font-size:22px;line-height:1.3;">New TechNest Contact Form Submission</h1><p style="margin:7px 0 0;font-size:14px;opacity:.9;">A visitor submitted the website contact form.</p></div><div style="padding:26px;"><table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;"><tr><td style="width:155px;padding:9px 0;font-weight:700;color:#515b70;vertical-align:top;">Full Name</td><td style="padding:9px 0;">{$safeName}</td></tr><tr><td style="padding:9px 0;font-weight:700;color:#515b70;vertical-align:top;">Email</td><td style="padding:9px 0;"><a href="mailto:{$safeEmail}" style="color:#087dcc;">{$safeEmail}</a></td></tr><tr><td style="padding:9px 0;font-weight:700;color:#515b70;vertical-align:top;">Phone</td><td style="padding:9px 0;">{$safePhone}</td></tr><tr><td style="padding:9px 0;font-weight:700;color:#515b70;vertical-align:top;">Submitted At</td><td style="padding:9px 0;">{$safeSubmittedAt}</td></tr></table><div style="margin-top:20px;border-radius:12px;background:#f5f8fc;padding:18px;"><div style="margin-bottom:8px;font-size:13px;font-weight:700;color:#515b70;">Message</div><div style="font-size:15px;line-height:1.7;color:#17213a;">{$safeMessage}</div></div><div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5eaf0;font-size:11px;line-height:1.6;color:#8791a3;">IP: {$safeIp}<br>Browser: {$safeUserAgent}</div></div></div></div></body></html>
HTML;

$plainBody = "New TechNest Contact Form Submission\n\nFull Name: {$fullName}\nEmail: {$email}\nPhone: {$phone}\nSubmitted At: " . $submittedAt->format('d M Y, h:i A T') . "\nIP Address: {$clientIp}\n\nMessage:\n{$message}\n";

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->Port = $smtpPort;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Timeout = 20;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->SMTPSecure = ($smtpEncryption === 'ssl' || $smtpEncryption === 'smtps') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    if ($smtpDebug) {
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        $mail->Debugoutput = static function (string $debugMessage, int $level): void { error_log('[technest-contact][smtp:' . $level . '] ' . $debugMessage); };
    }
    $mail->setFrom($fromAddress, $fromName);
    $mail->addAddress($toAddress);
    $mail->addReplyTo($email, $fullName);
    $mail->isHTML(true);
    $mail->Subject = 'New TechNest Website Contact Form Message';
    $mail->Body = $htmlBody;
    $mail->AltBody = $plainBody;
    $mail->send();
    respond(true, 'success', 'Thank you! Your message has been sent successfully.');
} catch (MailException | Throwable $exception) {
    @unlink($rateLimitFile);
    error_log('[technest-contact] Mail send failed: ' . $exception->getMessage());
    respond(false, 'send_error', 'We could not send your message right now. Please try again later.', 500);
}
