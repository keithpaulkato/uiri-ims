-- ============================================================
--  UIRI INVENTORY MANAGEMENT SYSTEM - DATABASE SCHEMA
--  Uganda Industrial Research Institute
--  Branches: Nakawa (HQ) | Namanve
-- ============================================================

DROP DATABASE IF EXISTS uiri_ims;
CREATE DATABASE uiri_ims CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE uiri_ims;

-- ------------------------------------------------------------
-- BRANCHES
-- ------------------------------------------------------------
CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(200) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    is_headquarters TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO branches (name, location, address, phone, email, is_headquarters) VALUES
('UIRI Nakawa', 'Nakawa, Kampala', 'Plot 2-12, Nakawa Industrial Area, Kampala, Uganda', '+256 414 287 501', 'nakawa@uiri.go.ug', 1),
('UIRI Namanve', 'Namanve, Mukono', 'Namanve Industrial Park, Mukono, Uganda', '+256 414 287 502', 'namanve@uiri.go.ug', 0);

-- ------------------------------------------------------------
-- SECTIONS
-- ------------------------------------------------------------
CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE 
);

INSERT INTO sections (branch_id, name, code, description) VALUES
(1, 'Mechatronics Section', 'MECH', 'Mechanical and automation systems'),
(1, 'Wood Technology Section', 'WOOD', 'Wood processing and design'),
(1, 'CNC Section', 'CNC', 'Computer numerical control division'),
(1, 'Materials and NDT Laboratory', 'MAT', 'Testing and materials analysis'),
(1, 'Welding, Powder Coating and Maintenance', 'WELD', 'Fabrication and maintenance support'),
(2, 'Mechatronics Section', 'MECH', 'Mechanical and automation systems'),
(2, 'Wood Technology Section', 'WOOD', 'Wood processing and design'),
(2, 'CNC Section', 'CNC', 'Computer numerical control division'),
(2, 'Materials and NDT Laboratory', 'MAT', 'Testing and materials analysis'),
(2, 'Welding, Powder Coating and Maintenance', 'WELD', 'Fabrication and maintenance support');

-- ------------------------------------------------------------
-- ROLES
-- ------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
);

-- ------------------------------------------------------------
-- PERMISSIONS
-- ------------------------------------------------------------
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- USERS
-- ------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    section_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    role_id INT NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    profile_photo VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    failed_login_attempts INT DEFAULT 0,
    last_login_attempt DATETIME DEFAULT NULL,
    password_reset_token VARCHAR(255) DEFAULT NULL,
    token_expiry DATETIME DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ------------------------------------------------------------
-- DEPARTMENTS
-- ------------------------------------------------------------
CREATE TABLE departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(50),
    manager_name VARCHAR(150),
    contact_email VARCHAR(150),
    description TEXT,
    section_manager_id INT DEFAULT NULL,
    department_manager_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE CASCADE,
    FOREIGN KEY (section_manager_id) REFERENCES users(id),
    FOREIGN KEY (department_manager_id) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- CATEGORIES
-- ------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL DEFAULT 1,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    UNIQUE KEY ux_categories_branch_name (branch_id, name)
);

