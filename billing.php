<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

requireLogin('billing.php');

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

// Handle Patient Actions (Pay Bill)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    
    if ($action === 'pay_bill') {
        $billId = (int) ($_POST['bill_id'] ?? 0);
        $method = trim($_POST['payment_method'] ?? 'UPI / Online Portal');

        if ($loggedIn && $billId > 0) {
            $pStmt = $conn->prepare('
                UPDATE bills 
                SET payment_status = "Paid" 
                WHERE id = ? AND patient_id = ? AND payment_status != "Paid"
            ');
            if ($pStmt) {
                $pStmt->bind_param('ii', $billId, $loggedPatient['id']);
                if ($pStmt->execute() && $pStmt->affected_rows > 0) {
                    $_SESSION['flash_message'] = 'Payment for Bill #WC-' . str_pad((string)$billId, 4, '0', STR_PAD_LEFT) . ' completed via ' . htmlspecialchars($method) . '. Official receipt issued.';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Could not update payment status or bill is already paid.';
                    $_SESSION['flash_type'] = 'info';
                }
                $pStmt->close();
            }
            header('Location: billing.php');
            exit;
        }
    }
}

// Fetch bills strictly for logged-in patient
$patientBills = [];
$totalOutstanding = 0.00;
$totalPaid = 0.00;
$totalBilled = 0.00;
$pendingCount = 0;
$paidCount = 0;

if ($loggedIn) {
    $stmt = $conn->prepare('
        SELECT b.id, b.patient_id, b.appointment_id, b.doctor_id, b.amount, b.bill_date, b.payment_status, b.services_breakdown, b.created_at,
               p.patient_name, p.age, p.gender, p.phone, p.address, p.email,
               d.doctor_name, d.specialization, d.department,
               a.appointment_date, a.appointment_time
        FROM bills b
        INNER JOIN patients p ON p.id = b.patient_id
        LEFT JOIN doctors d ON d.id = b.doctor_id
        LEFT JOIN appointments a ON a.id = b.appointment_id
        WHERE b.patient_id = ?
        ORDER BY b.bill_date DESC, b.id DESC
    ');
    if ($stmt) {
        $stmt->bind_param('i', $loggedPatient['id']);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $patientBills[] = $row;
            $amt = (float) $row['amount'];
            $totalBilled += $amt;
            if ($row['payment_status'] === 'Paid') {
                $totalPaid += $amt;
                $paidCount++;
            } else {
                $totalOutstanding += $amt;
                $pendingCount++;
            }
        }
        $stmt->close();
    }
}

