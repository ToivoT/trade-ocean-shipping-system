<?php
// Utility functions for Trade Ocean Namibia Shipping System

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Check if user is verified
function isVerified() {
    return isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1;
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

// Redirect to dashboard if not verified
function requireVerification() {
    if (!isVerified()) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=not_verified');
        exit;
    }
}

// Redirect to dashboard if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=unauthorized');
        exit;
    }
}

// Sanitize input data
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Generate unique tracking number (TON-YYYY-XXXXXX)
function generateTrackingNumber($conn) {
    $year = date('Y');
    $prefix = "TON-$year-";

    // Get the last tracking number for this year
    $query = "SELECT tracking_number FROM shipments
              WHERE tracking_number LIKE ?
              ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $search = $prefix . '%';
    $stmt->bind_param('s', $search);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $lastNumber = intval(substr($row['tracking_number'], -6));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
}

// Format date for display
function formatDate($date) {
    return date('d M Y, H:i', strtotime($date));
}

// Get user by ID
function getUserById($conn, $userId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get shipment by tracking number
function getShipmentByTracking($conn, $trackingNumber) {
    $stmt = $conn->prepare("SELECT s.*, u.full_name as customer_name, u.email as customer_email
                           FROM shipments s
                           JOIN users u ON s.user_id = u.id
                           WHERE s.tracking_number = ?");
    $stmt->bind_param('s', $trackingNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get shipment history
function getShipmentHistory($conn, $shipmentId) {
    $stmt = $conn->prepare("SELECT h.*, u.full_name as changed_by_name
                           FROM shipment_history h
                           JOIN users u ON h.changed_by = u.id
                           WHERE h.shipment_id = ?
                           ORDER BY h.changed_at DESC");
    $stmt->bind_param('i', $shipmentId);
    $stmt->execute();
    return $stmt->get_result();
}

// Get documents for a shipment
function getShipmentDocuments($conn, $shipmentId) {
    $stmt = $conn->prepare("SELECT d.*, u.full_name as uploader_name
                           FROM documents d
                           JOIN users u ON d.uploaded_by = u.id
                           WHERE d.shipment_id = ?
                           ORDER BY d.uploaded_at DESC");
    $stmt->bind_param('i', $shipmentId);
    $stmt->execute();
    return $stmt->get_result();
}

// Get packages for a shipment
function getShipmentPackages($conn, $shipmentId) {
    $stmt = $conn->prepare("SELECT * FROM packages WHERE shipment_id = ?");
    $stmt->bind_param('i', $shipmentId);
    $stmt->execute();
    return $stmt->get_result();
}

// Add shipment history entry
function addShipmentHistory($conn, $shipmentId, $status, $notes, $userId) {
    $stmt = $conn->prepare("INSERT INTO shipment_history (shipment_id, status, notes, changed_by)
                           VALUES (?, ?, ?, ?)");
    $stmt->bind_param('issi', $shipmentId, $status, $notes, $userId);
    return $stmt->execute();
}

// Update shipment status
function updateShipmentStatus($conn, $shipmentId, $status, $notes, $userId, $location = null) {
    // Update shipment table
    if ($location) {
        $stmt = $conn->prepare("UPDATE shipments SET status = ?, current_location = ? WHERE id = ?");
        $stmt->bind_param('ssi', $status, $location, $shipmentId);
    } else {
        $stmt = $conn->prepare("UPDATE shipments SET status = ? WHERE id = ?");
        $stmt->bind_param('si', $status, $shipmentId);
    }

    if ($stmt->execute()) {
        // Add to history
        return addShipmentHistory($conn, $shipmentId, $status, $notes, $userId);
    }
    return false;
}

// Validate file upload
function validateUpload($file, $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'], $maxSize = 5242880) {
    $errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error occurred";
        return $errors;
    }

    if ($file['size'] > $maxSize) {
        $errors[] = "File size exceeds " . ($maxSize / 1024 / 1024) . "MB limit";
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        $errors[] = "File type not allowed. Allowed: " . implode(', ', $allowedTypes);
    }

    return $errors;
}

// Upload document
function uploadDocument($conn, $shipmentId, $file, $documentType, $userId) {
    $errors = validateUpload($file);

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Create upload directory if it doesn't exist
    $uploadDir = UPLOAD_DIR . $shipmentId . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Generate unique filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Save to database
        $dbPath = 'uploads/' . $shipmentId . '/' . $filename;
        $stmt = $conn->prepare("INSERT INTO documents (shipment_id, document_type, file_name, file_path, uploaded_by)
                               VALUES (?, ?, ?, ?, ?)");
        $originalName = $file['name'];
        $stmt->bind_param('isssi', $shipmentId, $documentType, $originalName, $dbPath, $userId);

        if ($stmt->execute()) {
            return ['success' => true, 'file_path' => $dbPath];
        }
    }

    return ['success' => false, 'errors' => ['Failed to upload file']];
}

// Get status badge class for Bootstrap
function getStatusBadgeClass($status) {
    $classes = [
        'Registered' => 'secondary',
        'Documents Pending' => 'warning',
        'Documents Submitted' => 'info',
        'Customs Processing' => 'primary',
        'Cleared' => 'success',
        'In Transit' => 'primary',
        'At Port' => 'info',
        'Out for Delivery' => 'warning',
        'Delivered' => 'success',
        'Exception' => 'danger'
    ];

    return 'badge bg-' . ($classes[$status] ?? 'secondary');
}
?>