-- ------------------------------------------------------------
-- RBAC: role_permissions, user_permissions
-- ------------------------------------------------------------
CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE user_permissions (
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- Seed roles and permissions
-- ------------------------------------------------------------
INSERT INTO roles (name, description) VALUES
('Administrator', 'Full system access across all branches'),
('Campus Manager', 'Manage operations for assigned campus'),
('Store Manager', 'Manage inventory and stock for assigned branch'),
('Section Manager', 'Manage section inventory and requests'),
('Staff', 'View inventory and request items');

-- Add Department Head role
INSERT INTO roles (name, description) VALUES ('Department Head', 'Oversee department operations and approve requests');

-- Seed default permissions
INSERT INTO permissions (name, description) VALUES
('manage_users', 'Create, edit and deactivate users'),
('manage_permissions', 'Create and assign permissions to roles/users'),
('view_dashboard', 'Access dashboard and KPI cards'),
('manage_inventory', 'Add, edit, delete inventory items'),
('manage_stock', 'Perform stock in/out and adjustments'),
('manage_requests', 'Create and manage inventory requests'),
('approve_requests', 'Approve or reject requests'),
('manage_transfers', 'Initiate and approve transfers between campuses'),
('manage_suppliers', 'Add and manage suppliers'),
('view_reports', 'Access reporting and exports'),
('view_audit', 'View audit logs'),
('manage_sections', 'Create and edit sections'),
('manage_departments', 'Create and edit departments'),
('manage_assets', 'Register and manage assets'),
('manage_maintenance', 'Schedule and track maintenance'),
('manage_settings', 'Edit system settings');

-- Map default permissions to roles
-- Administrator -> all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'Administrator';

-- Campus Manager: dashboard, sections, departments, view_reports, manage_transfers
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name IN ('view_dashboard','manage_sections','manage_departments','view_reports','manage_transfers') WHERE r.name = 'Campus Manager';

-- Store Manager: inventory, stock, requests (approve/store), suppliers
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name IN ('manage_inventory','manage_stock','manage_requests','approve_requests','manage_suppliers') WHERE r.name = 'Store Manager';

-- Section Manager: manage requests, view dashboard, manage_sections
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name IN ('manage_requests','view_dashboard','manage_sections') WHERE r.name = 'Section Manager';

-- Department Head: approve requests, manage_departments, view_reports
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name IN ('approve_requests','manage_departments','view_reports') WHERE r.name = 'Department Head';

-- Staff: create requests, view dashboard
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.name IN ('manage_requests','view_dashboard') WHERE r.name = 'Staff';

-- Default admin (password: Admin@1234)
INSERT INTO users (branch_id, role_id, full_name, email, username, password, phone) VALUES
(1, 1, 'System Administrator', 'admin@uiri.go.ug', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+256 700 000001'),
(1, 2, 'John Ssemanda', 'jssemanda@uiri.go.ug', 'jssemanda', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+256 700 000002'),
(2, 2, 'Grace Akello', 'gakello@uiri.go.ug', 'gakello', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+256 700 000003');

INSERT INTO departments (section_id, name, code, manager_name, contact_email) VALUES
(1, 'Automation Unit', 'AUT', 'Eng. M. Kato', 'automation@uiri.go.ug'),
(1, 'Maintenance Unit', 'MNT', 'Mr. S. Okello', 'maintenance@uiri.go.ug'),
(2, 'Furniture Design Unit', 'FDU', 'Ms. J. Nakato', 'design@uiri.go.ug'),
(3, 'CNC Programming', 'CNC-P', 'Mr. L. Mugerwa', 'cnc@uiri.go.ug'),
(4, 'Materials Testing Lab', 'MTL', 'Dr. A. Nsubuga', 'materials@uiri.go.ug');

INSERT INTO categories (name, description) VALUES
('ICT Equipment', 'Computers, printers, networking devices and accessories'),
('Laboratory Equipment', 'Scientific instruments, lab tools and research equipment'),
('Office Supplies', 'Stationery, paper, pens and everyday office consumables'),
('Furniture', 'Desks, chairs, cabinets and office furniture'),
('Machinery', 'Industrial machines, generators and heavy equipment'),
('Vehicles', 'Cars, trucks and transport equipment'),
('Safety Equipment', 'PPE, fire extinguishers and safety gear');

-- ------------------------------------------------------------
-- Login history to record sign-ins and failures
-- ------------------------------------------------------------
CREATE TABLE login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    branch_id INT NULL,
    section_id INT NULL,
    department_id INT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO categories (name, description) VALUES
('ICT Equipment', 'Computers, printers, networking devices and accessories'),
('Laboratory Equipment', 'Scientific instruments, lab tools and research equipment'),
('Office Supplies', 'Stationery, paper, pens and everyday office consumables'),
('Furniture', 'Desks, chairs, cabinets and office furniture'),
('Machinery', 'Industrial machines, generators and heavy equipment'),
('Vehicles', 'Cars, trucks and transport equipment'),
('Safety Equipment', 'PPE, fire extinguishers and safety gear');

-- ------------------------------------------------------------
-- SUPPLIERS
-- ------------------------------------------------------------
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100),
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    tin_number VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO suppliers (company_name, contact_person, email, phone, address, tin_number) VALUES
('CompuTech Uganda Ltd', 'David Mukasa', 'sales@computech.co.ug', '+256 414 123 456', 'Plot 5, Kampala Road, Kampala', '1001234567'),
('Labtech East Africa', 'Sarah Namutebi', 'info@labtech.co.ug', '+256 772 987 654', 'Industrial Area, Kampala', '1009876543'),
('Office World Uganda', 'Peter Ocen', 'orders@officeworld.co.ug', '+256 756 111 222', 'Nakasero, Kampala', '1005556667'),
('National Enterprises Ltd', 'Rose Atim', 'procurement@nel.co.ug', '+256 414 555 999', 'Namanve Industrial Park', '1007778889');

-- ------------------------------------------------------------
-- PROCUREMENT AND MAINTENANCE
-- ------------------------------------------------------------
CREATE TABLE procurement_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(50) UNIQUE,
    branch_id INT NOT NULL,
    requested_by INT NOT NULL,
    supplier_id INT DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    requested_date DATE NOT NULL,
    status VARCHAR(30) DEFAULT 'Pending',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
);

CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_code VARCHAR(50) UNIQUE,
    procurement_request_id INT NOT NULL,
    supplier_id INT NOT NULL,
    branch_id INT NOT NULL,
    created_by INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery DATE DEFAULT NULL,
    status VARCHAR(30) DEFAULT 'Draft',
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (procurement_request_id) REFERENCES procurement_requests(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE goods_received_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grn_code VARCHAR(50) UNIQUE,
    purchase_order_id INT NOT NULL,
    received_by INT NOT NULL,
    received_date DATE NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
);

CREATE TABLE equipment_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_name VARCHAR(200) NOT NULL,
    equipment_type VARCHAR(100) NOT NULL,
    branch_id INT NOT NULL,
    assigned_to INT DEFAULT NULL,
    maintenance_date DATE NOT NULL,
    next_service_date DATE DEFAULT NULL,
    service_cost DECIMAL(15,2) DEFAULT 0.00,
    status VARCHAR(30) DEFAULT 'Scheduled',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- INVENTORY ITEMS
-- ------------------------------------------------------------
CREATE TABLE inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    section_id INT DEFAULT NULL,
    department_id INT DEFAULT NULL,
    category_id INT NOT NULL,
    supplier_id INT,
    item_code VARCHAR(50) NOT NULL UNIQUE,
    asset_code VARCHAR(100) DEFAULT NULL,
    qr_code VARCHAR(100) DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    unit VARCHAR(30) DEFAULT 'piece',
    unit_price DECIMAL(15,2) DEFAULT 0.00,
    current_stock INT DEFAULT 0,
    minimum_stock INT DEFAULT 5,
    asset_type VARCHAR(50) DEFAULT 'Consumable',
    purchase_date DATE DEFAULT NULL,
    warranty_date DATE DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

INSERT INTO inventory_items (branch_id, category_id, supplier_id, item_code, name, description, unit, unit_price, current_stock, minimum_stock, created_by) VALUES
(1, 1, 1, 'NK-ICT-001', 'Dell Latitude Laptop', '14" i5 8GB RAM 256GB SSD', 'piece', 2500000, 12, 3, 1),
(1, 1, 1, 'NK-ICT-002', 'HP LaserJet Printer', 'HP LaserJet Pro M404dn', 'piece', 850000, 6, 2, 1),
(1, 2, 2, 'NK-LAB-001', 'Digital pH Meter', 'Professional lab pH meter', 'piece', 350000, 8, 2, 2),
(1, 3, 3, 'NK-OFF-001', 'A4 Printing Paper', '80gsm Ream of 500 sheets', 'ream', 12000, 150, 20, 2),
(1, 4, 3, 'NK-FRN-001', 'Executive Office Chair', 'Ergonomic adjustable chair', 'piece', 450000, 25, 5, 2),
(2, 1, 1, 'NM-ICT-001', 'Desktop Computer Set', 'Core i5 with monitor, keyboard, mouse', 'set', 1800000, 8, 2, 3),
(2, 2, 2, 'NM-LAB-001', 'Analytical Balance', '0.001g precision digital balance', 'piece', 1200000, 4, 1, 3),
(2, 5, 4, 'NM-MCH-001', 'Industrial Generator', '50KVA Generator', 'piece', 18000000, 2, 1, 3),
(2, 3, 3, 'NM-OFF-001', 'Whiteboard Markers', 'Assorted colour markers box of 12', 'box', 8000, 3, 10, 3),
(1, 7, 3, 'NK-SAF-001', 'Fire Extinguisher', 'CO2 2kg fire extinguisher', 'piece', 180000, 15, 5, 1);

-- ------------------------------------------------------------
-- STOCK TRANSACTIONS
-- ------------------------------------------------------------
CREATE TABLE stock_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    branch_id INT NOT NULL,
    user_id INT NOT NULL,
    transaction_type ENUM('stock_in', 'stock_out', 'transfer_in', 'transfer_out', 'adjustment') NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) DEFAULT 0.00,
    reference_number VARCHAR(100),
    destination_branch_id INT DEFAULT NULL,
    remarks TEXT,
    transaction_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (destination_branch_id) REFERENCES branches(id)
);

