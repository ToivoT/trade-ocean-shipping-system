<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

requireLogin();
requireVerification();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_proof'])) {
    $shipmentId = intval($_POST['shipment_id'] ?? 0);
    $invoiceId = intval($_POST['invoice_id'] ?? 0);

    // Verify the shipment belongs to the user
    $stmt = $conn->prepare("SELECT * FROM shipments WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $shipmentId, $_SESSION['user_id']);
    $stmt->execute();
    $shipment = $stmt->get_result()->fetch_assoc();

    if (!$shipment) {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }

    // Verify the invoice exists and is pending
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND shipment_id = ? AND status = 'Pending'");
    $stmt->bind_param('ii', $invoiceId, $shipmentId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();

    if (!$payment) {
        header('Location: shipment_details.php?id=' . $shipmentId . '&error=invalid_invoice');
        exit;
    }

    // Check if file was uploaded
    if (!isset($_FILES['proof_file']) || $_FILES['proof_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Please select a file to upload";
    } else {
        $file = $_FILES['proof_file'];

        // Validate file
        $errors = validateUpload($file, ['pdf', 'jpg', 'jpeg', 'png'], 5242880); // 5MB max

        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Create upload directory for payment proofs
            $uploadDir = UPLOAD_DIR . 'payment_proofs/' . $shipmentId . '/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'proof_' . $payment['invoice_number'] . '_' . time() . '.' . $ext;
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Save to documents table
                $dbPath = 'uploads/payment_proofs/' . $shipmentId . '/' . $filename;
                $documentType = 'Other'; // Using 'Other' category for payment proofs
                $originalName = 'Payment Proof - ' . $payment['invoice_number'];

                $stmt = $conn->prepare("INSERT INTO documents (shipment_id, document_type, file_name, file_path, uploaded_by)
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('isssi', $shipmentId, $documentType, $originalName, $dbPath, $_SESSION['user_id']);

                if ($stmt->execute()) {
                    // Add note to shipment history
                    addShipmentHistory($conn, $shipmentId, $shipment['status'],
                                      'Payment proof uploaded by customer for invoice ' . $payment['invoice_number'],
                                      $_SESSION['user_id']);

                    header('Location: shipment_details.php?id=' . $shipmentId . '&success=proof_uploaded');
                    exit;
                } else {
                    $error = "Failed to save upload information";
                }
            } else {
                $error = "Failed to upload file. Please check directory permissions.";
            }
        }
    }

    // If there's an error, redirect back with error message
    if ($error) {
        header('Location: shipment_details.php?id=' . $shipmentId . '&error=' . urlencode($error));
        exit;
    }
} else {
    // Direct access without POST - redirect to dashboard
    header('Location: dashboard.php');
    exit;
}
?>
