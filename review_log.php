<?php
header("Content-Type: application/json");
require 'db.php'; // Uses $pdo instead of mysqli

// GET: return suspicious logs for user review
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT id, username, ip, device_type, prediction, created_at FROM attack_logs WHERE reviewed = 0 ORDER BY created_at DESC LIMIT 100");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["logs" => $rows]);
    exit;
}

// POST: user review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!isset($input['id']) || !isset($input['label'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing ID or label."]);
        exit;
    }

    $id = intval($input['id']);
    $label = ($input['label'] === 'suspicious') ? 'suspicious' : 'normal';

    $stmt = $pdo->prepare("UPDATE attack_logs SET prediction = ?, reviewed = 1 WHERE id = ?");
    $success = $stmt->execute([$label, $id]);

    if ($success) {
        echo json_encode(["success" => true, "message" => "Log #$id marked as $label"]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to update log."]);
    }

    exit;
}

// OPTIONS: review stats
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN reviewed = 1 THEN 1 ELSE 0 END) as reviewed,
            SUM(CASE WHEN prediction = 'suspicious' THEN 1 ELSE 0 END) as suspicious,
            SUM(CASE WHEN prediction = 'normal' THEN 1 ELSE 0 END) as normal
        FROM attack_logs
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($stats);
    exit;
}
?>
