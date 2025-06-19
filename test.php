<?php
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain'); // Not JSON yet

echo "--- DEBUG OUTPUT ---\n";

// Check if psad is executable
$psadTest = shell_exec('sudo -n /usr/sbin/psad --Status 2>&1');
echo "psad output:\n$psadTest\n";

// Check file access
echo "status.out readable? ";
echo is_readable('/var/log/psad/status.out') ? "yes\n" : "no\n";

echo "status.out content:\n";
echo @file_get_contents('/var/log/psad/status.out') ?: "CANNOT READ FILE\n";

echo "\n--- END DEBUG ---\n";
exit;