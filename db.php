<?php
/**
 * WeCare Hospital — Production-Safe Database Connection
 * 
 * Supports:
 * 1. Cloud MySQL on Render via explicit environment variables:
 *    DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME
 * 2. Provider connection URLs (DATABASE_URL, MYSQL_URL) as secondary fallback
 * 3. Safe local development fallback for XAMPP / local MySQL
 * 
 * Security:
 * - Credentials and passwords are NEVER exposed to the browser or in error output.
 * - mysqli error reporting is shielded during connection handshake.
 */

// 1. Read explicit cloud environment variables first (Preferred for Render)
$host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: getenv('MYSQLHOST') ?: '';
$port = (int) (getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: getenv('MYSQLPORT') ?: 3306);
$user = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: getenv('MYSQLUSER') ?: '';
$pass = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: getenv('MYSQLDATABASE') ?: '';

// 2. Secondary fallback: Connection URL (e.g. DATABASE_URL, MYSQL_URL, JAWSDB_URL)
if (empty($host)) {
    $dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL') ?: getenv('JAWSDB_URL') ?: getenv('CLEARDB_DATABASE_URL');
    if (!empty($dbUrl)) {
        $parsed = parse_url($dbUrl);
        if (!empty($parsed['host'])) {
            $host = $parsed['host'];
            $port = isset($parsed['port']) ? (int) $parsed['port'] : 3306;
            $user = $parsed['user'] ?? '';
            $pass = $parsed['pass'] ?? '';
            $dbname = isset($parsed['path']) ? ltrim($parsed['path'], '/') : '';
        }
    }
}

// 3. Determine if running in Cloud / Production mode or Local Development
$isCloud = !empty($host) && !in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);

$conn = null;
$lastError = '';
$lastErrno = 0;

// Suppress unhandled PHP connection warnings so credentials/addresses are not leaked
mysqli_report(MYSQLI_REPORT_OFF);

if ($isCloud) {
    // =========================================================================
    // PRODUCTION / CLOUD MYSQL MODE (Render, Railway, Aiven, etc.)
    // =========================================================================
    if (empty($user)) {
        $user = 'root';
    }
    if (empty($dbname)) {
        $dbname = 'hospital_management';
    }

    $try = @new mysqli($host, $user, $pass, $dbname, (int) $port);
    if (!$try->connect_error) {
        $conn = $try;
    } else {
        $lastError = $try->connect_error;
        $lastErrno = $try->connect_errno;
        // Log detailed error to server log (not browser)
        error_log(sprintf(
            '[WeCare DB] Cloud MySQL connection failed (Host: %s, Port: %d, DB: %s, Code: %d): %s',
            $host,
            $port,
            $dbname,
            $lastErrno,
            $lastError
        ));
    }
} else {
    // =========================================================================
    // LOCAL DEVELOPMENT FALLBACK (XAMPP / Local MySQL)
    // =========================================================================
    $localUser = $user !== '' ? $user : 'root';
    $localPass = $pass !== '' ? $pass : (getenv('HOSPITAL_MYSQL_PASS') !== false ? getenv('HOSPITAL_MYSQL_PASS') : '');
    $localDb = $dbname !== '' ? $dbname : 'hospital_management';
    $localPort = $port > 0 ? $port : 3306;

    $attempts = [
        ['127.0.0.1', $localPort],
        ['localhost', $localPort],
    ];
    if ($localPort === 3306) {
        $attempts[] = ['127.0.0.1', 3307];
        $attempts[] = ['localhost', 3307];
    }

    foreach ($attempts as [$tryHost, $tryPort]) {
        // Fast-path: Connect directly to existing database
        $try = @new mysqli($tryHost, $localUser, $localPass, $localDb, (int) $tryPort);
        if (!$try->connect_error) {
            $conn = $try;
            break;
        }

        $lastError = $try->connect_error;
        $lastErrno = $try->connect_errno;

        // Auto-initialize local database if not found
        $initTry = @new mysqli($tryHost, $localUser, $localPass, '', (int) $tryPort);
        if ($initTry->connect_error) {
            $lastError = $initTry->connect_error;
            $lastErrno = $initTry->connect_errno;
            continue;
        }

        $dbSafe = '`' . str_replace('`', '``', $localDb) . '`';
        @$initTry->query('CREATE DATABASE IF NOT EXISTS ' . $dbSafe);

        if (!$initTry->select_db($localDb)) {
            $lastError = $initTry->error;
            $initTry->close();
            continue;
        }

        // Run schema.sql if tables are missing
        $hasTables = $initTry->query("SHOW TABLES LIKE 'doctors'");
        if ($hasTables && $hasTables->num_rows === 0) {
            $schemaFile = __DIR__ . DIRECTORY_SEPARATOR . 'schema.sql';
            $sql = @file_get_contents($schemaFile);
            if ($sql !== false && $sql !== '') {
                $initTry->multi_query($sql);
                do {
                    if ($res = $initTry->store_result()) {
                        $res->free();
                    }
                } while ($initTry->more_results() && $initTry->next_result());
            }
        }

        $conn = $initTry;
        break;
    }
}

