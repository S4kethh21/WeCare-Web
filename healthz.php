<?php
/**
 * WeCare Hospital — Health Check Endpoint
 *
 * Responds with HTTP 200 so container deployment health checks on Render succeed.
 * Returns JSON status with web server and database health diagnostics.
 */
header('Content-Type: application/json; charset=UTF-8');
http_response_code(200);

$dbConfigured = !empty(getenv('DB_HOST'));
$dbConnected = false;
$dbStatus = 'Not configured (set DB_HOST in Render Environment)';

if ($dbConfigured) {
    mysqli_report(MYSQLI_REPORT_OFF);
    $host = getenv('DB_HOST');
    $port = (int) (getenv('DB_PORT') ?: 3306);
    if (strpos($host, ':') !== false) {
        [$h, $p] = explode(':', $host, 2);
        $host = $h;
        if (is_numeric($p)) {
            $port = (int) $p;
        }
    }
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASSWORD') !== false ? (string) getenv('DB_PASSWORD') : '';
    $name = getenv('DB_NAME') ?: '';

    $test = @new mysqli($host, $user, $pass, $name, $port);
    if ($test && !$test->connect_error) {
        $dbConnected = true;
        $dbStatus = 'Connected';
        $test->close();
    } else {
        $dbStatus = 'Unavailable: ' . ($test ? $test->connect_error : 'Connection error');
    }
}

echo json_encode([
    'status' => 'ok',
    'service' => 'wecare-hospital',
    'timestamp' => date('c'),
    'web_server' => 'running',
    'php_version' => PHP_VERSION,
    'database' => [
        'configured' => $dbConfigured,
        'connected' => $dbConnected,
        'status' => $dbStatus,
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
