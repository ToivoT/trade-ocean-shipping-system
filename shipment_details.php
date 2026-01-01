<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();

$shipmentId = intval($_GET['id'] ?? 0);

// Get shipment details
$stmt = $conn->prepare("SELECT s.*, u.full_name as customer_name, u.email as customer_email
                       FROM shipments s
                       JOIN users u ON s.user_id = u.id
                       WHERE s.id = ?");
$stmt->bind_param('i', $shipmentId);
$stmt->execute();
$shipment = $stmt->get_result()->fetch_assoc();

if (!$shipment) {
    header('Location: dashboard.php');
    exit;
}

// Check ownership (customers can only view their own shipments, admins can view all)
if (!isAdmin() && $shipment['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php?error=unauthorized');
    exit;
}

// Get shipment history
$history = getShipmentHistory($conn, $shipmentId);

// Get packages
$packages = getShipmentPackages($conn, $shipmentId);

// Get documents
$documents = getShipmentDocuments($conn, $shipmentId);

// Get payment/invoice for this shipment
$payment = getPaymentByShipment($conn, $shipmentId);

// Initialize success/error messages
$uploadSuccess = '';
$uploadError = '';

// Handle success/error messages from redirects
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'proof_uploaded') {
        $uploadSuccess = 'Proof of payment uploaded successfully! Our team will review it shortly.';
    }
}

if (isset($_GET['error']) && !empty($_GET['error'])) {
    $uploadError = htmlspecialchars($_GET['error']);
}

