<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$loggedPatient = getCurrentPatient($conn);
$loggedIn = ($loggedPatient !== null);
$today = date('Y-m-d');
$preselectedDoctorId = (int) ($_GET['doctor_id'] ?? 0);
$preselectedDate = trim($_GET['date'] ?? $today);

$message = '';
$messageType = '';

// Check current view: default or 'my_appointments'
$viewMode = trim($_GET['view'] ?? '');
if (isset($_GET['booked'])) {
    $viewMode = 'booking';
}

// Check for flash session toast
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// 1. Check if a confirmation card should be displayed after booking (Post-Redirect-Get)
$bookedId = (int) ($_GET['booked'] ?? 0);
$bookedDetails = null;
if ($bookedId > 0) {
    $bSql = '
        SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.created_at,
               p.patient_name,
               d.doctor_name, d.specialization, d.department, d.consultation_fee
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        INNER JOIN doctors d ON d.id = a.doctor_id
        WHERE a.id = ?
    ';
    if ($loggedIn) {
        $bSql .= ' AND a.patient_id = ' . (int)$loggedPatient['id'];
    }
    $bStmt = $conn->prepare($bSql);
    if ($bStmt) {
        $bStmt->bind_param('i', $bookedId);
        $bStmt->execute();
        $bRes = $bStmt->get_result()->fetch_assoc();
        if ($bRes) {
            $bookedDetails = [
                'id' => $bRes['id'],
                'patient_name' => $bRes['patient_name'],
                'doctor_name' => $bRes['doctor_name'],
                'specialization' => $bRes['specialization'],
                'department' => $bRes['department'] ?: ($bRes['specialization'] . ' Department'),
                'appointment_date' => date('l, F j, Y', strtotime($bRes['appointment_date'])),
                'appointment_time' => date('h:i A', strtotime($bRes['appointment_time'])),
                'fee' => (float) $bRes['consultation_fee'],
                'status' => $bRes['status']
            ];
        }
        $bStmt->close();
    }
}

