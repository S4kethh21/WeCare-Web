<?php
/**
 * WeCare Hospital — Authentication & Session Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Sends anti-cache headers to prevent browser back-button session leaks.
 */
function sendNoCacheHeaders(): void {
    if (!headers_sent()) {
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
        header("Pragma: no-cache");
        header("Expires: 0");
    }
}

/**
 * Checks if a patient is currently authenticated.
 */
function isLoggedIn(): bool {
    return !empty($_SESSION['patient_id']) && (int) $_SESSION['patient_id'] > 0;
}

/**
 * Retrieves the full profile of the currently logged-in patient.
 * Uses in-request static memoization to eliminate duplicate SQL queries across nav and page controllers.
 */
function getCurrentPatient($conn, bool $forceRefresh = false): ?array {
    static $cachedPatient = null;
    static $cachedId = null;

    if (!isLoggedIn() || !$conn) {
        $cachedPatient = null;
        $cachedId = null;
        return null;
    }
    $patientId = (int) $_SESSION['patient_id'];
    if (!$forceRefresh && $cachedId === $patientId && $cachedPatient !== null) {
        return $cachedPatient;
    }

    $stmt = $conn->prepare('SELECT id, patient_name, age, gender, phone, address, email, created_at, blood_group, allergies, chronic_conditions, emergency_name, emergency_phone, emergency_relation FROM patients WHERE id = ?');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $cachedPatient = $patient ?: null;
    $cachedId = $patientId;
    return $cachedPatient;
}

/**
 * Enforces authentication. Redirects to login page if not logged in.
 */
function requireLogin(string $redirectUrl = ''): void {
    sendNoCacheHeaders();
    if (!isLoggedIn()) {
        $target = $redirectUrl !== '' ? $redirectUrl : ($_SERVER['REQUEST_URI'] ?? 'index.php');
        $_SESSION['auth_redirect'] = $target;
        header('Location: login.php?redirect=' . urlencode($target));
        exit;
    }
}

/**
 * Generates 2-letter uppercase initials from patient name.
 */
function getInitials(string $name): string {
    $clean = trim(preg_replace('/^(Dr\.|Dr|Mr\.|Mr|Ms\.|Ms|Mrs\.)\s+/i', '', $name));
    $parts = preg_split('/\s+/', $clean);
    $initials = '';
    if (!empty($parts[0])) {
        $initials .= strtoupper(substr($parts[0], 0, 1));
    }
    if (count($parts) > 1 && !empty($parts[count($parts) - 1])) {
        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
    }
    if (strlen($initials) < 2 && !empty($clean)) {
        $initials = strtoupper(substr($clean, 0, 2));
    }
    return $initials !== '' ? $initials : 'PT';
}

/**
 * Extracts first name for greeting.
 */
function getFirstName(string $name): string {
    $clean = trim(preg_replace('/^(Dr\.|Dr|Mr\.|Mr|Ms\.|Ms|Mrs\.)\s+/i', '', $name));
    $parts = preg_split('/\s+/', $clean);
    return !empty($parts[0]) ? $parts[0] : $name;
}

/**
 * STRICT 3-TIER GREETING:
 * 5:00 AM - 11:59 AM: Good morning
 * 12:00 PM - 4:59 PM: Good afternoon
 * 5:00 PM - 4:59 AM: Good evening
 * ("Good night" is NEVER used anywhere)
 */
function getPatientGreeting(string $name = ''): string {
    $h = (int) date('G'); // 0 to 23
    if ($h >= 5 && $h < 12) {
        $greeting = 'Good morning';
    } elseif ($h >= 12 && $h < 17) {
        $greeting = 'Good afternoon';
    } else {
        $greeting = 'Good evening';
    }

    if ($name !== '') {
        $first = getFirstName($name);
        return $greeting . ', ' . $first . ' 👋';
    }
    return $greeting . ' 👋';
}
