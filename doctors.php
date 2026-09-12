<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deleteDoctorId = (int) ($_POST['delete_doctor_id'] ?? 0);
    if ($deleteDoctorId > 0) {
        $stmt = $conn->prepare('DELETE FROM doctors WHERE id = ?');
        if (!$stmt) {
            $message = 'Database error while deleting doctor: ' . htmlspecialchars($conn->error);
            $messageType = 'error';
        } else {
            $stmt->bind_param('i', $deleteDoctorId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $message = 'Doctor record removed from directory.';
                    $messageType = 'success';
                } else {
                    $message = 'Doctor record not found.';
                    $messageType = 'error';
                }
            } else {
                $message = 'Could not delete doctor: ' . htmlspecialchars($stmt->error);
                $messageType = 'error';
            }
            $stmt->close();
        }
    } else {
        $name = trim($_POST['doctor_name'] ?? '');
        $spec = trim($_POST['specialization'] ?? '');
        $dept = trim($_POST['department'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $fee = (float) ($_POST['consultation_fee'] ?? 800.00);
        $exp = trim($_POST['experience'] ?? '10+ years');
        $avail = trim($_POST['availability'] ?? 'Available Today');
        $edu = trim($_POST['education'] ?? 'MBBS, MD');
        $lang = trim($_POST['languages'] ?? 'English, Hindi');
        $bio = trim($_POST['bio'] ?? '');

        if ($name === '' || $spec === '' || $phone === '') {
            $message = 'Please fill all required doctor fields.';
            $messageType = 'error';
        } else {
            if ($dept === '') $dept = $spec . ' Department';
            $stmt = $conn->prepare(
                'INSERT INTO doctors (doctor_name, specialization, department, phone, consultation_fee, experience, availability, education, languages, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                $message = 'Database error: ' . htmlspecialchars($conn->error);
                $messageType = 'error';
            } else {
                $stmt->bind_param('ssssdsssss', $name, $spec, $dept, $phone, $fee, $exp, $avail, $edu, $lang, $bio);
                if ($stmt->execute()) {
                    $message = 'Doctor successfully registered in directory.';
                    $messageType = 'success';
                } else {
                    $message = 'Could not save doctor: ' . htmlspecialchars($stmt->error);
                    $messageType = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch all specialties for filter dropdown
$allSpecialties = [];
$specQ = $conn->query('SELECT DISTINCT specialization FROM doctors WHERE specialization IS NOT NULL AND specialization != "" ORDER BY specialization ASC');
if ($specQ) {
    while ($r = $specQ->fetch_assoc()) {
        $allSpecialties[] = $r['specialization'];
    }
}

// Search, filter, and sort handling
$search = trim($_GET['search'] ?? '');
$filterSpecialty = trim($_GET['specialty'] ?? '');
$filterAvail = trim($_GET['availability'] ?? '');
$sort = trim($_GET['sort'] ?? 'name_asc');

$sql = 'SELECT id, doctor_name, specialization, department, phone, age, gender, experience, consultation_fee, availability, languages, education, working_hours, profile_image, bio FROM doctors WHERE 1=1';
$params = [];
$types = '';

if ($search !== '') {
    $sql .= ' AND (doctor_name LIKE ? OR specialization LIKE ? OR department LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($filterSpecialty !== '') {
    $sql .= ' AND specialization = ?';
    $params[] = $filterSpecialty;
    $types .= 's';
}

if ($filterAvail !== '') {
    $sql .= ' AND availability = ?';
    $params[] = $filterAvail;
    $types .= 's';
}

switch ($sort) {
    case 'fee_asc':
        $sql .= ' ORDER BY consultation_fee ASC, doctor_name ASC';
        break;
    case 'fee_desc':
        $sql .= ' ORDER BY consultation_fee DESC, doctor_name ASC';
        break;
    case 'exp_desc':
        $sql .= ' ORDER BY CAST(SUBSTRING_INDEX(experience, " ", 1) AS UNSIGNED) DESC, doctor_name ASC';
        break;
    default:
        $sql .= ' ORDER BY id ASC';
        break;
}

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $doctors = $stmt->get_result();
} else {
    $doctors = $conn->query($sql);
}

$doctorList = [];
$doctorsDataMap = [];
// Custom per-doctor portrait object-position framing (ensures face, hair, neck, and shoulders are perfectly visible)
$doctorPositions = [
    1  => 'center 18%', // Dr. Ananya Sharma
    2  => 'center 15%', // Dr. Rohit Verma
    3  => 'center 18%', // Dr. Priya Nair
    4  => 'center 6%',  // Dr. Vikram Singh (high headroom protection)
    5  => 'center 24%', // Dr. Sneha Kulkarni (lower framing centered)
    6  => 'center 8%',  // Dr. Arvind Menon (high headroom protection)
    7  => 'center 10%', // Dr. Kavita Deshmukh
    8  => 'center 14%', // Dr. Rajesh Iyer
    9  => 'center 22%', // Dr. Sunita Mehra (lower framing centered)
    10 => 'center 8%',  // Dr. Tarun Kapoor (high headroom protection)
    11 => 'center 16%', // Dr. Deepa Nambiar
    12 => 'center 18%', // Dr. Alok Chatterjee
    13 => 'center 18%', // Dr. Meenakshi Sundaram
    14 => 'center 12%', // Dr. Harish Bhat
];

if ($doctors) {
    while ($d = $doctors->fetch_assoc()) {
        $doctorList[] = $d;

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
        $imgPos = $doctorPositions[$docId] ?? 'center 15%';

        $doctorsDataMap[(string) $docId] = [
            'id' => $docId,
            'name' => $d['doctor_name'],
            'specialization' => $d['specialization'],
            'department' => $d['department'] ?: ($d['specialization'] . ' Department'),
            'phone' => $d['phone'] ?? '',
            'consultation_fee' => number_format((float) ($d['consultation_fee'] ?? 800), 0),
            'experience' => $d['experience'] ?? '10+ years',
            'experience_label' => $expLabel,
            'education' => $d['education'] ?? 'MBBS, MD',
            'education_degree' => $eduDegree,
            'languages' => $d['languages'] ?? 'English, Hindi',
            'availability' => $d['availability'] ?? 'Available Today',
            'working_hours' => $d['working_hours'] ?? '09:00 AM - 05:00 PM',
            'bio' => $d['bio'] ?: 'Dedicated clinical physician providing compassionate care at WeCare Hospital.',
            'profile_image' => $img,
            'object_position' => $imgPos,
            'initials' => $initials
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find a Doctor | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <!-- Top Hero Banner (Matching Master Reference Mockup) -->
        <section class="doctors-hero-banner">
            <div class="banner-text-side">
                <h1 class="banner-title">Find the Right Doctor<br>for Your Health</h1>
                <p class="banner-subtitle">Experienced specialists, modern facilities and compassionate care — all under one roof.</p>
                <a href="#doctors-directory-grid" class="btn btn-banner-learn">Learn More</a>
            </div>
            <div class="banner-visual-side">
                <img src="images/stethoscope_banner.jpg" alt="Clinical Stethoscope" class="banner-stethoscope-img">
            </div>
        </section>

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

        <!-- Search & Filter Controls -->
        <section class="panel search-panel">
            <form method="get" action="doctors.php" class="doctor-filter-bar" role="search" id="doctor-search-form">
                <div class="search-select-wrap">
                    <select name="specialty" id="doctor-specialty-select" aria-label="Filter by specialty">
                        <option value="">All Specialties</option>
                        <?php foreach ($allSpecialties as $sp): ?>
                            <option value="<?php echo htmlspecialchars($sp); ?>" <?php echo $filterSpecialty === $sp ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sp); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="search-select-wrap">
                    <select name="department" id="doctor-dept-select" aria-label="Filter by department">
                        <option value="">All Departments</option>
                        <option value="Cardiology">Cardiology</option>
                        <option value="Orthopedics">Orthopedics</option>
                        <option value="Pediatrics">Pediatrics</option>
                        <option value="Neurology">Neurology</option>
                        <option value="General Medicine">General Medicine</option>
                        <option value="Dermatology">Dermatology</option>
                        <option value="Gynecology">Gynecology</option>
                    </select>
                </div>

                <div class="search-select-wrap">
                    <select name="availability" id="doctor-avail-select" aria-label="Filter by availability">
                        <option value="">All Availability</option>
                        <option value="Available Today" <?php echo $filterAvail === 'Available Today' ? 'selected' : ''; ?>>Available Today</option>
                        <option value="Available Tomorrow" <?php echo $filterAvail === 'Available Tomorrow' ? 'selected' : ''; ?>>Available Tomorrow</option>
                    </select>
                </div>

                <div class="search-select-wrap">
                    <select name="sort" id="doctor-sort-select" aria-label="Sort doctors">
                        <option value="exp_desc" <?php echo $sort === 'exp_desc' ? 'selected' : ''; ?>>Sort by: Experience (High to Low)</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Sort by: Name (A to Z)</option>
                        <option value="fee_asc" <?php echo $sort === 'fee_asc' ? 'selected' : ''; ?>>Sort by: Fee (Low to High)</option>
                        <option value="fee_desc" <?php echo $sort === 'fee_desc' ? 'selected' : ''; ?>>Sort by: Fee (High to Low)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary-highlight">Apply Filters</button>
                <?php if ($search !== '' || $filterSpecialty !== '' || $filterAvail !== '' || $sort !== 'name_asc'): ?>
                    <a href="doctors.php" class="btn btn-secondary">Clear</a>
                <?php endif; ?>
            </form>
        </section>

        <!-- Doctors Directory Grid -->
        <section class="directory-section">
            <?php if (!empty($doctorList)): ?>
                <div class="doctors-grid" id="doctors-directory-grid">
                    <?php foreach ($doctorList as $row): 
                        $docId = (int) $row['id'];
                        $docData = $doctorsDataMap[(string) $docId] ?? [];
                        $initials = $docData['initials'] ?? 'DR';
                        $isToday = (strpos(strtolower($row['availability'] ?? ''), 'today') !== false);
                        $img = $docData['profile_image'] ?? sprintf('assets/doctors/doctor-%02d.jpg', $docId);
                        $imgPos = $docData['object_position'] ?? ($doctorPositions[$docId] ?? 'center 15%');
                        $expLabel = $docData['experience_label'] ?? '10+ years exp.';
                        $eduDegree = $docData['education_degree'] ?? 'MBBS, MD';
                    ?>
                        <article class="doctor-card" 
                            data-name="<?php echo htmlspecialchars(strtolower($row['doctor_name'])); ?>" 
                            data-spec="<?php echo htmlspecialchars(strtolower($row['specialization'])); ?>"
                            data-avail="<?php echo htmlspecialchars(strtolower($row['availability'] ?? '')); ?>"
                            data-id="<?php echo $docId; ?>">
                            
                            <!-- Doctor Portrait Frame with Fallback Avatar -->
                            <div class="doctor-card-portrait-wrap">
                                <img src="<?php echo htmlspecialchars($img); ?>" 
                                     alt="<?php echo htmlspecialchars($row['doctor_name']); ?>" 
                                     class="doctor-card-img doc-img-<?php echo $docId; ?>" 
                                     style="object-position: <?php echo $imgPos; ?>;"
                                     loading="lazy"
                                     onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                <div class="doctor-card-avatar" style="display: none;" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>

                                <!-- Availability Badge (Top-Left Pill) -->
                                <span class="doctor-avail-badge <?php echo $isToday ? 'avail-today' : 'avail-tomorrow'; ?>">
                                    <span class="avail-dot" aria-hidden="true"></span>
                                    <?php echo htmlspecialchars($row['availability'] ?? 'Available Today'); ?>
                                </span>

                                <!-- Verified Specialist Badge (Top-Right Blue Pill) -->
                                <span class="doctor-verified-badge" title="WeCare Board Verified Specialist">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <span>Verified</span>
                                </span>
                            </div>

                            <div class="doctor-card-body">
                                <h3 class="doctor-name"><?php echo htmlspecialchars($row['doctor_name']); ?></h3>
                                <div class="doctor-spec-blue"><?php echo htmlspecialchars($row['specialization']); ?></div>

                                <!-- Experience & Credentials Row -->
                                <div class="doctor-meta-exp-edu" title="<?php echo htmlspecialchars($expLabel . ' · ' . $eduDegree); ?>">
                                    <span class="meta-icon" aria-hidden="true">💼</span>
                                    <span class="meta-text"><?php echo htmlspecialchars($expLabel); ?> · <?php echo htmlspecialchars($eduDegree); ?></span>
                                </div>

                                <?php if (!empty($row['bio'])): ?>
                                    <p class="doctor-short-bio">
                                        <?php echo htmlspecialchars(mb_strimwidth($row['bio'], 0, 115, '...')); ?>
                                    </p>
                                <?php endif; ?>

                                <div class="doctor-languages-row">
                                    <span class="lang-icon" aria-hidden="true">💬</span>
                                    <span class="lang-label">Speaks:</span>
                                    <span class="lang-text"><?php echo htmlspecialchars($row['languages'] ?? 'English, Hindi'); ?></span>
                                </div>
                            </div>

                            <div class="doctor-card-actions">
                                <button type="button" class="btn btn-doc-profile view-profile-btn btn-view-profile" 
                                    data-doctor-id="<?php echo $docId; ?>" 
                                    data-id="<?php echo $docId; ?>"
                                    data-name="<?php echo htmlspecialchars($row['doctor_name']); ?>"
                                    data-spec="<?php echo htmlspecialchars($row['specialization']); ?>"
                                    data-dept="<?php echo htmlspecialchars($docData['department'] ?? ''); ?>"
                                    data-fee="₹<?php echo $docData['consultation_fee'] ?? '800'; ?>"
                                    data-exp="<?php echo htmlspecialchars($docData['experience'] ?? ''); ?>"
                                    data-avail="<?php echo htmlspecialchars($docData['availability'] ?? ''); ?>"
                                    data-edu="<?php echo htmlspecialchars($docData['education'] ?? ''); ?>"
                                    data-lang="<?php echo htmlspecialchars($docData['languages'] ?? ''); ?>"
                                    data-hours="<?php echo htmlspecialchars($docData['working_hours'] ?? ''); ?>"
                                    data-bio="<?php echo htmlspecialchars($docData['bio'] ?? ''); ?>"
                                    data-img="<?php echo htmlspecialchars($img); ?>"
                                    data-img-pos="<?php echo $imgPos; ?>"
                                    data-initials="<?php echo htmlspecialchars($initials); ?>">
                                    View Profile
                                </button>
                                <a href="appointments.php?doctor_id=<?php echo $docId; ?>" class="btn btn-book-doc">
                                    Book Appointment &rarr;
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div id="no-doctors-notice" class="empty-state-card" style="display: none;">
                    <div class="empty-state-icon">👨‍⚕️</div>
                    <h3>No specialists found matching your search</h3>
                    <p>Try searching for a different doctor name or clear the active specialty filter.</p>
                    <button type="button" class="btn btn-secondary" id="btn-reset-dir-search">Clear filters</button>
                </div>
            <?php else: ?>
                <div class="empty-state-card">
                    <div class="empty-state-icon">👨‍⚕️</div>
                    <h3>No doctors found matching your criteria</h3>
                    <p>Try searching for a different physician name or select another clinical department.</p>
                    <a href="doctors.php" class="btn btn-secondary">Clear filters</a>
                </div>
            <?php endif; ?>
        </section>

        <!-- Preserved Staff / Administrative Portal -->
        <details class="staff-portal-drawer">
            <summary class="staff-portal-toggle">
                <span>🔒 Hospital Administration (Add new physician to WeCare directory)</span>
            </summary>
            <div class="staff-portal-body">
                <section class="panel">
                    <h2>Register New Medical Specialist</h2>
                    <form method="post" action="doctors.php" class="form-grid two">
                        <div>
                            <label for="doctor_name">Doctor Full Name <span class="req">*</span></label>
                            <input type="text" id="doctor_name" name="doctor_name" placeholder="e.g. Dr. Ramesh Gupta" required>
                        </div>
                        <div>
                            <label for="specialization">Specialization <span class="req">*</span></label>
                            <input type="text" id="specialization" name="specialization" placeholder="e.g. Neurology" required>
                        </div>
                        <div>
                            <label for="department">Department</label>
                            <input type="text" id="department" name="department" placeholder="e.g. Institute of Neurosciences">
                        </div>
                        <div>
                            <label for="phone">Contact Number <span class="req">*</span></label>
                            <input type="text" id="phone" name="phone" placeholder="e.g. +91 98765 43210" required>
                        </div>
                        <div>
                            <label for="consultation_fee">Consultation Fee (₹)</label>
                            <input type="number" id="consultation_fee" name="consultation_fee" value="800" step="50">
                        </div>
                        <div>
                            <label for="experience">Experience</label>
                            <input type="text" id="experience" name="experience" placeholder="e.g. 15 years">
                        </div>
                        <div>
                            <label for="education">Education & Credentials</label>
                            <input type="text" id="education" name="education" placeholder="e.g. MBBS, MD, DM">
                        </div>
                        <div>
                            <label for="languages">Languages Spoken</label>
                            <input type="text" id="languages" name="languages" placeholder="e.g. English, Hindi">
                        </div>
                        <div style="grid-column: 1 / -1;">
                            <label for="bio">Professional Bio</label>
                            <textarea id="bio" name="bio" rows="3" placeholder="Brief description of clinical expertise and patient care background..."></textarea>
                        </div>
                        <div class="form-actions" style="grid-column: 1 / -1;">
                            <button type="submit" class="btn btn-primary-highlight">Register Physician</button>
                        </div>
                    </form>
                </section>
            </div>
        </details>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
        </div>
    </footer>

    <!-- Modern Doctor Profile Modal Dialog -->
    <div class="doctor-modal-backdrop" id="doctor-modal" role="dialog" aria-modal="true" aria-labelledby="doc-modal-name">
        <div class="doctor-modal-card">
            <button type="button" class="doctor-modal-close" id="doc-modal-close" aria-label="Close doctor details">&times;</button>
            
            <div class="doc-modal-head">
                <div class="doc-modal-photo-col">
                    <img src="" alt="" id="doc-modal-img" class="doc-modal-photo" style="display: none;">
                    <div class="doc-modal-avatar-circle" id="doc-modal-avatar">DR</div>
                </div>
                <div class="doc-modal-title-meta">
                    <div class="doc-modal-badge-row">
                        <span class="doc-modal-spec-tag" id="doc-modal-spec">Cardiology</span>
                        <span class="badge-verified-inline">✓ Verified Specialist</span>
                    </div>
                    <h3 id="doc-modal-name">Dr. Specialist</h3>
                    <div class="doc-modal-avail" id="doc-modal-avail-wrap">
                        <span class="avail-dot" aria-hidden="true"></span> 
                        <span id="doc-modal-avail">Available for consultations today</span>
                    </div>
                </div>
            </div>

            <div class="doc-modal-body">
                <div class="doc-info-grid">
                    <div class="doc-info-item">
                        <span class="doc-info-label">Specialization</span>
                        <strong class="doc-info-val" id="doc-modal-spec-val">Cardiology</strong>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Department</span>
                        <strong class="doc-info-val" id="doc-modal-dept-val">Cardiology Department</strong>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Experience</span>
                        <strong class="doc-info-val" id="doc-modal-exp-val">16 years</strong>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Consultation Fee</span>
                        <strong class="doc-info-val text-fee" id="doc-modal-fee-val">₹1,000</strong>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Education</span>
                        <strong class="doc-info-val" id="doc-modal-edu-val">MBBS, MD</strong>
                    </div>
                    <div class="doc-info-item">
                        <span class="doc-info-label">Languages</span>
                        <strong class="doc-info-val" id="doc-modal-lang-val">English, Hindi</strong>
                    </div>
                    <div class="doc-info-item" style="grid-column: 1 / -1;">
                        <span class="doc-info-label">Clinical Consulting Hours</span>
                        <strong class="doc-info-val" id="doc-modal-hours-val">09:00 AM - 02:00 PM (Mon - Sat)</strong>
                    </div>
                </div>

                <div class="doc-modal-bio-wrap">
                    <h4 class="bio-heading">About the Doctor</h4>
                    <p id="doc-modal-bio-text" class="bio-text">Dedicated specialist providing comprehensive patient consultations.</p>
                </div>

                <div class="doc-modal-reassurance">
                    <span class="reassurance-icon" aria-hidden="true">🛡️</span>
                    <span>Board-certified clinical practitioner at WeCare Hospital. Consultations include complete physical assessment, treatment planning, and digital prescriptions.</span>
                </div>
            </div>

            <div class="doc-modal-footer">
                <a href="appointments.php" id="doc-modal-book-link" class="btn btn-primary-highlight btn-full">
                    Book Appointment with this Doctor →
                </a>
            </div>
        </div>
    </div>

    <!-- Embedded Doctor Data JSON -->
    <script id="wecare-doctors-data" type="application/json">
    <?php echo json_encode($doctorsDataMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>

    <!-- Live Search & Doctor Modal Script -->
    <script>
    (function () {
        var searchInput = document.getElementById('doctor-search-input');
        var specialtySelect = document.getElementById('doctor-specialty-select');
        var availSelect = document.getElementById('doctor-avail-select');
        var doctorCards = document.querySelectorAll('#doctors-directory-grid .doctor-card');
        var countBadge = document.getElementById('doctor-count-badge');
        var noDocNotice = document.getElementById('no-doctors-notice');
        var resetBtn = document.getElementById('btn-reset-dir-search');

        function filterDoctorsLive() {
            var q = searchInput ? searchInput.value.trim().toLowerCase() : '';
            var spec = specialtySelect ? specialtySelect.value.trim().toLowerCase() : '';
            var avail = availSelect ? availSelect.value.trim().toLowerCase() : '';
            var visibleCount = 0;

            doctorCards.forEach(function (card) {
                var cardName = card.getAttribute('data-name') || '';
                var cardSpec = card.getAttribute('data-spec') || '';
                var cardAvail = card.getAttribute('data-avail') || '';

                var matchesQ = !q || cardName.indexOf(q) !== -1 || cardSpec.indexOf(q) !== -1;
                var matchesSpec = !spec || cardSpec.indexOf(spec) !== -1;
                var matchesAvail = !avail || cardAvail.indexOf(avail) !== -1;

                if (matchesQ && matchesSpec && matchesAvail) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });

            if (countBadge) countBadge.textContent = visibleCount;
            if (noDocNotice) {
                noDocNotice.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterDoctorsLive);
        if (specialtySelect) specialtySelect.addEventListener('change', filterDoctorsLive);
        if (availSelect) availSelect.addEventListener('change', filterDoctorsLive);
        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (searchInput) searchInput.value = '';
                if (specialtySelect) specialtySelect.value = '';
                if (availSelect) availSelect.value = '';
                filterDoctorsLive();
            });
        }

        // Parse Doctor Data Map from embedded JSON
        var doctorsData = {};
        try {
            var rawDataEl = document.getElementById('wecare-doctors-data');
            if (rawDataEl) {
                doctorsData = JSON.parse(rawDataEl.textContent);
            }
        } catch (e) {
            console.error('Failed to parse doctors data:', e);
        }

        // Doctor Detail Modal Elements
        var docModal = document.getElementById('doctor-modal');
        var docCloseBtn = document.getElementById('doc-modal-close');
        var modalName = document.getElementById('doc-modal-name');
        var modalSpec = document.getElementById('doc-modal-spec');
        var modalSpecVal = document.getElementById('doc-modal-spec-val');
        var modalDeptVal = document.getElementById('doc-modal-dept-val');
        var modalExpVal = document.getElementById('doc-modal-exp-val');
        var modalFeeVal = document.getElementById('doc-modal-fee-val');
        var modalEduVal = document.getElementById('doc-modal-edu-val');
        var modalLangVal = document.getElementById('doc-modal-lang-val');
        var modalHoursVal = document.getElementById('doc-modal-hours-val');
        var modalBioText = document.getElementById('doc-modal-bio-text');
        var modalAvail = document.getElementById('doc-modal-avail');
        var modalAvatar = document.getElementById('doc-modal-avatar');
        var modalImg = document.getElementById('doc-modal-img');
        var modalBookLink = document.getElementById('doc-modal-book-link');

        function openDoctorProfile(doctorId, triggerBtn) {
            if (!docModal) return;
            var data = (doctorsData && doctorsData[String(doctorId)]) ? doctorsData[String(doctorId)] : null;
            if (!data && triggerBtn) {
                data = {
                    id: triggerBtn.getAttribute('data-doctor-id') || triggerBtn.getAttribute('data-id'),
                    name: triggerBtn.getAttribute('data-name') || 'Doctor Profile',
                    specialization: triggerBtn.getAttribute('data-spec') || 'Specialist',
                    department: triggerBtn.getAttribute('data-dept') || '',
                    experience: triggerBtn.getAttribute('data-exp') || '10+ years',
                    consultation_fee: (triggerBtn.getAttribute('data-fee') || '₹800').replace('₹', ''),
                    education: triggerBtn.getAttribute('data-edu') || 'MBBS, MD',
                    languages: triggerBtn.getAttribute('data-lang') || 'English, Hindi',
                    working_hours: triggerBtn.getAttribute('data-hours') || '09:00 AM - 05:00 PM',
                    bio: triggerBtn.getAttribute('data-bio') || 'Dedicated specialist providing comprehensive patient consultations.',
                    availability: triggerBtn.getAttribute('data-avail') || 'Available Today',
                    profile_image: triggerBtn.getAttribute('data-img') || '',
                    initials: triggerBtn.getAttribute('data-initials') || 'DR'
                };
            }
            if (!data) {
                console.error('Doctor record not found for ID:', doctorId);
                return;
            }

            if (modalName) modalName.textContent = data.name;
            if (modalSpec) modalSpec.textContent = data.specialization;
            if (modalSpecVal) modalSpecVal.textContent = data.specialization;
            if (modalDeptVal) modalDeptVal.textContent = data.department || (data.specialization + ' Department');
            if (modalExpVal) modalExpVal.textContent = data.experience;
            var feeVal = String(data.consultation_fee).indexOf('₹') !== -1 ? String(data.consultation_fee) : ('₹' + data.consultation_fee);
            if (modalFeeVal) modalFeeVal.textContent = feeVal;
            if (modalEduVal) modalEduVal.textContent = data.education;
            if (modalLangVal) modalLangVal.textContent = data.languages;
            if (modalHoursVal) modalHoursVal.textContent = data.working_hours;
            if (modalBioText) modalBioText.textContent = data.bio;
            if (modalAvail) modalAvail.textContent = data.availability ? ('• ' + data.availability) : '• Available for consultations';
            if (modalAvatar) modalAvatar.textContent = data.initials || 'DR';

            if (data.profile_image && modalImg) {
                modalImg.src = data.profile_image;
                modalImg.alt = data.name;
                modalImg.style.objectPosition = data.object_position || (triggerBtn ? triggerBtn.getAttribute('data-img-pos') : '') || 'center 15%';
                modalImg.style.display = 'block';
                if (modalAvatar) modalAvatar.style.display = 'none';
                modalImg.onerror = function () {
                    modalImg.style.display = 'none';
                    if (modalAvatar) modalAvatar.style.display = 'flex';
                };
            } else {
                if (modalImg) modalImg.style.display = 'none';
                if (modalAvatar) modalAvatar.style.display = 'flex';
            }

            if (modalBookLink) {
                modalBookLink.href = 'appointments.php?doctor_id=' + encodeURIComponent(data.id);
            }

            // Guaranteed visibility via both class and direct style
            docModal.style.display = 'flex';
            docModal.style.opacity = '1';
            docModal.style.pointerEvents = 'auto';
            docModal.classList.add('open');
            document.body.classList.add('modal-open');
        }

        function closeDoctorModal() {
            if (!docModal) return;
            docModal.classList.remove('open');
            docModal.style.display = 'none';
            docModal.style.opacity = '0';
            docModal.style.pointerEvents = 'none';
            document.body.classList.remove('modal-open');
        }

        // Global Event Delegation for all View Profile buttons
        document.addEventListener('click', function (event) {
            var btn = event.target.closest('.view-profile-btn, .btn-view-profile, [data-doctor-id]');
            if (!btn) return;
            event.preventDefault();
            var doctorId = btn.getAttribute('data-doctor-id') || btn.getAttribute('data-id');
            if (!doctorId) {
                console.error('Missing doctor ID on View Profile button');
                return;
            }
            openDoctorProfile(doctorId, btn);
        });

        if (docCloseBtn) {
            docCloseBtn.addEventListener('click', closeDoctorModal);
        }

        if (docModal) {
            docModal.addEventListener('click', function (e) {
                if (e.target === docModal) {
                    closeDoctorModal();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && docModal && (docModal.classList.contains('open') || docModal.style.display === 'flex')) {
                closeDoctorModal();
            }
        });

        // Expose globally for testing/interaction
        window.openDoctorProfile = openDoctorProfile;
        window.closeDoctorModal = closeDoctorModal;
    })();
    </script>
</body>
</html>
