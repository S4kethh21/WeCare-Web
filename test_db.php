<?php
/**
 * WeCare Hospital — Safe Database Connection Diagnostic
 * 
 * Safely validates database connectivity and table readiness for Render / Cloud MySQL.
 * IMPORTANT: This diagnostic never displays passwords or connection strings.
 */
header('Content-Type: text/plain; charset=UTF-8');

echo "=== WeCare Hospital Database Diagnostic ===\n\n";

$isProduction = (getenv('RENDER') !== false) ||
                (getenv('RENDER_SERVICE_ID') !== false) ||
                (getenv('APP_ENV') === 'production') ||
                (file_exists('/.dockerenv')) ||
                (!empty(getenv('DB_HOST')) && !in_array(strtolower((string)getenv('DB_HOST')), ['localhost', '127.0.0.1', '::1'], true));

$dbHost = getenv('DB_HOST') ?: '';
$dbPort = (int) (getenv('DB_PORT') ?: 3306);
$dbUser = getenv('DB_USER') ?: '';
$dbName = getenv('DB_NAME') ?: '';
$hasPassword = (getenv('DB_PASSWORD') !== false && getenv('DB_PASSWORD') !== '');

echo "Deployment Mode    : " . ($isProduction ? "Production (Render)" : "Local Development (XAMPP)") . "\n";
if ($isProduction) {
    echo "Configured Host    : " . ($dbHost !== '' ? htmlspecialchars($dbHost) : "[Not set — Configure DB_HOST in Render]") . "\n";
} else {
    echo "Configured Host    : " . ($dbHost !== '' ? htmlspecialchars($dbHost) : "[Local default: 127.0.0.1]") . "\n";
}
echo "Configured Port    : " . $dbPort . "\n";
echo "Configured Database: " . ($dbName !== '' ? htmlspecialchars($dbName) : ($isProduction ? "[Not set — Configure DB_NAME in Render]" : "[Default: hospital_management]")) . "\n";
echo "Configured User    : " . ($dbUser !== '' ? htmlspecialchars($dbUser) : ($isProduction ? "[Not set — Configure DB_USER in Render]" : "[Default: root]")) . "\n";
echo "Password Provided  : " . ($hasPassword ? "YES (hidden)" : "NO / Empty") . "\n\n";

// Connect through standard db.php handler
require_once __DIR__ . '/db.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    echo "Result: FAILED - Connection object not created.\n";
    exit(1);
}

// 1. Basic ping
$ping = $conn->query("SELECT 1 AS ok");
if (!$ping) {
    echo "Result: FAILED - Query test failed.\n";
    exit(1);
}

echo "Database Connection: SUCCESSFUL!\n";
echo "Server Version     : " . $conn->server_info . "\n";
echo "Host Protocol      : " . $conn->host_info . "\n";
echo "Character Set      : " . $conn->character_set_name() . "\n\n";

// 2. Verify all required application tables
$requiredTables = ['patients', 'doctors', 'appointments', 'bills', 'prescriptions'];
$foundTables = [];
$res = $conn->query("SHOW TABLES");
if ($res) {
    while ($row = $res->fetch_array()) {
        $foundTables[] = strtolower($row[0]);
    }
}

$missing = [];
echo "Table Structure Verification:\n";
foreach ($requiredTables as $tbl) {
    $exists = in_array($tbl, $foundTables, true);
    if (!$exists) {
        $missing[] = $tbl;
    }
    echo "  - " . str_pad($tbl, 16) . ": " . ($exists ? "OK [Found]" : "MISSING (import schema.sql)") . "\n";
}

echo "\n";
if (!empty($missing)) {
    echo "WARNING: One or more tables are missing. Please import schema.sql into your database.\n";
} else {
    // Count seeded doctors
    $docCount = $conn->query("SELECT COUNT(*) FROM doctors");
    $numDocs = $docCount ? (int)$docCount->fetch_row()[0] : 0;
    echo "Specialists Seeded : $numDocs verified doctors\n";
    echo "\nStatus: All database checks PASSED. Application is ready.\n";
}
