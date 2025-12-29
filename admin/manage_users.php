<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$success = '';
$error = '';

// Handle user verification/unverification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId > 0 && in_array($action, ['verify', 'unverify'])) {
        $isVerified = ($action === 'verify') ? 1 : 0;
        $stmt = $conn->prepare("UPDATE users SET is_verified = ? WHERE id = ? AND role != 'admin'");
        $stmt->bind_param('ii', $isVerified, $userId);

        if ($stmt->execute()) {
            $success = $action === 'verify' ? "User verified successfully" : "User unverified successfully";
        } else {
            $error = "Failed to update user";
        }
    }
}

// Get all users
$users = $conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY is_verified ASC, created_at DESC");

// Get statistics
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 0 AND role = 'customer'");
$pendingCount = $stmt->fetch_assoc()['total'];

$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_verified = 1 AND role = 'customer'");
$verifiedCount = $stmt->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
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
                        <a class="nav-link active" href="manage_users.php">Users</a>
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
            <h2>User Management</h2>
            <p class="mb-0 text-muted">Verify and manage customer accounts</p>
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

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value text-warning"><?= $pendingCount ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body stat-card">
                        <div class="stat-value text-success"><?= $verifiedCount ?></div>
                        <div class="stat-label">Verified Users</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Users</h5>
            </div>
            <div class="card-body p-0">
                <?php if ($users->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Company</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($user = $users->fetch_assoc()): ?>
                                    <tr class="<?= $user['is_verified'] == 0 ? 'table-warning' : '' ?>">
                                        <td><?= $user['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><?= htmlspecialchars($user['phone'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($user['company_name'] ?? 'N/A') ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?= ucfirst(htmlspecialchars($user['role'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_verified'] == 1): ?>
                                                <span class="badge bg-success"> Verified</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">† Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <?php if ($user['is_verified'] == 0): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="action" value="verify">
                                                    <button type="submit" class="btn btn-sm btn-success"
                                                            onclick="return confirm('Verify this user?')">
                                                        Verify
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <input type="hidden" name="action" value="unverify">
                                                    <button type="submit" class="btn btn-sm btn-warning"
                                                            onclick="return confirm('Unverify this user?')">
                                                        Unverify
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" data-bs-target="#userModal<?= $user['id'] ?>">
                                                Details
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- User Details Modal -->
                                    <div class="modal fade" id="userModal<?= $user['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">User Details</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Full Name:</strong><br><?= htmlspecialchars($user['full_name']) ?></p>
                                                    <p><strong>Email:</strong><br><?= htmlspecialchars($user['email']) ?></p>
                                                    <p><strong>Phone:</strong><br><?= htmlspecialchars($user['phone'] ?? 'Not provided') ?></p>
                                                    <p><strong>Company:</strong><br><?= htmlspecialchars($user['company_name'] ?? 'Not provided') ?></p>
                                                    <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($user['address'] ?? 'Not provided')) ?></p>
                                                    <p><strong>Role:</strong><br><?= ucfirst(htmlspecialchars($user['role'])) ?></p>
                                                    <p><strong>Verification Status:</strong><br>
                                                        <?= $user['is_verified'] == 1 ? '<span class="badge bg-success">Verified</span>' : '<span class="badge bg-warning">Pending</span>' ?>
                                                    </p>
                                                    <p><strong>Registered:</strong><br><?= formatDate($user['created_at']) ?></p>
                                                    <p><strong>Last Updated:</strong><br><?= formatDate($user['updated_at']) ?></p>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center text-muted">
                        No users found
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-outline-secondary">ê Back to Dashboard</a>
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
