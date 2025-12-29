<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();

// Get user's shipments
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM shipments WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$shipments = $stmt->get_result();

// Get statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM shipments WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalShipments = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM shipments WHERE user_id = ? AND status IN ('In Transit', 'At Port', 'Out for Delivery')");
$stmt->bind_param('i', $userId);
$stmt->execute();
$activeShipments = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM shipments WHERE user_id = ? AND status = 'Delivered'");
$stmt->bind_param('i', $userId);
$stmt->execute();
$deliveredShipments = $stmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Trade Ocean Namibia</title>
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_shipment.php">Create Shipment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="track.php">Track</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <h2>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
            <p class="mb-0 text-muted">Manage your shipments and track cargo</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Verification Notice -->
        <?php if (!isVerified()): ?>
            <div class="verification-notice">
                <h5>  Account Pending Verification</h5>
                <p class="mb-0">Your account is awaiting admin verification. You will be able to create shipments once your account is approved.</p>
            </div>
        <?php endif; ?>

        <!-- Error/Success Messages -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger">
                <?php
                if ($_GET['error'] === 'not_verified') {
                    echo "Your account must be verified before performing this action.";
                } elseif ($_GET['error'] === 'unauthorized') {
                    echo "You don't have permission to access that page.";
                }
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <?php
                if ($_GET['success'] === 'shipment_created') {
                    echo "Shipment created successfully! Tracking Number: " . htmlspecialchars($_GET['tracking'] ?? '');
                }
                ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value"><?= $totalShipments ?></div>
                        <div class="stat-label">Total Shipments</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value"><?= $activeShipments ?></div>
                        <div class="stat-label">Active Shipments</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value"><?= $deliveredShipments ?></div>
                        <div class="stat-label">Delivered</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <?php if (isVerified()): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <a href="create_shipment.php" class="btn btn-primary btn-lg">
                        • Create New Shipment
                    </a>
                    <a href="track.php" class="btn btn-outline-primary btn-lg">
                        =Í Track Shipment
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Shipments List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">My Shipments</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($shipments->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Tracking Number</th>
                                    <th>Origin</th>
                                    <th>Destination</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($shipment = $shipments->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($shipment['tracking_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($shipment['origin_port'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($shipment['destination_port']) ?></td>
                                        <td>
                                            <span class="<?= getStatusBadgeClass($shipment['status']) ?>">
                                                <?= htmlspecialchars($shipment['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= formatDate($shipment['created_at']) ?></td>
                                        <td>
                                            <a href="shipment_details.php?id=<?= $shipment['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <p class="text-muted mb-3">You haven't created any shipments yet.</p>
                        <?php if (isVerified()): ?>
                            <a href="create_shipment.php" class="btn btn-primary">Create Your First Shipment</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
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
