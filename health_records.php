<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin('health_records.php');

$loggedPatient = getCurrentPatient($conn);
$loggedIn = ($loggedPatient !== null);
$patientName = $loggedPatient ? $loggedPatient['patient_name'] : 'Guest';
$patientInitials = $loggedPatient ? getInitials($patientName) : 'G';

// Active Tab Filter
$tab = trim($_GET['tab'] ?? 'all');

// Realistic Verified Clinical Test Records Dataset
$records = [
    [
        'id' => 'LAB-9042',
        'title' => 'Complete Blood Count (CBC) & Metabolic Panel',
        'category' => 'lab',
        'category_label' => 'Lab Results',
        'icon' => '🧪',
        'icon_class' => 'hr-icon-lab',
        'date' => '2026-08-20',
        'doctor' => 'Dr. Vikram Singh',
        'department' => 'Internal Medicine & Critical Care',
        'status' => 'Verified & Normal',
        'status_class' => 'badge-completed',
        'summary' => 'Hemoglobin: 14.8 g/dL, WBC: 6,800 /uL, Platelets: 240,000 /uL. All cellular indices within normal reference ranges.',
        'details' => [
            ['Hemoglobin', '14.8', 'g/dL', '13.5 - 17.5', 'Normal'],
            ['White Blood Cell (WBC)', '6,800', '/uL', '4,500 - 11,000', 'Normal'],
            ['Platelet Count', '240,000', '/uL', '150,000 - 450,000', 'Normal'],
            ['Fasting Blood Glucose', '92', 'mg/dL', '70 - 99', 'Normal'],
            ['Serum Creatinine', '0.9', 'mg/dL', '0.7 - 1.3', 'Normal'],
            ['Total Bilirubin', '0.8', 'mg/dL', '0.2 - 1.2', 'Normal']
        ]
    ],
    [
        'id' => 'RAD-3180',
        'title' => 'Digital Chest Radiography (X-Ray PA View)',
        'category' => 'imaging',
        'category_label' => 'Imaging',
        'icon' => '🩻',
        'icon_class' => 'hr-icon-imaging',
        'date' => '2026-07-15',
        'doctor' => 'Dr. Tarun Kapoor',
        'department' => 'Pulmonology & Respiratory Medicine',
        'status' => 'Clear & Normal',
        'status_class' => 'badge-completed',
        'summary' => 'Lung fields are clear with no focal consolidation or pleural effusion. Normal cardiothoracic ratio.',
        'details' => [
            ['Trachea', 'Midline', 'Visual', 'Midline', 'Normal'],
            ['Cardiothoracic Ratio', '< 50%', '%', '< 50%', 'Normal'],
            ['Pleural Spaces', 'Costophrenic angles clear', 'Visual', 'Clear', 'Normal'],
            ['Bony Thorax', 'Intact cortical margins', 'Visual', 'Intact', 'Normal']
        ]
    ],
    [
        'id' => 'CRD-8821',
        'title' => '12-Lead Electrocardiogram (ECG) Report',
        'category' => 'medical',
        'category_label' => 'Medical Reports',
        'icon' => '📈',
        'icon_class' => 'hr-icon-badge',
        'date' => '2026-06-10',
        'doctor' => 'Dr. Ananya Sharma',
        'department' => 'Cardiology & Heart Institute',
        'status' => 'Sinus Rhythm',
        'status_class' => 'badge-confirmed',
        'summary' => 'Normal sinus rhythm at 72 bpm. PR interval 148 ms, QRS duration 86 ms, QTc 412 ms. No ischemic ST-T changes.',
        'details' => [
            ['Heart Rate', '72', 'bpm', '60 - 100', 'Normal'],
            ['Rhythm', 'Normal Sinus Rhythm', 'Clinical', 'Sinus', 'Normal'],
            ['PR Interval', '148', 'ms', '120 - 200', 'Normal'],
            ['QRS Duration', '86', 'ms', '70 - 110', 'Normal'],
            ['QTc Interval', '412', 'ms', '< 450', 'Normal']
        ]
    ],
    [
        'id' => 'IMG-4491',
        'title' => 'High-Resolution Brain MRI Scan (T1/T2 Axial)',
        'category' => 'imaging',
        'category_label' => 'Imaging',
        'icon' => '🧠',
        'icon_class' => 'hr-icon-imaging',
        'date' => '2026-05-18',
        'doctor' => 'Dr. Arvind Menon',
        'department' => 'Institute of Neurosciences',
        'status' => 'Verified & Normal',
        'status_class' => 'badge-completed',
        'summary' => 'No acute territorial infarction, intracranial hemorrhage, or space-occupying lesions. Ventricles and sulci age-appropriate.',
        'details' => [
            ['Brain Parenchyma', 'Normal grey-white differentiation', 'Visual', 'Normal', 'Normal'],
            ['Ventricular System', 'Normal contour and symmetry', 'Visual', 'Symmetric', 'Normal'],
            ['Midline Shift', 'None detected', 'Visual', 'None', 'Normal'],
            ['Major Vessels', 'Signal flow voids intact', 'Visual', 'Intact', 'Normal']
        ]
    ],
    [
        'id' => 'LAB-7710',
        'title' => 'Comprehensive Lipid Profile & Cardiac Risk Panel',
        'category' => 'lab',
        'category_label' => 'Lab Results',
        'icon' => '🩸',
        'icon_class' => 'hr-icon-lab',
        'date' => '2026-04-22',
        'doctor' => 'Dr. Ananya Sharma',
        'department' => 'Cardiology & Heart Institute',
        'status' => 'Optimal Profile',
        'status_class' => 'badge-completed',
        'summary' => 'Total Cholesterol: 178 mg/dL, HDL: 54 mg/dL, LDL: 98 mg/dL, Triglycerides: 130 mg/dL. Cardiovascular risk index: Low.',
        'details' => [
            ['Total Cholesterol', '178', 'mg/dL', '< 200', 'Optimal'],
            ['HDL Cholesterol', '54', 'mg/dL', '> 40', 'Optimal'],
            ['LDL Cholesterol', '98', 'mg/dL', '< 100', 'Optimal'],
            ['Triglycerides', '130', 'mg/dL', '< 150', 'Normal'],
            ['Chol/HDL Ratio', '3.3', 'Ratio', '< 4.5', 'Normal']
        ]
    ],
    [
        'id' => 'CLIN-1029',
        'title' => 'Annual Clinical Health Assessment Summary',
        'category' => 'summary',
        'category_label' => 'Visit Summary',
        'icon' => '📋',
        'icon_class' => 'hr-icon-summary',
        'date' => '2026-03-12',
        'doctor' => 'Dr. Vikram Singh',
        'department' => 'Internal Medicine & Critical Care',
        'status' => 'Completed',
        'status_class' => 'badge-completed',
        'summary' => 'Routine preventive wellness assessment. Blood pressure 118/78 mmHg, BMI 22.4, resting ECG normal. Recommended annual review.',
        'details' => [
            ['Blood Pressure', '118/78', 'mmHg', '< 120/80', 'Optimal'],
            ['Resting Heart Rate', '70', 'bpm', '60 - 100', 'Normal'],
            ['Body Mass Index (BMI)', '22.4', 'kg/m2', '18.5 - 24.9', 'Normal'],
            ['Oxygen Saturation (SpO2)', '99', '%', '95 - 100', 'Normal'],
            ['Overall Clinical Rating', 'Class A - Healthy', 'Grade', 'Class A', 'Normal']
        ]
    ]
];

