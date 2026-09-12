<?php

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, bool $success, string $message): never
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, false, 'Method not allowed.');
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) respond(413, false, 'Request too large.');
$source = file_get_contents(__DIR__ . '/config/config.js');
if ($source === false || !preg_match('/window\.SITE_CONFIG\s*=\s*(\{.*\})\s*;/s', $source, $matches)) respond(500, false, 'Configuration unavailable.');
$config = json_decode($matches[1], true);
if (!is_array($config)) respond(500, false, 'Configuration unavailable.');
foreach (['name', 'email', 'service', 'space', 'message', 'privacy_consent', 'website_check'] as $key) {
    if (isset($_POST[$key]) && !is_string($_POST[$key])) respond(422, false, 'Invalid form data.');
}
if (trim($_POST['website_check'] ?? '') !== '') respond(422, false, 'Unable to process this enquiry.');
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$service = trim($_POST['service'] ?? '');
$space = trim($_POST['space'] ?? '');
$message = trim($_POST['message'] ?? '');
if (strlen($name) < 2 || strlen($name) > 100 || strlen($message) < 10 || strlen($message) > 5000 || strlen($space) > 150) respond(422, false, 'Please check your name and message.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || preg_match('/[\r\n]/', $email)) respond(422, false, 'Please enter a valid email address.');
if (!in_array($service, ['water-extraction', 'structural-drying', 'damage-restoration'], true)) respond(422, false, 'Please choose a service.');
if (($_POST['privacy_consent'] ?? '') !== '1') respond(422, false, 'Please confirm the privacy policy.');
$recipient = $config['email'] ?? '';
if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $recipient) || str_ends_with(strtolower($recipient), '.example')) respond(503, false, 'Please configure a delivery email address before sending enquiries.');
session_start();
if (isset($_SESSION['last_enquiry']) && time() - $_SESSION['last_enquiry'] < 30) respond(429, false, 'Please wait before sending another enquiry.');
$_SESSION['last_enquiry'] = time();
session_write_close();
$subject = 'Website enquiry: ' . $service;
$body = "Name: $name\nEmail: $email\nService: $service\nAffected space: $space\nPrivacy consent: accepted\n\n$message";
$headers = ['From' => $recipient, 'Reply-To' => $email, 'Content-Type' => 'text/plain; charset=UTF-8', 'MIME-Version' => '1.0'];
if (!function_exists('mail') || !mail($recipient, $subject, $body, $headers)) respond(502, false, 'Your enquiry could not be sent. Please try email.');
respond(200, true, $config['formSuccessMessage'] ?? 'Успешно отправлено');
