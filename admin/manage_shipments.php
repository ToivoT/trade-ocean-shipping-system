<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$success = '';
$error = '';
$selectedShipment = null;

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $shipmentId = intval($_POST['shipment_id'] ?? 0);
    $newStatus = sanitize($_POST['status'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $location = sanitize($_POST['location'] ?? '');

    if ($shipmentId > 0 && !empty($newStatus)) {
        if (updateShipmentStatus($conn, $shipmentId, $newStatus, $notes, $_SESSION['user_id'], $location)) {
            $success = "Shipment status updated successfully!";
        } else {
            $error = "Failed to update shipment status";
        }
    }
}

// Get shipment for editing if ID provided
if (isset($_GET['id'])) {
    $shipmentId = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT s.*, u.full_name as customer_name, u.email as customer_email
                           FROM shipments s
                           JOIN users u ON s.user_id = u.id
                           WHERE s.id = ?");
    $stmt->bind_param('i', $shipmentId);
    $stmt->execute();
    $selectedShipment = $stmt->get_result()->fetch_assoc();
}

// Get all shipments with filters
$filter = $_GET['filter'] ?? 'all';
$search = sanitize($_GET['search'] ?? '');

$query = "SELECT s.*, u.full_name as customer_name, u.email as customer_email,
          p.id as payment_id, p.invoice_number, p.amount, p.currency, p.status as payment_status
          FROM shipments s
          JOIN users u ON s.user_id = u.id
          LEFT JOIN payments p ON s.id = p.shipment_id
          WHERE 1=1";

if ($filter !== 'all') {
    $query .= " AND s.status = '" . $conn->real_escape_string($filter) . "'";
}

if (!empty($search)) {
    $query .= " AND (s.tracking_number LIKE '%" . $conn->real_escape_string($search) . "%'
                OR u.full_name LIKE '%" . $conn->real_escape_string($search) . "%'
                OR s.receiver_name LIKE '%" . $conn->real_escape_string($search) . "%')";
}

$query .= " ORDER BY s.created_at DESC";

$shipments = $conn->query($query);

// Available statuses
$statuses = [
    'Registered',
    'Documents Pending',
    'Documents Submitted',
    'Customs Processing',
    'Cleared',
    'In Transit',
    'At Port',
    'Out for Delivery',
    'Delivered',
    'Exception'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Shipments - Admin</title>
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
                        <a class="nav-link active" href="manage_shipments.php">Shipments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../track.php">Track</a>
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
        <div class="container-fluid">
            <h2>Shipment Management</h2>
            <p class="mb-0 text-muted">Update shipment status and track progress</p>
        </div>
    </div>

    <div class="container-fluid mb-5">
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

        <!-- Update Status Form (if shipment selected) -->
        <?php if ($selectedShipment): ?>
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Update Shipment Status</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Tracking Number:</strong> <?= htmlspecialchars($selectedShipment['tracking_number']) ?><br>
                            <strong>Customer:</strong> <?= htmlspecialchars($selectedShipment['customer_name']) ?><br>
                            <strong>Current Status:</strong>
                            <span class="<?= getStatusBadgeClass($selectedShipment['status']) ?>">
                                <?= htmlspecialchars($selectedShipment['status']) ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Origin:</strong> <?= htmlspecialchars($selectedShipment['origin_port'] ?? 'N/A') ?><br>
                            <strong>Destination:</strong> <?= htmlspecialchars($selectedShipment['destination_port']) ?><br>
                            <strong>Current Location:</strong> <?= htmlspecialchars($selectedShipment['current_location'] ?? 'N/A') ?>
                        </div>
                    </div>

                    <form method="POST" action="">
                        <input type="hidden" name="shipment_id" value="<?= $selectedShipment['id'] ?>">

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="status" class="form-label">New Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status) ?>"
                                                <?= $selectedShipment['status'] === $status ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="location" class="form-label">Current Location</label>
                                <input type="text" class="form-control" id="location" name="location"
                                       value="<?= htmlspecialchars($selectedShipment['current_location'] ?? '') ?>"
                                       placeholder="e.g., Walvis Bay Port, In Transit to Windhoek">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"
                                      placeholder="Add notes about this status update..."></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                            <a href="manage_shipments.php" class="btn btn-outline-secondary">Cancel</a>
                            <a href="../shipment_details.php?id=<?= $selectedShipment['id'] ?>"
                               class="btn btn-outline-primary">View Full Details</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="filter" class="form-label">Filter by Status</label>
                            <select class="form-select" id="filter" name="filter" onchange="this.form.submit()">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Shipments</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?= htmlspecialchars($status) ?>"
                                            <?= $filter === $status ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search"
                                   value="<?= htmlspecialchars($search) ?>"
                                   placeholder="Search by tracking number, customer, or receiver...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Shipments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Shipments (<?= $shipments->num_rows ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($shipments->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tracking Number</th>
                                    <th>Customer</th>
                                    <th>Origin � Destination</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($shipment = $shipments->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($shipment['tracking_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($shipment['customer_name']) ?></td>
                                        <td>
                                            <?= htmlspecialchars($shipment['origin_port'] ?? 'N/A') ?> �
                                            <?= htmlspecialchars($shipment['destination_port']) ?>
                                        </td>
                                        <td>
                                            <span class="<?= getStatusBadgeClass($shipment['status']) ?>">
                                                <?= htmlspecialchars($shipment['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($shipment['payment_id']): ?>
                                                <?php if ($shipment['payment_status'] === 'Paid'): ?>
                                                    <span class="badge bg-success">Paid</span><br>
                                                    <small class="text-muted"><?= number_format($shipment['amount'], 0) ?> <?= $shipment['currency'] ?></small>
                                                <?php elseif ($shipment['payment_status'] === 'Pending'): ?>
                                                    <span class="badge bg-warning">Pending</span><br>
                                                    <small class="text-muted"><?= number_format($shipment['amount'], 0) ?> <?= $shipment['currency'] ?></small>
                                                <?php endif; ?>
                                            <?php elseif ($shipment['status'] === 'Documents Submitted'): ?>
                                                <span class="text-muted">No invoice</span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($shipment['created_at'])) ?></td>
                                        <td>
                                            <?php if ($shipment['payment_id'] && $shipment['payment_status'] === 'Pending'): ?>
                                                <a href="mark_payment_paid.php?payment_id=<?= $shipment['payment_id'] ?>"
                                                   class="btn btn-sm btn-success mb-1" title="Confirm Payment">
                                                    Confirm Pay
                                                </a>
                                            <?php elseif ($shipment['status'] === 'Documents Submitted' && !$shipment['payment_id']): ?>
                                                <a href="generate_invoice.php?shipment_id=<?= $shipment['id'] ?>"
                                                   class="btn btn-sm btn-info mb-1" title="Generate Invoice">
                                                    Gen Invoice
                                                </a>
                                            <?php endif; ?>
                                            <a href="manage_shipments.php?id=<?= $shipment['id'] ?>"
                                               class="btn btn-sm btn-primary mb-1">Update</a>
                                            <a href="../shipment_details.php?id=<?= $shipment['id'] ?>"
                                               class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        No shipments found
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-outline-secondary">� Back to Dashboard</a>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container-fluid text-center">
            <p>&copy; 2025 Trade Ocean Namibia. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