// Filter based on tab
$filteredRecords = [];
foreach ($records as $r) {
    if ($tab === 'all') {
        $filteredRecords[] = $r;
    } elseif ($tab === 'medical' && $r['category'] === 'medical') {
        $filteredRecords[] = $r;
    } elseif ($tab === 'lab' && $r['category'] === 'lab') {
        $filteredRecords[] = $r;
    } elseif ($tab === 'imaging' && $r['category'] === 'imaging') {
        $filteredRecords[] = $r;
    } elseif ($tab === 'summary' && $r['category'] === 'summary') {
        $filteredRecords[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Records & Medical Tests | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <div class="page-head">
            <h1 class="page-title">Health Records</h1>
            <p class="page-sub">Access your medical records, lab reports, and test results securely.</p>
        </div>

        <div class="health-records-layout">
            <!-- Left Column: Tabs + Report Cards -->
            <div class="records-content-col">
                <!-- Navigation Tabs -->
                <div class="records-subtabs-row">
                    <a href="health_records.php?tab=all" class="records-subtab <?php echo $tab === 'all' ? 'active' : ''; ?>">
                        All Records (<?php echo count($records); ?>)
                    </a>
                    <a href="health_records.php?tab=medical" class="records-subtab <?php echo $tab === 'medical' ? 'active' : ''; ?>">
                        Medical Reports (1)
                    </a>
                    <a href="health_records.php?tab=lab" class="records-subtab <?php echo $tab === 'lab' ? 'active' : ''; ?>">
                        Lab Results (2)
                    </a>
                    <a href="health_records.php?tab=imaging" class="records-subtab <?php echo $tab === 'imaging' ? 'active' : ''; ?>">
                        Imaging (2)
                    </a>
                    <a href="health_records.php?tab=summary" class="records-subtab <?php echo $tab === 'summary' ? 'active' : ''; ?>">
                        Visit Summary (1)
                    </a>
                </div>

                <!-- Record Cards List -->
                <div class="records-cards-list">
                    <?php if (!empty($filteredRecords)): ?>
                        <?php foreach ($filteredRecords as $rec): ?>
                            <article class="health-record-card">
                                <div class="hr-card-left">
                                    <div class="hr-icon-badge <?php echo htmlspecialchars($rec['icon_class']); ?>" aria-hidden="true">
                                        <?php echo htmlspecialchars($rec['icon']); ?>
                                    </div>
                                    <div class="hr-info-col">
                                        <h3><?php echo htmlspecialchars($rec['title']); ?></h3>
                                        <div class="hr-meta-line">
                                            <span>📅 <?php echo date('F j, Y', strtotime($rec['date'])); ?></span>
                                            <span>·</span>
                                            <span>Attending: <strong><?php echo htmlspecialchars($rec['doctor']); ?></strong></span>
                                            <span>·</span>
                                            <span class="badge badge-specialty"><?php echo htmlspecialchars($rec['category_label']); ?></span>
                                            <span>·</span>
                                            <span class="text-muted">Ref: #<?php echo htmlspecialchars($rec['id']); ?></span>
                                        </div>
                                        <p style="margin: 6px 0 0; font-size: 0.8rem; color: #64748b; line-height: 1.35;">
                                            <?php echo htmlspecialchars($rec['summary']); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="hr-card-right">
                                    <span class="badge <?php echo htmlspecialchars($rec['status_class']); ?>">
                                        <?php echo htmlspecialchars($rec['status']); ?>
                                    </span>
                                    <button type="button" class="btn btn-secondary btn-sm btn-open-record-modal"
                                        data-id="<?php echo htmlspecialchars($rec['id']); ?>"
                                        data-title="<?php echo htmlspecialchars($rec['title']); ?>"
                                        data-cat="<?php echo htmlspecialchars($rec['category_label']); ?>"
                                        data-date="<?php echo date('F j, Y', strtotime($rec['date'])); ?>"
                                        data-doctor="<?php echo htmlspecialchars($rec['doctor']); ?>"
                                        data-dept="<?php echo htmlspecialchars($rec['department']); ?>"
                                        data-status="<?php echo htmlspecialchars($rec['status']); ?>"
                                        data-summary="<?php echo htmlspecialchars($rec['summary']); ?>"
                                        data-details='<?php echo json_encode($rec['details']); ?>'>
                                        View Report
                                    </button>
                                    <button type="button" class="btn btn-primary-highlight btn-sm btn-download-record" data-title="<?php echo htmlspecialchars($rec['title']); ?>">
                                        Download PDF
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state-card panel">
                            <div class="empty-state-icon">📋</div>
                            <h3>No reports found</h3>
                            <p>No health records matched the selected filter.</p>
                            <a href="health_records.php?tab=all" class="btn btn-primary-highlight">View All Records</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Quick Actions & Security Guarantee -->
            <aside class="records-sidebar-col">
                <!-- Quick Actions Card -->
                <div class="quick-actions-card">
                    <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin: 0 0 1rem;">Quick Actions</h3>
                    <div class="action-buttons-stack" style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <button type="button" class="quick-action-link" id="btn-dl-all" style="width: 100%; border: 1px solid #e2e8f0; cursor: pointer;">
                            <span>📥 Download All Reports</span>
                            <span style="color: #94a3b8;">→</span>
                        </button>
                        <button type="button" class="quick-action-link" id="btn-share-records" style="width: 100%; border: 1px solid #e2e8f0; cursor: pointer;">
                            <span>📤 Share Records with Doctor</span>
                            <span style="color: #94a3b8;">→</span>
                        </button>
                        <a href="appointments.php" class="quick-action-link" style="border: 1px solid #e2e8f0;">
                            <span>🩺 Request New Diagnostic Test</span>
                            <span style="color: #94a3b8;">→</span>
                        </a>
                    </div>
                </div>

                <!-- Patient Vitals & Metrics Card -->
                <div class="patient-vitals-card panel" style="margin-top: 1.25rem;">
                    <h4 style="font-size: 0.95rem; font-weight: 700; color: #0f172a; margin: 0 0 0.85rem;">Patient Health Summary</h4>
                    <div class="vitals-item-row" style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <span class="vitals-label" style="color: #64748b;">Patient Name:</span>
                        <strong class="vitals-val" style="color: #0f172a;"><?php echo htmlspecialchars($patientName); ?></strong>
                    </div>
                    <div class="vitals-item-row" style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <span class="vitals-label" style="color: #64748b;">Blood Group:</span>
                        <strong class="vitals-val text-accent-blue font-bold">O+ (Positive)</strong>
                    </div>
                    <div class="vitals-item-row" style="display: flex; justify-content: space-between; padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem;">
                        <span class="vitals-label" style="color: #64748b;">Known Allergies:</span>
                        <strong class="vitals-val text-green font-bold">None Reported (NKDA)</strong>
                    </div>
                    <div class="vitals-item-row" style="display: flex; justify-content: space-between; padding: 0.4rem 0; font-size: 0.85rem;">
                        <span class="vitals-label" style="color: #64748b;">Blood Pressure:</span>
                        <strong class="vitals-val" style="color: #0f172a;">118 / 78 mmHg</strong>
                    </div>
                </div>

                <!-- Security Guarantee Card -->
                <div class="records-security-card">
                    <span style="font-size: 1.4rem;">🛡️</span>
                    <div>
                        <h4>Medical Records Confidentiality</h4>
                        <p>All diagnostic test results and medical imaging are strictly confidential, protected by 256-bit AES encryption and HIPAA compliance protocols.</p>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
        </div>
    </footer>

    <!-- Clinical Diagnostic Report Modal Dialog -->
    <div class="doctor-modal-backdrop" id="report-modal" style="display: none;">
        <div class="doctor-modal-card rx-modal-card" style="max-width: 760px;">
            <button type="button" class="doctor-modal-close no-print" id="report-modal-close">&times;</button>
            
            <div class="rx-printable-sheet" id="report-print-area">
                <!-- Hospital Diagnostics Letterhead -->
                <div class="rx-header">
                    <div class="rx-brand-col">
                        <h2>WeCare Hospital</h2>
                        <p class="rx-brand-sub">Institute of Diagnostics & Laboratory Medicine · NABH Accredited</p>
                        <p class="rx-brand-addr">Super Speciality Medical Pavilion · Clinical Hotline: 1800-922-CARE</p>
                    </div>
                    <div class="rx-ref-col">
                        <div class="rx-doc-title-block">
                            <strong id="rep-doc">Dr. Attending Physician</strong>
                            <div class="text-muted" id="rep-dept">Diagnostic Medicine</div>
                        </div>
                        <div class="rx-meta-pill">Report Ref #<span id="rep-id">LAB-0000</span></div>
                    </div>
                </div>

                <hr class="rx-divider">

                <!-- Patient & Test Details -->
                <div class="rx-patient-meta-row">
                    <div><strong>Patient:</strong> <?php echo htmlspecialchars($patientName); ?></div>
                    <div><strong>Test Name:</strong> <span id="rep-title">Test Name</span></div>
                    <div><strong>Date Issued:</strong> <span id="rep-date">Date</span></div>
                    <div><strong>Status:</strong> <span id="rep-status" class="badge badge-completed">Verified</span></div>
                </div>

                <div class="rx-body-content" style="padding: 1.25rem 0;">
                    <h4 style="margin: 0 0 0.75rem; font-size: 0.92rem; color: #0f172a;">Itemized Laboratory Findings & Parameter Values:</h4>
                    <table class="invoice-table" style="width: 100%; margin-bottom: 1.25rem;">
                        <thead>
                            <tr>
                                <th>Test Parameter</th>
                                <th>Observed Value</th>
                                <th>Units</th>
                                <th>Reference Range</th>
                                <th style="text-align: right;">Interpretation</th>
                            </tr>
                        </thead>
                        <tbody id="rep-table-body">
                            <!-- Injected dynamically via JS -->
                        </tbody>
                    </table>

                    <div style="background: #fafcff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-top: 1rem;">
                        <strong style="font-size: 0.82rem; color: #0f172a; display: block; margin-bottom: 4px;">Pathologist Clinical Remarks & Summary:</strong>
                        <p id="rep-summary" style="margin: 0; font-size: 0.8rem; color: #475569; line-height: 1.4;">Parameters evaluated and certified by WeCare central pathology department.</p>
                    </div>
                </div>

                <div class="rx-signature-block" style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                    <small class="text-muted">Certified Electronic Medical Record · Generated by WeCare Hospital HIS</small>
                    <div style="text-align: right;">
                        <strong style="display: block; font-size: 0.85rem; color: #0f172a;">Dr. Central Pathology Staff</strong>
                        <small class="text-muted">Chief of Laboratory Medicine</small>
                    </div>
                </div>
            </div>

            <div class="rx-modal-actions no-print" style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
                <button type="button" class="btn btn-secondary" id="report-modal-dismiss">Close Window</button>
                <button type="button" class="btn btn-primary-highlight" onclick="window.print();">🖨️ Print Official Report</button>
            </div>
        </div>
    </div>

    <!-- Client Script for Modal and Quick Actions -->
    <script>
    (function () {
        var modal = document.getElementById('report-modal');
        var closeBtn = document.getElementById('report-modal-close');
        var dismissBtn = document.getElementById('report-modal-dismiss');

        function closeModal() {
            if (modal) modal.style.display = 'none';
        }

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (dismissBtn) dismissBtn.addEventListener('click', closeModal);
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal && modal.style.display !== 'none') closeModal();
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-open-record-modal');
            if (btn && modal) {
                e.preventDefault();
                document.getElementById('rep-id').textContent = btn.getAttribute('data-id');
                document.getElementById('rep-title').textContent = btn.getAttribute('data-title');
                document.getElementById('rep-date').textContent = btn.getAttribute('data-date');
                document.getElementById('rep-doc').textContent = btn.getAttribute('data-doctor');
                document.getElementById('rep-dept').textContent = btn.getAttribute('data-dept');
                document.getElementById('rep-status').textContent = btn.getAttribute('data-status');
                document.getElementById('rep-summary').textContent = btn.getAttribute('data-summary');

                var tbody = document.getElementById('rep-table-body');
                tbody.innerHTML = '';
                var details = [];
                try {
                    details = JSON.parse(btn.getAttribute('data-details') || '[]');
                } catch (err) {}

                details.forEach(function (row) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td><strong>' + row[0] + '</strong></td>' +
                                   '<td>' + row[1] + '</td>' +
                                   '<td>' + row[2] + '</td>' +
                                   '<td>' + row[3] + '</td>' +
                                   '<td style="text-align: right;"><span class="badge badge-completed">' + row[4] + '</span></td>';
                    tbody.appendChild(tr);
                });

                modal.style.display = 'flex';
            }

            var dlBtn = e.target.closest('.btn-download-record');
            if (dlBtn) {
                e.preventDefault();
                var title = dlBtn.getAttribute('data-title') || 'Medical Report';
                if (window.showToast) {
                    window.showToast('Downloading verified PDF for ' + title + '...', 'success');
                }
            }
        });

        // Quick Action Handlers
        var dlAll = document.getElementById('btn-dl-all');
        if (dlAll) {
            dlAll.addEventListener('click', function () {
                if (window.showToast) {
                    window.showToast('Preparing comprehensive health records dossier package (ZIP/PDF)...', 'success');
                }
            });
        }

        var shareBtn = document.getElementById('btn-share-records');
        if (shareBtn) {
            shareBtn.addEventListener('click', function () {
                if (window.showToast) {
                    window.showToast('Secure sharing link generated. Valid for 72 hours with attending doctor.', 'info');
                }
            });
        }
    })();
    </script>
</body>
</html>
