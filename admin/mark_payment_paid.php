<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$success = '';
$error = '';

// Handle mark as paid action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $paymentId = intval($_POST['payment_id'] ?? 0);

    if ($paymentId > 0) {
        // Get payment details
        $payment = getPaymentById($conn, $paymentId);

        if ($payment && $payment['status'] === 'Pending') {
            // Update payment status
            $stmt = $conn->prepare("UPDATE payments SET status = 'Paid', paid_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $paymentId);

            if ($stmt->execute()) {
                // Update shipment status to Customs Processing
                $shipmentId = $payment['shipment_id'];
                updateShipmentStatus($conn, $shipmentId, 'Customs Processing',
                                   'Payment confirmed by admin. Invoice ' . $payment['invoice_number'] . ' marked as paid.',
                                   $_SESSION['user_id']);

                header('Location: manage_shipments.php?id=' . $shipmentId . '&success=payment_confirmed');
                exit;
            } else {
                $error = "Failed to update payment status";
            }
        } else {
            $error = "Invalid payment or payment already processed";
        }
    }
}

// Get payment ID from URL
if (!isset($_GET['payment_id'])) {
    header('Location: manage_shipments.php');
    exit;
}

$paymentId = intval($_GET['payment_id']);
$payment = getPaymentById($conn, $paymentId);

if (!$payment) {
    header('Location: manage_shipments.php?error=payment_not_found');
    exit;
}

// Get shipment details
$stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ?");
$stmt->bind_param('i', $payment['shipment_id']);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();

// Get payment proof documents
$stmt = $conn->prepare("SELECT * FROM documents
                       WHERE shipment_id = ?
                       AND document_type = 'Other'
                       AND file_name LIKE '%proof%'
                       ORDER BY uploaded_at DESC");
$stmt->bind_param('i', $payment['shipment_id']);
$stmt->execute();
$proofs = $stmt->get_result();

// Parse invoice breakdown
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Payment - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Trade Ocean Namibia - Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_shipments.php">Shipments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <h2>Confirm Payment</h2>
            <p class="mb-0 text-muted">Review proof of payment and mark as paid</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Success/Error Messages -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Payment Details -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Invoice Details</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Invoice Number:</strong> <?= htmlspecialchars($payment['invoice_number']) ?></p>
                        <p><strong>Tracking Number:</strong> <?= htmlspecialchars($payment['tracking_number']) ?></p>
                        <p><strong>Customer:</strong> <?= htmlspecialchars($payment['customer_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($payment['customer_email']) ?></p>
                        <p><strong>Invoice Date:</strong> <?= date('d M Y', strtotime($payment['created_at'])) ?></p>
                        <p class="mb-0">
                            <strong>Payment Status:</strong>
                            <?php if ($payment['status'] === 'Paid'): ?>
                                <span class="badge bg-success">PAID</span>
                                <br><small class="text-muted">Paid on: <?= date('d M Y, H:i', strtotime($payment['paid_at'])) ?></small>
                            <?php else: ?>
                                <span class="badge bg-warning">PENDING</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Invoice Breakdown</h6>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <?php if ($breakdown['service_fee'] > 0): ?>
                                <tr>
                                    <td>Service Fee</td>
                                    <td class="text-end"><?= number_format($breakdown['service_fee'], 2) ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($breakdown['customs_duty'] > 0): ?>
                                <tr>
                                    <td>Customs Duty</td>
                                    <td class="text-end"><?= number_format($breakdown['customs_duty'], 2) ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($breakdown['port_charges'] > 0): ?>
                                <tr>
                                    <td>Port Charges</td>
                                    <td class="text-end"><?= number_format($breakdown['port_charges'], 2) ?></td>
                                </tr>
                                <?php endif; ?>

                                <?php if ($breakdown['other_fees'] > 0): ?>
                                <tr>
                                    <td>Other Fees</td>
                                    <td class="text-end"><?= number_format($breakdown['other_fees'], 2) ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>TOTAL</th>
                                    <th class="text-end"><?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="../view_invoice.php?id=<?= $payment['id'] ?>" class="btn btn-outline-primary" target="_blank">
                        View Full Invoice
                    </a>
                    <a href="manage_shipments.php?id=<?= $shipment['id'] ?>" class="btn btn-outline-secondary">
                        View Shipment
                    </a>
                </div>
            </div>

            <!-- Payment Proof -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Proof of Payment</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($proofs->num_rows > 0): ?>
                            <?php while ($proof = $proofs->fetch_assoc()): ?>
                                <div class="border rounded p-3 mb-3">
                                    <p class="mb-2">
                                        <strong>File:</strong> <?= htmlspecialchars($proof['file_name']) ?>
                                    </p>
                                    <p class="mb-2">
                                        <strong>Uploaded:</strong> <?= date('d M Y, H:i', strtotime($proof['uploaded_at'])) ?>
                                    </p>

                                    <?php
                                    $ext = strtolower(pathinfo($proof['file_path'], PATHINFO_EXTENSION));
                                    $fullPath = '../' . $proof['file_path'];
                                    ?>

                                    <?php if (in_array($ext, ['jpg', 'jpeg', 'png'])): ?>
                                        <div class="mb-2">
                                            <img src="<?= htmlspecialchars($fullPath) ?>"
                                                 alt="Payment Proof"
                                                 class="img-fluid rounded border"
                                                 style="max-height: 400px;">
                                        </div>
                                    <?php endif; ?>

                                    <a href="<?= htmlspecialchars($fullPath) ?>"
                                       class="btn btn-sm btn-primary"
                                       target="_blank">
                                        <?= $ext === 'pdf' ? 'View PDF' : 'Download' ?>
                                    </a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <strong>No proof of payment uploaded yet.</strong>
                                <p class="mb-0">The customer has not uploaded proof of payment. You can still manually mark this as paid if payment was received through other means.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Mark as Paid Form -->
                <?php if ($payment['status'] === 'Pending'): ?>
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Confirm Payment</h5>
                    </div>
                    <div class="card-body">
                        <p>Once you confirm that payment has been received:</p>
                        <ul>
                            <li>Payment status will change to <strong>PAID</strong></li>
                            <li>Shipment status will update to <strong>Customs Processing</strong></li>
                            <li>Customer will be notified</li>
                        </ul>

                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to mark this payment as PAID?')">
                            <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                            <button type="submit" name="mark_paid" class="btn btn-success btn-lg w-100">
                                Mark as PAID
                            </button>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-success">
                    <strong>Payment Already Confirmed!</strong><br>
                    This payment was marked as paid on <?= date('d M Y, H:i', strtotime($payment['paid_at'])) ?>.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="manage_shipments.php" class="btn btn-outline-secondary">Back to Shipments</a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-center">
            <p>&copy; 2025 Trade Ocean Namibia. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
