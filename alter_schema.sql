-- ============================================================
--  UIRI IMS - DATABASE SCHEMA ALTERATIONS
--  Apply these changes to the existing database
-- ============================================================

-- Add missing columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0 AFTER is_active;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_attempt DATETIME DEFAULT NULL AFTER last_login;
ALTER TABLE users ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) DEFAULT NULL AFTER last_login_attempt;
ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expiry DATETIME DEFAULT NULL AFTER password_reset_token;
ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) DEFAULT NULL AFTER token_expiry;
ALTER TABLE users ADD COLUMN IF NOT EXISTS section_id INT DEFAULT NULL AFTER branch_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS department_id INT DEFAULT NULL AFTER section_id;

-- Add missing columns to departments table
ALTER TABLE departments ADD COLUMN IF NOT EXISTS section_manager_id INT DEFAULT NULL AFTER description;
ALTER TABLE departments ADD COLUMN IF NOT EXISTS department_manager_id INT DEFAULT NULL AFTER section_manager_id;

-- Add foreign key constraints to departments for managers
ALTER TABLE departments ADD CONSTRAINT IF NOT EXISTS fk_dept_section_mgr FOREIGN KEY (section_manager_id) REFERENCES users(id);
ALTER TABLE departments ADD CONSTRAINT IF NOT EXISTS fk_dept_dept_mgr FOREIGN KEY (department_manager_id) REFERENCES users(id);

-- Add missing columns to inventory_items table
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS asset_code VARCHAR(100) DEFAULT NULL AFTER item_code;
ALTER TABLE inventory_items ADD COLUMN IF NOT EXISTS qr_code VARCHAR(100) DEFAULT NULL AFTER asset_code;

-- Add missing columns to notifications table
ALTER TABLE notifications MODIFY user_id INT DEFAULT NULL;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL AFTER user_id;
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS link VARCHAR(255) DEFAULT NULL AFTER message;

-- Add foreign key for branch_id in notifications
ALTER TABLE notifications ADD CONSTRAINT IF NOT EXISTS fk_notif_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE;

-- Procurement and maintenance tables
CREATE TABLE IF NOT EXISTS procurement_requests (
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

CREATE TABLE IF NOT EXISTS purchase_orders (
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

CREATE TABLE IF NOT EXISTS goods_received_notes (
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

CREATE TABLE IF NOT EXISTS equipment_maintenance (
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

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_branch ON users(branch_id);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role_id);
CREATE INDEX IF NOT EXISTS idx_inventory_items_branch ON inventory_items(branch_id);
CREATE INDEX IF NOT EXISTS idx_inventory_items_category ON inventory_items(category_id);
CREATE INDEX IF NOT EXISTS idx_inventory_items_supplier ON inventory_items(supplier_id);
CREATE INDEX IF NOT EXISTS idx_inventory_items_code ON inventory_items(item_code);
CREATE INDEX IF NOT EXISTS idx_stock_transactions_item ON stock_transactions(item_id);
CREATE INDEX IF NOT EXISTS idx_stock_transactions_branch ON stock_transactions(branch_id);
CREATE INDEX IF NOT EXISTS idx_stock_transactions_date ON stock_transactions(transaction_date);
CREATE INDEX IF NOT EXISTS idx_inventory_requests_user ON inventory_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_inventory_requests_status ON inventory_requests(status);
CREATE INDEX IF NOT EXISTS idx_inventory_requests_branch ON inventory_requests(branch_id);
CREATE INDEX IF NOT EXISTS idx_transfers_from_branch ON transfers(from_branch_id);
CREATE INDEX IF NOT EXISTS idx_transfers_to_branch ON transfers(to_branch_id);
CREATE INDEX IF NOT EXISTS idx_transfers_status ON transfers(status);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_branch ON notifications(branch_id);
CREATE INDEX IF NOT EXISTS idx_notifications_type ON notifications(type);
CREATE INDEX IF NOT EXISTS idx_audit_log_user ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_log_action ON audit_log(action);
CREATE INDEX IF NOT EXISTS idx_audit_log_created ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_procurement_branch ON procurement_requests(branch_id);
CREATE INDEX IF NOT EXISTS idx_procurement_status ON procurement_requests(status);
CREATE INDEX IF NOT EXISTS idx_maintenance_branch ON equipment_maintenance(branch_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_status ON equipment_maintenance(status);
