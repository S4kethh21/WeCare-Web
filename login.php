<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$mode = isset($_GET['action']) && $_GET['action'] === 'register' ? 'register' : 'login';
$redirect = trim($_GET['redirect'] ?? '');
if ($redirect === '' && !empty($_SESSION['auth_redirect'])) {
    $redirect = $_SESSION['auth_redirect'];
}

$message = '';
$messageType = '';

if (isset($_GET['logged_out']) && $_GET['logged_out'] === '1') {
    $message = 'You have been safely signed out. Your session has ended.';
    $messageType = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['auth_action'] ?? 'login');
    $redirect = trim($_POST['redirect'] ?? '');

    if ($action === 'login') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $message = 'Please enter both your email address and password.';
            $messageType = 'error';
            $mode = 'login';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
            $mode = 'login';
        } else {
            $stmt = $conn->prepare('SELECT id, patient_name, password FROM patients WHERE email = ?');
            if (!$stmt) {
                $message = 'Authentication service temporarily unavailable. Please try again.';
                $messageType = 'error';
            } else {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $res = $stmt->get_result();
                $user = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
                    $_SESSION['patient_id'] = (int) $user['id'];
                    unset($_SESSION['auth_redirect']);
                    $_SESSION['flash_message'] = 'Welcome back, ' . htmlspecialchars($user['patient_name']) . '!';
                    $_SESSION['flash_type'] = 'success';

                    $target = ($redirect !== '' && strpos($redirect, 'logout') === false) ? $redirect : 'index.php';
                    header('Location: ' . $target);
                    exit;
                } else {
                    $message = 'Invalid email address or password. Please check your credentials.';
                    $messageType = 'error';
                    $mode = 'login';
                }
            }
        }
    } elseif ($action === 'register') {
        $name = trim($_POST['patient_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $age = (int) ($_POST['age'] ?? 0);
        $gender = trim($_POST['gender'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? 'WeCare Hospital Main Campus');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($name === '' || $phone === '' || $age <= 0 || $gender === '' || $email === '' || $password === '') {
            $message = 'Please fill all required fields.';
            $messageType = 'error';
            $mode = 'register';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $messageType = 'error';
            $mode = 'register';
        } elseif ($age < 1 || $age > 150) {
            $message = 'Please enter a realistic age between 1 and 150.';
            $messageType = 'error';
            $mode = 'register';
        } elseif (strlen($password) < 6) {
            $message = 'Password must be at least 6 characters long.';
            $messageType = 'error';
            $mode = 'register';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match. Please verify your password.';
            $messageType = 'error';
            $mode = 'register';
        } else {
            // Check if email already registered
            $checkStmt = $conn->prepare('SELECT id FROM patients WHERE email = ?');
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                $message = 'An account with this email address already exists. Please sign in.';
                $messageType = 'error';
                $mode = 'login';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare('INSERT INTO patients (patient_name, age, gender, phone, address, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if (!$ins) {
                    $message = 'Database error creating account. Please try again.';
                    $messageType = 'error';
                    $mode = 'register';
                } else {
                    $ins->bind_param('sisssss', $name, $age, $gender, $phone, $address, $email, $hash);
                    if ($ins->execute()) {
                        $newId = (int) $ins->insert_id;
                        $ins->close();

                        // Automatically log in newly registered patient
                        $_SESSION['patient_id'] = $newId;
                        unset($_SESSION['auth_redirect']);
                        $_SESSION['flash_message'] = 'Account created successfully! Welcome to WeCare Hospital.';
                        $_SESSION['flash_type'] = 'success';

                        $target = ($redirect !== '' && strpos($redirect, 'logout') === false) ? $redirect : 'index.php';
                        header('Location: ' . $target);
                        exit;
                    } else {
                        $message = 'Could not create account: ' . htmlspecialchars($ins->error);
                        $messageType = 'error';
                        $mode = 'register';
                        $ins->close();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal Authentication | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="auth-page-body">
    <div class="auth-viewport-wrapper">
        <main class="auth-unified-container">
            <!-- ================= LEFT BRANDING PANEL ================= -->
            <aside class="auth-left-branding">
                <div class="branding-inner-content">
                    <!-- Brand Header -->
                    <div class="brand-top-row">
                        <a href="index.php" class="brand-identity-link" title="Return to WeCare Hospital">
                            <span class="brand-heart-icon" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                                    <path d="M12 7v6"/>
                                    <path d="M9 10h6"/>
                                </svg>
                            </span>
                            <div class="brand-names">
                                <span class="brand-main-name">WeCare Hospital</span>
                                <span class="brand-sub-name">Care • Compassion • Clinical Excellence</span>
                            </div>
                        </a>
                    </div>

                    <!-- Main Headline -->
                    <div class="brand-headline-block">
                        <h1 class="brand-big-heading">Quality care,<br>when you need it<br>most</h1>
                        <p class="brand-lead-text">
                            WeCare Hospital is committed to providing you and your family with trusted, compassionate and personalized healthcare.
                        </p>
                    </div>

                    <!-- Feature Highlights -->
                    <div class="brand-highlights-list">
                        <!-- Feature 1 -->
                        <div class="highlight-item">
                            <div class="highlight-icon-wrap" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    <path d="m9 12 2 2 4-4"/>
                                </svg>
                            </div>
                            <div class="highlight-meta">
                                <strong class="highlight-title">Verified Specialists</strong>
                                <span class="highlight-desc">Experienced and trusted doctors</span>
                            </div>
                        </div>

                        <!-- Feature 2 -->
                        <div class="highlight-item">
                            <div class="highlight-icon-wrap" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                                    <line x1="16" x2="16" y1="2" y2="6"/>
                                    <line x1="8" x2="8" y1="2" y2="6"/>
                                    <line x1="3" x2="21" y1="10" y2="10"/>
                                    <path d="m9 16 2 2 4-4"/>
                                </svg>
                            </div>
                            <div class="highlight-meta">
                                <strong class="highlight-title">Easy Appointment</strong>
                                <span class="highlight-desc">Book appointments in minutes</span>
                            </div>
                        </div>

                        <!-- Feature 3 -->
                        <div class="highlight-item">
                            <div class="highlight-icon-wrap" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            </div>
                            <div class="highlight-meta">
                                <strong class="highlight-title">Secure & Private</strong>
                                <span class="highlight-desc">Your data is safe with us</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Anchored Hospital Photograph -->
                <div class="brand-hospital-photo-anchor">
                    <img src="images/hospital_hero.png" alt="WeCare Hospital Campus" class="hospital-anchor-img" width="750" height="525" loading="eager" decoding="async">
                    <div class="hospital-photo-gradient-fade" aria-hidden="true"></div>
                </div>
            </aside>

            <!-- ================= RIGHT AUTHENTICATION PANEL ================= -->
            <section class="auth-right-form-panel">
                <!-- Underline Tab Navigation (Matching Reference) -->
                <nav class="auth-underline-tabs" role="tablist" aria-label="Sign in or create account">
                    <button type="button" class="tab-underline-btn <?php echo $mode === 'login' ? 'active' : ''; ?>" id="tab-btn-login" role="tab" aria-selected="<?php echo $mode === 'login' ? 'true' : 'false'; ?>" aria-controls="pane-login">
                        Sign in
                    </button>
                    <button type="button" class="tab-underline-btn <?php echo $mode === 'register' ? 'active' : ''; ?>" id="tab-btn-register" role="tab" aria-selected="<?php echo $mode === 'register' ? 'true' : 'false'; ?>" aria-controls="pane-register">
                        Create account
                    </button>
                </nav>

                <!-- Status Feedback Alerts -->
                <?php if ($message !== ''): ?>
                    <div class="auth-feedback-card auth-feedback-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" role="alert">
                        <span class="feedback-icon" aria-hidden="true">
                            <?php echo $messageType === 'success' ? '✓' : '⚠'; ?>
                        </span>
                        <div class="feedback-text"><?php echo htmlspecialchars($message); ?></div>
                    </div>
                <?php endif; ?>

                <div class="auth-panes-wrapper">
                    <!-- ================= PANE 1: SIGN IN ================= -->
                    <div class="auth-pane-view <?php echo $mode === 'login' ? 'pane-active' : ''; ?>" id="pane-login" role="tabpanel" aria-labelledby="tab-btn-login">
                        <div class="pane-intro">
                            <h2 class="pane-main-title">Welcome back 👋</h2>
                            <p class="pane-lead-desc">Sign in to manage your appointments and healthcare visits.</p>
                        </div>

                        <form method="post" action="login.php" class="auth-actual-form" id="login-form">
                            <input type="hidden" name="auth_action" value="login">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">

                            <!-- Email Field -->
                            <div class="form-field-unit">
                                <label for="login-email" class="field-label-text">Email address</label>
                                <div class="field-input-box">
                                    <span class="field-leading-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="20" height="16" x="2" y="4" rx="2"/>
                                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                        </svg>
                                    </span>
                                    <input type="email" id="login-email" name="email" class="clean-field-input" required placeholder="Enter your email" autocomplete="email">
                                </div>
                            </div>

                            <!-- Password Field -->
                            <div class="form-field-unit">
                                <label for="login-password" class="field-label-text">Password</label>
                                <div class="field-input-box">
                                    <span class="field-leading-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                        </svg>
                                    </span>
                                    <input type="password" id="login-password" name="password" class="clean-field-input" required placeholder="Enter your password" autocomplete="current-password">
                                    <button type="button" class="btn-toggle-eye" aria-label="Toggle password visibility" data-target="login-password">
                                        <svg class="eye-open-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <svg class="eye-closed-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                            <line x1="2" x2="22" y1="2" y2="22"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Options: Remember Me & Forgot Password -->
                            <div class="auth-aux-row">
                                <label class="checkbox-unit">
                                    <input type="checkbox" name="remember_me" value="1" class="field-checkbox">
                                    <span class="checkbox-label">Remember me</span>
                                </label>
                                <a href="javascript:void(0)" class="aux-link" onclick="alert('For security assistance or password reset, please contact WeCare Hospital Helpdesk or visit the outpatient reception.')">Forgot password?</a>
                            </div>

                            <!-- Primary Submit Button -->
                            <button type="submit" class="btn-auth-action" id="btn-submit-login">
                                <span class="btn-svg-symbol" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                    </svg>
                                </span>
                                <span class="btn-label-text">Sign in</span>
                            </button>

                            <!-- Social SSO Visual Divider & Buttons -->
                            <div class="sso-divider-line">
                                <span>or continue with</span>
                            </div>

                            <div class="sso-buttons-pair">
                                <button type="button" class="btn-sso-item" disabled title="Google Authentication (Enterprise SSO preview)">
                                    <svg width="18" height="18" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17Z"/>
                                        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.26v3.15C3.25 21.36 7.33 24 12 24Z"/>
                                        <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.26A11.96 11.96 0 0 0 0 12c0 1.92.45 3.74 1.26 5.42l4.02-3.15Z"/>
                                        <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.33 0 3.25 2.64 1.26 6.58l4.02 3.15c.95-2.83 3.6-4.98 6.72-4.98Z"/>
                                    </svg>
                                    <span>Google</span>
                                </button>
                                <button type="button" class="btn-sso-item" disabled title="Apple Authentication (Enterprise SSO preview)">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.88c.64-.78 1.08-1.86.96-2.95-1 .04-2.13.67-2.8 1.45-.58.67-1.1 1.76-.96 2.82 1.11.09 2.16-.54 2.8-1.32Z"/>
                                    </svg>
                                    <span>Apple</span>
                                </button>
                            </div>

                            <!-- Account Switch Link -->
                            <div class="auth-switch-prompt">
                                Don't have an account? 
                                <button type="button" class="switch-link-btn" id="link-to-register">Create account</button>
                            </div>

                            <!-- Security Reassurance Card -->
                            <div class="auth-security-reassurance-card">
                                <div class="reassurance-icon-box" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        <path d="m9 12 2 2 4-4"/>
                                    </svg>
                                </div>
                                <div class="reassurance-content">
                                    <strong class="reassurance-heading">Secure & private</strong>
                                    <p class="reassurance-body">Your information is protected with secure authentication and privacy controls.</p>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- ================= PANE 2: CREATE ACCOUNT ================= -->
                    <div class="auth-pane-view <?php echo $mode === 'register' ? 'pane-active' : ''; ?>" id="pane-register" role="tabpanel" aria-labelledby="tab-btn-register">
                        <div class="pane-intro pane-intro-with-badge">
                            <div>
                                <h2 class="pane-main-title">Create your account</h2>
                                <p class="pane-lead-desc">Join WeCare Hospital to book appointments and manage your healthcare.</p>
                            </div>
                            <div class="header-user-circle" aria-hidden="true">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <line x1="19" x2="19" y1="8" y2="14"/>
                                    <line x1="22" x2="16" y1="11" y2="11"/>
                                </svg>
                            </div>
                        </div>

                        <form method="post" action="login.php" class="auth-actual-form" id="register-form">
                            <input type="hidden" name="auth_action" value="register">
                            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                            <input type="hidden" name="address" value="WeCare Hospital Main Campus">

                            <!-- SECTION 1: Personal Information -->
                            <div class="form-section-label">Personal Information</div>

                            <div class="form-paired-row">
                                <div class="form-field-unit">
                                    <label for="reg-name" class="field-label-text">Full Name <span class="req-star">*</span></label>
                                    <div class="field-input-box">
                                        <span class="field-leading-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                                <circle cx="12" cy="7" r="4"/>
                                            </svg>
                                        </span>
                                        <input type="text" id="reg-name" name="patient_name" class="clean-field-input" required placeholder="Enter your full name" autocomplete="name">
                                    </div>
                                </div>

                                <div class="form-field-unit">
                                    <label for="reg-phone" class="field-label-text">Phone Number <span class="req-star">*</span></label>
                                    <div class="field-input-box">
                                        <span class="field-leading-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                            </svg>
                                        </span>
                                        <input type="tel" id="reg-phone" name="phone" class="clean-field-input" required placeholder="Enter phone number" autocomplete="tel">
                                    </div>
                                </div>
                            </div>

                            <div class="form-paired-row">
                                <div class="form-field-unit">
                                    <label for="reg-age" class="field-label-text">Age <span class="req-star">*</span></label>
                                    <div class="field-input-box">
                                        <span class="field-leading-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect width="18" height="18" x="3" y="4" rx="2" ry="2"/>
                                                <line x1="16" x2="16" y1="2" y2="6"/>
                                                <line x1="8" x2="8" y1="2" y2="6"/>
                                                <line x1="3" x2="21" y1="10" y2="10"/>
                                            </svg>
                                        </span>
                                        <input type="number" id="reg-age" name="age" class="clean-field-input" min="1" max="150" required placeholder="Enter your age">
                                    </div>
                                </div>

                                <div class="form-field-unit">
                                    <label for="reg-gender" class="field-label-text">Gender <span class="req-star">*</span></label>
                                    <div class="field-input-box select-field-box">
                                        <span class="field-leading-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                                <circle cx="12" cy="7" r="4"/>
                                            </svg>
                                        </span>
                                        <select id="reg-gender" name="gender" class="clean-field-input clean-select-input" required>
                                            <option value="">Select gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- SECTION 2: Account Information -->
                            <div class="form-section-label">Account Information</div>

                            <div class="form-field-unit">
                                <label for="reg-email" class="field-label-text">Email Address <span class="req-star">*</span></label>
                                <div class="field-input-box">
                                    <span class="field-leading-icon" aria-hidden="true">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="20" height="16" x="2" y="4" rx="2"/>
                                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                                        </svg>
                                    </span>
                                    <input type="email" id="reg-email" name="email" class="clean-field-input" required placeholder="Enter your email" autocomplete="email">
                                </div>
                            </div>

                            <div class="form-paired-row">
                                <div class="form-field-unit">
                                    <label for="reg-pass" class="field-label-text">Password <span class="req-star">*</span></label>
                                    <div class="field-input-box">
                                        <span class="field-leading-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                        </span>
                                        <input type="password" id="reg-pass" name="password" class="clean-field-input" required minlength="6" placeholder="Create password" autocomplete="new-password">
                                        <button type="button" class="btn-toggle-eye" aria-label="Toggle password visibility" data-target="reg-pass">
                                            <svg class="eye-open-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            <svg class="eye-closed-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                                <line x1="2" x2="22" y1="2" y2="22"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-field-unit">
                                    <label for="reg-confirm" class="field-label-text">Confirm Password <span class="req-star">*</span></label>
                                    <div class="field-input-box">
                                        <span class="field-leading-icon" aria-hidden="true">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                            </svg>
                                        </span>
                                        <input type="password" id="reg-confirm" name="confirm_password" class="clean-field-input" required minlength="6" placeholder="Confirm password" autocomplete="new-password">
                                        <button type="button" class="btn-toggle-eye" aria-label="Toggle password visibility" data-target="reg-confirm">
                                            <svg class="eye-open-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            <svg class="eye-closed-svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                                                <line x1="2" x2="22" y1="2" y2="22"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Primary Submit Button -->
                            <button type="submit" class="btn-auth-action" id="btn-submit-register">
                                <span class="btn-svg-symbol" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                </span>
                                <span class="btn-label-text">Create account</span>
                            </button>

                            <!-- Account Switch Link -->
                            <div class="auth-switch-prompt">
                                Already have an account? 
                                <button type="button" class="switch-link-btn" id="link-to-login">Sign in</button>
                            </div>

                            <!-- Security Reassurance Card -->
                            <div class="auth-security-reassurance-card">
                                <div class="reassurance-icon-box" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        <path d="m9 12 2 2 4-4"/>
                                    </svg>
                                </div>
                                <div class="reassurance-content">
                                    <strong class="reassurance-heading">Secure & private</strong>
                                    <p class="reassurance-body">Your information is protected with secure authentication and privacy controls.</p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Client-side Interactive Behaviors -->
    <script>
    (function () {
        var tabBtnLogin = document.getElementById('tab-btn-login');
        var tabBtnRegister = document.getElementById('tab-btn-register');
        var paneLogin = document.getElementById('pane-login');
        var paneRegister = document.getElementById('pane-register');
        var linkToReg = document.getElementById('link-to-register');
        var linkToLogin = document.getElementById('link-to-login');

        function switchMode(targetMode) {
            if (targetMode === 'register') {
                tabBtnLogin.classList.remove('active');
                tabBtnLogin.setAttribute('aria-selected', 'false');
                tabBtnRegister.classList.add('active');
                tabBtnRegister.setAttribute('aria-selected', 'true');

                paneLogin.classList.remove('pane-active');
                paneRegister.classList.add('pane-active');

                var nameInput = document.getElementById('reg-name');
                if (nameInput) nameInput.focus();

                if (window.history.replaceState) {
                    var u = new URL(window.location.href);
                    u.searchParams.set('action', 'register');
                    window.history.replaceState({}, '', u);
                }
            } else {
                tabBtnRegister.classList.remove('active');
                tabBtnRegister.setAttribute('aria-selected', 'false');
                tabBtnLogin.classList.add('active');
                tabBtnLogin.setAttribute('aria-selected', 'true');

                paneRegister.classList.remove('pane-active');
                paneLogin.classList.add('pane-active');

                var emailInput = document.getElementById('login-email');
                if (emailInput) emailInput.focus();

                if (window.history.replaceState) {
                    var u = new URL(window.location.href);
                    u.searchParams.delete('action');
                    window.history.replaceState({}, '', u);
                }
            }
        }

        if (tabBtnLogin) tabBtnLogin.addEventListener('click', function () { switchMode('login'); });
        if (tabBtnRegister) tabBtnRegister.addEventListener('click', function () { switchMode('register'); });
        if (linkToReg) linkToReg.addEventListener('click', function (e) { e.preventDefault(); switchMode('register'); });
        if (linkToLogin) linkToLogin.addEventListener('click', function (e) { e.preventDefault(); switchMode('login'); });

        // Password eye toggles
        document.querySelectorAll('.btn-toggle-eye').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-target');
                var input = document.getElementById(targetId);
                if (!input) return;

                var eyeOpen = btn.querySelector('.eye-open-svg');
                var eyeClosed = btn.querySelector('.eye-closed-svg');

                if (input.type === 'password') {
                    input.type = 'text';
                    if (eyeOpen) eyeOpen.style.display = 'none';
                    if (eyeClosed) eyeClosed.style.display = 'block';
                    btn.setAttribute('aria-label', 'Hide password');
                } else {
                    input.type = 'password';
                    if (eyeOpen) eyeOpen.style.display = 'block';
                    if (eyeClosed) eyeClosed.style.display = 'none';
                    btn.setAttribute('aria-label', 'Show password');
                }
            });
        });

        // Anti-double-submit protection
        var forms = document.querySelectorAll('.auth-actual-form');
        forms.forEach(function (f) {
            f.addEventListener('submit', function (e) {
                var btn = f.querySelector('.btn-auth-action');
                if (!btn) return;
                if (btn.disabled) {
                    e.preventDefault();
                    return false;
                }
                btn.disabled = true;
                btn.classList.add('btn-submitting');
                var isReg = f.querySelector('input[name="auth_action"]').value === 'register';
                var labelSpan = btn.querySelector('.btn-label-text');
                if (labelSpan) {
                    labelSpan.textContent = isReg ? 'Creating account...' : 'Signing in...';
                }
            });
        });
    })();
    </script>
</body>
</html>