INSERT INTO stock_transactions (item_id, branch_id, user_id, transaction_type, quantity, unit_price, reference_number, remarks, transaction_date) VALUES
(1, 1, 1, 'stock_in', 15, 2500000, 'PO-2026-001', 'Initial stock from CompuTech', '2026-01-10'),
(1, 1, 2, 'stock_out', 3, 2500000, 'ISS-2026-001', 'Issued to ICT Dept', '2026-02-14'),
(2, 1, 2, 'stock_in', 8, 850000, 'PO-2026-002', 'Stock received from supplier', '2026-01-15'),
(2, 1, 2, 'stock_out', 2, 850000, 'ISS-2026-002', 'Issued to Admin Office', '2026-03-01'),
(4, 1, 2, 'stock_in', 200, 12000, 'PO-2026-003', 'Quarterly paper purchase', '2026-01-05'),
(4, 1, 2, 'stock_out', 50, 12000, 'ISS-2026-003', 'General office use', '2026-04-20'),
(6, 2, 3, 'stock_in', 10, 1800000, 'PO-2026-004', 'Initial computers for Namanve', '2026-01-20'),
(6, 2, 3, 'stock_out', 2, 1800000, 'ISS-2026-004', 'Issued to Research Lab', '2026-03-15'),
(9, 2, 3, 'stock_in', 20, 8000, 'PO-2026-005', 'Monthly stationery', '2026-05-01'),
(9, 2, 3, 'stock_out', 17, 8000, 'ISS-2026-005', 'Issued to departments', '2026-05-20');