// Handle document upload
$docUploadError = '';
$docUploadSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $documentType = sanitize($_POST['document_type'] ?? '');

    if (empty($documentType)) {
        $docUploadError = "Please select a document type";
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        $docUploadError = "Please select a file to upload";
    } else {
        $result = uploadDocument($conn, $shipmentId, $_FILES['document'], $documentType, $_SESSION['user_id']);

        if ($result['success']) {
            $docUploadSuccess = "Document uploaded successfully!";
            // Refresh documents
            $documents = getShipmentDocuments($conn, $shipmentId);

            // Auto-update status to "Documents Submitted" if currently "Documents Pending"
            if ($shipment['status'] === 'Documents Pending') {
                updateShipmentStatus($conn, $shipmentId, 'Documents Submitted', 'Documents uploaded by customer', $_SESSION['user_id']);
                $shipment['status'] = 'Documents Submitted'; // Update local variable
            }
        } else {
            $docUploadError = implode(', ', $result['errors']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipment Details - <?= htmlspecialchars($shipment['tracking_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Trade Ocean Namibia</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= isAdmin() ? 'admin/index.php' : 'dashboard.php' ?>">Dashboard</a>
                    </li>
                    <?php if (!isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="create_shipment.php">Create Shipment</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="track.php">Track</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <!-- Success/Error Messages -->
        <?php if ($uploadSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $uploadSuccess ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($uploadError): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $uploadError ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>Shipment Details</h2>
                        <p class="text-muted mb-0">Tracking Number: <strong><?= htmlspecialchars($shipment['tracking_number']) ?></strong></p>
                    </div>
                    <div>
                        <span class="<?= getStatusBadgeClass($shipment['status']) ?> fs-5">
                            <?= htmlspecialchars($shipment['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Shipment Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Shipment Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Type:</strong><br>
                                <?= ucfirst(htmlspecialchars($shipment['shipment_type'])) ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Total Weight:</strong><br>
                                <?= htmlspecialchars($shipment['total_weight']) ?> kg
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Origin Port:</strong><br>
                                <?= htmlspecialchars($shipment['origin_port'] ?? 'Not specified') ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Destination Port:</strong><br>
                                <?= htmlspecialchars($shipment['destination_port']) ?>
                            </div>
                            <?php if ($shipment['container_number']): ?>
                            <div class="col-md-6 mb-3">
                                <strong>Container Number:</strong><br>
                                <?= htmlspecialchars($shipment['container_number']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($shipment['vessel_name']): ?>
                            <div class="col-md-6 mb-3">
                                <strong>Vessel Name:</strong><br>
                                <?= htmlspecialchars($shipment['vessel_name']) ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($shipment['current_location']): ?>
                            <div class="col-md-12 mb-3">
                                <strong>Current Location:</strong><br>
                                <?= htmlspecialchars($shipment['current_location']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sender & Receiver Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Sender & Receiver</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-ocean">Sender</h6>
                                <p>
                                    <strong><?= htmlspecialchars($shipment['sender_name']) ?></strong><br>
                                    <?php if ($shipment['sender_phone']): ?>
                                        <?= htmlspecialchars($shipment['sender_phone']) ?><br>
                                    <?php endif; ?>
                                    <?php if ($shipment['sender_address']): ?>
                                        <?= nl2br(htmlspecialchars($shipment['sender_address'])) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-ocean">Receiver</h6>
                                <p>
                                    <strong><?= htmlspecialchars($shipment['receiver_name']) ?></strong><br>
                                    <?php if ($shipment['receiver_phone']): ?>
                                        <?= htmlspecialchars($shipment['receiver_phone']) ?><br>
                                    <?php endif; ?>
                                    <?= nl2br(htmlspecialchars($shipment['receiver_address'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Packages -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Packages (<?= $packages->num_rows ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($packages->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Qty</th>
                                            <th>Weight</th>
                                            <th>Dimensions</th>
                                            <th>Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($package = $packages->fetch_assoc()): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($package['description']) ?></td>
                                                <td><?= $package['quantity'] ?></td>
                                                <td><?= $package['weight'] ?> kg</td>
                                                <td><?= htmlspecialchars($package['dimensions'] ?? 'N/A') ?></td>
                                                <td>NAD <?= number_format($package['declared_value'], 2) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No packages recorded</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tracking History -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Tracking History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($history->num_rows > 0): ?>
                            <div class="tracking-timeline">
                                <?php $first = true; ?>
                                <?php while ($entry = $history->fetch_assoc()): ?>
                                    <div class="timeline-item <?= $first ? 'active' : '' ?>">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <h6><?= htmlspecialchars($entry['status']) ?></h6>
                                            <?php if ($entry['notes']): ?>
                                                <p class="mb-1"><?= htmlspecialchars($entry['notes']) ?></p>
                                            <?php endif; ?>
                                            <small class="timeline-date">
                                                <?= formatDate($entry['changed_at']) ?> " By <?= htmlspecialchars($entry['changed_by_name']) ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php $first = false; ?>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No history available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Documents -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Documents</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($docUploadSuccess): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($docUploadSuccess) ?></div>
                        <?php endif; ?>
                        <?php if ($docUploadError): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($docUploadError) ?></div>
                        <?php endif; ?>

                        <!-- Upload Form -->
                        <?php if (!isAdmin() && $shipment['user_id'] == $_SESSION['user_id']): ?>
                            <form method="POST" enctype="multipart/form-data" class="mb-3">
                                <div class="mb-2">
                                    <select class="form-select form-select-sm" name="document_type" required>
                                        <option value="">Select Type</option>
                                        <option value="Bill of Lading">Bill of Lading</option>
                                        <option value="Commercial Invoice">Commercial Invoice</option>
                                        <option value="Packing List">Packing List</option>
                                        <option value="Certificate of Origin">Certificate of Origin</option>
                                        <option value="Dangerous Goods Declaration">Dangerous Goods Declaration</option>
                                        <option value="Insurance Certificate">Insurance Certificate</option>
                                        <option value="Import Permit">Import Permit</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <input type="file" class="form-control form-control-sm" name="document" required>
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary w-100">Upload</button>
                            </form>
                            <hr>
                        <?php endif; ?>

                        <!-- Documents List -->
                        <?php if ($documents->num_rows > 0): ?>
                            <?php while ($doc = $documents->fetch_assoc()): ?>
                                <div class="document-item">
                                    <div>
                                        <strong><?= htmlspecialchars($doc['document_type']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($doc['file_name']) ?><br>
                                            <?= formatDate($doc['uploaded_at']) ?>
                                        </small>
                                    </div>
                                    <a href="<?= UPLOAD_URL . $doc['file_path'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">View</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted mb-0">No documents uploaded yet</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Invoice & Payment -->
                <?php if ($payment): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-<?= $payment['status'] === 'Paid' ? 'success' : 'warning' ?> text-white">
                            <h5 class="mb-0">Invoice & Payment</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2">
                                <strong>Invoice Number:</strong><br>
                                <?= htmlspecialchars($payment['invoice_number']) ?>
                            </p>
                            <p class="mb-2">
                                <strong>Amount:</strong><br>
                                <span class="fs-5 fw-bold text-primary">
                                    <?= number_format($payment['amount'], 2) ?> <?= htmlspecialchars($payment['currency']) ?>
                                </span>
                            </p>
                            <p class="mb-3">
                                <strong>Status:</strong><br>
                                <?php if ($payment['status'] === 'Paid'): ?>
                                    <span class="badge bg-success">PAID</span>
                                    <br><small class="text-muted">Paid on <?= date('d M Y', strtotime($payment['paid_at'])) ?></small>
                                <?php elseif ($payment['status'] === 'Pending'): ?>
                                    <span class="badge bg-warning">PENDING PAYMENT</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($payment['status']) ?></span>
                                <?php endif; ?>
                            </p>

                            <a href="view_invoice.php?id=<?= $payment['id'] ?>" class="btn btn-primary btn-sm w-100 mb-2">
                                View Invoice
                            </a>

                            <?php if (isAdmin() && $payment['status'] === 'Pending'): ?>
                                <a href="admin/mark_payment_paid.php?payment_id=<?= $payment['id'] ?>" class="btn btn-success btn-sm w-100">
                                    Mark as Paid
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif ($shipment['status'] === 'Documents Submitted' && isAdmin()): ?>
                    <div class="card mb-4 border-primary">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Generate Invoice</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Documents have been submitted. Generate an invoice for this shipment.</p>
                            <a href="admin/generate_invoice.php?shipment_id=<?= $shipment['id'] ?>" class="btn btn-primary w-100">
                                Generate Invoice
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Customer Info (Admin only) -->
                <?php if (isAdmin()): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Customer</h5>
                        </div>
                        <div class="card-body">
                            <p>
                                <strong><?= htmlspecialchars($shipment['customer_name']) ?></strong><br>
                                <?= htmlspecialchars($shipment['customer_email']) ?>
                            </p>
                            <a href="admin/manage_shipments.php?id=<?= $shipment['id'] ?>" class="btn btn-primary btn-sm w-100">
                                Update Status
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="<?= isAdmin() ? 'admin/index.php' : 'dashboard.php' ?>" class="btn btn-outline-secondary">
                � Back to Dashboard
            </a>
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