// 2. Query all doctors for dropdown and dynamic preview card
$allDoctors = [];
$doctorsDataMap = [];
$resDocs = $conn->query('
    SELECT id, doctor_name, specialization, department, phone, age, gender, experience, 
           consultation_fee, availability, languages, education, working_hours, profile_image, bio 
    FROM doctors 
    ORDER BY id ASC
');
if ($resDocs) {
    while ($d = $resDocs->fetch_assoc()) {
        $allDoctors[] = $d;
        $docId = (int) $d['id'];
        $parts = explode(' ', trim($d['doctor_name']));
        $initials = '';
        foreach ($parts as $p) {
            if (strtolower($p) !== 'dr.' && strtolower($p) !== 'dr') {
                $initials .= strtoupper(substr($p, 0, 1));
            }
        }
        if ($initials === '') $initials = 'DR';
        $initials = substr($initials, 0, 2);

        $expYears = (int) filter_var($d['experience'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        $expLabel = $expYears > 0 ? ($expYears . '+ years exp.') : ($d['experience'] ?? '10+ years');

        $eduClean = $d['education'] ?? 'MBBS, MD';
        if (strpos($eduClean, ' - ') !== false) {
            $eduDegree = trim(explode(' - ', $eduClean)[0]);
        } else {
            $eduDegree = $eduClean;
        }

        $img = !empty($d['profile_image']) ? $d['profile_image'] : sprintf('assets/doctors/doctor-%02d.jpg', $docId);

        $doctorsDataMap[(string) $docId] = [
            'id' => $docId,
            'name' => $d['doctor_name'],
            'specialization' => $d['specialization'],
            'department' => $d['department'] ?: ($d['specialization'] . ' Department'),
            'experience' => $d['experience'] ?? '10+ years',
            'experience_label' => $expLabel,
            'education' => $eduDegree,
            'availability' => $d['availability'] ?? 'Available Today',
            'working_hours' => $d['working_hours'] ?? '09:00 AM - 05:00 PM',
            'profile_image' => $img,
            'initials' => $initials,
            'location' => 'Main Clinical Pavilion · Suite ' . (100 + $docId * 12) . ', Floor 2',
            'next_slot' => (strpos(strtolower($d['availability'] ?? ''), 'today') !== false) ? 'Today at 02:30 PM' : 'Tomorrow at 10:30 AM'
        ];
    }
}
$nDoctors = count($allDoctors);

// 3. Handle POST Actions (Booking, Cancellation, Removal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');

    // Patient Cancels an Upcoming Appointment
    if ($action === 'cancel_appointment') {
        $cancelId = (int) ($_POST['appointment_id'] ?? 0);
        if ($loggedIn && $cancelId > 0) {
            $cStmt = $conn->prepare('
                UPDATE appointments 
                SET status = "Cancelled" 
                WHERE id = ? AND patient_id = ? AND status NOT IN ("Completed", "Cancelled")
            ');
            if ($cStmt) {
                $cStmt->bind_param('ii', $cancelId, $loggedPatient['id']);
                if ($cStmt->execute() && $cStmt->affected_rows > 0) {
                    $_SESSION['flash_message'] = 'Appointment #' . $cancelId . ' has been cancelled successfully.';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Could not cancel appointment. Only scheduled visits can be cancelled.';
                    $_SESSION['flash_type'] = 'error';
                }
                $cStmt->close();
            }
            header('Location: appointments.php?view=my_appointments');
            exit;
        }
    }

    // Patient Removes a Past/Cancelled Record
    elseif ($action === 'delete_appointment') {
        $delId = (int) ($_POST['appointment_id'] ?? 0);
        if ($loggedIn && $delId > 0) {
            $dStmt = $conn->prepare('
                DELETE FROM appointments 
                WHERE id = ? AND patient_id = ? AND (status IN ("Completed", "Cancelled") OR appointment_date < CURDATE())
            ');
            if ($dStmt) {
                $dStmt->bind_param('ii', $delId, $loggedPatient['id']);
                if ($dStmt->execute() && $dStmt->affected_rows > 0) {
                    $_SESSION['flash_message'] = 'Appointment record #' . $delId . ' removed from history.';
                    $_SESSION['flash_type'] = 'info';
                }
                $dStmt->close();
            }
            header('Location: appointments.php?view=my_appointments');
            exit;
        }
    }

    // Book a New Appointment (Supports Logged-In & Guest Patients)
    else {
        $doctorId = (int) ($_POST['doctor_id'] ?? 0);
        $date = trim($_POST['appointment_date'] ?? '');
        $time = trim($_POST['appointment_time'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($nDoctors === 0) {
            $message = 'No medical specialists currently available. Please check back shortly.';
            $messageType = 'error';
        } elseif ($doctorId <= 0 || $date === '' || $time === '') {
            $message = 'Please select a physician, consultation date, and appointment time.';
            $messageType = 'error';
        } else {
            if (strlen($time) === 5) {
                $time .= ':00';
            }

            $patientId = 0;

            if ($loggedIn) {
                $patientId = (int) $loggedPatient['id'];
            } else {
                // Handle Guest Booking
                $pName = trim($_POST['patient_name'] ?? '');
                $pPhone = trim($_POST['phone'] ?? '');
                $pEmail = trim($_POST['email'] ?? '');
                $pAge = (int) ($_POST['age'] ?? 30);
                $pGender = trim($_POST['gender'] ?? 'Male');

                if ($pName === '' || $pPhone === '') {
                    $message = 'Please provide your full name and contact phone number.';
                    $messageType = 'error';
                } else {
                    // Check if patient already exists by phone or email
                    $findStmt = $conn->prepare('SELECT id FROM patients WHERE (email = ? AND email != "") OR phone = ? LIMIT 1');
                    if ($findStmt) {
                        $findStmt->bind_param('ss', $pEmail, $pPhone);
                        $findStmt->execute();
                        $found = $findStmt->get_result()->fetch_assoc();
                        $findStmt->close();
                        if ($found) {
                            $patientId = (int) $found['id'];
                        }
                    }

                    if ($patientId === 0) {
                        // Create new patient record
                        $defPass = password_hash('wecare' . rand(1000, 9999), PASSWORD_DEFAULT);
                        $insP = $conn->prepare('INSERT INTO patients (patient_name, phone, age, gender, email, password) VALUES (?, ?, ?, ?, ?, ?)');
                        if ($insP) {
                            $insP->bind_param('ssisss', $pName, $pPhone, $pAge, $pGender, $pEmail, $defPass);
                            if ($insP->execute()) {
                                $patientId = (int) $insP->insert_id;
                            }
                            $insP->close();
                        }
                    }

                    if ($patientId > 0) {
                        $_SESSION['patient_id'] = $patientId;
                        $loggedPatient = getCurrentPatient($conn);
                        $loggedIn = true;
                    }
                }
            }

            if ($patientId > 0 && $messageType !== 'error') {
                $initialStatus = 'Confirmed';
                $stmt = $conn->prepare('
                    INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, status, notes) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                if (!$stmt) {
                    $message = 'Database error: ' . htmlspecialchars($conn->error);
                    $messageType = 'error';
                } else {
                    $stmt->bind_param('iissss', $patientId, $doctorId, $date, $time, $initialStatus, $notes);
                    if ($stmt->execute()) {
                        $newId = $stmt->insert_id;
                        $stmt->close();

                        $_SESSION['flash_message'] = 'Appointment confirmed! Your visit reference is #' . $newId . '.';
                        $_SESSION['flash_type'] = 'success';

                        // Redirect with PRG pattern
                        header('Location: appointments.php?booked=' . $newId);
                        exit;
                    } else {
                        $message = 'Could not book appointment: ' . htmlspecialchars($stmt->error);
                        $messageType = 'error';
                        $stmt->close();
                    }
                }
            } elseif ($message === '') {
                $message = 'Could not register patient record. Please check required fields.';
                $messageType = 'error';
            }
        }
    }
}

// 4. Query Appointments strictly for logged-in patient
$upcomingAppointments = [];
$pastAppointments = [];
$cancelledAppointments = [];

if ($loggedIn) {
    $pId = (int) $loggedPatient['id'];
    $sqlA = '
        SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time, a.status, a.notes, a.created_at,
               d.doctor_name, d.specialization, d.department, d.consultation_fee, d.profile_image, d.education,
               pr.id AS prescription_id,
               b.id AS bill_id, b.payment_status, b.amount AS bill_amount
        FROM appointments a
        INNER JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN prescriptions pr ON pr.appointment_id = a.id
        LEFT JOIN bills b ON b.appointment_id = a.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ';
    $stmtA = $conn->prepare($sqlA);
    if ($stmtA) {
        $stmtA->bind_param('i', $pId);
        $stmtA->execute();
        $resA = $stmtA->get_result();
        while ($row = $resA->fetch_assoc()) {
            $aptDate = $row['appointment_date'];
            $st = strtolower($row['status']);

            if ($st === 'cancelled') {
                $cancelledAppointments[] = $row;
            } elseif ($aptDate >= $today && $st !== 'completed') {
                $upcomingAppointments[] = $row;
            } else {
                $pastAppointments[] = $row;
            }
        }
        $stmtA->close();
    }
}

// Default preview doctor data (first doctor or preselected doctor)
$initialPreviewDoc = null;
if ($preselectedDoctorId > 0 && isset($doctorsDataMap[(string)$preselectedDoctorId])) {
    $initialPreviewDoc = $doctorsDataMap[(string)$preselectedDoctorId];
} elseif (!empty($allDoctors)) {
    $initialPreviewDoc = $doctorsDataMap[(string)$allDoctors[0]['id']] ?? null;
}

$activeTab = ($viewMode === 'my_appointments') ? 'my_appointments' : 'booking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <!-- Top Primary Tabs: Request Appointment / My Appointments -->
        <div class="appt-tabs-bar">
            <a href="appointments.php" class="appt-tab-btn <?php echo $activeTab === 'booking' ? 'active' : ''; ?>">
                <span>Request an Appointment</span>
            </a>
            <a href="appointments.php?view=my_appointments" class="appt-tab-btn <?php echo $activeTab === 'my_appointments' ? 'active' : ''; ?>">
                <span>My Appointments</span>
                <?php if ($loggedIn): ?>
                    <span class="appt-tab-badge"><?php echo count($upcomingAppointments) + count($pastAppointments); ?></span>
                <?php endif; ?>
            </a>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (window.showToast) {
                        window.showToast(<?php echo json_encode($message); ?>, <?php echo json_encode($messageType); ?>);
                    }
                });
            </script>
        <?php endif; ?>

        <!-- VIEW 1: REQUEST AN APPOINTMENT -->
        <?php if ($activeTab === 'booking'): ?>
            <div class="page-head">
                <h1 class="page-title">Request an Appointment</h1>
                <p class="page-sub">Schedule your consultation with WeCare board-certified specialists. Instant system confirmation.</p>
            </div>

            <!-- Confirmation Card (Post-Redirect-Get) -->
            <?php if ($bookedDetails !== null): ?>
                <section class="confirmation-card panel" id="confirmation" style="margin-bottom: 2rem;">
                    <div class="confirmation-badge" aria-hidden="true">✓</div>
                    <h2>✓ Appointment Confirmed</h2>
                    <p class="confirmation-lead">Your consultation has been booked and registered in the WeCare clinical system. Please arrive 10 minutes before your scheduled time.</p>
                    
                    <div class="confirmation-grid">
                        <div class="confirm-item">
                            <span class="confirm-label">Appointment ID</span>
                            <strong class="confirm-val text-accent-blue">#<?php echo (int) $bookedDetails['id']; ?></strong>
                        </div>
                        <div class="confirm-item">
                            <span class="confirm-label">Patient</span>
                            <strong class="confirm-val"><?php echo htmlspecialchars($bookedDetails['patient_name']); ?></strong>
                        </div>
                        <div class="confirm-item">
                            <span class="confirm-label">Doctor</span>
                            <strong class="confirm-val"><?php echo htmlspecialchars($bookedDetails['doctor_name']); ?></strong>
                        </div>
                        <div class="confirm-item">
                            <span class="confirm-label">Department</span>
                            <strong class="confirm-val"><?php echo htmlspecialchars($bookedDetails['department']); ?></strong>
                        </div>
                        <div class="confirm-item">
                            <span class="confirm-label">Date</span>
                            <strong class="confirm-val"><?php echo htmlspecialchars($bookedDetails['appointment_date']); ?></strong>
                        </div>
                        <div class="confirm-item">
                            <span class="confirm-label">Time</span>
                            <strong class="confirm-val"><?php echo htmlspecialchars($bookedDetails['appointment_time']); ?></strong>
                        </div>
                    </div>

                    <div class="confirmation-actions">
                        <a href="appointments.php?view=my_appointments" class="btn btn-primary-highlight">
                            View My Appointments →
                        </a>
                        <a href="appointments.php" class="btn btn-secondary">
                            Book another appointment
                        </a>
                    </div>
                </section>
            <?php endif; ?>

            <div class="booking-split-container">
                <!-- Left: Main Booking Form -->
                <div class="panel booking-main-card">
                    <form method="post" action="appointments.php" id="appointment-form">
                        
                        <!-- Section 1: Patient Information -->
                        <div class="form-section-block">
                            <div class="form-section-header-flex" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <h3 class="form-section-heading" style="margin: 0;">PATIENT INFORMATION</h3>
                                <?php if ($loggedIn): ?>
                                    <span class="badge badge-completed" style="font-size: 0.76rem;">✓ Verified Account (<?php echo htmlspecialchars($loggedPatient['patient_name']); ?>)</span>
                                <?php else: ?>
                                    <a href="login.php?redirect=appointments.php" class="text-accent-blue" style="font-size: 0.82rem; font-weight: 600; text-decoration: none;">Already registered? Sign in →</a>
                                <?php endif; ?>
                            </div>

                            <?php if ($loggedIn): ?>
                                <div class="form-grid three">
                                    <div>
                                        <label for="p_name">Full Name</label>
                                        <input type="text" id="p_name" value="<?php echo htmlspecialchars($loggedPatient['patient_name']); ?>" readonly class="input-readonly">
                                    </div>
                                    <div>
                                        <label for="p_phone">Phone Number</label>
                                        <input type="text" id="p_phone" value="<?php echo htmlspecialchars($loggedPatient['phone']); ?>" readonly class="input-readonly">
                                    </div>
                                    <div>
                                        <label for="p_email">Email Address</label>
                                        <input type="email" id="p_email" value="<?php echo htmlspecialchars($loggedPatient['email'] ?: 'patient@wecare.com'); ?>" readonly class="input-readonly">
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="form-grid two">
                                    <div>
                                        <label for="p_name_input">Full Name <span class="req">*</span></label>
                                        <input type="text" id="p_name_input" name="patient_name" placeholder="Enter full name" required>
                                    </div>
                                    <div>
                                        <label for="p_phone_input">Contact Phone Number <span class="req">*</span></label>
                                        <input type="tel" id="p_phone_input" name="phone" placeholder="e.g. +91 98765 43210" required>
                                    </div>
                                    <div>
                                        <label for="p_email_input">Email Address</label>
                                        <input type="email" id="p_email_input" name="email" placeholder="e.g. patient@example.com">
                                    </div>
                                    <div class="form-grid two" style="gap: 10px;">
                                        <div>
                                            <label for="p_age_input">Age <span class="req">*</span></label>
                                            <input type="number" id="p_age_input" name="age" min="1" max="130" value="30" required>
                                        </div>
                                        <div>
                                            <label for="p_gender_input">Gender <span class="req">*</span></label>
                                            <select id="p_gender_input" name="gender" required>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <hr class="form-divider" style="margin: 1.5rem 0; border: none; border-top: 1px solid #e2e8f0;">

                        <!-- Section 2: Appointment Details -->
                        <div class="form-section-block">
                            <h3 class="form-section-heading">APPOINTMENT DETAILS</h3>
                            <div class="form-grid two">
                                <div style="grid-column: 1 / -1;">
                                    <label for="doctor_id">Select Doctor <span class="req">*</span></label>
                                    <select id="doctor_id" name="doctor_id" required>
                                        <option value="">-- Choose specialist from directory --</option>
                                        <?php foreach ($allDoctors as $d): 
                                            $docId = (int)$d['id'];
                                            $selected = ($preselectedDoctorId > 0 && $docId === $preselectedDoctorId) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $docId; ?>" <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($d['doctor_name']) . ' — ' . htmlspecialchars($d['specialization']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="appointment_date">Preferred Date <span class="req">*</span></label>
                                    <input type="date" id="appointment_date" name="appointment_date" value="<?php echo htmlspecialchars($preselectedDate); ?>" min="<?php echo htmlspecialchars($today); ?>" required>
                                </div>

                                <div>
                                    <label for="appointment_time">Preferred Time <span class="req">*</span></label>
                                    <input type="time" id="appointment_time" name="appointment_time" value="10:30" required>
                                </div>

                                <!-- Quick Slots Selector -->
                                <div style="grid-column: 1 / -1; margin-top: 0.5rem;">
                                    <label class="slots-section-label">Quick Slot Selection:</label>
                                    <div class="slots-chips-grid" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 6px;">
                                        <button type="button" class="slot-chip" data-time="09:30">09:30 AM</button>
                                        <button type="button" class="slot-chip active" data-time="10:30">10:30 AM</button>
                                        <button type="button" class="slot-chip" data-time="11:30">11:30 AM</button>
                                        <button type="button" class="slot-chip" data-time="14:00">02:00 PM</button>
                                        <button type="button" class="slot-chip" data-time="15:30">03:30 PM</button>
                                        <button type="button" class="slot-chip" data-time="16:30">04:30 PM</button>
                                    </div>
                                </div>

                                <div style="grid-column: 1 / -1; margin-top: 0.75rem;">
                                    <label for="notes">Reason for Visit / Clinical Symptoms <span class="req">*</span></label>
                                    <textarea id="notes" name="notes" rows="3" placeholder="Briefly describe your symptoms, reason for consultation, or required medical checkup..." required style="width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; padding: 10px; font-family: inherit; font-size: 0.9rem; box-sizing: border-box;"></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 1.75rem;">
                            <button type="submit" class="btn btn-primary-highlight btn-full-submit" style="width: 100%; padding: 0.85rem; font-size: 1rem; font-weight: 600; border-radius: 10px;">
                                Submit Request
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Right: Friendly Assistance Card (Matching Reference Mockup) -->
                <aside class="appointment-help-card panel">
                    <div class="help-graphic-wrap">
                        <img src="images/appointment_help_doctor.jpg" alt="WeCare Appointment Support" class="help-doctor-avatar-img">
                    </div>
                    <div class="help-card-content">
                        <h3>We're here to help</h3>
                        <p>Our team will get back to you shortly to confirm your appointment.</p>
                    </div>
                </aside>
            </div>

        <!-- VIEW 2: MY APPOINTMENTS -->
        <?php else: ?>
            <div class="page-head">
                <h1 class="page-title">My Appointments</h1>
                <p class="page-sub">Review and manage your scheduled consultations, clinical records, and visit histories.</p>
            </div>

            <?php if (!$loggedIn): ?>
                <section class="panel signin-required-card">
                    <div class="signin-required-icon" aria-hidden="true">🔒</div>
                    <h2>Sign in to view your appointments</h2>
                    <p>A registered patient profile is required to inspect upcoming visits, review doctor notes, download digital prescriptions, and view consultation invoices.</p>
                    <div class="signin-required-actions">
                        <a href="login.php?redirect=appointments.php%3Fview=my_appointments" class="btn btn-primary-highlight">Sign in</a>
                        <a href="login.php?action=register&redirect=appointments.php%3Fview=my_appointments" class="btn btn-secondary">Create account</a>
                    </div>
                </section>
            <?php else: ?>

                <!-- Sub-tabs: Upcoming, Past, Cancelled -->
                <div class="status-subtabs-bar" style="display: flex; gap: 8px; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 2px;">
                    <button type="button" class="subtab-btn active" data-filter="upcoming">
                        Upcoming (<?php echo count($upcomingAppointments); ?>)
                    </button>
                    <button type="button" class="subtab-btn" data-filter="past">
                        Past (<?php echo count($pastAppointments); ?>)
                    </button>
                    <button type="button" class="subtab-btn" data-filter="cancelled">
                        Cancelled (<?php echo count($cancelledAppointments); ?>)
                    </button>
                </div>

                <!-- 1. UPCOMING APPOINTMENTS -->
                <div class="appt-group-container" id="group-upcoming">
                    <?php if (!empty($upcomingAppointments)): ?>
                        <div class="appointments-list-grid">
                            <?php foreach ($upcomingAppointments as $apt): 
                                $docInitials = getInitials($apt['doctor_name']);
                                $aptDateTs = strtotime($apt['appointment_date']);
                                $dayNum = date('d', $aptDateTs);
                                $monthStr = strtoupper(date('M', $aptDateTs));
                                $weekdayStr = date('D', $aptDateTs);
                                $img = !empty($apt['profile_image']) ? $apt['profile_image'] : sprintf('assets/doctors/doctor-%02d.jpg', (int)$apt['doctor_id']);
                            ?>
                                <article class="appointment-card panel">
                                    <!-- Left Date Block -->
                                    <div class="appt-date-block">
                                        <span class="date-month"><?php echo $monthStr; ?></span>
                                        <span class="date-day"><?php echo $dayNum; ?></span>
                                        <span class="date-weekday"><?php echo $weekdayStr; ?></span>
                                        <span class="date-time"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                                    </div>

                                    <!-- Middle Doctor Info -->
                                    <div class="appt-doc-details">
                                        <div class="appt-doc-header">
                                            <div class="appt-doc-portrait">
                                                <img src="<?php echo htmlspecialchars($img); ?>" 
                                                     alt="<?php echo htmlspecialchars($apt['doctor_name']); ?>" 
                                                     class="appt-thumb-img"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="appt-thumb-fallback" style="display: none;"><?php echo htmlspecialchars($docInitials); ?></div>
                                            </div>

                                            <div>
                                                <div class="appt-ref-num">Visit #<?php echo (int) $apt['id']; ?></div>
                                                <h3 class="appt-doc-name"><?php echo htmlspecialchars($apt['doctor_name']); ?></h3>
                                                <span class="badge badge-specialty"><?php echo htmlspecialchars($apt['specialization']); ?></span>
                                                <div class="appt-dept-text"><?php echo htmlspecialchars($apt['department'] ?: ($apt['specialization'] . ' Department')); ?></div>
                                            </div>
                                        </div>

                                        <?php if (!empty($apt['notes'])): ?>
                                            <div class="appt-notes-box">
                                                <strong>Reason:</strong> <?php echo htmlspecialchars($apt['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Right Status & Action Buttons -->
                                    <div class="appt-actions-col">
                                        <div class="appt-status-badge-wrap">
                                            <span class="badge badge-confirmed"><?php echo htmlspecialchars($apt['status']); ?></span>
                                        </div>

                                        <div class="appt-btns-stack">
                                            <button type="button" class="btn btn-secondary btn-sm btn-view-appt-details"
                                                data-id="<?php echo (int) $apt['id']; ?>"
                                                data-doctor="<?php echo htmlspecialchars($apt['doctor_name']); ?>"
                                                data-spec="<?php echo htmlspecialchars($apt['specialization']); ?>"
                                                data-dept="<?php echo htmlspecialchars($apt['department']); ?>"
                                                data-date="<?php echo date('l, F j, Y', $aptDateTs); ?>"
                                                data-time="<?php echo date('h:i A', strtotime($apt['appointment_time'])); ?>"
                                                data-status="<?php echo htmlspecialchars($apt['status']); ?>"
                                                data-notes="<?php echo htmlspecialchars($apt['notes'] ?? 'Routine consultation'); ?>">
                                                View Details
                                            </button>

                                            <a href="appointments.php?doctor_id=<?php echo (int) $apt['doctor_id']; ?>" class="btn btn-secondary btn-sm">
                                                Reschedule
                                            </a>

                                            <form method="post" action="appointments.php" onsubmit="return confirm('Are you sure you want to cancel appointment #<?php echo (int) $apt['id']; ?>?');" style="display: inline;">
                                                <input type="hidden" name="action" value="cancel_appointment">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int) $apt['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-card panel">
                            <div class="empty-state-icon">📅</div>
                            <h3>No upcoming appointments</h3>
                            <p>You have no clinical consultations scheduled at this time. Book a visit with one of our 14+ specialists.</p>
                            <a href="appointments.php" class="btn btn-primary-highlight">Request an Appointment</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. PAST CONSULTATIONS -->
                <div class="appt-group-container" id="group-past" style="display: none;">
                    <?php if (!empty($pastAppointments)): ?>
                        <div class="appointments-list-grid">
                            <?php foreach ($pastAppointments as $apt): 
                                $docInitials = getInitials($apt['doctor_name']);
                                $aptDateTs = strtotime($apt['appointment_date']);
                                $dayNum = date('d', $aptDateTs);
                                $monthStr = strtoupper(date('M', $aptDateTs));
                                $weekdayStr = date('D', $aptDateTs);
                                $img = !empty($apt['profile_image']) ? $apt['profile_image'] : sprintf('assets/doctors/doctor-%02d.jpg', (int)$apt['doctor_id']);
                                $hasRx = !empty($apt['prescription_id']);
                                $hasBill = !empty($apt['bill_id']);
                            ?>
                                <article class="appointment-card panel">
                                    <div class="appt-date-block past-date">
                                        <span class="date-month"><?php echo $monthStr; ?></span>
                                        <span class="date-day"><?php echo $dayNum; ?></span>
                                        <span class="date-weekday"><?php echo $weekdayStr; ?></span>
                                        <span class="date-time"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                                    </div>

                                    <div class="appt-doc-details">
                                        <div class="appt-doc-header">
                                            <div class="appt-doc-portrait">
                                                <img src="<?php echo htmlspecialchars($img); ?>" 
                                                     alt="<?php echo htmlspecialchars($apt['doctor_name']); ?>" 
                                                     class="appt-thumb-img"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="appt-thumb-fallback" style="display: none;"><?php echo htmlspecialchars($docInitials); ?></div>
                                            </div>

                                            <div>
                                                <div class="appt-ref-num">Visit #<?php echo (int) $apt['id']; ?> · Completed</div>
                                                <h3 class="appt-doc-name"><?php echo htmlspecialchars($apt['doctor_name']); ?></h3>
                                                <span class="badge badge-specialty"><?php echo htmlspecialchars($apt['specialization']); ?></span>
                                            </div>
                                        </div>

                                        <?php if (!empty($apt['notes'])): ?>
                                            <div class="appt-notes-box">
                                                <strong>Notes:</strong> <?php echo htmlspecialchars($apt['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="appt-actions-col">
                                        <div class="appt-status-badge-wrap">
                                            <span class="badge badge-completed">Completed</span>
                                        </div>

                                        <div class="appt-btns-stack">
                                            <?php if ($hasRx): ?>
                                                <a href="prescriptions.php?appointment_id=<?php echo (int) $apt['id']; ?>" class="btn btn-primary-highlight btn-sm">
                                                    View Prescription
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($hasBill): ?>
                                                <a href="billing.php?appointment_id=<?php echo (int) $apt['id']; ?>" class="btn btn-secondary btn-sm">
                                                    View Bill
                                                </a>
                                            <?php endif; ?>

                                            <a href="appointments.php?doctor_id=<?php echo (int) $apt['doctor_id']; ?>" class="btn btn-secondary btn-sm">
                                                Book Follow-up
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-card panel">
                            <div class="empty-state-icon">📋</div>
                            <h3>No past consultations</h3>
                            <p>You have not attended any completed visits under this patient account yet.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. CANCELLED APPOINTMENTS -->
                <div class="appt-group-container" id="group-cancelled" style="display: none;">
                    <?php if (!empty($cancelledAppointments)): ?>
                        <div class="appointments-list-grid">
                            <?php foreach ($cancelledAppointments as $apt): 
                                $docInitials = getInitials($apt['doctor_name']);
                                $aptDateTs = strtotime($apt['appointment_date']);
                                $dayNum = date('d', $aptDateTs);
                                $monthStr = strtoupper(date('M', $aptDateTs));
                                $img = !empty($apt['profile_image']) ? $apt['profile_image'] : sprintf('assets/doctors/doctor-%02d.jpg', (int)$apt['doctor_id']);
                            ?>
                                <article class="appointment-card panel" style="opacity: 0.85;">
                                    <div class="appt-date-block past-date">
                                        <span class="date-month"><?php echo $monthStr; ?></span>
                                        <span class="date-day"><?php echo $dayNum; ?></span>
                                        <span class="date-time"><?php echo date('h:i A', strtotime($apt['appointment_time'])); ?></span>
                                    </div>

                                    <div class="appt-doc-details">
                                        <div class="appt-doc-header">
                                            <div class="appt-doc-portrait">
                                                <img src="<?php echo htmlspecialchars($img); ?>" 
                                                     alt="<?php echo htmlspecialchars($apt['doctor_name']); ?>" 
                                                     class="appt-thumb-img"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="appt-thumb-fallback" style="display: none;"><?php echo htmlspecialchars($docInitials); ?></div>
                                            </div>

                                            <div>
                                                <div class="appt-ref-num">Visit #<?php echo (int) $apt['id']; ?></div>
                                                <h3 class="appt-doc-name"><?php echo htmlspecialchars($apt['doctor_name']); ?></h3>
                                                <span class="badge badge-specialty"><?php echo htmlspecialchars($apt['specialization']); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="appt-actions-col">
                                        <span class="badge badge-error">Cancelled</span>
                                        <div class="appt-btns-stack">
                                            <a href="appointments.php?doctor_id=<?php echo (int) $apt['doctor_id']; ?>" class="btn btn-primary-highlight btn-sm">
                                                Rebook Visit
                                            </a>
                                            <form method="post" action="appointments.php" onsubmit="return confirm('Remove cancelled appointment #<?php echo (int) $apt['id']; ?> from history?');" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_appointment">
                                                <input type="hidden" name="appointment_id" value="<?php echo (int) $apt['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-card panel">
                            <div class="empty-state-icon">✓</div>
                            <h3>No cancelled appointments</h3>
                            <p>You have zero cancelled consultations.</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
        </div>
    </footer>

    <!-- Visit Details Modal -->
    <div class="doctor-modal-backdrop" id="appt-detail-modal" style="display: none;">
        <div class="doctor-modal-card" style="max-width: 500px;">
            <button type="button" class="doctor-modal-close" id="appt-modal-close">&times;</button>
            <div class="pay-modal-head">
                <div class="pay-icon-circle" style="background: #e0f2fe; color: #0284c7;">📅</div>
                <h3 id="m-appt-title">Appointment Details</h3>
                <p>Official clinical consultation booking record</p>
            </div>

            <div class="profile-info-list" style="margin-top: 1rem;">
                <div class="profile-info-row">
                    <span class="info-label">Appointment ID</span>
                    <strong class="info-value text-accent-blue" id="m-appt-id">#0000</strong>
                </div>
                <div class="profile-info-row">
                    <span class="info-label">Attending Doctor</span>
                    <strong class="info-value" id="m-appt-doc">Dr. Physician</strong>
                </div>
                <div class="profile-info-row">
                    <span class="info-label">Department</span>
                    <strong class="info-value" id="m-appt-dept">Cardiology</strong>
                </div>
                <div class="profile-info-row">
                    <span class="info-label">Scheduled Date</span>
                    <strong class="info-value" id="m-appt-date">Monday, Date</strong>
                </div>
                <div class="profile-info-row">
                    <span class="info-label">Time Slot</span>
                    <strong class="info-value text-green font-bold" id="m-appt-time">10:30 AM</strong>
                </div>
                <div class="profile-info-row">
                    <span class="info-label">Status</span>
                    <span class="badge badge-confirmed" id="m-appt-status">Confirmed</span>
                </div>
                <div class="profile-info-row" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                    <span class="info-label">Reason for Visit</span>
                    <p style="margin: 0; font-size: 0.88rem; color: #1e293b;" id="m-appt-notes">Routine Checkup</p>
                </div>
            </div>

            <div class="inv-modal-actions" style="margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary btn-full" id="m-appt-close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Embedded Doctors JSON Data Map for Dynamic Preview Card -->
    <script type="application/json" id="wecare-doctors-data">
        <?php echo json_encode($doctorsDataMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    </script>

    <script>
    (function () {
        // Parse Doctors Data
        var doctorsData = {};
        try {
            var rawEl = document.getElementById('wecare-doctors-data');
            if (rawEl) {
                doctorsData = JSON.parse(rawEl.textContent);
            }
        } catch (e) {
            console.error('Failed to parse doctors data:', e);
        }

        // Dynamic Doctor Preview Card Update
        var docSelect = document.getElementById('doctor_id');
        var prevImg = document.getElementById('prev-doc-img');
        var prevAvatar = document.getElementById('prev-doc-avatar');
        var prevAvail = document.getElementById('prev-doc-avail');
        var prevName = document.getElementById('prev-doc-name');
        var prevSpec = document.getElementById('prev-doc-spec');
        var prevDept = document.getElementById('prev-doc-dept');
        var prevExp = document.getElementById('prev-doc-exp');
        var prevSlot = document.getElementById('prev-doc-slot');
        var prevLoc = document.getElementById('prev-doc-loc');

        function updateDoctorPreview(docId) {
            var doc = doctorsData[String(docId)];
            if (!doc) return;

            if (prevName) prevName.textContent = doc.name;
            if (prevSpec) prevSpec.textContent = doc.specialization;
            if (prevDept) prevDept.textContent = doc.department;
            if (prevExp) prevExp.textContent = doc.experience_label + ' (' + doc.education + ')';
            if (prevSlot) prevSlot.textContent = doc.next_slot;
            if (prevLoc) prevLoc.textContent = doc.location;

            if (prevAvail) {
                var isToday = doc.availability.toLowerCase().indexOf('today') !== -1;
                prevAvail.className = 'doctor-avail-badge ' + (isToday ? 'avail-today' : 'avail-tomorrow');
                prevAvail.innerHTML = '<span class="avail-dot" aria-hidden="true"></span><span>' + doc.availability + '</span>';
            }

            if (doc.profile_image && prevImg) {
                prevImg.src = doc.profile_image;
                prevImg.style.display = 'block';
                if (prevAvatar) prevAvatar.style.display = 'none';
            } else if (prevAvatar) {
                if (prevImg) prevImg.style.display = 'none';
                prevAvatar.textContent = doc.initials;
                prevAvatar.style.display = 'flex';
            }
        }

        if (docSelect) {
            docSelect.addEventListener('change', function () {
                var selId = this.value;
                if (selId) {
                    updateDoctorPreview(selId);
                }
            });
            // If already preselected on load
            if (docSelect.value) {
                updateDoctorPreview(docSelect.value);
            }
        }

        // Quick Slot Chips
        var slotChips = document.querySelectorAll('.slot-chip');
        var timeInput = document.getElementById('appointment_time');
        slotChips.forEach(function (btn) {
            btn.addEventListener('click', function () {
                slotChips.forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');
                if (timeInput) {
                    timeInput.value = this.getAttribute('data-time');
                }
            });
        });

        // Subtabs (Upcoming, Past, Cancelled)
        var subtabBtns = document.querySelectorAll('.subtab-btn');
        var groupUpcoming = document.getElementById('group-upcoming');
        var groupPast = document.getElementById('group-past');
        var groupCancelled = document.getElementById('group-cancelled');

        subtabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = this.getAttribute('data-filter');
                subtabBtns.forEach(function (b) { b.classList.remove('active'); });
                this.classList.add('active');

                if (groupUpcoming) groupUpcoming.style.display = (filter === 'upcoming') ? 'block' : 'none';
                if (groupPast) groupPast.style.display = (filter === 'past') ? 'block' : 'none';
                if (groupCancelled) groupCancelled.style.display = (filter === 'cancelled') ? 'block' : 'none';
            });
        });

        // Appointment Details Modal
        var apptModal = document.getElementById('appt-detail-modal');
        var apptCloseBtn = document.getElementById('appt-modal-close');
        var apptCloseBtn2 = document.getElementById('m-appt-close-btn');

        document.querySelectorAll('.btn-view-appt-details').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = this.getAttribute('data-id');
                var doc = this.getAttribute('data-doctor');
                var spec = this.getAttribute('data-spec');
                var dept = this.getAttribute('data-dept');
                var date = this.getAttribute('data-date');
                var time = this.getAttribute('data-time');
                var status = this.getAttribute('data-status');
                var notes = this.getAttribute('data-notes');

                document.getElementById('m-appt-id').textContent = '#' + id;
                document.getElementById('m-appt-doc').textContent = doc;
                document.getElementById('m-appt-dept').textContent = dept || (spec + ' Department');
                document.getElementById('m-appt-date').textContent = date;
                document.getElementById('m-appt-time').textContent = time;
                document.getElementById('m-appt-status').textContent = status;
                document.getElementById('m-appt-notes').textContent = notes || 'Routine consultation';

                if (apptModal) {
                    apptModal.style.display = 'flex';
                    apptModal.style.opacity = '1';
                }
            });
        });

        function closeApptModal() {
            if (apptModal) {
                apptModal.style.display = 'none';
                apptModal.style.opacity = '0';
            }
        }

        if (apptCloseBtn) apptCloseBtn.addEventListener('click', closeApptModal);
        if (apptCloseBtn2) apptCloseBtn2.addEventListener('click', closeApptModal);
        if (apptModal) {
            apptModal.addEventListener('click', function (e) {
                if (e.target === apptModal) closeApptModal();
            });
        }
    })();
    </script>
</body>
</html>
