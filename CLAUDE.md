# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Trade Ocean Namibia Shipping Web-Based System - A web platform for remote cargo shipment management and tracking. This academic project (Bachelor of Science in Business Information System, IUM Namibia) enables Trade Ocean Namibia clients to register shipments, upload documents, and track cargo remotely, reducing the need for physical visits to Walvis Bay port or Windhoek customs offices.

## Technology Stack

- **Backend**: PHP (procedural style)
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, Bootstrap 5
- **Server**: Apache (XAMPP on Windows)

## Database Setup

The database schema is defined in `shipping_db.sql`. To set up:

```bash
# Using MySQL command line
mysql -u root -p < shipping_db.sql

# Or import via phpMyAdmin
# Navigate to http://localhost/phpmyadmin and import shipping_db.sql
```

The database includes 6 main tables:
- `users` - Customer, admin, and staff accounts (role-based access)
- `shipments` - Main shipment records with tracking numbers (format: TON-2025-XXXXXX)
- `packages` - Items within shipments (one-to-many relationship)
- `documents` - Uploaded files (Bill of Lading, Commercial Invoice, etc.)
- `shipment_history` - Full tracking timeline with status changes
- `payments` - Payment records linked to shipments

## Architecture

### Directory Structure

```
/                           # Public-facing pages
├── index.php               # Landing page
├── login.php               # User authentication
├── register.php            # User registration
├── dashboard.php           # Customer dashboard
├── create_shipment.php     # Shipment creation form
├── shipment_details.php    # Detailed shipment view
├── track.php               # Public tracking page (no login required)
└── logout.php              # Session termination

/admin/                     # Admin-only pages (requires admin role)
├── index.php               # Admin dashboard
├── manage_shipments.php    # Update shipment status, add history
└── manage_users.php        # Verify/approve user registrations

/includes/
├── config.php              # Database connection configuration
└── functions.php           # Shared utility functions

/assets/
├── css/                    # Stylesheets
└── js/                     # JavaScript files

/uploads/                   # User-uploaded documents (ensure write permissions)
```

### Session & Authentication

- Sessions are used throughout the application
- `includes/config.php` contains database connection settings (DSN: `localhost`, DB: `shipping_db`)
- User roles: `customer`, `admin`, `staff`
- `is_verified` flag controls whether users can access the system (admin approval required)
- Passwords are stored using PHP's `password_hash()` with bcrypt

### Shipment Workflow

1. Customer registers and waits for admin verification
2. Verified customer creates shipment with sender/receiver details
3. System generates unique tracking number (TON-YYYY-XXXXXX format)
4. Customer uploads required documents (Bill of Lading, Commercial Invoice, etc.)
5. Admin updates shipment status through lifecycle:
   - Registered → Documents Pending → Documents Submitted → Customs Processing → Cleared → In Transit → At Port → Out for Delivery → Delivered
6. Each status change is logged in `shipment_history` table
7. Public can track shipments via tracking number (no authentication required)

### File Upload Strategy

- Documents are stored in `/uploads/` directory
- File paths are saved in `documents` table
- Document types: Bill of Lading, Commercial Invoice, Packing List, Certificate of Origin, Dangerous Goods Declaration, Insurance Certificate, Import Permit, Other
- Each upload links to `shipment_id` and `uploaded_by` user

## Development Environment

Since this runs on XAMPP:

```bash
# Start Apache and MySQL
# Open XAMPP Control Panel and start both services

# Access the application
# Navigate to: http://localhost/Trade Ocean Namibia Shipping Web-Based System/

# Database management
# phpMyAdmin: http://localhost/phpmyadmin
```

## Key Business Rules

- Tracking numbers follow format: `TON-YYYY-XXXXXX` (Trade Ocean Namibia - Year - Sequential)
- Default destination port is Walvis Bay (Namibia's main port)
- Shipment types: import, export, local
- All measurements: weight in kg, volume in cbm
- Supported currencies: NAD (Namibian Dollar), USD, ZAR (South African Rand)
- Admin must verify new user registrations before they can create shipments
- Shipment history is immutable (audit trail) - never delete history records

## Security Considerations

- Use `password_hash()` and `password_verify()` for all password operations
- Validate and sanitize all user inputs before database queries
- Use prepared statements to prevent SQL injection
- Check user role and `is_verified` status before granting access to protected pages
- Validate file uploads (type, size) before storing in `/uploads/`
- Session management: check session variables on every protected page
