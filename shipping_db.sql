-- Database: shipping_db
-- Description: MySQL schema for the Trade Ocean Namibia Web-Based Shipping System
-- Supports user roles, shipments, packages, payments, document uploads, and full tracking history

CREATE DATABASE IF NOT EXISTS shipping_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shipping_db;

-- 1. Users Table (customers, admins, drivers/staff)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,  -- Store hashed passwords only
    phone VARCHAR(20),
    address TEXT,
    company_name VARCHAR(100),
    role ENUM('customer', 'admin', 'staff') DEFAULT 'customer' NOT NULL,
    is_verified TINYINT(1) DEFAULT 0,     -- Admin must verify/approve registration
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Shipments Table (main shipment record)
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_number VARCHAR(30) UNIQUE NOT NULL,  -- e.g., TON-2025-000123
    user_id INT NOT NULL,
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20),
    sender_address TEXT,
    receiver_name VARCHAR(100) NOT NULL,
    receiver_phone VARCHAR(20),
    receiver_address TEXT,
    origin_port VARCHAR(50),              -- e.g., Durban, Rotterdam
    destination_port VARCHAR(50) DEFAULT 'Walvis Bay',
    shipment_type ENUM('import', 'export', 'local') DEFAULT 'import',
    total_weight DECIMAL(10,2),            -- in kg
    total_volume DECIMAL(10,2),            -- in cbm (optional)
    container_number VARCHAR(20),
    vessel_name VARCHAR(100),
    status ENUM(
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
    ) DEFAULT 'Registered' NOT NULL,
    current_location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Packages Table (details of items inside a shipment - one-to-many)
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity INT DEFAULT 1,
    weight DECIMAL(8,2),                   -- kg
    dimensions VARCHAR(50),                -- e.g., 120x80x100 cm
    declared_value DECIMAL(12,2),          -- for insurance/customs
    hs_code VARCHAR(20),                   -- Harmonized System code (optional)
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
);

-- 4. Documents Table (uploaded files - PDFs, images, etc.)
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    document_type ENUM(
        'Bill of Lading',
        'Commercial Invoice',
        'Packing List',
        'Certificate of Origin',
        'Dangerous Goods Declaration',
        'Insurance Certificate',
        'Import Permit',
        'Other'
    ) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,       -- path on server, e.g., uploads/docs/123/filename.pdf
    uploaded_by INT NOT NULL,              -- user who uploaded
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 5. Shipment History / Tracking Log (full timeline)
CREATE TABLE shipment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status ENUM(
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
    ) NOT NULL,
    notes TEXT,
    changed_by INT NOT NULL,               -- admin/staff who made the change
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
);

-- 6. Payments Table
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT,
    invoice_number VARCHAR(50) UNIQUE,
    amount DECIMAL(12,2) NOT NULL,
    currency ENUM('NAD', 'USD', 'ZAR') DEFAULT 'NAD',
    status ENUM('Pending', 'Paid', 'Failed', 'Refunded') DEFAULT 'Pending',
    payment_method VARCHAR(50),             -- e.g., Bank Transfer, Cash
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL
);

-- Indexes for better performance
CREATE INDEX idx_tracking_number ON shipments(tracking_number);
CREATE INDEX idx_user_id ON shipments(user_id);
CREATE INDEX idx_status ON shipments(status);
CREATE INDEX idx_shipment_id_docs ON documents(shipment_id);
CREATE INDEX idx_shipment_id_history ON shipment_history(shipment_id);

-- Optional: Insert a default admin user (password should be hashed in real use)
-- Example: password = "Admin123!" → use password_hash() in PHP
INSERT INTO users (full_name, email, password_hash, role, is_verified)
VALUES ('System Administrator', 'admin@tradeocean.na', '$2y$10$examplehashedpasswordhere', 'admin', 1);