-- ------------------------------------------------------------
-- INVENTORY REQUESTS
-- ------------------------------------------------------------
CREATE TABLE inventory_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    reason TEXT,
    status ENUM('Pending','Approved','Rejected','Issued','Cancelled') DEFAULT 'Pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL,
    processed_by INT DEFAULT NULL,
    processed_at TIMESTAMP NULL,
    remarks TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- ------------------------------------------------------------
-- TRANSFERS
-- ------------------------------------------------------------
CREATE TABLE transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_code VARCHAR(50) NOT NULL UNIQUE,
    from_branch_id INT NOT NULL,
    to_branch_id INT NOT NULL,
    requested_by INT NOT NULL,
    approved_by INT DEFAULT NULL,
    status ENUM('Requested','Approved','Dispatched','Received','Rejected','Cancelled') DEFAULT 'Requested',
    request_date DATE NOT NULL,
    approved_date DATE DEFAULT NULL,
    dispatched_date DATE DEFAULT NULL,
    received_date DATE DEFAULT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id) REFERENCES branches(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

CREATE TABLE transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    remarks TEXT,
    FOREIGN KEY (transfer_id) REFERENCES transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
);

-- ------------------------------------------------------------
-- NOTIFICATIONS
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    branch_id INT DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
);

-- ------------------------------------------------------------
-- SETTINGS
-- ------------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

INSERT INTO settings (setting_key, setting_value, updated_by) VALUES
('site_name', 'UIRI Inventory Management System', 1),
('currency_code', 'UGX', 1),
('low_stock_threshold', '5', 1),
('allow_self_registration', '1', 1);

-- ------------------------------------------------------------
-- AUDIT TRAIL
-- ------------------------------------------------------------
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    branch_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
);

-- ------------------------------------------------------------
-- REPORTS (Saved report metadata)
-- ------------------------------------------------------------
CREATE TABLE reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL,
    title VARCHAR(150),
    generated_by INT,
    branch_id INT,
    category_id INT,
    date_from DATE,
    date_to DATE,
    file_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- ============================================================
-- INDEXES ON FREQUENTLY QUERIED COLUMNS
-- ============================================================
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_branch ON users(branch_id);
CREATE INDEX idx_users_role ON users(role_id);
CREATE INDEX idx_inventory_items_branch ON inventory_items(branch_id);
CREATE INDEX idx_inventory_items_category ON inventory_items(category_id);
CREATE INDEX idx_inventory_items_supplier ON inventory_items(supplier_id);
CREATE INDEX idx_inventory_items_code ON inventory_items(item_code);
CREATE INDEX idx_stock_transactions_item ON stock_transactions(item_id);
CREATE INDEX idx_stock_transactions_branch ON stock_transactions(branch_id);
CREATE INDEX idx_stock_transactions_date ON stock_transactions(transaction_date);
CREATE INDEX idx_inventory_requests_user ON inventory_requests(user_id);
CREATE INDEX idx_inventory_requests_status ON inventory_requests(status);
CREATE INDEX idx_inventory_requests_branch ON inventory_requests(branch_id);
CREATE INDEX idx_transfers_from_branch ON transfers(from_branch_id);
CREATE INDEX idx_transfers_to_branch ON transfers(to_branch_id);
CREATE INDEX idx_transfers_status ON transfers(status);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_notifications_branch ON notifications(branch_id);
CREATE INDEX idx_notifications_type ON notifications(type);
CREATE INDEX idx_audit_log_user ON audit_log(user_id);
CREATE INDEX idx_audit_log_action ON audit_log(action);
CREATE INDEX idx_audit_log_created ON audit_log(created_at);
