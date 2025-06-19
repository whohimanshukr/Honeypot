<?php
require __DIR__ . '/db.php';
require 'vendor/autoload.php'; // PHPMailer

// $python = "C:\\Users\\arman\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";
$python = "/home/kali/honeypot-env/bin/python3 ";  
$script = "/opt/lampp/htdocs/honeypod/predict_login.py ";// <-- Virtualenv Python

$username = $_POST['username'] ?? '';
$honeypot_id = $_POST['honeypot_id'] ?? 'honeypot1';
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
// $ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$timestamp = date('Y-m-d H:i:s');

// Detect device type from User-Agent
function getDeviceType($agent) {
    if (preg_match('/mobile/i', $agent)) return 'Android';
    elseif (preg_match('/tablet|ipad/i', $agent)) return 'Tablet';
    elseif (preg_match('/macintosh/i', $agent)) return 'Mac';
    elseif (preg_match('/windows/i', $agent)) return 'Windows';
    elseif (preg_match('/linux/i', $agent)) return 'Linux';
    else return 'linux';
}


$device_used = getDeviceType($user_agent);
// === Run Python ML model ===
$command = escapeshellcmd($python.$script. escapeshellarg(json_encode([
    'username' => $username,
    'ip' => $ip,
    'device_type' => $device_used
])));
$output = shell_exec($command);
$prediction = trim($output);

// === Insert into DB ===
$stmt = $pdo->prepare("INSERT INTO attack_logs (honeypot_id, username, ip, device_type, prediction, reviewed, created_at) 
                       VALUES (?, ?, ?, ?, ?, 0, ?)");
$stmt->execute([$honeypot_id, $username, $ip, $device_used, $prediction, $timestamp]);
$log_id = $pdo->lastInsertId();

// === Auto-block and Email Alert ===
if ($prediction === 'suspicious') {
    // if you want to block
    // $blockUntil = date('Y-m-d H:i:s', strtotime('+24 hours'));
    // $block = $pdo->prepare("INSERT INTO blocked_ips (ip, blocked_until) 
    //                         VALUES (?, ?) ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until)");
    // $block->execute([$ip, $blockUntil]);

    // Email alert logic
    try {
        $checkStmt = $pdo->prepare("SELECT last_alert_sent FROM email_alert_log WHERE ip_address = ?");
        $checkStmt->execute([$ip]);
        $lastAlert = $checkStmt->fetchColumn();

        $now = new DateTime();
        $sendAlert = true;

        if ($lastAlert) {
            $lastAlertTime = new DateTime($lastAlert);
            $diff = $now->getTimestamp() - $lastAlertTime->getTimestamp();
            if ($diff < 3600) $sendAlert = false; // skip if less than 1 hour
        }

        if ($sendAlert) {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'hakcedur.mr@gmail.com';
            $mail->Password = 'xxxmacempztdpmbu'; // App password only
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('armank8000@gmail.com', 'PSAD Monitor');
            $mail->addAddress('armank8000@gmail.com');
            $mail->Subject = "🚨 Suspicious Login Detected";
            $mail->Body = "Login Details:\n\nIP: $ip\nUsername: $username\nDevice: $device_used\nPrediction: $prediction\nTime: $timestamp";
            $mail->send();

            // Log alert
            $updateStmt = $pdo->prepare("INSERT INTO email_alert_log (ip_address, last_alert_sent)
                                         VALUES (?, ?)
                                         ON DUPLICATE KEY UPDATE last_alert_sent = VALUES(last_alert_sent)");
            $updateStmt->execute([$ip, $now->format('Y-m-d H:i:s')]);
        }

    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
    }
}

echo json_encode(['status' => 'ok', 'message' => "Logged with prediction: $prediction", "device_type: $user_agent"]);
