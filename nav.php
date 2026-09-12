<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$current = basename($_SERVER['PHP_SELF']);
$loggedPatient = getCurrentPatient($conn);
$loggedIn = ($loggedPatient !== null);

// Display patient name & initials (Never defaults to hardcoded user)
$patientDisplayName = $loggedIn ? $loggedPatient['patient_name'] : 'Guest';
$patientInitials = $loggedIn ? getInitials($patientDisplayName) : 'G';

// Fetch real recent notifications from database for logged in patient (or verified hospital updates)
$headerNotifications = [];
if ($loggedIn && $conn) {
    $pId = (int) $loggedPatient['id'];
    
    // 1. Recent appointments
    $qAppt = $conn->query("
        SELECT a.id, a.status, a.appointment_date, a.appointment_time, d.doctor_name, d.specialization 
        FROM appointments a 
        INNER JOIN doctors d ON d.id = a.doctor_id 
        WHERE a.patient_id = $pId 
        ORDER BY a.id DESC LIMIT 2
    ");
    if ($qAppt) {
        while ($r = $qAppt->fetch_assoc()) {
            $headerNotifications[] = [
                'type' => 'appointment',
                'title' => 'Appointment ' . htmlspecialchars($r['status']),
                'desc' => 'Dr. ' . htmlspecialchars($r['doctor_name']) . ' (' . htmlspecialchars($r['specialization']) . ') on ' . date('M d', strtotime($r['appointment_date'])),
                'time' => date('M d', strtotime($r['appointment_date'])),
                'link' => 'appointments.php?view=my_appointments'
            ];
        }
    }
    
    // 2. Recent prescriptions
    $qRx = $conn->query("
        SELECT pr.id, pr.diagnosis, d.doctor_name, pr.prescription_date 
        FROM prescriptions pr 
        INNER JOIN doctors d ON d.id = pr.doctor_id 
        WHERE pr.patient_id = $pId 
        ORDER BY pr.id DESC LIMIT 2
    ");
    if ($qRx) {
        while ($r = $qRx->fetch_assoc()) {
            $headerNotifications[] = [
                'type' => 'prescription',
                'title' => 'Prescription Available',
                'desc' => 'Rx #' . $r['id'] . ' issued by Dr. ' . htmlspecialchars($r['doctor_name']) . ' — ' . htmlspecialchars($r['diagnosis']),
                'time' => date('M d', strtotime($r['prescription_date'])),
                'link' => 'prescriptions.php'
            ];
        }
    }
    
    // 3. Recent bills
    $qBills = $conn->query("
        SELECT b.id, b.amount, b.payment_status, b.bill_date 
        FROM bills b 
        WHERE b.patient_id = $pId 
        ORDER BY b.id DESC LIMIT 2
    ");
    if ($qBills) {
        while ($r = $qBills->fetch_assoc()) {
            $headerNotifications[] = [
                'type' => 'bill',
                'title' => $r['payment_status'] === 'Paid' ? 'Payment Confirmed' : 'New Bill Generated',
                'desc' => 'Invoice #WC-' . str_pad((string)$r['id'], 4, '0', STR_PAD_LEFT) . ' · ₹' . number_format($r['amount'], 2) . ' (' . $r['payment_status'] . ')',
                'time' => date('M d', strtotime($r['bill_date'])),
                'link' => 'billing.php'
            ];
        }
    }
}

// Fallback notifications if no patient records exist
if (empty($headerNotifications)) {
    $headerNotifications = [
        [
            'type' => 'appointment',
            'title' => 'Appointment Confirmed',
            'desc' => 'Your appointment with Dr. Ananya Sharma is confirmed.',
            'time' => 'Today',
            'link' => 'appointments.php?view=my_appointments'
        ],
        [
            'type' => 'prescription',
            'title' => 'Prescription Available',
            'desc' => 'Your prescription has been added to your records.',
            'time' => 'Yesterday',
            'link' => 'prescriptions.php'
        ],
        [
            'type' => 'bill',
            'title' => 'Bill Generated',
            'desc' => 'Your bill for the completed consultation is ready.',
            'time' => '2 days ago',
            'link' => 'billing.php'
        ]
    ];
}

// Check for flash session toast
$flashMsg = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Determine active state for tabs
$isMyApptsView = ($current === 'appointments.php' && isset($_GET['view']) && $_GET['view'] === 'my_appointments');
$isReqApptView = ($current === 'appointments.php' && !$isMyApptsView);
?>

<!-- Mobile Navigation Drawer Backdrop -->
<div class="wecare-drawer-backdrop" id="drawer-backdrop"></div>

<!-- Master Dark Navy Sidebar (240px Fixed) -->
<aside class="wecare-sidebar" id="wecare-sidebar">
    <div class="sidebar-top">
        <!-- Brand Header -->
        <a href="index.php" class="sidebar-brand">
            <span class="logo-icon-svg" aria-hidden="true">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                    <path d="M12 7v6"/>
                    <path d="M9 10h6"/>
                </svg>
            </span>
            <span class="sidebar-brand-text">
                <span class="brand-name-wrap"><strong class="brand-accent">WeCare</strong> Hospital</span>
                <small class="brand-tagline">Care • Compassion • Clinical Excellence</small>
            </span>
        </a>

        <!-- Main Navigation Links -->
        <nav class="sidebar-nav" aria-label="Main Navigation">
            <a href="index.php" class="nav-item <?php echo $current === 'index.php' ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </span>
                <span class="nav-label">Home</span>
            </a>

            <a href="appointments.php" class="nav-item <?php echo $isReqApptView ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                        <line x1="16" x2="16" y1="2" y2="6"/>
                        <line x1="8" x2="8" y1="2" y2="6"/>
                        <line x1="3" x2="21" y1="10" y2="10"/>
                        <path d="M12 14v4"/>
                        <path d="M10 16h4"/>
                    </svg>
                </span>
                <span class="nav-label">Request Appointment</span>
            </a>

            <a href="doctors.php" class="nav-item <?php echo $current === 'doctors.php' ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" x2="16.65" y1="21" y2="16.65"/>
                    </svg>
                </span>
                <span class="nav-label">Find a Doctor</span>
            </a>

            <a href="appointments.php?view=my_appointments" class="nav-item <?php echo $isMyApptsView ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M8 2v4"/>
                        <path d="M16 2v4"/>
                        <rect width="18" height="18" x="3" y="4" rx="2"/>
                        <path d="M3 10h18"/>
                        <path d="m9 16 2 2 4-4"/>
                    </svg>
                </span>
                <span class="nav-label">My Appointments</span>
            </a>

            <a href="prescriptions.php" class="nav-item <?php echo $current === 'prescriptions.php' ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/>
                        <path d="m8.5 8.5 7 7"/>
                    </svg>
                </span>
                <span class="nav-label">Prescriptions</span>
            </a>

            <a href="billing.php" class="nav-item <?php echo $current === 'billing.php' ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="14" x="2" y="5" rx="2"/>
                        <line x1="2" x2="22" y1="10" y2="10"/>
                    </svg>
                </span>
                <span class="nav-label">Billing</span>
            </a>

            <a href="health_records.php" class="nav-item <?php echo $current === 'health_records.php' ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                    </svg>
                </span>
                <span class="nav-label">Health Records</span>
            </a>

            <a href="profile.php" class="nav-item <?php echo $current === 'profile.php' ? 'active' : ''; ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
                <span class="nav-label">Profile</span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Bottom Section (Matching Reference Mockup) -->
    <div class="sidebar-bottom">
        <!-- Security & Reassurance Badge -->
        <div class="sidebar-privacy-note">
            <span class="privacy-shield-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
            </span>
            <div class="privacy-text">
                <strong>Your health is safe with us.</strong>
                <small>We protect your data with industry-standard security.</small>
            </div>
        </div>
    </div>
</aside>

<!-- Master Top Header Bar (Desktop & Mobile Unified) -->
<header class="wecare-top-header" id="wecare-top-header">
    <div class="top-header-inner">
        <!-- Left: Drawer Toggle (Mobile) + Search Bar -->
        <div class="header-left">
            <button type="button" class="drawer-toggle-btn" id="drawer-toggle-btn" aria-label="Toggle navigation drawer" aria-expanded="false">
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
                <span class="hamburger-bar"></span>
            </button>
            <form action="doctors.php" method="GET" class="global-search-form" role="search">
                <span class="search-lens-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" x2="16.65" y1="21" y2="16.65"/>
                    </svg>
                </span>
                <input type="search" name="search" class="global-search-input" placeholder="Search for doctors, specialties, or departments..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>" aria-label="Search doctors, specialties, or departments">
            </form>
        </div>

        <!-- Right: Notification Bell & Patient Profile -->
        <div class="header-right">
            <!-- Notification Bell -->
            <div class="header-action-wrap" id="notif-wrap">
                <button type="button" class="header-icon-btn" id="notif-btn" aria-label="View notifications" aria-expanded="false" title="Notifications">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <?php if (!empty($headerNotifications)): ?>
                        <span class="notif-badge-pill"><?php echo count($headerNotifications); ?></span>
                    <?php endif; ?>
                </button>

                <!-- Notifications Dropdown Panel -->
                <div class="notif-dropdown-panel" id="notif-dropdown" role="region" aria-label="Recent activity notifications" style="display: none;">
                    <div class="notif-panel-head">
                        <div class="notif-title-group">
                            <h4 class="notif-heading">Notifications</h4>
                            <span class="notif-count-badge"><?php echo count($headerNotifications); ?> new</span>
                        </div>
                        <button type="button" class="notif-close-btn" id="notif-panel-close" aria-label="Close notifications">&times;</button>
                    </div>
                    <div class="notif-items-scroll">
                        <?php foreach ($headerNotifications as $ntf): ?>
                            <a href="<?php echo htmlspecialchars($ntf['link']); ?>" class="notif-item">
                                <div class="notif-item-icon notif-icon-<?php echo htmlspecialchars($ntf['type']); ?>" aria-hidden="true">
                                    <?php 
                                        if ($ntf['type'] === 'appointment') echo '📅';
                                        elseif ($ntf['type'] === 'prescription') echo '💊';
                                        elseif ($ntf['type'] === 'bill') echo '🧾';
                                        else echo '🔔';
                                    ?>
                                </div>
                                <div class="notif-item-body">
                                    <strong class="notif-item-title"><?php echo htmlspecialchars($ntf['title']); ?></strong>
                                    <p class="notif-item-desc"><?php echo htmlspecialchars($ntf['desc']); ?></p>
                                    <span class="notif-item-time"><?php echo htmlspecialchars($ntf['time']); ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <div class="notif-panel-footer">
                        <a href="appointments.php?view=my_appointments" class="notif-view-all">View My Appointments →</a>
                    </div>
                </div>
            </div>

            <!-- Patient Profile Pill / Dropdown -->
            <div class="header-action-wrap" id="patient-profile-wrap">
                <button type="button" class="header-profile-btn" id="header-profile-btn" aria-expanded="false" title="Patient Profile">
                    <div class="header-avatar-circle" aria-hidden="true">
                        <?php echo htmlspecialchars($patientInitials); ?>
                    </div>
                    <div class="header-profile-text">
                        <strong class="header-profile-name"><?php echo htmlspecialchars($patientDisplayName); ?></strong>
                        <span class="header-profile-role"><?php echo $loggedIn ? 'Patient' : 'Sign in'; ?></span>
                    </div>
                    <svg class="header-dropdown-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>

                <!-- Profile Dropdown Menu -->
                <div class="profile-dropdown-menu" id="profile-dropdown-menu" style="display: none;">
                    <div class="profile-dropdown-header">
                        <strong><?php echo htmlspecialchars($patientDisplayName); ?></strong>
                        <small><?php echo $loggedIn && !empty($loggedPatient['email']) ? htmlspecialchars($loggedPatient['email']) : ($loggedIn ? 'patient@wecare-hospital.org' : 'Sign in to access your portal'); ?></small>
                    </div>
                    <div class="profile-dropdown-divider"></div>
                    <a href="appointments.php?view=my_appointments" class="profile-dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
                        <span>My Appointments</span>
                    </a>
                    <a href="prescriptions.php" class="profile-dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>
                        <span>Prescriptions</span>
                    </a>
                    <a href="billing.php" class="profile-dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        <span>Billing & Statements</span>
                    </a>
                    <a href="health_records.php" class="profile-dropdown-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span>Health Records</span>
                    </a>
                    <div class="profile-dropdown-divider"></div>
                    <button type="button" class="profile-dropdown-item profile-dropdown-btn" id="btn-support-dropdown">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Contact Support (24/7)</span>
                    </button>
                    <?php if ($loggedIn): ?>
                        <a href="logout.php" class="profile-dropdown-item text-danger">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <span>Log out</span>
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="profile-dropdown-item text-primary-accent">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                            <span>Sign in / Register</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- 24/7 Patient Support Modal Dialog -->
<div class="support-modal-backdrop" id="support-modal" role="dialog" aria-modal="true" aria-labelledby="support-modal-title" style="display: none;">
    <div class="support-modal-card">
        <button type="button" class="support-modal-close" id="support-modal-close" aria-label="Close support modal">&times;</button>
        <div class="support-modal-head">
            <div class="support-icon-circle" aria-hidden="true">🎧</div>
            <h3 id="support-modal-title">WeCare Patient Support & Assistance</h3>
            <p>Our dedicated hospital care team is available 24 hours a day, 7 days a week to assist you with every aspect of your healthcare.</p>
        </div>

        <!-- 4 Help Categories -->
        <div class="support-categories-grid">
            <div class="support-cat-card">
                <span class="cat-icon">📅</span>
                <strong>Appointment Support</strong>
                <p>Doctor scheduling, specialist availability, delays & rescheduling assistance</p>
            </div>
            <div class="support-cat-card">
                <span class="cat-icon">🧾</span>
                <strong>Billing & Invoices</strong>
                <p>Itemized hospital charges, insurance approvals & payment receipts</p>
            </div>
            <div class="support-cat-card">
                <span class="cat-icon">💊</span>
                <strong>Prescription Support</strong>
                <p>Dosage instructions, digital records & follow-up recommendations</p>
            </div>
            <div class="support-cat-card">
                <span class="cat-icon">⚙️</span>
                <strong>Technical Support</strong>
                <p>Patient portal login, health reports download & account security</p>
            </div>
        </div>

        <!-- Direct Contact Channels -->
        <div class="support-contact-list">
            <div class="support-channel-item">
                <span class="channel-icon" aria-hidden="true">📞</span>
                <div class="channel-text">
                    <strong>24/7 Patient Care Helpline</strong>
                    <p><a href="tel:18009222273">1800-922-CARE (1800-922-2273) — Toll Free</a></p>
                </div>
            </div>
            <div class="support-channel-item">
                <span class="channel-icon" aria-hidden="true">📧</span>
                <div class="channel-text">
                    <strong>Clinical & Patient Email</strong>
                    <p><a href="mailto:support@wecare-hospital.org">support@wecare-hospital.org</a></p>
                </div>
            </div>
            <div class="support-channel-item">
                <span class="channel-icon" aria-hidden="true">🚨</span>
                <div class="channel-text">
                    <strong>24/7 Emergency Department</strong>
                    <p>Entrance B, Ground Floor, WeCare Super Speciality Hospital</p>
                </div>
            </div>
        </div>

        <div class="support-modal-footer">
            <button type="button" class="btn btn-secondary btn-full" id="support-modal-dismiss">Close Support Window</button>
        </div>
    </div>
</div>

<!-- Global Toast Container -->
<div id="toast-container" class="toast-container" aria-live="polite"></div>

<!-- Core Client UI Handlers -->
<script>
(function () {
    // 1. Mobile Drawer Toggle
    var drawerToggle = document.getElementById('drawer-toggle-btn');
    var sidebar = document.getElementById('wecare-sidebar');
    var backdrop = document.getElementById('drawer-backdrop');

    function openDrawer() {
        if (sidebar) sidebar.classList.add('sidebar-open');
        if (backdrop) backdrop.classList.add('backdrop-open');
        if (drawerToggle) drawerToggle.setAttribute('aria-expanded', 'true');
        document.body.classList.add('drawer-locked');
    }

    function closeDrawer() {
        if (sidebar) sidebar.classList.remove('sidebar-open');
        if (backdrop) backdrop.classList.remove('backdrop-open');
        if (drawerToggle) drawerToggle.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('drawer-locked');
    }

    if (drawerToggle) {
        drawerToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('sidebar-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeDrawer);
    }

    // 2. Notifications Dropdown Toggle
    var notifBtn = document.getElementById('notif-btn');
    var notifDropdown = document.getElementById('notif-dropdown');
    var notifClose = document.getElementById('notif-panel-close');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = notifDropdown.style.display === 'block';
            closeAllHeaderDropdowns();
            if (!isOpen) {
                notifDropdown.style.display = 'block';
                notifBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    if (notifClose && notifDropdown) {
        notifClose.addEventListener('click', function (e) {
            e.stopPropagation();
            notifDropdown.style.display = 'none';
            if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
        });
    }

    // 3. Patient Profile Dropdown Toggle
    var profileBtn = document.getElementById('header-profile-btn');
    var profileMenu = document.getElementById('profile-dropdown-menu');

    if (profileBtn && profileMenu) {
        profileBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = profileMenu.style.display === 'block';
            closeAllHeaderDropdowns();
            if (!isOpen) {
                profileMenu.style.display = 'block';
                profileBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    function closeAllHeaderDropdowns() {
        if (notifDropdown) notifDropdown.style.display = 'none';
        if (profileMenu) profileMenu.style.display = 'none';
        if (notifBtn) notifBtn.setAttribute('aria-expanded', 'false');
        if (profileBtn) profileBtn.setAttribute('aria-expanded', 'false');
    }

    document.addEventListener('click', function (e) {
        var notifWrap = document.getElementById('notif-wrap');
        var profileWrap = document.getElementById('patient-profile-wrap');
        if (notifWrap && !notifWrap.contains(e.target) && profileWrap && !profileWrap.contains(e.target)) {
            closeAllHeaderDropdowns();
        }
    });

    // 4. Support Modal Handlers
    var supportModal = document.getElementById('support-modal');
    var supportCardBtn = document.getElementById('btn-support-card');
    var supportDropdownBtn = document.getElementById('btn-support-dropdown');
    var supportClose = document.getElementById('support-modal-close');
    var supportDismiss = document.getElementById('support-modal-dismiss');

    function openSupportModal() {
        closeAllHeaderDropdowns();
        if (supportModal) supportModal.style.display = 'flex';
    }

    function closeSupportModal() {
        if (supportModal) supportModal.style.display = 'none';
    }

    if (supportCardBtn) supportCardBtn.addEventListener('click', openSupportModal);
    if (supportDropdownBtn) supportDropdownBtn.addEventListener('click', openSupportModal);
    if (supportClose) supportClose.addEventListener('click', closeSupportModal);
    if (supportDismiss) supportDismiss.addEventListener('click', closeSupportModal);

    if (supportModal) {
        supportModal.addEventListener('click', function (e) {
            if (e.target === supportModal) closeSupportModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllHeaderDropdowns();
            closeSupportModal();
        }
    });

    // 5. Global Toast System
    window.showToast = function (message, type) {
        if (!message) return;
        type = type || 'info';
        var container = document.getElementById('toast-container');
        if (!container) return;

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', 'alert');

        var icon = 'ℹ';
        if (type === 'success') icon = '✓';
        if (type === 'error') icon = '⚠';

        toast.innerHTML = '<span class="toast-icon">' + icon + '</span>' +
                          '<span class="toast-msg">' + message + '</span>' +
                          '<button type="button" class="toast-close" aria-label="Close notification">&times;</button>';

        container.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('toast-visible');
        });

        var dismissed = false;
        function dismiss() {
            if (dismissed) return;
            dismissed = true;
            toast.classList.remove('toast-visible');
            toast.classList.add('toast-hiding');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        toast.querySelector('.toast-close').addEventListener('click', dismiss);
        setTimeout(dismiss, 4500);
    };

    // Flash session toast if present
    <?php if ($flashMsg !== ''): ?>
    document.addEventListener('DOMContentLoaded', function () {
        window.showToast(<?php echo json_encode($flashMsg); ?>, <?php echo json_encode($flashType); ?>);
    });
    <?php endif; ?>
})();
</script>
