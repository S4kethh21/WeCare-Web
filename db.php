<?php
/**
 * Database connection using MySQLi
 * XAMPP default: root, no password. You can set $pass below or use env HOSPITAL_MYSQL_PASS.
 */
$user = 'root';
$pass = '';
if ($pass === '') {
    $envPass = getenv('HOSPITAL_MYSQL_PASS');
    if ($envPass !== false && $envPass !== '') {
        $pass = $envPass;
    }
}
$dbname = 'hospital_management';
$port = 3306;

$attempts = [
    ['127.0.0.1', $port],
    ['localhost', $port],
];
if ((int) $port === 3306) {
    $attempts[] = ['127.0.0.1', 3307];
    $attempts[] = ['localhost', 3307];
}

$conn = null;
$lastError = '';

mysqli_report(MYSQLI_REPORT_OFF);

foreach ($attempts as $pair) {
    [$host, $p] = $pair;

    // Fast-path: Connect directly to existing database in a single round-trip
    $try = new mysqli($host, $user, $pass, $dbname, (int) $p);
    if (!$try->connect_error) {
        $conn = $try;
        break;
    }

    $lastError = $try->connect_error;

    // Fallback: If database is missing (error 1049) or initial setup is needed
    $initTry = new mysqli($host, $user, $pass, '', (int) $p);
    if ($initTry->connect_error) {
        $lastError = $initTry->connect_error;
        continue;
    }

    $dbSafe = '`' . str_replace('`', '``', $dbname) . '`';
    if (!$initTry->query('CREATE DATABASE IF NOT EXISTS ' . $dbSafe)) {
        $lastError = $initTry->error;
        $initTry->close();
        continue;
    }

    if (!$initTry->select_db($dbname)) {
        $lastError = $initTry->error;
        $initTry->close();
        continue;
    }

    // First-time setup: no tables yet — run schema.sql
    $hasTables = $initTry->query("SHOW TABLES LIKE 'doctors'");
    if ($hasTables === false) {
        $lastError = $initTry->error;
        $initTry->close();
        continue;
    }
    if ($hasTables->num_rows === 0) {
        $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'schema.sql';
        $sql = @file_get_contents($schemaFile);
        if ($sql !== false && $sql !== '') {
            if (!$initTry->multi_query($sql)) {
                $lastError = $initTry->error;
                $initTry->close();
                continue;
            }
            do {
                if ($res = $initTry->store_result()) {
                    $res->free();
                }
            } while ($initTry->more_results() && $initTry->next_result());
            if ($initTry->errno !== 0) {
                $lastError = $initTry->error;
                $initTry->close();
                continue;
            }
        }
    }

    $conn = $initTry;
    break;
}

mysqli_report(MYSQLI_REPORT_ERROR);

if ($conn === null) {
    $hint = '<br><br><strong>Could not open MySQL or create the database.</strong><br>'
        . '1. Run <code>serve.bat</code> so MySQL starts, or start MySQL in XAMPP.<br>'
        . '2. If <code>root</code> has a password, set <code>$pass</code> in <code>db.php</code> or <code>HOSPITAL_MYSQL_PASS</code>.';

    die(
        'Database connection failed: ' . htmlspecialchars($lastError) . $hint
    );
}

$conn->set_charset('utf8mb4');
