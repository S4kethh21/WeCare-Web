<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin('profile.php');

$patient = getCurrentPatient($conn);
if (!$patient) {
    header('Location: logout.php');
    exit;
}

$editMode = isset($_GET['edit']) && $_GET['edit'] === '1';
$activeTab = trim($_GET['tab'] ?? 'personal');
if (!in_array($activeTab, ['personal', 'medical', 'emergency'], true)) {
    $activeTab = 'personal';
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['patient_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $age = (int) ($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Medical fields
    $bloodGroup = trim($_POST['blood_group'] ?? 'O+');
    $allergies = trim($_POST['allergies'] ?? 'No known drug allergies (NKDA)');
    $chronicConditions = trim($_POST['chronic_conditions'] ?? 'None reported');

    // Emergency contact fields
    $emergencyName = trim($_POST['emergency_name'] ?? 'Emergency Contact');
    $emergencyPhone = trim($_POST['emergency_phone'] ?? '+91 98765 43210');
    $emergencyRelation = trim($_POST['emergency_relation'] ?? 'Family / Guardian');

    // Password fields
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if ($name === '' || $phone === '' || $age <= 0 || $gender === '') {
        $message = 'Please complete all required personal fields.';
        $messageType = 'error';
        $editMode = true;
    } elseif ($age < 1 || $age > 150) {
        $message = 'Please enter a realistic age.';
        $messageType = 'error';
        $editMode = true;
    } else {
        $passwordChanged = false;
        $hashToUpdate = null;

        if ($currentPass !== '' || $newPass !== '' || $confirmPass !== '') {
            $hashStmt = $conn->prepare('SELECT password FROM patients WHERE id = ?');
            $hashStmt->bind_param('i', $patient['id']);
            $hashStmt->execute();
            $storedHash = $hashStmt->get_result()->fetch_assoc()['password'] ?? '';
            $hashStmt->close();

            if (!empty($storedHash) && !password_verify($currentPass, $storedHash)) {
                $message = 'Current password does not match.';
                $messageType = 'error';
                $editMode = true;
            } elseif (strlen($newPass) < 6) {
                $message = 'New password must be at least 6 characters long.';
                $messageType = 'error';
                $editMode = true;
            } elseif ($newPass !== $confirmPass) {
                $message = 'New password confirmation does not match.';
                $messageType = 'error';
                $editMode = true;
            } else {
                $hashToUpdate = password_hash($newPass, PASSWORD_DEFAULT);
                $passwordChanged = true;
            }
        }

        if ($messageType !== 'error') {
            if ($hashToUpdate !== null) {
                $up = $conn->prepare('
                    UPDATE patients 
                    SET patient_name = ?, phone = ?, age = ?, gender = ?, address = ?,
                        blood_group = ?, allergies = ?, chronic_conditions = ?,
                        emergency_name = ?, emergency_phone = ?, emergency_relation = ?,
                        password = ?
                    WHERE id = ?
                ');
                $up->bind_param(
                    'ssisssssssssi',
                    $name, $phone, $age, $gender, $address,
                    $bloodGroup, $allergies, $chronicConditions,
                    $emergencyName, $emergencyPhone, $emergencyRelation,
                    $hashToUpdate, $patient['id']
                );
            } else {
                $up = $conn->prepare('
                    UPDATE patients 
                    SET patient_name = ?, phone = ?, age = ?, gender = ?, address = ?,
                        blood_group = ?, allergies = ?, chronic_conditions = ?,
                        emergency_name = ?, emergency_phone = ?, emergency_relation = ?
                    WHERE id = ?
                ');
                $up->bind_param(
                    'ssissssssssi',
                    $name, $phone, $age, $gender, $address,
                    $bloodGroup, $allergies, $chronicConditions,
                    $emergencyName, $emergencyPhone, $emergencyRelation,
                    $patient['id']
                );
            }

            if ($up && $up->execute()) {
                $message = 'Profile and medical details updated successfully.';
                $messageType = 'success';
                $editMode = false;
                $patient = getCurrentPatient($conn, true);
            } else {
                $message = 'Could not update profile: ' . htmlspecialchars($conn->error);
                $messageType = 'error';
                $editMode = true;
            }
            if ($up) $up->close();
        }
    }
}

$initials = getInitials($patient['patient_name'] ?? 'Patient');
$memberSince = !empty($patient['created_at']) ? date('F Y', strtotime($patient['created_at'])) : date('Y');
$patientIdFormatted = 'WC-P' . str_pad((string)$patient['id'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <div class="page-head">
            <h1 class="page-title">My Profile</h1>
            <p class="page-sub">Manage your personal information, contact details, and account preferences.</p>
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

        <div class="profile-mockup-layout">
            <!-- Main Profile Column (Left, ~70%) -->
            <div class="profile-main-card">
                <!-- Header Strip with Avatar + Name + Roles -->
                <div class="profile-header-strip">
                    <div class="profile-avatar-circle">
                        <?php echo htmlspecialchars($initials); ?>
                    </div>
                    <div class="profile-info-heading">
                        <h2><?php echo htmlspecialchars($patient['patient_name']); ?></h2>
                        <div class="profile-tags-row">
                            <span class="badge-role">Patient</span>
                            <span class="status-pill-green">Active</span>
                            <span style="font-size: 0.82rem; color: #64748b; margin-left: 0.25rem;">ID: <?php echo $patientIdFormatted; ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$editMode): ?>
                    <!-- VIEW MODE: 3 TABS -->
                    <div class="panel-tab-bar" role="tablist" aria-label="Profile Sections">
                        <button type="button" class="tab-btn <?php echo $activeTab === 'personal' ? 'active' : ''; ?>" data-target="tab-personal" role="tab" aria-selected="<?php echo $activeTab === 'personal' ? 'true' : 'false'; ?>">
                            Personal Information
                        </button>
                        <button type="button" class="tab-btn <?php echo $activeTab === 'medical' ? 'active' : ''; ?>" data-target="tab-medical" role="tab" aria-selected="<?php echo $activeTab === 'medical' ? 'true' : 'false'; ?>">
                            Medical Information
                        </button>
                        <button type="button" class="tab-btn <?php echo $activeTab === 'emergency' ? 'active' : ''; ?>" data-target="tab-emergency" role="tab" aria-selected="<?php echo $activeTab === 'emergency' ? 'true' : 'false'; ?>">
                            Emergency Contact
                        </button>
                    </div>

                    <!-- TAB 1: PERSONAL INFORMATION -->
                    <div class="profile-tab-pane <?php echo $activeTab === 'personal' ? 'active' : ''; ?>" id="tab-personal" role="tabpanel">
                        <div class="profile-fields-grid-2col">
                            <div class="profile-field-box">
                                <span class="profile-field-label">Full Name</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['patient_name']); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Phone Number</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['phone']); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Gender</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['gender']); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Email Address</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['email'] ?: 'saketh0@gmail.com'); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Age</span>
                                <span class="profile-field-value"><?php echo (int) $patient['age']; ?> years</span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Blood Group</span>
                                <span class="profile-field-value" style="color: #2563eb;"><?php echo htmlspecialchars($patient['blood_group'] ?? 'O+'); ?></span>
                            </div>
                            <div class="profile-field-box" style="grid-column: 1 / -1;">
                                <span class="profile-field-label">Residential Address</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['address'] ?: 'Campus Pavilion, Sector 4, Bangalore'); ?></span>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; gap: 0.75rem;">
                            <a href="profile.php?edit=1&tab=personal" class="btn btn-primary-highlight">
                                Edit Profile
                            </a>
                        </div>
                    </div>

                    <!-- TAB 2: MEDICAL INFORMATION -->
                    <div class="profile-tab-pane <?php echo $activeTab === 'medical' ? 'active' : ''; ?>" id="tab-medical" role="tabpanel">
                        <div class="profile-fields-grid-2col">
                            <div class="profile-field-box">
                                <span class="profile-field-label">Blood Group</span>
                                <span class="profile-field-value" style="color: #2563eb;">🩸 <?php echo htmlspecialchars($patient['blood_group'] ?? 'O+'); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Known Allergies</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['allergies'] ?? 'No known drug allergies (NKDA)'); ?></span>
                            </div>
                            <div class="profile-field-box" style="grid-column: 1 / -1;">
                                <span class="profile-field-label">Chronic Conditions</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['chronic_conditions'] ?? 'None reported'); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Vaccination Status</span>
                                <span class="profile-field-value" style="color: #059669;">✓ Up-to-date (Verified by WeCare)</span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Preferred Pharmacy</span>
                                <span class="profile-field-value">WeCare Main Campus In-House Dispensary</span>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; gap: 0.75rem;">
                            <a href="profile.php?edit=1&tab=medical" class="btn btn-primary-highlight">
                                Update Medical Details
                            </a>
                        </div>
                    </div>

                    <!-- TAB 3: EMERGENCY CONTACT -->
                    <div class="profile-tab-pane <?php echo $activeTab === 'emergency' ? 'active' : ''; ?>" id="tab-emergency" role="tabpanel">
                        <div class="profile-fields-grid-2col">
                            <div class="profile-field-box">
                                <span class="profile-field-label">Primary Contact Person</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['emergency_name'] ?? 'Emergency Contact'); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Relationship to Patient</span>
                                <span class="profile-field-value"><?php echo htmlspecialchars($patient['emergency_relation'] ?? 'Family / Guardian'); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Emergency Phone Number</span>
                                <span class="profile-field-value" style="color: #2563eb;"><?php echo htmlspecialchars($patient['emergency_phone'] ?? '+91 98765 43210'); ?></span>
                            </div>
                            <div class="profile-field-box">
                                <span class="profile-field-label">Emergency Treatment Consent</span>
                                <span class="profile-field-value" style="color: #059669;">✓ Full Consent Granted for Urgent Care</span>
                            </div>
                            <div class="profile-field-box" style="grid-column: 1 / -1;">
                                <span class="profile-field-label">Hospital Emergency Hotline</span>
                                <span class="profile-field-value" style="color: #dc2626;">WeCare 24/7 Trauma: +91 80 4567 8999</span>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; display: flex; gap: 0.75rem;">
                            <a href="profile.php?edit=1&tab=emergency" class="btn btn-primary-highlight">
                                Edit Emergency Contact
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- EDIT MODE -->
                    <div class="panel-header-flex">
                        <div>
                            <h2>Update Profile & Medical Records</h2>
                            <p class="section-subtext">Modify your personal contact details, clinical information, and emergency contacts</p>
                        </div>
                    </div>

                    <form method="post" action="profile.php" class="form-grid two" id="profile-edit-form">
                        <!-- Personal Details Section -->
                        <div style="grid-column: 1 / -1; margin-top: 0.25rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem;">
                            <h3 style="color: var(--accent); font-size: 1.05rem; margin: 0;">1. Personal Information</h3>
                        </div>

                        <div>
                            <label for="prof-name">Full Legal Name <span class="req">*</span></label>
                            <input type="text" id="prof-name" name="patient_name" value="<?php echo htmlspecialchars($patient['patient_name']); ?>" required>
                        </div>
                        <div>
                            <label for="prof-phone">Contact Phone Number <span class="req">*</span></label>
                            <input type="tel" id="prof-phone" name="phone" value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                        </div>
                        <div>
                            <label for="prof-age">Age <span class="req">*</span></label>
                            <input type="number" id="prof-age" name="age" min="1" max="150" value="<?php echo (int) $patient['age']; ?>" required>
                        </div>
                        <div>
                            <label for="prof-gender">Gender <span class="req">*</span></label>
                            <select id="prof-gender" name="gender" required>
                                <option value="Male" <?php echo ($patient['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($patient['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($patient['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="prof-address">Residential Address</label>
                            <textarea id="prof-address" name="address" rows="2"><?php echo htmlspecialchars($patient['address']); ?></textarea>
                        </div>

                        <!-- Medical Information Section -->
                        <div style="grid-column: 1 / -1; margin-top: 1rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem;">
                            <h3 style="color: var(--accent); font-size: 1.05rem; margin: 0;">2. Medical Information</h3>
                        </div>

                        <div>
                            <label for="prof-blood">Blood Group</label>
                            <select id="prof-blood" name="blood_group">
                                <?php
                                $bGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                $curB = $patient['blood_group'] ?? 'O+';
                                foreach ($bGroups as $bg):
                                ?>
                                    <option value="<?php echo $bg; ?>" <?php echo $curB === $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="prof-allergies">Known Drug / Food Allergies</label>
                            <input type="text" id="prof-allergies" name="allergies" value="<?php echo htmlspecialchars($patient['allergies'] ?? 'No known drug allergies (NKDA)'); ?>">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="prof-chronic">Chronic Medical Conditions / History</label>
                            <input type="text" id="prof-chronic" name="chronic_conditions" value="<?php echo htmlspecialchars($patient['chronic_conditions'] ?? 'None reported'); ?>">
                        </div>

                        <!-- Emergency Contact Section -->
                        <div style="grid-column: 1 / -1; margin-top: 1rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem;">
                            <h3 style="color: var(--accent); font-size: 1.05rem; margin: 0;">3. Emergency Contact</h3>
                        </div>

                        <div>
                            <label for="prof-em-name">Emergency Contact Name</label>
                            <input type="text" id="prof-em-name" name="emergency_name" value="<?php echo htmlspecialchars($patient['emergency_name'] ?? 'Emergency Contact'); ?>">
                        </div>

                        <div>
                            <label for="prof-em-relation">Relationship</label>
                            <input type="text" id="prof-em-relation" name="emergency_relation" value="<?php echo htmlspecialchars($patient['emergency_relation'] ?? 'Family / Guardian'); ?>">
                        </div>

                        <div style="grid-column: 1 / -1;">
                            <label for="prof-em-phone">Emergency Contact Phone Number</label>
                            <input type="tel" id="prof-em-phone" name="emergency_phone" value="<?php echo htmlspecialchars($patient['emergency_phone'] ?? '+91 98765 43210'); ?>">
                        </div>

                        <!-- Security & Password Section -->
                        <div style="grid-column: 1 / -1; margin-top: 1rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem;">
                            <h3 style="color: var(--text-dark); font-size: 1.05rem; margin: 0;">4. Portal Security <small style="font-weight: normal; font-size: 0.82rem; color: var(--text-muted);">(Leave blank if not changing)</small></h3>
                        </div>

                        <div>
                            <label for="cur-pass">Current Password</label>
                            <input type="password" id="cur-pass" name="current_password" placeholder="Enter current password">
                        </div>
                        <div>
                            <label for="new-pass">New Password (min 6 chars)</label>
                            <input type="password" id="new-pass" name="new_password" placeholder="Enter new password">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="conf-pass">Confirm New Password</label>
                            <input type="password" id="conf-pass" name="confirm_password" placeholder="Confirm new password">
                        </div>

                        <div class="form-actions" style="grid-column: 1 / -1; margin-top: 1rem;">
                            <button type="submit" class="btn btn-primary-highlight">Save All Changes</button>
                            <a href="profile.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Right Column: Encouragement & Quick Navigation -->
            <div class="profile-side-card">
                <!-- Profile Tip / Encouragement Card -->
                <div class="profile-tip-card">
                    <div class="profile-tip-icon">💙</div>
                    <h3 class="profile-tip-title">Keep Your Profile Updated</h3>
                    <p class="profile-tip-text">Accurate health details help our doctors make better clinical decisions during consultations and emergencies.</p>
                </div>

                <div style="margin-top: 1.5rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 0.85rem;">Quick Navigation</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="appointments.php" class="quick-action-link">
                            <span>📅 My Appointments</span>
                            <span>→</span>
                        </a>
                        <a href="prescriptions.php" class="quick-action-link">
                            <span>💊 Prescriptions</span>
                            <span>→</span>
                        </a>
                        <a href="health_records.php" class="quick-action-link">
                            <span>📋 Health Records</span>
                            <span>→</span>
                        </a>
                        <a href="billing.php" class="quick-action-link">
                            <span>💳 Billing & Statements</span>
                            <span>→</span>
                        </a>
                        <a href="logout.php" class="quick-action-link" style="color: #dc2626;">
                            <span>🚪 Sign Out</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
        </div>
    </footer>

    <script>
    (function () {
        // Tab switching logic for profile
        var tabBtns = document.querySelectorAll('.panel-tab-bar .tab-btn');
        var tabPanes = document.querySelectorAll('.profile-tab-pane');

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = this.getAttribute('data-target');
                tabBtns.forEach(function (b) {
                    b.classList.remove('active');
                    b.setAttribute('aria-selected', 'false');
                });
                tabPanes.forEach(function (p) {
                    p.classList.remove('active');
                });

                this.classList.add('active');
                this.setAttribute('aria-selected', 'true');
                var activePane = document.getElementById(targetId);
                if (activePane) {
                    activePane.classList.add('active');
                }
            });
        });
    })();
    </script>
</body>
</html>
