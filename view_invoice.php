<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();

// Get invoice ID
if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$invoiceId = intval($_GET['id']);

// Get invoice/payment details
$payment = getPaymentById($conn, $invoiceId);

if (!$payment) {
    header('Location: dashboard.php?error=invoice_not_found');
    exit;
}

// Check if user has permission to view this invoice
if ($_SESSION['role'] !== 'admin' && $payment['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}

// Get shipment details
$stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
$stmt->bind_param('i', $payment['shipment_id']);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();

// Parse invoice breakdown from payment_method field (temporary storage)
$breakdown = json_decode($payment['payment_method'], true);
if (!$breakdown) {
    $breakdown = [
        'service_fee' => $payment['amount'],
        'customs_duty' => 0,
        'port_charges' => 0,
        'other_fees' => 0,
        'notes' => ''
    ];
}

// Check if payment proof has been uploaded
$stmt = $conn->prepare("SELECT * FROM documents WHERE shipment_id = ? AND document_type = 'Other' AND file_name LIKE '%proof%'");
$stmt->bind_param('i', $payment['shipment_id']);
$stmt->execute();
$paymentProof = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($payment['invoice_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .navbar, .footer, .btn { display: none !important; }
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
        }
        .invoice-header {
            border-bottom: 3px solid #0d6efd;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .bank-details {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Trade Ocean Namibia</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= isAdmin() ? 'admin/index.php' : 'dashboard.php' ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shipment_details.php?id=<?= $shipment['id'] ?>">Shipment Details</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- Action Buttons -->
        <div class="mb-4 no-print">
            <button onclick="window.print()" class="btn btn-primary">Print / Save as PDF</button>
            <a href="shipment_details.php?id=<?= $shipment['id'] ?>" class="btn btn-outline-secondary">Back to Shipment</a>
        </div>

        <!-- Invoice -->
        <div class="invoice-container">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-md-6">
                        <h2 class="text-primary fw-bold">INVOICE</h2>
                        <p class="mb-1"><strong>Trade Ocean Namibia</strong></p>
                        <p class="mb-1">Walvis Bay Port, Namibia</p>
                        <p class="mb-1">Email: billing@tradeocean.na</p>
                        <p class="mb-0">Phone: +264 64 200 000</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h4 class="text-primary"><?= htmlspecialchars($payment['invoice_number']) ?></h4>
                        <p class="mb-1"><strong>Date:</strong> <?= date('d M Y', strtotime($payment['created_at'])) ?></p>
                        <p class="mb-1"><strong>Tracking:</strong> <?= htmlspecialchars($payment['tracking_number']) ?></p>
                        <p class="mb-0">
                            <strong>Status:</strong>
                            <?php if ($payment['status'] === 'Paid'): ?>
                                <span class="badge bg-success">PAID</span>
                            <?php elseif ($payment['status'] === 'Pending'): ?>
                                <span class="badge bg-warning">PENDING</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($payment['status']) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bill To -->
            <div class="mb-4">
                <h6 class="text-muted">BILL TO:</h6>
                <p class="mb-1"><strong><?= htmlspecialchars($payment['customer_name']) ?></strong></p>
                <p class="mb-1"><?= htmlspecialchars($payment['customer_email']) ?></p>
                <?php if ($payment['customer_phone']): ?>
                    <p class="mb-1"><?= htmlspecialchars($payment['customer_phone']) ?></p>
                <?php endif; ?>
                <?php if ($payment['customer_address']): ?>
                    <p class="mb-0"><?= htmlspecialchars($payment['customer_address']) ?></p>
                <?php endif; ?>
            </div>

            <!-- Shipment Info -->
            <div class="mb-4">
                <h6 class="text-muted">SHIPMENT DETAILS:</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>From:</strong> <?= htmlspecialchars($shipment['origin_port'] ?? 'N/A') ?></p>
                        <p class="mb-1"><strong>To:</strong> <?= htmlspecialchars($shipment['destination_port']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Type:</strong> <?= htmlspecialchars(ucfirst($shipment['shipment_type'])) ?></p>
                        <p class="mb-1"><strong>Weight:</strong> <?= htmlspecialchars($shipment['total_weight']) ?> kg</p>
                    </div>
                </div>
            </div>

            <!-- Invoice Items -->
            <div class="mb-4">
                <table class="table">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th class="text-end">Amount (<?= htmlspecialchars($payment['currency']) ?>)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($breakdown['service_fee'] > 0): ?>
                        <tr>
                            <td>Shipping Service Fee</td>
                            <td class="text-end"><?= number_format($breakdown['service_fee'], 2) ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($breakdown['customs_duty'] > 0): ?>
                        <tr>
                            <td>Customs Duty & Taxes</td>
                            <td class="text-end"><?= number_format($breakdown['customs_duty'], 2) ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($breakdown['port_charges'] > 0): ?>
                        <tr>
                            <td>Port Handling Charges</td>
                            <td class="text-end"><?= number_format($breakdown['port_charges'], 2) ?></td>
                        </tr>
                        <?php endif; ?>

                        <?php if ($breakdown['other_fees'] > 0): ?>
                        <tr>
                            <td>Other Fees (Documentation, Insurance, etc.)</td>
                            <td class="text-end"><?= number_format($breakdown['other_fees'], 2) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th>TOTAL AMOUNT DUE</th>
                            <th class="text-end fs-5 text-primary"><?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if (!empty($breakdown['notes'])): ?>
            <div class="mb-4">
                <h6 class="text-muted">NOTES:</h6>
                <p><?= nl2br(htmlspecialchars($breakdown['notes'])) ?></p>
            </div>
            <?php endif; ?>

            <!-- Payment Instructions -->
            <?php if ($payment['status'] === 'Pending'): ?>
            <div class="bank-details mb-4">
                <h6 class="text-primary mb-3">PAYMENT INSTRUCTIONS</h6>
                <p class="mb-2"><strong>Please make payment to:</strong></p>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Bank:</strong> First National Bank (FNB)</p>
                        <p class="mb-1"><strong>Account Name:</strong> Trade Ocean Namibia (Pty) Ltd</p>
                        <p class="mb-1"><strong>Account Number:</strong> 62345678901</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Branch Code:</strong> 280172</p>
                        <p class="mb-1"><strong>Swift Code:</strong> FIRNNANX</p>
                        <p class="mb-1"><strong>Reference:</strong> <span class="text-danger fw-bold"><?= htmlspecialchars($payment['tracking_number']) ?></span></p>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0">
                    <strong>Important:</strong> Please use your tracking number <strong><?= htmlspecialchars($payment['tracking_number']) ?></strong> as payment reference.
                    After payment, upload your proof of payment on the shipment details page.
                </div>
            </div>
            <?php endif; ?>

            <?php if ($payment['status'] === 'Paid'): ?>
            <div class="alert alert-success">
                <strong>Payment Received!</strong> This invoice has been paid on <?= date('d M Y, H:i', strtotime($payment['paid_at'])) ?>.
                Thank you for your payment.
            </div>
            <?php endif; ?>

            <!-- Footer Note -->
            <div class="mt-5 pt-3 border-top text-center text-muted small">
                <p class="mb-0">Trade Ocean Namibia (Pty) Ltd | Registered in Namibia | VAT No: 123456789</p>
                <p class="mb-0">For queries, contact: billing@tradeocean.na | +264 64 200 000</p>
            </div>
        </div>

        <!-- Upload Proof Section (visible only if pending and customer) -->
        <?php if ($payment['status'] === 'Pending' && $_SESSION['role'] !== 'admin'): ?>
        <div class="mt-4 no-print">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Upload Proof of Payment</h5>
                </div>
                <div class="card-body">
                    <?php if ($paymentProof): ?>
                        <div class="alert alert-info">
                            <strong>Proof of payment uploaded!</strong> Our team will review and confirm your payment shortly.
                            <br><small>Uploaded: <?= date('d M Y, H:i', strtotime($paymentProof['uploaded_at'])) ?></small>
                        </div>
                    <?php else: ?>
                        <p>Once you have made payment, please upload your bank slip or proof of payment here:</p>
                        <form action="upload_payment_proof.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="shipment_id" value="<?= $shipment['id'] ?>">
                            <input type="hidden" name="invoice_id" value="<?= $payment['id'] ?>">

                            <div class="mb-3">
                                <label for="proof_file" class="form-label">Select File (PDF, JPG, PNG)</label>
                                <input type="file" class="form-control" id="proof_file" name="proof_file"
                                       accept=".pdf,.jpg,.jpeg,.png" required>
                                <small class="text-muted">Maximum file size: 5MB</small>
                            </div>

                            <button type="submit" name="upload_proof" class="btn btn-primary">Upload Proof of Payment</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer no-print">
        <div class="container text-center">
            <p>&copy; 2025 Trade Ocean Namibia. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