// Restore reporting for application queries
mysqli_report(MYSQLI_REPORT_ERROR);

// Handle connection failure securely
if ($conn === null) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');

    if ($isCloud) {
        $safeHost = htmlspecialchars($host, ENT_QUOTES, 'UTF-8');
        $safePort = (int) $port;
        $safeDb = htmlspecialchars($dbname, ENT_QUOTES, 'UTF-8');

        // Diagnose error category without leaking sensitive passwords
        if ($lastErrno === 2002 || stripos($lastError, 'getaddrinfo') !== false) {
            $detail = "Unable to resolve or reach database host: <code>{$safeHost}</code> on port <code>{$safePort}</code>.<br>"
                . "Please verify that <strong>DB_HOST</strong> in your Render Environment settings matches your cloud MySQL provider hostname.";
        } elseif ($lastErrno === 1045) {
            $detail = "Database authentication failed for configured user.<br>"
                . "Please verify that <strong>DB_USER</strong> and <strong>DB_PASSWORD</strong> are correctly set in your Render Environment settings.";
        } elseif ($lastErrno === 1049) {
            $detail = "Database <code>{$safeDb}</code> was not found on the MySQL host.<br>"
                . "Please create the database or ensure <strong>DB_NAME</strong> matches your database, then import <code>schema.sql</code>.";
        } else {
            $detail = "A connection to the cloud database could not be established.<br>"
                . "Please verify your Render Environment variables: <strong>DB_HOST, DB_PORT, DB_USER, DB_PASSWORD, DB_NAME</strong>.";
        }

        die("<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <title>Database Service Unavailable — WeCare Hospital</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0b192c; color: #f8fafc; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; margin: 0; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 2.5rem; max-width: 620px; width: 100%; box-shadow: 0 20px 40px rgba(0,0,0,0.4); }
        h1 { font-size: 1.45rem; color: #38bdf8; margin: 0 0 1rem; display: flex; align-items: center; gap: 0.5rem; }
        p { font-size: 0.94rem; line-height: 1.6; color: #cbd5e1; margin: 0 0 1.25rem; }
        code { background: #0f172a; color: #f43f5e; padding: 0.2rem 0.45rem; border-radius: 6px; font-size: 0.88rem; }
        .steps { background: #0f172a; border-left: 4px solid #38bdf8; padding: 1rem 1.25rem; border-radius: 8px; margin-top: 1.5rem; font-size: 0.85rem; color: #94a3b8; }
        .steps ol { margin: 0.5rem 0 0; padding-left: 1.25rem; }
        .steps li { margin-bottom: 0.35rem; }
    </style>
</head>
<body>
    <div class=\"card\">
        <h1>🏥 WeCare Hospital — Database Connection</h1>
        <p>{$detail}</p>
        <div class=\"steps\">
            <strong>Required Render Environment Variables:</strong>
            <ol>
                <li><code>DB_HOST</code> — Cloud MySQL host (e.g. mysql.railway.internal or aivencloud.com)</li>
                <li><code>DB_PORT</code> — Cloud MySQL port (e.g. 3306)</li>
                <li><code>DB_USER</code> — Database user name</li>
                <li><code>DB_PASSWORD</code> — Database password</li>
                <li><code>DB_NAME</code> — Database name</li>
            </ol>
        </div>
    </div>
</body>
</html>");
    } else {
        die("<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <title>Database Connection — WeCare Hospital</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; margin: 0; }
        .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 2.5rem; max-width: 580px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.08); }
        h1 { font-size: 1.35rem; color: #2563eb; margin: 0 0 1rem; }
        p { font-size: 0.94rem; line-height: 1.5; color: #475569; margin: 0 0 1rem; }
        code { background: #f1f5f9; color: #0f172a; padding: 0.2rem 0.4rem; border-radius: 4px; font-size: 0.88rem; }
        ol { margin: 0.5rem 0 0; padding-left: 1.25rem; }
        li { margin-bottom: 0.4rem; }
    </style>
</head>
<body>
    <div class=\"card\">
        <h1>🏥 WeCare Hospital — Local Database Setup</h1>
        <p>Could not connect to the local MySQL database server.</p>
        <ol>
            <li>Start <strong>MySQL</strong> in the XAMPP Control Panel, or run <code>serve.bat</code>.</li>
            <li>If MySQL runs on port 3306/3307 with default user <code>root</code> and no password, it will connect automatically.</li>
            <li>If <code>root</code> has a password, set <code>HOSPITAL_MYSQL_PASS</code> in your environment.</li>
        </ol>
    </div>
</body>
</html>");
    }
}

// 4. Enforce UTF-8 Character Set
$conn->set_charset('utf8mb4');

