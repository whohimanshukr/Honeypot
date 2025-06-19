<?php
require 'db.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

$scanData = [];
$topIps = [];
$debug = [];
$portScans = [];
$alerted = [];

// --- 1. Run psad status ---
$output = shell_exec('sudo -n /usr/sbin/psad --Status 2>&1');
if (!$output || str_contains($output, 'not permitted')) {
    echo json_encode(["error" => "No output from psad or permission denied", "debug" => $output]);
    exit;
}
$debug['raw'] = $output;

// --- 2. Extract summary metrics ---
$scanData['total_sources'] = preg_match('/Total scan sources:\s+(\d+)/', $output, $m) ? (int)$m[1] : 0;
$scanData['blocked_ips'] = preg_match('/Blocked IPs:\s+(\d+)/', $output, $m) ? (int)$m[1] : 0;

// --- 3. Extract top attackers ---
$lines = explode("\n", $output);
$parsingIps = false;
foreach ($lines as $line) {
    if (str_contains($line, 'Top 25 attackers:')) {
        $parsingIps = true;
        continue;
    }
    if ($parsingIps && trim($line) === '') break;

    if ($parsingIps) {
        if (preg_match('/^\s*(\d{1,3}(?:\.\d{1,3}){3})\s+DL:\s*(\d+),\s+Packets:\s*(\d+),/i', $line, $m)) {

            $topIps[] = [
                'ip' => $m[1],
                'danger_level' => (int)$m[2],
                'packets' => (int)$m[3],
                'sig_count' => 0
            ];
        }
    }
}

// --- 4. Insert into psad_logs ---
$now = date('Y-m-d H:i:s');
$stmt = $pdo->prepare("INSERT INTO psad_logs (timestamp, total_sources, blocked_ips) VALUES (?, ?, ?)");
$stmt->execute([$now, $scanData['total_sources'], $scanData['blocked_ips']]);
$log_id = $pdo->lastInsertId();

// --- 5. Insert top attackers into psad_top_ips ---
$ipStmt = $pdo->prepare("INSERT INTO psad_top_ips (psad_log_id, ip, scan_count, timestamp) VALUES (?, ?, ?, ?)");
foreach ($topIps as $row) {
    $ipStmt->execute([$log_id, $row['ip'], $row['packets'], $now]);
}

// --- 6. Parse status.out for port scans ---
$statusOut = @file('/var/log/psad/status.out');
if ($statusOut) {
    $scanInsert = $pdo->prepare("INSERT INTO port_scan_logs (ip_address, port, protocol, timestamp, scan_type) VALUES (?, ?, ?, ?, ?)");

    $currentIP = null;

foreach ($statusOut as $line) {
    if (preg_match('/SRC:\s+(\d+\.\d+\.\d+\.\d+)/', $line, $srcMatch)) {
        $currentIP = $srcMatch[1];
            if ($currentIP === '127.0.0.1'||$ip ==='8.8.8.8') {
            $currentIP = null;
            continue;
        }

        continue;
    }

    if ($currentIP && preg_match('/Scanned ports:\s+(TCP|UDP)\s+([\d,-]+)/i', $line, $portMatch)) {
        $protocol = strtoupper($portMatch[1]);
        $ports = explode(',', preg_replace('/\s+/', '', $portMatch[2]));
        $timestamp = date('Y-m-d H:i:s');

        foreach ($ports as $portRange) {
            if (strpos($portRange, '-') !== false) {
                [$start, $end] = explode('-', $portRange);
                for ($p = (int)$start; $p <= (int)$end; $p++) {
                    insertAndAlert($scanInsert, $currentIP, $p, $protocol, $timestamp);
                }
            } else {
                $p = (int)$portRange;
                insertAndAlert($scanInsert, $currentIP, $p, $protocol, $timestamp);
            }
        }

        // Reset only after port line is parsed
        $currentIP = null;
    }
}
}

// --- 7. Insert & Alert Function ---
function insertAndAlert($stmt, $ip, $port, $protocol, $timestamp) {
    global $portScans, $alerted;

    $scan_type = $protocol === 'TCP' ? 'SYN Scan' : 'UDP Scan';
     if ($ip === '127.0.0.1'||$ip ==='8.8.8.8') return;

    $stmt->execute([$ip, $port, $protocol, $timestamp, $scan_type]);

    $portScans[] = [
        'ip' => $ip,
        'port' => $port,
        'protocol' => $protocol,
        'timestamp' => $timestamp,
        'scan_type' => $scan_type
    ];

    $key = "$ip:$port:$protocol";
    if (!in_array($key, $alerted)) {
        $alerted[] = $key;
        sendAlert($ip, $port, $protocol, $scan_type, $timestamp);
    }
}

// --- 8. Email Alert Function ---
function sendAlert($ip, $port, $protocol, $scan_type, $timestamp) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'hakcedur.mr@gmail.com'; // Gmail
        $mail->Password = 'xxxmacempztdpmbu';      // App Password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->Timeout = 10;

        $mail->setFrom('armank8000@gmail.com', 'PSAD Monitor');
        $mail->addAddress('armank8000@gmail.com');
        $mail->Subject = "🚨 Port Scan Detected";
        $mail->Body = <<<EOD
A potential port scan was detected.

IP Address: $ip
Port: $port
Protocol: $protocol
Scan Type: $scan_type
Time: $timestamp
EOD;

        $mail->send();
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
    }
}

// --- 9. Final Output ---
echo json_encode([
    'status' => 'ok',
    'summary' => $scanData,
    'top_ips' => $topIps,
    'scans_logged' => count($portScans),
    'recent_scans' => $portScans,
    'debug' => $debug
]);
