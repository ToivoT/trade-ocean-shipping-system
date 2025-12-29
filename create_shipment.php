<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();
requireVerification();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize shipment data
    $sender_name = sanitize($_POST['sender_name'] ?? '');
    $sender_phone = sanitize($_POST['sender_phone'] ?? '');
    $sender_address = sanitize($_POST['sender_address'] ?? '');
    $receiver_name = sanitize($_POST['receiver_name'] ?? '');
    $receiver_phone = sanitize($_POST['receiver_phone'] ?? '');
    $receiver_address = sanitize($_POST['receiver_address'] ?? '');
    $origin_port = sanitize($_POST['origin_port'] ?? '');
    $destination_port = sanitize($_POST['destination_port'] ?? 'Walvis Bay');
    $shipment_type = sanitize($_POST['shipment_type'] ?? 'import');
    $container_number = sanitize($_POST['container_number'] ?? '');
    $vessel_name = sanitize($_POST['vessel_name'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    // Package data
    $package_descriptions = $_POST['package_description'] ?? [];
    $package_quantities = $_POST['package_quantity'] ?? [];
    $package_weights = $_POST['package_weight'] ?? [];
    $package_dimensions = $_POST['package_dimensions'] ?? [];
    $package_values = $_POST['package_value'] ?? [];

    // Validation
    if (empty($sender_name) || empty($receiver_name)) {
        $errors[] = "Sender and receiver names are required";
    }

    if (empty($receiver_address)) {
        $errors[] = "Receiver address is required";
    }

    if (empty($package_descriptions) || count($package_descriptions) === 0) {
        $errors[] = "At least one package is required";
    }

    // Create shipment
    if (empty($errors)) {
        $conn->begin_transaction();

        try {
            // Generate tracking number
            $tracking_number = generateTrackingNumber($conn);
            $userId = $_SESSION['user_id'];

            // Calculate total weight
            $total_weight = array_sum($package_weights);

            // Insert shipment
            $stmt = $conn->prepare("INSERT INTO shipments (tracking_number, user_id, sender_name, sender_phone, sender_address,
                                   receiver_name, receiver_phone, receiver_address, origin_port, destination_port,
                                   shipment_type, total_weight, container_number, vessel_name, notes, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Registered')");

            $stmt->bind_param('sisssssssssdsss',
                $tracking_number, $userId, $sender_name, $sender_phone, $sender_address,
                $receiver_name, $receiver_phone, $receiver_address, $origin_port, $destination_port,
                $shipment_type, $total_weight, $container_number, $vessel_name, $notes
            );

            $stmt->execute();
            $shipment_id = $conn->insert_id;

            // Insert packages
            $stmt = $conn->prepare("INSERT INTO packages (shipment_id, description, quantity, weight, dimensions, declared_value)
                                   VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($package_descriptions as $index => $description) {
                if (!empty($description)) {
                    $quantity = intval($package_quantities[$index] ?? 1);
                    $weight = floatval($package_weights[$index] ?? 0);
                    $dimensions = sanitize($package_dimensions[$index] ?? '');
                    $value = floatval($package_values[$index] ?? 0);

                    $stmt->bind_param('isidsd', $shipment_id, $description, $quantity, $weight, $dimensions, $value);
                    $stmt->execute();
                }
            }

            // Add initial history entry
            addShipmentHistory($conn, $shipment_id, 'Registered', 'Shipment registered by customer', $userId);

            $conn->commit();

            header("Location: dashboard.php?success=shipment_created&tracking=" . urlencode($tracking_number));
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Failed to create shipment: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Shipment - Trade Ocean Namibia</title>
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create_shipment.php">Create Shipment</a>
                    </li>
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
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Create New Shipment</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <!-- Shipment Type -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="shipment_type" class="form-label">Shipment Type *</label>
                                    <select class="form-select" id="shipment_type" name="shipment_type" required>
                                        <option value="import" selected>Import</option>
                                        <option value="export">Export</option>
                                        <option value="local">Local</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Sender Information -->
                            <h5 class="mb-3 text-ocean">Sender Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="sender_name" class="form-label">Sender Name *</label>
                                    <input type="text" class="form-control" id="sender_name" name="sender_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="sender_phone" class="form-label">Sender Phone</label>
                                    <input type="tel" class="form-control" id="sender_phone" name="sender_phone">
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="sender_address" class="form-label">Sender Address</label>
                                <textarea class="form-control" id="sender_address" name="sender_address" rows="2"></textarea>
                            </div>

                            <!-- Receiver Information -->
                            <h5 class="mb-3 text-ocean">Receiver Information</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="receiver_name" class="form-label">Receiver Name *</label>
                                    <input type="text" class="form-control" id="receiver_name" name="receiver_name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="receiver_phone" class="form-label">Receiver Phone *</label>
                                    <input type="tel" class="form-control" id="receiver_phone" name="receiver_phone" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="receiver_address" class="form-label">Receiver Address *</label>
                                <textarea class="form-control" id="receiver_address" name="receiver_address" rows="2" required></textarea>
                            </div>

                            <!-- Shipping Details -->
                            <h5 class="mb-3 text-ocean">Shipping Details</h5>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="origin_port" class="form-label">Origin Port</label>
                                    <input type="text" class="form-control" id="origin_port" name="origin_port"
                                           placeholder="e.g., Durban, Rotterdam">
                                </div>
                                <div class="col-md-6">
                                    <label for="destination_port" class="form-label">Destination Port *</label>
                                    <input type="text" class="form-control" id="destination_port" name="destination_port"
                                           value="Walvis Bay" required>
                                </div>
                            </div>
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="container_number" class="form-label">Container Number</label>
                                    <input type="text" class="form-control" id="container_number" name="container_number"
                                           placeholder="e.g., MSCU1234567">
                                </div>
                                <div class="col-md-6">
                                    <label for="vessel_name" class="form-label">Vessel Name</label>
                                    <input type="text" class="form-control" id="vessel_name" name="vessel_name">
                                </div>
                            </div>

                            <!-- Package Information -->
                            <h5 class="mb-3 text-ocean">Package Information</h5>
                            <div id="packages-container">
                                <div class="package-item border rounded p-3 mb-3">
                                    <h6>Package 1</h6>
                                    <div class="row mb-2">
                                        <div class="col-md-12">
                                            <label class="form-label">Description *</label>
                                            <input type="text" class="form-control" name="package_description[]"
                                                   placeholder="e.g., Electronics, Machinery" required>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" class="form-control" name="package_quantity[]" value="1" min="1">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Weight (kg)</label>
                                            <input type="number" class="form-control" name="package_weight[]"
                                                   step="0.01" min="0">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Dimensions (cm)</label>
                                            <input type="text" class="form-control" name="package_dimensions[]"
                                                   placeholder="L x W x H">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Value (NAD)</label>
                                            <input type="number" class="form-control" name="package_value[]"
                                                   step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary mb-4" id="add-package">
                                • Add Another Package
                            </button>

                            <!-- Additional Notes -->
                            <div class="mb-4">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="Any special instructions or additional information"></textarea>
                            </div>

                            <!-- Submit -->
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Create Shipment</button>
                                <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
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
    <script>
        let packageCount = 1;

        document.getElementById('add-package').addEventListener('click', function() {
            packageCount++;
            const container = document.getElementById('packages-container');
            const packageDiv = document.createElement('div');
            packageDiv.className = 'package-item border rounded p-3 mb-3';
            packageDiv.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6>Package ${packageCount}</h6>
                    <button type="button" class="btn btn-sm btn-danger remove-package">Remove</button>
                </div>
                <div class="row mb-2">
                    <div class="col-md-12">
                        <label class="form-label">Description *</label>
                        <input type="text" class="form-control" name="package_description[]"
                               placeholder="e.g., Electronics, Machinery" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="package_quantity[]" value="1" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" class="form-control" name="package_weight[]" step="0.01" min="0">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Dimensions (cm)</label>
                        <input type="text" class="form-control" name="package_dimensions[]" placeholder="L x W x H">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Value (NAD)</label>
                        <input type="number" class="form-control" name="package_value[]" step="0.01" min="0">
                    </div>
                </div>
            `;
            container.appendChild(packageDiv);

            // Add remove event listener
            packageDiv.querySelector('.remove-package').addEventListener('click', function() {
                packageDiv.remove();
                packageCount--;
            });
        });
    </script>
</body>
</html>
