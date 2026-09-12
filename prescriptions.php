<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin('prescriptions.php');

$loggedPatient = getCurrentPatient($conn);
$loggedIn = ($loggedPatient !== null);
$today = date('Y-m-d');

$message = '';
$messageType = '';

// Check for flash session toast
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'info';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Optional query by appointment_id
$filterApptId = (int) ($_GET['appointment_id'] ?? 0);

// Fetch prescriptions strictly for logged-in patient
$patientPrescriptions = [];

if ($loggedIn) {
    $sql = '
        SELECT pr.id, pr.patient_id, pr.doctor_id, pr.appointment_id, pr.diagnosis, pr.medicines, pr.instructions, pr.follow_up_date, pr.prescription_date, pr.created_at,
               p.patient_name, p.age, p.gender, p.phone, p.address,
               d.doctor_name, d.specialization, d.department, d.education, d.profile_image,
               a.appointment_date, a.appointment_time
        FROM prescriptions pr
        INNER JOIN patients p ON p.id = pr.patient_id
        INNER JOIN doctors d ON d.id = pr.doctor_id
        LEFT JOIN appointments a ON a.id = pr.appointment_id
        WHERE pr.patient_id = ?
    ';
    if ($filterApptId > 0) {
        $sql .= ' AND pr.appointment_id = ' . $filterApptId;
    }
    $sql .= ' ORDER BY pr.prescription_date DESC, pr.id DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $loggedPatient['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $patientPrescriptions[] = $row;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <div class="page-head">
            <h1 class="page-title">Your Prescriptions</h1>
            <p class="page-sub">View and manage your medical prescriptions.</p>
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

        <?php if (!$loggedIn): ?>
            <section class="panel signin-required-card">
                <div class="signin-required-icon" aria-hidden="true">🔒</div>
                <h2>Sign in to view your prescriptions</h2>
                <p>Log in to access digital prescriptions, medication schedules, and clinical notes provided by your WeCare attending specialists.</p>
                <div class="signin-required-actions">
                    <a href="login.php?redirect=prescriptions.php" class="btn btn-primary-highlight">Sign in</a>
                    <a href="login.php?action=register&redirect=prescriptions.php" class="btn btn-secondary">Create account</a>
                </div>
            </section>
        <?php else: ?>

            <div class="rx-subtabs-row">
                <a href="prescriptions.php" class="rx-subtab active">Active (<?php echo count($patientPrescriptions); ?>)</a>
                <a href="prescriptions.php?past=1" class="rx-subtab">Past (0)</a>
            </div>

            <?php if (!empty($patientPrescriptions)): ?>
                <div class="rx-horizontal-list">
                    <?php foreach ($patientPrescriptions as $rx): 
                        $docInitials = getInitials($rx['doctor_name']);
                        $rxId = (int) $rx['id'];
                        $apptId = (int) ($rx['appointment_id'] ?? 0);
                    ?>
                        <article class="rx-horizontal-card">
                            <div class="rx-card-left">
                                <div class="rx-card-avatar">
                                    <?php if (!empty($rx['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($rx['profile_image']); ?>" 
                                             alt="<?php echo htmlspecialchars($rx['doctor_name']); ?>" 
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="rx-card-initials" style="display: none;"><?php echo htmlspecialchars($docInitials); ?></div>
                                    <?php else: ?>
                                        <div class="rx-card-initials"><?php echo htmlspecialchars($docInitials); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="rx-card-info">
                                    <h3 class="rx-card-doctor"><?php echo htmlspecialchars($rx['doctor_name']); ?></h3>
                                    <div class="rx-card-specialty"><?php echo htmlspecialchars($rx['specialization']); ?></div>
                                    <div class="rx-card-date">
                                        <span>📅 Issued on: <?php echo date('M d, Y', strtotime($rx['prescription_date'])); ?></span>
                                        <?php if (!empty($rx['diagnosis'])): ?>
                                            <span>· <strong style="color: #475569;"><?php echo htmlspecialchars($rx['diagnosis']); ?></strong></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="rx-card-right">
                                <span class="status-pill-green">Active</span>
                                <button type="button" class="btn btn-secondary btn-sm btn-open-rx-modal"
                                    data-id="<?php echo $rxId; ?>"
                                    data-doctor="<?php echo htmlspecialchars($rx['doctor_name']); ?>"
                                    data-spec="<?php echo htmlspecialchars($rx['specialization']); ?>"
                                    data-dept="<?php echo htmlspecialchars($rx['department'] ?? ($rx['specialization'] . ' Department')); ?>"
                                    data-edu="<?php echo htmlspecialchars($rx['education'] ?? 'MBBS, MD'); ?>"
                                    data-patient="<?php echo htmlspecialchars($rx['patient_name']); ?>"
                                    data-age="<?php echo (int) $rx['age']; ?>"
                                    data-gender="<?php echo htmlspecialchars($rx['gender']); ?>"
                                    data-date="<?php echo date('F j, Y', strtotime($rx['prescription_date'])); ?>"
                                    data-diagnosis="<?php echo htmlspecialchars($rx['diagnosis']); ?>"
                                    data-medicines="<?php echo htmlspecialchars($rx['medicines']); ?>"
                                    data-instructions="<?php echo htmlspecialchars($rx['instructions'] ?? 'Follow dosage schedule as advised.'); ?>"
                                    data-follow="<?php echo !empty($rx['follow_up_date']) ? date('F j, Y', strtotime($rx['follow_up_date'])) : 'As needed'; ?>"
                                    data-apptid="<?php echo $apptId; ?>">
                                    View Prescription
                                </button>
                                <a href="appointments.php?doctor_id=<?php echo (int) $rx['doctor_id']; ?>#booking-form" class="btn btn-primary-highlight btn-sm" title="Schedule consultation with this doctor">
                                    Book Follow-up
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state-card panel">
                    <div class="empty-state-icon">💊</div>
                    <h3>No prescriptions on file</h3>
                    <p>You do not have any digital prescriptions registered under this patient account yet. Once you complete a consultation with a WeCare specialist, your prescriptions will appear here.</p>
                    <a href="appointments.php" class="btn btn-primary-highlight">Schedule Consultation</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
        </div>
    </footer>

    <!-- Clinical Prescription Document Modal -->
    <div class="doctor-modal-backdrop" id="rx-modal" style="display: none;">
        <div class="doctor-modal-card rx-modal-card" style="max-width: 720px;">
            <button type="button" class="doctor-modal-close no-print" id="rx-close-btn">&times;</button>
            
            <div class="rx-printable-sheet" id="rx-print-area">
                <!-- Prescription Letterhead -->
                <div class="rx-header">
                    <div class="rx-brand-col">
                        <h2>WeCare Hospital</h2>
                        <p class="rx-brand-sub">Comprehensive Outpatient Department · Clinical Excellence</p>
                        <p class="rx-brand-addr">Campus Pavilion, Sector 4, Bangalore · Direct Line: +91 80 4567 8900</p>
                    </div>
                    <div class="rx-ref-col">
                        <div class="rx-doc-title-block">
                            <strong id="rx-modal-doc">Dr. Specialist</strong>
                            <div class="text-muted" id="rx-modal-spec-dept">Cardiology</div>
                            <small class="text-muted" id="rx-modal-edu">MBBS, MD</small>
                        </div>
                        <div class="rx-meta-pill">Prescription #<span id="rx-modal-id">0</span></div>
                    </div>
                </div>

                <hr class="rx-divider">

                <!-- Patient Meta Row -->
                <div class="rx-patient-meta-row">
                    <div><strong>Patient:</strong> <span id="rx-modal-patient">Patient Name</span></div>
                    <div><strong>Age / Gender:</strong> <span id="rx-modal-age-gender">35 · Male</span></div>
                    <div><strong>Date:</strong> <span id="rx-modal-date">Date</span></div>
                    <div><strong>Visit Ref:</strong> #<span id="rx-modal-appt">0</span></div>
                </div>

                <div class="rx-body-content">
                    <div class="rx-diag-row">
                        <span class="diag-tag">Diagnosis:</span>
                        <strong id="rx-modal-diag">Essential Hypertension</strong>
                    </div>

                    <div class="rx-symbol-heading">℞ Prescribed Medications & Dosage:</div>
                    <div class="rx-meds-content" id="rx-modal-meds">
                        <!-- Prescribed medicines will be injected here -->
                    </div>

                    <div class="rx-instructions-box">
                        <strong>Clinical Instructions / Lifestyle Notes:</strong>
                        <p id="rx-modal-instructions" style="margin: 4px 0 0 0;">Take medications as directed.</p>
                    </div>

                    <div class="rx-follow-row">
                        <span><strong>Recommended Next Review:</strong> <span id="rx-modal-follow">In 14 days</span></span>
                    </div>
                </div>

                <div class="rx-footer-sign">
                    <div class="rx-sign-block">
                        <div class="signature-line"></div>
                        <strong>Attending Specialist Signature</strong>
                        <small>WeCare Medical Council Reg.</small>
                    </div>
                </div>
            </div>

            <div class="doc-modal-footer no-print">
                <button type="button" class="btn btn-primary-highlight" onclick="window.print();">
                    🖨️ Print Prescription
                </button>
                <button type="button" class="btn btn-secondary" id="rx-dismiss-btn">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Script for Rx modal -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var rxModal = document.getElementById('rx-modal');
        var rxClose = document.getElementById('rx-close-btn');
        var rxDismiss = document.getElementById('rx-dismiss-btn');

        document.querySelectorAll('.btn-open-rx-modal').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-id');
                var doc = btn.getAttribute('data-doctor');
                var spec = btn.getAttribute('data-spec');
                var dept = btn.getAttribute('data-dept');
                var edu = btn.getAttribute('data-edu');
                var patient = btn.getAttribute('data-patient');
                var age = btn.getAttribute('data-age');
                var gender = btn.getAttribute('data-gender');
                var date = btn.getAttribute('data-date');
                var diag = btn.getAttribute('data-diagnosis');
                var meds = btn.getAttribute('data-medicines');
                var instructions = btn.getAttribute('data-instructions');
                var follow = btn.getAttribute('data-follow');
                var appt = btn.getAttribute('data-apptid');

                document.getElementById('rx-modal-id').textContent = id;
                document.getElementById('rx-modal-doc').textContent = doc;
                document.getElementById('rx-modal-spec-dept').textContent = spec + ' · ' + dept;
                document.getElementById('rx-modal-edu').textContent = edu;
                document.getElementById('rx-modal-patient').textContent = patient;
                document.getElementById('rx-modal-age-gender').textContent = age + ' yrs · ' + gender;
                document.getElementById('rx-modal-date').textContent = date;
                document.getElementById('rx-modal-appt').textContent = appt || 'N/A';
                document.getElementById('rx-modal-diag').textContent = diag;
                document.getElementById('rx-modal-meds').innerHTML = (meds || 'No oral medicines prescribed.').replace(/\n/g, '<br>');
                document.getElementById('rx-modal-instructions').textContent = instructions;
                document.getElementById('rx-modal-follow').textContent = follow;

                if (rxModal) rxModal.style.display = 'flex';
            });
        });

        function closeRx() {
            if (rxModal) rxModal.style.display = 'none';
        }
        if (rxClose) rxClose.addEventListener('click', closeRx);
        if (rxDismiss) rxDismiss.addEventListener('click', closeRx);
        if (rxModal) {
            rxModal.addEventListener('click', function (e) {
                if (e.target === rxModal) closeRx();
            });
        }
    });
    </script>
</body>
</html>
