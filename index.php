<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$loggedPatient = getCurrentPatient($conn);
$loggedIn = ($loggedPatient !== null);
$patientName = $loggedPatient ? $loggedPatient['patient_name'] : '';
$patientFirstName = $loggedPatient ? getFirstName($loggedPatient['patient_name']) : '';

// STRICT 3-TIER GREETING (Never "Good Night")
$h = (int) date('G');
if ($h >= 5 && $h < 12) {
    $timeGreeting = 'Good Morning';
} elseif ($h >= 12 && $h < 17) {
    $timeGreeting = 'Good Afternoon';
} else {
    $timeGreeting = 'Good Evening';
}

if ($loggedIn && $patientFirstName !== '') {
    $leadGreeting = $timeGreeting . ', ' . $patientFirstName . '! 👋';
} else {
    $leadGreeting = $timeGreeting . ', Welcome to WeCare! 👋';
}


// Fetch patient-specific summary & activity if logged in
$nextAppointment = null;
$countUpcoming = 0;
$countPendingBills = 0;
$totalPendingAmount = 0.00;
$countPrescriptions = 0;
$patientTimeline = [];

if ($loggedIn) {
    $pId = (int) $loggedPatient['id'];

    // 1. Next upcoming appointment
    $nextStmt = $conn->prepare('
        SELECT a.id, a.appointment_date, a.appointment_time, a.status,
               d.doctor_name, d.specialization, d.department, d.profile_image
        FROM appointments a
        INNER JOIN doctors d ON d.id = a.doctor_id
        WHERE a.patient_id = ? AND a.status IN ("Booked", "Confirmed", "Checked In", "In Consultation") AND a.appointment_date >= CURDATE()
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 1
    ');
    if ($nextStmt) {
        $nextStmt->bind_param('i', $pId);
        $nextStmt->execute();
        $nextAppointment = $nextStmt->get_result()->fetch_assoc();
        $nextStmt->close();
    }

    // 2. Count upcoming appointments
    $upgStmt = $conn->prepare('
        SELECT COUNT(*) AS c FROM appointments 
        WHERE patient_id = ? AND status IN ("Booked", "Confirmed", "Checked In", "In Consultation") AND appointment_date >= CURDATE()
    ');
    if ($upgStmt) {
        $upgStmt->bind_param('i', $pId);
        $upgStmt->execute();
        $countUpcoming = (int) $upgStmt->get_result()->fetch_assoc()['c'];
        $upgStmt->close();
    }

    // 3. Count pending bills
    $bStmt = $conn->prepare('
        SELECT COUNT(*) AS c, SUM(amount) AS total FROM bills 
        WHERE patient_id = ? AND payment_status = "Pending"
    ');
    if ($bStmt) {
        $bStmt->bind_param('i', $pId);
        $bStmt->execute();
        $bRow = $bStmt->get_result()->fetch_assoc();
        $countPendingBills = (int) ($bRow['c'] ?? 0);
        $totalPendingAmount = (float) ($bRow['total'] ?? 0);
        $bStmt->close();
    }

    // 4. Count prescriptions
    $rxStmt = $conn->prepare('SELECT COUNT(*) AS c FROM prescriptions WHERE patient_id = ?');
    if ($rxStmt) {
        $rxStmt->bind_param('i', $pId);
        $rxStmt->execute();
        $countPrescriptions = (int) $rxStmt->get_result()->fetch_assoc()['c'];
        $rxStmt->close();
    }

    // 5. Build recent activity events from real database records (with UTF8MB4 cast)
    $actStmt = $conn->prepare('
        (SELECT "appointment" AS evt_type, a.id AS evt_id, 
                CONVERT(IF(a.status = "Booked", "Appointment Booked", CONCAT("Appointment ", a.status)) USING utf8mb4) AS evt_title,
                CONVERT(a.status USING utf8mb4) AS evt_status, 
                CONVERT(CONCAT("Your appointment with ", d.doctor_name, " was ", LOWER(a.status), ".") USING utf8mb4) AS evt_desc,
                a.created_at AS evt_time
         FROM appointments a
         INNER JOIN doctors d ON d.id = a.doctor_id
         WHERE a.patient_id = ?)
        UNION ALL
        (SELECT "prescription" AS evt_type, pr.id AS evt_id, 
                CONVERT(_utf8mb4"Prescription Updated" USING utf8mb4) AS evt_title,
                CONVERT(_utf8mb4"Issued" USING utf8mb4) AS evt_status,
                CONVERT(CONCAT("A new prescription has been added to your records (", pr.diagnosis, ").") USING utf8mb4) AS evt_desc,
                pr.created_at AS evt_time
         FROM prescriptions pr
         INNER JOIN doctors d ON d.id = pr.doctor_id
         WHERE pr.patient_id = ?)
        UNION ALL
        (SELECT "bill" AS evt_type, b.id AS evt_id, 
                CONVERT(IF(b.payment_status = "Paid", "Payment Completed", "Bill Generated") USING utf8mb4) AS evt_title,
                CONVERT(b.payment_status USING utf8mb4) AS evt_status, 
                CONVERT(IF(b.payment_status = "Paid", 
                    "Your recent hospital payment was successfully recorded.", 
                    CONCAT("A bill for your completed consultation is now available (Statement #WC-", LPAD(b.id, 4, "0"), ", ₹", FORMAT(b.amount, 0), ").")
                ) USING utf8mb4) AS evt_desc,
                b.created_at AS evt_time
         FROM bills b
         WHERE b.patient_id = ?)
        ORDER BY evt_time DESC
        LIMIT 5
    ');
    if ($actStmt) {
        $actStmt->bind_param('iii', $pId, $pId, $pId);
        if ($actStmt->execute()) {
            $actRes = $actStmt->get_result();
            if ($actRes) {
                while ($r = $actRes->fetch_assoc()) {
                    $patientTimeline[] = $r;
                }
                $actRes->free();
            }
        }
        $actStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeCare Hospital | Patient Portal & Appointments</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <!-- 2. WELCOME HERO -->
        <section class="home-hero-card" aria-label="Welcome Hero">
            <div class="home-hero-left">
                <h1 class="home-hero-greeting">
                    <?php echo htmlspecialchars($leadGreeting); ?>
                </h1>
                <p class="home-hero-sub">
                    Your health is our priority.<br>Stay healthy, stay strong.
                </p>

                <div class="home-hero-btn-row">
                    <a href="appointments.php" class="btn btn-hero-primary">
                        Book Appointment &rarr;
                    </a>
                    <a href="doctors.php" class="btn btn-hero-secondary">
                        Find a Doctor
                    </a>
                </div>
            </div>

            <div class="home-hero-right">
                <div class="home-hero-img-wrap">
                    <img src="images/home_doctor_banner.jpg" alt="WeCare Hospital Doctors" class="home-hero-img" width="1376" height="768" loading="eager" decoding="async">
                    <div class="hero-floating-badge">
                        <span class="hero-badge-icon" aria-hidden="true">💙</span>
                        <div>
                            <strong>Better Care</strong>
                            <small>Brighter Future</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Four Quick Action Cards (Matching Master Reference Mockup) -->
        <!-- 3. QUICK ACTION CARDS -->
        <section class="home-actions-grid" aria-label="Quick Actions">
            <!-- Card 1: Find a Doctor -->
            <a href="doctors.php" class="home-action-card">
                <div class="action-card-top">
                    <div class="action-icon-box action-icon-blue" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </div>
                    <span class="action-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="action-card-text">
                    <h3 class="action-card-title">Find a Doctor</h3>
                    <p class="action-card-desc">Connect with trusted specialists.</p>
                </div>
            </a>

            <!-- Card 2: Book Appointment -->
            <a href="appointments.php" class="home-action-card">
                <div class="action-card-top">
                    <div class="action-icon-box action-icon-green" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <span class="action-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="action-card-text">
                    <h3 class="action-card-title">Book Appointment</h3>
                    <p class="action-card-desc">Schedule your hospital visit.</p>
                </div>
            </a>

            <!-- Card 3: View Prescriptions -->
            <a href="prescriptions.php" class="home-action-card">
                <div class="action-card-top">
                    <div class="action-icon-box action-icon-purple" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/>
                            <path d="m8.5 8.5 7 7"/>
                        </svg>
                    </div>
                    <span class="action-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="action-card-text">
                    <h3 class="action-card-title">View Prescriptions</h3>
                    <p class="action-card-desc">Access your medical prescriptions.</p>
                </div>
            </a>

            <!-- Card 4: View Billing -->
            <a href="billing.php" class="home-action-card">
                <div class="action-card-top">
                    <div class="action-icon-box action-icon-amber" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="14" x="2" y="5" rx="2"/>
                            <line x1="2" y1="10" x2="22" y2="10"/>
                        </svg>
                    </div>
                    <span class="action-arrow" aria-hidden="true">&rarr;</span>
                </div>
                <div class="action-card-text">
                    <h3 class="action-card-title">View Billing</h3>
                    <p class="action-card-desc">Check your bills and payments.</p>
                </div>
            </a>
        </section>

        <!-- 4 & 5. UPCOMING APPOINTMENT & RECENT ACTIVITY (2 Columns) -->
        <section class="home-two-col-grid">
            <!-- 4. UPCOMING APPOINTMENT -->
            <div class="panel upcoming-appt-panel">
                <div class="panel-header-flex">
                    <div>
                        <h2>Upcoming Appointment</h2>
                        <p class="section-subtext">Your next scheduled hospital consultation</p>
                    </div>
                    <?php if ($loggedIn && $nextAppointment !== null): ?>
                        <span class="badge badge-confirmed"><?php echo htmlspecialchars($nextAppointment['status']); ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($loggedIn && $nextAppointment !== null): 
                    $docInitials = getInitials($nextAppointment['doctor_name']);
                    $apptTimestamp = strtotime($nextAppointment['appointment_date']);
                    $todayTimestamp = strtotime(date('Y-m-d'));
                    $diffDays = (int) round(($apptTimestamp - $todayTimestamp) / 86400);
                    if ($diffDays === 0) {
                        $dateLabel = 'Today';
                    } elseif ($diffDays === 1) {
                        $dateLabel = 'Tomorrow';
                    } else {
                        $dateLabel = date('l, M d', $apptTimestamp);
                    }
                    $timeStart = date('h:i A', strtotime($nextAppointment['appointment_time']));
                    $timeEnd = date('h:i A', strtotime($nextAppointment['appointment_time']) + 1800);
                ?>
                    <div class="upcoming-doc-card-inner">
                        <div class="upcoming-doc-avatar-wrap">
                            <?php if (!empty($nextAppointment['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($nextAppointment['profile_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($nextAppointment['doctor_name']); ?>" 
                                     class="upcoming-doc-img"
                                     width="896" height="1200"
                                     loading="lazy" decoding="async"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="upcoming-avatar-circle" style="display: none;"><?php echo htmlspecialchars($docInitials); ?></div>
                            <?php else: ?>
                                <div class="upcoming-avatar-circle"><?php echo htmlspecialchars($docInitials); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="upcoming-doc-details">
                            <h3 class="upcoming-doc-name"><?php echo htmlspecialchars($nextAppointment['doctor_name']); ?></h3>
                            <div class="upcoming-doc-spec"><?php echo htmlspecialchars($nextAppointment['specialization']); ?></div>
                            <div class="upcoming-doc-dept"><?php echo htmlspecialchars($nextAppointment['department'] ?: ($nextAppointment['specialization'] . ' & Heart Institute')); ?></div>

                            <div class="upcoming-datetime-badge">
                                <span class="upcoming-date-pill">📅 <?php echo htmlspecialchars($dateLabel); ?></span>
                                <span class="upcoming-time-pill">⏰ <?php echo htmlspecialchars($timeStart . ' – ' . $timeEnd); ?></span>
                            </div>

                            <div class="upcoming-actions-row">
                                <a href="appointments.php?view=my_appointments" class="btn btn-primary-highlight btn-sm">
                                    View Details
                                </a>
                                <a href="appointments.php" class="btn btn-secondary btn-sm">
                                    Reschedule
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-upcoming-card-box">
                        <div class="no-upcoming-icon" aria-hidden="true">📅</div>
                        <h3>No upcoming appointments</h3>
                        <p>Your next visit will appear here once you book an appointment.</p>
                        <a href="appointments.php" class="btn btn-primary-highlight">
                            Book Appointment
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 5. RECENT ACTIVITY (Real Database Records) -->
            <div class="panel recent-activity-panel">
                <div class="panel-header-flex">
                    <div>
                        <h2>Recent Activity</h2>
                        <p class="section-subtext">Real-time clinical timeline from hospital database</p>
                    </div>
                    <?php if ($loggedIn): ?>
                        <span class="badge badge-specialty">Live Feed</span>
                    <?php endif; ?>
                </div>

                <div class="activity-timeline-scroll">
                    <?php if ($loggedIn && !empty($patientTimeline)): ?>
                        <ul class="timeline-list">
                            <?php foreach ($patientTimeline as $act): 
                                $actIcon = '📅';
                                $badgeType = 'badge-confirmed';
                                if ($act['evt_type'] === 'prescription') {
                                    $actIcon = '💊';
                                    $badgeType = 'badge-specialty';
                                } elseif ($act['evt_type'] === 'bill') {
                                    $actIcon = '🧾';
                                    $badgeType = ($act['evt_status'] === 'Paid') ? 'badge-completed' : 'badge-checked-in';
                                }
                            ?>
                                <li class="timeline-item">
                                    <span class="timeline-dot-icon" aria-hidden="true"><?php echo $actIcon; ?></span>
                                    <div class="timeline-item-content">
                                        <div class="timeline-item-title-row">
                                            <span class="timeline-item-title"><?php echo htmlspecialchars($act['evt_title']); ?></span>
                                            <span class="badge badge-sm <?php echo $badgeType; ?>"><?php echo htmlspecialchars($act['evt_status']); ?></span>
                                        </div>
                                        <p class="timeline-item-desc"><?php echo htmlspecialchars($act['evt_desc']); ?></p>
                                        <span class="timeline-time"><?php echo date('M d, Y · h:i A', strtotime($act['evt_time'])); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php elseif ($loggedIn): ?>
                        <div class="empty-state-card" style="padding: 2.5rem 1rem; text-align: center;">
                            <div class="empty-state-icon" style="font-size: 2rem; margin-bottom: 0.5rem;">📅</div>
                            <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.35rem;">No Recent Activity</h3>
                            <p style="font-size: 0.84rem; color: #64748b; margin: 0;">Your completed consultations and issued statements will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-card" style="padding: 2.5rem 1rem; text-align: center;">
                            <div class="empty-state-icon" style="font-size: 2rem; margin-bottom: 0.5rem;">🔒</div>
                            <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0 0 0.35rem;">Sign In to View Activity</h3>
                            <p style="font-size: 0.84rem; color: #64748b; margin: 0 0 1rem;">Log in to access your live consultation updates, digital prescriptions, and billing receipts.</p>
                            <a href="login.php" class="btn btn-secondary btn-sm">Sign in to WeCare</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- 6. HEALTHCARE FEATURES ("Why Choose WeCare?") -->
        <section class="panel">
            <div class="panel-header-flex">
                <div>
                    <h2>Why Choose WeCare?</h2>
                    <p class="section-subtext">Clinical excellence, transparent medical procedures, and patient-centered hospital workflows</p>
                </div>
            </div>

            <div class="why-choose-grid">
                <div class="why-card-mini">
                    <div class="why-icon-mini" aria-hidden="true">✓</div>
                    <h3 class="why-title-mini">Verified Specialists</h3>
                    <p class="why-desc-mini">Experienced healthcare professionals.</p>
                </div>

                <div class="why-card-mini">
                    <div class="why-icon-mini" aria-hidden="true">✓</div>
                    <h3 class="why-title-mini">Easy Online Booking</h3>
                    <p class="why-desc-mini">Book appointments quickly.</p>
                </div>

                <div class="why-card-mini">
                    <div class="why-icon-mini" aria-hidden="true">✓</div>
                    <h3 class="why-title-mini">Digital Prescriptions</h3>
                    <p class="why-desc-mini">Access prescriptions anytime.</p>
                </div>

                <div class="why-card-mini">
                    <div class="why-icon-mini" aria-hidden="true">✓</div>
                    <h3 class="why-title-mini">Secure Health Records</h3>
                    <p class="why-desc-mini">Your medical information stays protected.</p>
                </div>

                <div class="why-card-mini">
                    <div class="why-icon-mini" aria-hidden="true">✓</div>
                    <h3 class="why-title-mini">Transparent Billing</h3>
                    <p class="why-desc-mini">View clear hospital billing information.</p>
                </div>

                <div class="why-card-mini">
                    <div class="why-icon-mini" aria-hidden="true">✓</div>
                    <h3 class="why-title-mini">Follow-up Care</h3>
                    <p class="why-desc-mini">Keep track of your continuing care.</p>
                </div>
            </div>
        </section>

        <!-- 7. MEDICAL DEPARTMENTS ("Our Medical Departments") -->
        <section class="panel">
            <div class="panel-header-flex">
                <div>
                    <h2>Our Medical Departments</h2>
                    <p class="section-subtext">Comprehensive outpatient and inpatient clinical specialties</p>
                </div>
                <a href="doctors.php" class="section-header-link">View all doctors &rarr;</a>
            </div>

            <div class="dept-grid-8">
                <!-- 1. Cardiology -->
                <a href="doctors.php?specialty=Cardiology" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">❤️</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">Cardiology</h3>
                        <p class="dept-card-desc">Heart &amp; cardiovascular care</p>
                    </div>
                </a>

                <!-- 2. Orthopedics -->
                <a href="doctors.php?specialty=Orthopedics" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">🦴</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">Orthopedics</h3>
                        <p class="dept-card-desc">Bones, joints &amp; mobility</p>
                    </div>
                </a>

                <!-- 3. Pediatrics -->
                <a href="doctors.php?specialty=Pediatrics" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">👶</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">Pediatrics</h3>
                        <p class="dept-card-desc">Child &amp; adolescent care</p>
                    </div>
                </a>

                <!-- 4. Neurology -->
                <a href="doctors.php?specialty=Neurology" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">🧠</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">Neurology</h3>
                        <p class="dept-card-desc">Brain &amp; nervous system</p>
                    </div>
                </a>

                <!-- 5. Dermatology -->
                <a href="doctors.php?specialty=Dermatology" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">✨</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">Dermatology</h3>
                        <p class="dept-card-desc">Skin &amp; hair care</p>
                    </div>
                </a>

                <!-- 6. Gynecology -->
                <a href="doctors.php?specialty=Gynecology" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">🌸</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">Gynecology</h3>
                        <p class="dept-card-desc">Women's health</p>
                    </div>
                </a>

                <!-- 7. ENT -->
                <a href="doctors.php?specialty=ENT" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">👂</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">ENT</h3>
                        <p class="dept-card-desc">Ear, nose &amp; throat</p>
                    </div>
                </a>

                <!-- 8. General Medicine -->
                <a href="doctors.php?specialty=General+Medicine" class="dept-grid-card">
                    <div class="dept-card-top">
                        <span class="dept-icon-box" aria-hidden="true">🩺</span>
                        <span class="dept-arrow" aria-hidden="true">&rarr;</span>
                    </div>
                    <div class="dept-card-info">
                        <h3 class="dept-card-name">General Medicine</h3>
                        <p class="dept-card-desc">Comprehensive medical care</p>
                    </div>
                </a>
            </div>
        </section>

        <!-- 8. EMERGENCY / SUPPORT SECTION -->
        <section class="home-support-card">
            <div class="support-content-left">
                <h3 class="support-title">Need Help With Your Care?</h3>
                <p class="support-desc">Our support team is available to assist you with appointments, prescriptions, billing and general questions.</p>
            </div>
            <div class="support-btn-group">
                <a href="mailto:support@wecare.hospital" class="btn btn-support-contact">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="16" x="2" y="4" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    Contact Support
                </a>
                <a href="appointments.php" class="btn btn-support-appt">
                    Request Appointment
                </a>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
            <p class="footer-links">
                <a href="index.php">Home</a> · 
                <a href="appointments.php">Request Appointment</a> · 
                <a href="doctors.php">Doctor Directory</a> · 
                <a href="billing.php">Billing</a> · 
                <a href="prescriptions.php">Prescriptions</a> · 
                <a href="health_records.php">Health Records</a> · 
                <a href="profile.php">Profile</a>
            </p>
        </div>
    </footer>
</body>
</html>
