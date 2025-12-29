<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade Ocean Namibia - Shipping Management System</title>
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
                        <a class="nav-link" href="track.php">Track Shipment</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <h1>Remote Cargo Shipment Management</h1>
            <p>Manage your imports and exports from anywhere in Namibia</p>
            <p class="mb-4">No need to travel to Walvis Bay or Windhoek - handle everything online!</p>
            <a href="register.php" class="btn btn-light btn-lg me-2">Get Started</a>
            <a href="track.php" class="btn btn-outline-light btn-lg">Track Shipment</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="row text-center mb-5">
                <div class="col-12">
                    <h2 class="text-ocean">Why Choose Trade Ocean Namibia?</h2>
                    <p class="lead">Streamlined shipping services for remote areas</p>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">=æ</div>
                        <h3>Online Shipment Registration</h3>
                        <p>Register your import/export shipments from anywhere with internet access</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">=Ä</div>
                        <h3>Document Upload</h3>
                        <p>Upload Bills of Lading, Commercial Invoices, and customs documents remotely</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">=Í</div>
                        <h3>Real-Time Tracking</h3>
                        <p>Track your cargo status 24/7 with detailed shipment history</p>
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">=¢</div>
                        <h3>Walvis Bay Port Access</h3>
                        <p>Seamless integration with Namibia's main port operations</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">=</div>
                        <h3>Secure Platform</h3>
                        <p>Your shipment data and documents are protected with enterprise security</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">=ñ</div>
                        <h3>Mobile Friendly</h3>
                        <p>Access the system from your phone, tablet, or computer</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="bg-light py-5">
        <div class="container">
            <h2 class="text-center text-ocean mb-5">How It Works</h2>
            <div class="row">
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-ocean mb-3">1</div>
                    <h4>Register</h4>
                    <p>Create your free account and wait for verification</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-ocean mb-3">2</div>
                    <h4>Create Shipment</h4>
                    <p>Enter shipment details and get your tracking number</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-ocean mb-3">3</div>
                    <h4>Upload Documents</h4>
                    <p>Submit all required customs and shipping documents</p>
                </div>
                <div class="col-md-3 text-center mb-4">
                    <div class="display-4 text-ocean mb-3">4</div>
                    <h4>Track & Receive</h4>
                    <p>Monitor your shipment until delivery</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5">
        <div class="container text-center">
            <h2 class="text-ocean mb-4">Ready to Get Started?</h2>
            <p class="lead mb-4">Join hundreds of Namibian businesses managing their shipments remotely</p>
            <a href="register.php" class="btn btn-primary btn-lg">Register Now</a>
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
                <p class="mb-0"><small>Developed by Petrus Nghilukilwa - IUM Namibia</small></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
