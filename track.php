<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$shipment = null;
$history = null;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['tracking'])) {
    $trackingNumber = sanitize($_GET['tracking']);

    if (!empty($trackingNumber)) {
        $shipment = getShipmentByTracking($conn, $trackingNumber);

        if ($shipment) {
            $history = getShipmentHistory($conn, $shipment['id']);
        } else {
            $error = "Shipment not found. Please check your tracking number.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Shipment - Trade Ocean Namibia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Trade Ocean Namibia</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="track.php">Track Shipment</a>
                    </li>
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= isAdmin() ? 'admin/index.php' : 'dashboard.php' ?>">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Logout</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Track Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="text-center mb-4">
                        <h2 class="text-ocean">Track Your Shipment</h2>
                        <p class="text-muted">Enter your tracking number to view shipment status</p>
                    </div>

                    <!-- Tracking Form -->
                    <div class="card shadow">
                        <div class="card-body p-4">
                            <form method="GET" action="">
                                <div class="input-group input-group-lg">
                                    <input type="text" class="form-control" name="tracking"
                                           placeholder="Enter Tracking Number (e.g., TON-2025-000001)"
                                           value="<?= htmlspecialchars($_GET['tracking'] ?? '') ?>" required>
                                    <button class="btn btn-primary" type="submit">Track</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger mt-4">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tracking Results -->
                    <?php if ($shipment): ?>
                        <div class="mt-4">
                            <!-- Status Overview -->
                            <div class="card shadow mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <h5 class="mb-1">Tracking: <?= htmlspecialchars($shipment['tracking_number']) ?></h5>
                                            <p class="text-muted mb-0">Created: <?= formatDate($shipment['created_at']) ?></p>
                                        </div>
                                        <span class="<?= getStatusBadgeClass($shipment['status']) ?> fs-6">
                                            <?= htmlspecialchars($shipment['status']) ?>
                                        </span>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-6 mb-3">
                                            <strong>Origin:</strong><br>
                                            <?= htmlspecialchars($shipment['origin_port'] ?? 'Not specified') ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Destination:</strong><br>
                                            <?= htmlspecialchars($shipment['destination_port']) ?>
                                        </div>
                                        <?php if ($shipment['current_location']): ?>
                                            <div class="col-md-12 mb-3">
                                                <strong>Current Location:</strong><br>
                                                <span class="text-primary fs-5">=Í <?= htmlspecialchars($shipment['current_location']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="col-md-6 mb-3">
                                            <strong>Shipment Type:</strong><br>
                                            <?= ucfirst(htmlspecialchars($shipment['shipment_type'])) ?>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <strong>Total Weight:</strong><br>
                                            <?= htmlspecialchars($shipment['total_weight']) ?> kg
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="text-ocean mb-2">Receiver</h6>
                                            <p class="mb-0">
                                                <strong><?= htmlspecialchars($shipment['receiver_name']) ?></strong><br>
                                                <?= nl2br(htmlspecialchars($shipment['receiver_address'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Tracking Timeline -->
                            <div class="card shadow">
                                <div class="card-header bg-ocean">
                                    <h5 class="mb-0">Shipment History</h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($history && $history->num_rows > 0): ?>
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
                                                            <?= formatDate($entry['changed_at']) ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <?php $first = false; ?>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No tracking history available</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Additional Info -->
                            <?php if ($shipment['notes']): ?>
                                <div class="card shadow mt-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Additional Notes</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($shipment['notes'])) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Help Text -->
                            <div class="alert alert-info mt-4">
                                <strong>Need help?</strong> If you have questions about your shipment, please contact us at
                                <a href="mailto:info@tradeocean.na">info@tradeocean.na</a> or call +264 64 123 456.
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Information Card (shown when no search yet) -->
                    <?php if (!$shipment && !$error && empty($_GET['tracking'])): ?>
                        <div class="card mt-4">
                            <div class="card-body">
                                <h5 class="card-title">How to Track Your Shipment</h5>
                                <ol>
                                    <li>Enter your tracking number in the search box above</li>
                                    <li>Click the "Track" button</li>
                                    <li>View real-time status and location of your cargo</li>
                                </ol>
                                <p class="mb-0">
                                    <strong>Note:</strong> Your tracking number was provided when you created the shipment.
                                    It follows the format TON-YYYY-XXXXXX.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>Trade Ocean Namibia</h5>
                    <p>Remote cargo shipment management system for importers and exporters across Namibia.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="track.php">Track Shipment</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <p>Email: info@tradeocean.na<br>
                    Phone: +264 64 123 456<br>
                    Walvis Bay, Namibia</p>
                </div>
            </div>
            <hr class="bg-light">
            <div class="text-center">
                <p>&copy; 2025 Trade Ocean Namibia. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