// Filter bills if requested in query
$statusFilter = trim($_GET['status'] ?? 'all');
$filteredBills = [];
foreach ($patientBills as $b) {
    if ($statusFilter === 'pending' && $b['payment_status'] !== 'Pending') continue;
    if ($statusFilter === 'paid' && $b['payment_status'] !== 'Paid') continue;
    $filteredBills[] = $b;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Invoices | WeCare Hospital</title>
    <link rel="stylesheet" href="style.css?v=<?php echo urlencode((string) filemtime(__DIR__ . '/style.css')); ?>">
</head>
<body class="wecare-body">
    <?php include __DIR__ . '/nav.php'; ?>

    <main class="wecare-main">
        <div class="page-head">
            <h1 class="page-title">Billing & Invoices</h1>
            <p class="page-sub">Manage your medical bills, payments, and view transaction history.</p>
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
                <h2>Sign in to view your billing statements</h2>
                <p>Log in to your WeCare patient profile to view itemized consultation charges, payment receipts, and outstanding hospital balances.</p>
                <div class="signin-required-actions">
                    <a href="login.php?redirect=billing.php" class="btn btn-primary-highlight">Sign in</a>
                    <a href="login.php?action=register&redirect=billing.php" class="btn btn-secondary">Create account</a>
                </div>
            </section>
        <?php else: ?>

            <!-- 3 Top Financial Stat Cards Matching Mockup -->
            <div class="billing-3stat-grid">
                <div class="stat-metric-card">
                    <div class="stat-icon-circle stat-icon-purple">📄</div>
                    <div class="stat-metric-info">
                        <span class="stat-metric-label">Total Bills</span>
                        <strong class="stat-metric-value">₹<?php echo number_format($totalBilled, 0); ?></strong>
                    </div>
                </div>

                <div class="stat-metric-card">
                    <div class="stat-icon-circle stat-icon-green">✓</div>
                    <div class="stat-metric-info">
                        <span class="stat-metric-label">Paid</span>
                        <strong class="stat-metric-value">₹<?php echo number_format($totalPaid, 0); ?></strong>
                    </div>
                </div>

                <div class="stat-metric-card">
                    <div class="stat-icon-circle stat-icon-amber">⏳</div>
                    <div class="stat-metric-info">
                        <span class="stat-metric-label">Pending</span>
                        <strong class="stat-metric-value">₹<?php echo number_format($totalOutstanding, 0); ?></strong>
                    </div>
                </div>
            </div>

            <!-- 2-Column Layout: Table on Left, Payment Methods on Right -->
            <div class="billing-2col-layout">
                <div class="billing-table-container">
                    <div class="panel-header-flex">
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0 0 0.25rem; color: #0f172a;">Recent Bills</h2>
                            <p class="section-subtext">Bills are automatically generated upon completion of consultations</p>
                        </div>
                        
                        <div class="billing-filter-pills">
                            <a href="billing.php?status=all" class="filter-pill-btn <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">All (<?php echo count($patientBills); ?>)</a>
                            <a href="billing.php?status=pending" class="filter-pill-btn <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending (<?php echo $pendingCount; ?>)</a>
                            <a href="billing.php?status=paid" class="filter-pill-btn <?php echo $statusFilter === 'paid' ? 'active' : ''; ?>">Paid (<?php echo $paidCount; ?>)</a>
                        </div>
                    </div>

                    <?php if (!empty($filteredBills)): ?>
                        <div style="overflow-x: auto;">
                            <table class="billing-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredBills as $bill): 
                                        $bId = (int) $bill['id'];
                                        $bRef = 'WC-' . str_pad((string)$bId, 4, '0', STR_PAD_LEFT);
                                        $isPaid = ($bill['payment_status'] === 'Paid');
                                        $amount = (float) $bill['amount'];
                                        $docName = $bill['doctor_name'] ?? 'WeCare Medical Specialist';
                                        $spec = $bill['specialization'] ?? 'Clinical Outpatient';
                                        $services = $bill['services_breakdown'] ?: ('Consultation Fee: ₹' . number_format($amount, 2));
                                    ?>
                                        <tr>
                                            <td style="font-weight: 600; white-space: nowrap; color: #0f172a;">
                                                <?php echo date('M d, Y', strtotime($bill['bill_date'])); ?>
                                            </td>
                                            <td>
                                                <strong style="color: #0f172a;">Consultation - <?php echo htmlspecialchars($docName); ?></strong>
                                                <div style="font-size: 0.78rem; color: #64748b;"><?php echo htmlspecialchars($spec); ?> · #<?php echo $bRef; ?></div>
                                            </td>
                                            <td style="font-weight: 700; font-size: 0.95rem; white-space: nowrap; color: #0f172a;">
                                                ₹<?php echo number_format($amount, 0); ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $isPaid ? 'badge-completed' : 'badge-checked-in'; ?>">
                                                    <?php echo htmlspecialchars($bill['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: inline-flex; gap: 0.4rem; align-items: center;">
                                                    <button type="button" class="btn-download-receipt btn-open-invoice"
                                                        data-ref="<?php echo $bRef; ?>"
                                                        data-id="<?php echo $bId; ?>"
                                                        data-patient="<?php echo htmlspecialchars($bill['patient_name']); ?>"
                                                        data-age="<?php echo (int) $bill['age']; ?>"
                                                        data-gender="<?php echo htmlspecialchars($bill['gender']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($bill['phone']); ?>"
                                                        data-doctor="<?php echo htmlspecialchars($docName); ?>"
                                                        data-spec="<?php echo htmlspecialchars($spec); ?>"
                                                        data-date="<?php echo date('M d, Y', strtotime($bill['bill_date'])); ?>"
                                                        data-amount="₹<?php echo number_format($amount, 2); ?>"
                                                        data-status="<?php echo htmlspecialchars($bill['payment_status']); ?>"
                                                        data-services="<?php echo htmlspecialchars($services); ?>">
                                                        <span>📥</span> Download
                                                    </button>

                                                    <button type="button" class="btn btn-secondary btn-sm btn-print-direct"
                                                        data-ref="<?php echo $bRef; ?>"
                                                        data-id="<?php echo $bId; ?>"
                                                        data-patient="<?php echo htmlspecialchars($bill['patient_name']); ?>"
                                                        data-age="<?php echo (int) $bill['age']; ?>"
                                                        data-gender="<?php echo htmlspecialchars($bill['gender']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($bill['phone']); ?>"
                                                        data-doctor="<?php echo htmlspecialchars($docName); ?>"
                                                        data-spec="<?php echo htmlspecialchars($spec); ?>"
                                                        data-date="<?php echo date('M d, Y', strtotime($bill['bill_date'])); ?>"
                                                        data-amount="₹<?php echo number_format($amount, 2); ?>"
                                                        data-status="<?php echo htmlspecialchars($bill['payment_status']); ?>"
                                                        data-services="<?php echo htmlspecialchars($services); ?>"
                                                        title="Print Invoice Directly">
                                                        🖨️
                                                    </button>

                                                    <?php if (!$isPaid): ?>
                                                        <button type="button" class="btn btn-primary-highlight btn-sm btn-open-pay-modal"
                                                            data-id="<?php echo $bId; ?>"
                                                            data-ref="<?php echo $bRef; ?>"
                                                            data-doctor="<?php echo htmlspecialchars($docName); ?>"
                                                            data-amount="₹<?php echo number_format($amount, 2); ?>"
                                                            style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                                            Pay Now →
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state-card" style="margin-top: 1rem;">
                            <div class="empty-state-icon">🧾</div>
                            <h3>No billing statements found</h3>
                            <p>When you complete a physician consultation at WeCare Hospital, your automated itemized bills and receipts will be stored here.</p>
                            <a href="appointments.php" class="btn btn-primary-highlight">Book a Consultation</a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Column: Payment Methods & Security Guarantee -->
                <div class="billing-side-col">
                    <div class="payment-methods-card">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #0f172a; margin: 0 0 0.25rem;">Payment Methods</h3>
                        <p style="font-size: 0.82rem; color: #64748b; margin: 0 0 1rem;">Choose from fast and secure options</p>

                        <div class="payment-method-item">
                            <div class="pm-icon-badge">📱</div>
                            <div class="pm-details">
                                <span class="pm-title">UPI / QR Code</span>
                                <span class="pm-sub">Google Pay, PhonePe, Paytm, BHIM</span>
                            </div>
                        </div>

                        <div class="payment-method-item">
                            <div class="pm-icon-badge">💳</div>
                            <div class="pm-details">
                                <span class="pm-title">Credit & Debit Cards</span>
                                <span class="pm-sub">Visa, MasterCard, RuPay, Maestro</span>
                            </div>
                        </div>

                        <div class="payment-method-item">
                            <div class="pm-icon-badge">🏦</div>
                            <div class="pm-details">
                                <span class="pm-title">Net Banking</span>
                                <span class="pm-sub">All major 50+ Indian commercial banks</span>
                            </div>
                        </div>

                        <a href="appointments.php" class="btn btn-secondary btn-full" style="margin-top: 1.25rem;">
                            Manage Payment Methods
                        </a>
                    </div>

                    <div class="sidebar-privacy-note" style="margin-top: 1.25rem; padding: 1rem; border-radius: 12px; background: #ffffff; border: 1px solid #e2e8f0; display: flex; align-items: flex-start; gap: 0.75rem;">
                        <div class="sec-dot" style="background:#10b981; width: 10px; height: 10px; border-radius: 50%; margin-top: 4px; flex-shrink: 0;"></div>
                        <div>
                            <strong style="color: #0f172a; font-size: 0.85rem;">100% Secure Payments</strong>
                            <p style="margin: 2px 0 0; font-size: 0.75rem; color: #64748b; line-height: 1.4;">All transactions are encrypted with 256-bit banking grade SSL and comply strictly with PCI-DSS safety standards.</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer class="site-footer">
        <div class="footer-inner">
            <p><strong>WeCare Hospital</strong> · Care • Compassion • Clinical Excellence</p>
        </div>
    </footer>

    <!-- Professional Invoice Modal Dialog -->
    <div class="doctor-modal-backdrop" id="invoice-modal" style="display: none;">
        <div class="doctor-modal-card invoice-modal-card">
            <button type="button" class="doctor-modal-close no-print" id="invoice-close-btn">&times;</button>
            
            <div class="invoice-sheet" id="invoice-printable-area">
                <!-- Hospital Header -->
                <div class="invoice-header">
                    <div class="inv-brand">
                        <div class="inv-logo-wrap">
                            <span class="logo-icon-svg" aria-hidden="true">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2.5">
                                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
                                    <path d="M12 7v6"/>
                                    <path d="M9 10h6"/>
                                </svg>
                            </span>
                            <h2>WECARE HOSPITAL</h2>
                        </div>
                        <p class="inv-sub">Super Speciality Healthcare Campus · Care • Compassion • Clinical Excellence</p>
                        <p class="inv-address">Clinical Pavilion Road, Sector 4, Bangalore · Ph: 1800-922-CARE</p>
                    </div>
                    <div class="inv-meta">
                        <span class="inv-type-pill">Official Medical Invoice</span>
                        <div class="inv-num">Invoice #<span id="inv-ref">WC-0000</span></div>
                        <div class="inv-date">Date: <span id="inv-date"></span></div>
                        <div class="inv-stamp" id="inv-stamp">PAID</div>
                    </div>
                </div>

                <hr class="inv-divider">

                <!-- Patient & Attending Info -->
                <div class="inv-entities-grid">
                    <div class="inv-entity-col">
                        <span class="inv-col-label">Patient:</span>
                        <strong class="inv-col-name" id="inv-patient-name">Patient Name</strong>
                        <div class="inv-col-sub" id="inv-patient-meta">Age: 32 · Male · Ph: 9876543210</div>
                        <div class="inv-col-sub">Appointment Ref: #<span id="inv-appt-ref">Visit Scheduled</span></div>
                    </div>

                    <div class="inv-entity-col">
                        <span class="inv-col-label">Doctor:</span>
                        <strong class="inv-col-name" id="inv-doctor-name">Dr. Specialist</strong>
                        <div class="inv-col-sub" id="inv-spec">Cardiology Department</div>
                        <div class="inv-col-sub">Outpatient Consultation Desk</div>
                    </div>
                </div>

                <!-- Itemized Table -->
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Department</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <strong>Physician Outpatient Consultation</strong><br>
                                <small class="text-muted" id="inv-services-desc">Clinical evaluation & prescription review</small>
                            </td>
                            <td id="inv-dept-cell">Clinical Outpatient</td>
                            <td style="text-align: right;"><strong id="inv-table-consult">₹800.00</strong></td>
                        </tr>
                        <tr>
                            <td>
                                <strong>Diagnostic Services & Records Processing</strong><br>
                                <small class="text-muted">Digital health record archival & pathology verification</small>
                            </td>
                            <td>Diagnostics</td>
                            <td style="text-align: right;"><strong>₹0.00</strong></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="text-align: right;">Subtotal:</td>
                            <td style="text-align: right;" id="inv-subtotal">₹800.00</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: right;">Discount (WeCare Patient Care Subsidy):</td>
                            <td style="text-align: right;">₹0.00</td>
                        </tr>
                        <tr class="inv-grand-total-row">
                            <td colspan="2" style="text-align: right;"><strong>Grand Total:</strong></td>
                            <td style="text-align: right;"><strong class="text-accent-blue" id="inv-total">₹800.00</strong></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="invoice-footer-note">
                    <p>Computer-generated statement. WeCare Hospital operates automated clinical workflows. For queries, contact Patient Support at 1800-922-CARE.</p>
                </div>
            </div>

            <div class="inv-modal-actions no-print">
                <button type="button" class="btn btn-secondary" id="inv-btn-close">Close</button>
                <button type="button" class="btn btn-primary-highlight" onclick="window.print();">🖨️ Print Invoice</button>
                <button type="button" class="btn btn-primary-bright" id="inv-btn-pay" style="display: none;">Pay Now</button>
            </div>
        </div>
    </div>

    <!-- Payment Simulation Modal -->
    <div class="doctor-modal-backdrop" id="pay-modal" style="display: none;">
        <div class="doctor-modal-card" style="max-width: 440px;">
            <button type="button" class="doctor-modal-close" id="pay-close-btn">&times;</button>
            <div class="pay-modal-head">
                <div class="pay-icon-circle">💳</div>
                <h3>Settle Consultation Statement</h3>
                <p>Confirm simulated payment for <strong id="pay-ref-text">#WC-0000</strong></p>
            </div>

            <form method="post" action="billing.php" id="pay-form">
                <input type="hidden" name="action" value="pay_bill">
                <input type="hidden" name="bill_id" id="pay-bill-id" value="0">

                <div class="pay-due-box">
                    <span>Total Outstanding Due:</span>
                    <strong id="pay-amount-text" class="text-accent-blue">₹800.00</strong>
                </div>

                <div class="pay-methods-group">
                    <label class="pay-method-label">Select Payment Method:</label>
                    <div class="pay-method-options">
                        <label class="pay-opt">
                            <input type="radio" name="payment_method" value="UPI / QR Code" checked>
                            <span>📱 UPI / QR Code Instant Pay</span>
                        </label>
                        <label class="pay-opt">
                            <input type="radio" name="payment_method" value="Debit / Credit Card">
                            <span>💳 Visa / Mastercard / RuPay</span>
                        </label>
                        <label class="pay-opt">
                            <input type="radio" name="payment_method" value="Net Banking">
                            <span>🏦 Net Banking (All Major Banks)</span>
                        </label>
                    </div>
                </div>

                <div class="pay-modal-actions">
                    <button type="submit" class="btn btn-primary-highlight btn-full">
                        Authorize Payment of <span id="pay-btn-amount">₹800.00</span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-full" id="pay-btn-cancel">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client Script for Billing Invoices & Pay Modal -->
    <script>
    (function () {
        var invModal = document.getElementById('invoice-modal');
        var invClose = document.getElementById('invoice-close-btn');
        var invClose2 = document.getElementById('inv-btn-close');
        var invPayBtn = document.getElementById('inv-btn-pay');

        var payModal = document.getElementById('pay-modal');
        var payClose = document.getElementById('pay-close-btn');
        var payCancel = document.getElementById('pay-btn-cancel');

        function closeInv() { if (invModal) invModal.style.display = 'none'; }
        function closePay() { if (payModal) payModal.style.display = 'none'; }

        if (invClose) invClose.addEventListener('click', closeInv);
        if (invClose2) invClose2.addEventListener('click', closeInv);
        if (payClose) payClose.addEventListener('click', closePay);
        if (payCancel) payCancel.addEventListener('click', closePay);

        function populateInvoice(btn) {
            var ref = btn.getAttribute('data-ref');
            var id = btn.getAttribute('data-id');
            var patient = btn.getAttribute('data-patient');
            var age = btn.getAttribute('data-age');
            var gender = btn.getAttribute('data-gender');
            var phone = btn.getAttribute('data-phone');
            var doc = btn.getAttribute('data-doctor');
            var spec = btn.getAttribute('data-spec');
            var date = btn.getAttribute('data-date');
            var amount = btn.getAttribute('data-amount');
            var status = btn.getAttribute('data-status');
            var services = btn.getAttribute('data-services');

            document.getElementById('inv-ref').textContent = ref;
            document.getElementById('inv-date').textContent = date;
            document.getElementById('inv-patient-name').textContent = patient;
            document.getElementById('inv-patient-meta').textContent = 'Age: ' + age + ' · ' + gender + ' · Ph: ' + phone;
            document.getElementById('inv-doctor-name').textContent = doc;
            document.getElementById('inv-spec').textContent = spec + ' Department';
            document.getElementById('inv-services-desc').textContent = services;
            document.getElementById('inv-dept-cell').textContent = spec;
            document.getElementById('inv-table-consult').textContent = amount;
            document.getElementById('inv-subtotal').textContent = amount;
            document.getElementById('inv-total').textContent = amount;

            var stamp = document.getElementById('inv-stamp');
            stamp.textContent = (status === 'Paid') ? 'PAID' : 'PAYMENT PENDING';
            stamp.className = 'inv-stamp ' + ((status === 'Paid') ? 'stamp-paid' : 'stamp-pending');

            if (invPayBtn) {
                if (status === 'Paid') {
                    invPayBtn.style.display = 'none';
                } else {
                    invPayBtn.style.display = 'inline-flex';
                    invPayBtn.onclick = function () {
                        closeInv();
                        openPayModal(id, ref, doc, amount);
                    };
                }
            }
        }

        function openPayModal(id, ref, doc, amount) {
            document.getElementById('pay-bill-id').value = id;
            document.getElementById('pay-ref-text').textContent = ref;
            document.getElementById('pay-amount-text').textContent = amount;
            document.getElementById('pay-btn-amount').textContent = amount;
            payModal.style.display = 'flex';
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.btn-open-invoice');
            if (btn && invModal) {
                e.preventDefault();
                populateInvoice(btn);
                invModal.style.display = 'flex';
            }

            var printBtn = e.target.closest('.btn-print-direct');
            if (printBtn && invModal) {
                e.preventDefault();
                populateInvoice(printBtn);
                invModal.style.display = 'flex';
                setTimeout(function () {
                    window.print();
                }, 200);
            }

            var payBtn = e.target.closest('.btn-open-pay-modal');
            if (payBtn && payModal) {
                e.preventDefault();
                openPayModal(
                    payBtn.getAttribute('data-id'),
                    payBtn.getAttribute('data-ref'),
                    payBtn.getAttribute('data-doctor'),
                    payBtn.getAttribute('data-amount')
                );
            }
        });
    })();
    </script>
</body>
</html>
