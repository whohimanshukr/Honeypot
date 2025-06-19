<?php
header("Content-Type: application/json");
require 'db.php'; // Uses $pdo instead of mysqli

$response = [
    'activities'=>[],
    'port_scans' => [],
    'attack_types' => [],
    'time_series' => [
        'labels' => [],
        'data' => []
    ],
    'ssh_logins' => [],
    'psad' => [
        'top_ips' => [],
        'summary' => []
    ],
    'ssh_commands' => [] // ✅ Added for recent shell activity
];

// 1. Attack type distribution
$attackQuery = $pdo->query("SELECT prediction, COUNT(*) as count FROM attack_logs GROUP BY prediction");
while ($row = $attackQuery->fetch()) {
    $response['attack_types'][$row['prediction']] = (int)$row['count'];
}

// 2. Suspicious attacks over last 12 hours
$timeWindow = 12;
$dataMap = [];
$labels = [];

$currentHour = (int)date('G');
for ($i = $timeWindow - 1; $i >= 0; $i--) {
    $hour = ($currentHour - $i + 24) % 24;
    $label = sprintf('%02d:00', $hour);
    $labels[] = $label;
    $dataMap[$hour] = 0;
}

$timeQuery = $pdo->query("
    SELECT HOUR(created_at) as hour, COUNT(*) as count
    FROM attack_logs
    WHERE prediction = 'suspicious' AND created_at >= NOW() - INTERVAL $timeWindow HOUR
    GROUP BY HOUR(created_at)
");

while ($row = $timeQuery->fetch()) {
    $dataMap[(int)$row['hour']] = (int)$row['count'];
}

$finalData = [];
foreach ($labels as $label) {
    $hour = (int)explode(':', $label)[0];
    $finalData[] = $dataMap[$hour];
}

$response['time_series']['labels'] = $labels;
$response['time_series']['data'] = $finalData;

// 3. SSH login summary
try {
    $sshQuery = $pdo->query("SELECT success, COUNT(*) as count FROM ssh_logs GROUP BY success");
    while ($row = $sshQuery->fetch()) {
        $label = $row['success'] ? 'Success' : 'Fail';
        $response['ssh_logins'][$label] = (int)$row['count'];
    }
} catch (Exception $e) {
    $response['ssh_logins'] = ["Fail" => 0, "Success" => 0];
}

// 4. PSAD: top scan IPs (latest)
$latestTs = $pdo->query("SELECT MAX(timestamp) FROM psad_top_ips")->fetchColumn();
if ($latestTs) {
    $ipStmt = $pdo->prepare("
        SELECT ip, scan_count 
        FROM psad_top_ips 
        WHERE timestamp = ? 
        ORDER BY scan_count DESC 
        LIMIT 5
    ");
    $ipStmt->execute([$latestTs]);
    while ($row = $ipStmt->fetch()) {
        $response['psad']['top_ips'][] = [
            'ip' => $row['ip'],
            'count' => (int)$row['scan_count']
        ];
    }
}

// 5. PSAD summary
try {
    $psadStats = $pdo->query("SELECT total_sources, blocked_ips FROM psad_logs ORDER BY timestamp DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $response['psad']['total_sources'] = (int)($psadStats['total_sources'] ?? 0);
    $response['psad']['blocked_count'] = (int)($psadStats['blocked_ips'] ?? 0);
} catch (Exception $e) {
    $response['psad']['total_sources'] = 0;
    $response['psad']['blocked_count'] = 0;
}

// 6. Latest port scan logs (last 5 entries)
$portScanStmt = $pdo->query("SELECT ip_address, port, protocol, scan_type, timestamp FROM port_scan_logs ORDER BY timestamp DESC LIMIT 10");
$response['port_scans'] = $portScanStmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Attacker activity from ssh_command_logs
$cmdStmt = $pdo->query("SELECT ip_address, command, timestamp FROM ssh_command_logs ORDER BY timestamp DESC LIMIT 10");
$response['ssh_commands'] = $cmdStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Final JSON Output
echo json_encode($response);
