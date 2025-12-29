<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM shipments");
$totalShipments = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM shipments WHERE status IN ('In Transit', 'At Port', 'Out for Delivery')");
$activeShipments = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$totalCustomers = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 0");
$pendingUsers = $stmt->fetch_assoc()['total'];

// Get recent shipments
$recentShipments = $conn->query("SELECT s.*, u.full_name as customer_name
                                 FROM shipments s
                                 JOIN users u ON s.user_id = u.id
                                 ORDER BY s.created_at DESC
                                 LIMIT 10");

// Get shipments needing attention
$needsAttention = $conn->query("SELECT s.*, u.full_name as customer_name
                                FROM shipments s
                                JOIN users u ON s.user_id = u.id
                                WHERE s.status IN ('Registered', 'Documents Pending')
                                ORDER BY s.created_at ASC
                                LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Trade Ocean Namibia</title>
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
                        <a class="nav-link active" href="index.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_shipments.php">Shipments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            Users
                            <?php if ($pendingUsers > 0): ?>
                                <span class="badge bg-danger"><?= $pendingUsers ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../track.php">Track</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <?= htmlspecialchars($_SESSION['full_name']) ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container-fluid">
            <h2>Admin Dashboard</h2>
            <p class="mb-0 text-muted">Manage shipments and users</p>
        </div>
    </div>

    <div class="container-fluid mb-5">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value"><?= $totalShipments ?></div>
                        <div class="stat-label">Total Shipments</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value"><?= $activeShipments ?></div>
                        <div class="stat-label">Active Shipments</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value"><?= $totalCustomers ?></div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body stat-card">
                        <div class="stat-value text-warning"><?= $pendingUsers ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <a href="manage_shipments.php" class="btn btn-primary me-2">Manage Shipments</a>
                        <a href="manage_users.php" class="btn btn-primary me-2">
                            Verify Users
                            <?php if ($pendingUsers > 0): ?>
                                <span class="badge bg-danger"><?= $pendingUsers ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="../track.php" class="btn btn-outline-primary">Track Shipment</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Shipments Needing Attention -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">  Needs Attention</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($needsAttention->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Customer</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($shipment = $needsAttention->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($shipment['tracking_number']) ?></strong></td>
                                                <td><?= htmlspecialchars($shipment['customer_name']) ?></td>
                                                <td>
                                                    <span class="<?= getStatusBadgeClass($shipment['status']) ?>">
                                                        <?= htmlspecialchars($shipment['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="manage_shipments.php?id=<?= $shipment['id'] ?>" class="btn btn-sm btn-primary">Update</a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                All shipments are up to date!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Shipments -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Shipments</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($recentShipments->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tracking</th>
                                            <th>Customer</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($shipment = $recentShipments->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <a href="../shipment_details.php?id=<?= $shipment['id'] ?>">
                                                        <?= htmlspecialchars($shipment['tracking_number']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars($shipment['customer_name']) ?></td>
                                                <td>
                                                    <span class="<?= getStatusBadgeClass($shipment['status']) ?>">
                                                        <?= htmlspecialchars($shipment['status']) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($shipment['created_at'])) ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center text-muted">
                                No shipments yet
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="manage_shipments.php" class="btn btn-sm btn-outline-primary">View All Shipments</a>
                    </div>
                </div>
            </div>
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
