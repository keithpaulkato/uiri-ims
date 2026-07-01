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

-- NAKAWA CAMPUS SECTIONS (Branch 1) - Food & Agro-Processing Focus
INSERT INTO sections (branch_id, name, code, description) VALUES
(1, 'Meat Processing Technology Section', 'MEAT', 'Meat butchering, processing and value addition'),
(1, 'Dairy Processing Technology Section', 'DAIRY', 'Milk and dairy products processing'),
(1, 'Bakery and Confectionery Technology Section', 'BAKERY', 'Bread, pastry and confectionery production'),
(1, 'Fruits and Vegetables Processing Section', 'FRUITS', 'Fruit and vegetable preservation and processing'),
(1, 'Bamboo Processing Section', 'BAMBOO', 'Bamboo crafts and material processing'),
(1, 'Ceramics and Materials Processing Section', 'CERAMICS', 'Pottery, ceramics and advanced materials'),
(1, 'Handmade Paper Technology Section', 'PAPER', 'Paper production and paper craft technology'),
(1, 'Agro-Production and Value Addition Laboratory', 'AGRO', 'Agricultural product development and testing'),
(1, 'Chemistry Analytical Laboratory', 'CHEM', 'Chemical analysis and quality testing'),
(1, 'Microbiology and Biotechnology Laboratory', 'MICRO', 'Microbial analysis and biotech research'),
(1, 'Mineral Testing Laboratory', 'MINERAL', 'Mineral and ore analysis'),
(1, 'Instrumentation Design and Electronics Prototyping Laboratory', 'INSTR', 'Electronic device design and testing'),
(1, 'Printed Circuit Board Production Unit', 'PCB', 'PCB design and manufacturing'),
(1, 'Wood Technology and Carpentry Unit', 'WOOD', 'Wood processing and furniture production'),
(1, 'ICT Software Development Section', 'ICT', 'Software development and IT support'),
(1, 'In-House Business Incubation Hub', 'INCUB', 'Business development and entrepreneurship'),
(1, 'Virtual Business Incubation Hub', 'VHUB', 'Digital business incubation and support'),

-- NAMANVE CAMPUS SECTIONS (Branch 2) - Heavy Manufacturing Focus
(2, 'CNC Milling and Drilling Section', 'CNC-MILL', 'Precision milling and drilling operations'),
(2, 'Conventional Machining and Lathe Operations Section', 'LATHE', 'Traditional lathe work and machining'),
(2, 'Precision Parts Fabrication Shop', 'PRECISION', 'Precision manufacturing and assembly'),
(2, 'Industrial Robotics and Automation Section', 'ROBOTICS', 'Robot programming and automation systems'),
(2, 'Pneumatics and Hydraulics Systems Unit', 'PNEUMATIC', 'Pneumatic and hydraulic systems'),
(2, 'Programmable Logic Controllers Laboratory', 'PLC', 'PLC programming and industrial control'),
(2, 'Systems Integration and Technical Advisory Unit', 'SYSTEMS', 'Systems integration and technical consulting'),
(2, 'Computer-Aided Design and Manufacture Lab', 'CAD', 'CAD/CAM design and manufacturing'),
(2, 'Industrial Foundry and Metal Casting Section', 'FOUNDRY', 'Metal casting and foundry operations'),
(2, 'Mechanical Assembly and Tooling Area', 'ASSEMBLY', 'Assembly operations and tool design'),
(2, 'Industrial Plant Maintenance and Repair Hub', 'MAINT', 'Equipment maintenance and repair'),
(2, 'Heavy-Industry Technical Vocational Skilling Centre', 'HVOC', 'Heavy industry training and skills'),
(2, 'Curriculum Development and Training Unit', 'TRAINING', 'Course development and instructor training');

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

-- DEPARTMENTS FOR NAKAWA SECTIONS
INSERT INTO departments (section_id, name, code, manager_name, contact_email) VALUES
-- Meat Processing (Section 1)
(1, 'Butchering and Processing Unit', 'MPROC-1', 'Mr. Patrick Ssekamatte', 'mproc1@uiri.go.ug'),
(1, 'Meat Product Packaging Unit', 'MPROC-2', 'Ms. Sarah Nabwire', 'mproc2@uiri.go.ug'),
-- Dairy Processing (Section 2)
(2, 'Milk Reception and Testing Lab', 'DAIRY-1', 'Dr. Kimberly Natukunda', 'dairy1@uiri.go.ug'),
(2, 'Cheese and Yoghurt Production Unit', 'DAIRY-2', 'Mr. Robert Kiprotich', 'dairy2@uiri.go.ug'),
(2, 'Butter and Ghee Production Unit', 'DAIRY-3', 'Ms. Florence Kampire', 'dairy3@uiri.go.ug'),
-- Bakery and Confectionery (Section 3)
(3, 'Bread Production Unit', 'BAKERY-1', 'Mr. Isaac Sebaggala', 'bakery1@uiri.go.ug'),
(3, 'Pastry and Cake Production Unit', 'BAKERY-2', 'Ms. Harriet Namusoke', 'bakery2@uiri.go.ug'),
(3, 'Confectionery Production Unit', 'BAKERY-3', 'Mr. Vincent Mukasa', 'bakery3@uiri.go.ug'),
-- Fruits and Vegetables Processing (Section 4)
(4, 'Juice and Beverage Production', 'FRUITS-1', 'Dr. Grace Owiny', 'fruits1@uiri.go.ug'),
(4, 'Jam and Preserve Production', 'FRUITS-2', 'Ms. Nakato Josephine', 'fruits2@uiri.go.ug'),
(4, 'Dried Fruit and Vegetable Processing', 'FRUITS-3', 'Mr. Stephen Mpuuga', 'fruits3@uiri.go.ug'),
-- Bamboo Processing (Section 5)
(5, 'Bamboo Weaving and Craft Unit', 'BAMBOO-1', 'Mr. Rashid Bwayo', 'bamboo1@uiri.go.ug'),
(5, 'Bamboo Composite and Structural Unit', 'BAMBOO-2', 'Eng. Christopher Obwogi', 'bamboo2@uiri.go.ug'),
-- Ceramics (Section 6)
(6, 'Pottery Production Unit', 'CERAMICS-1', 'Ms. Alice Zziwa', 'ceramics1@uiri.go.ug'),
(6, 'Advanced Materials Lab', 'CERAMICS-2', 'Dr. Andrew Muwanga', 'ceramics2@uiri.go.ug'),
-- Handmade Paper (Section 7)
(7, 'Paper Pulping and Formation Unit', 'PAPER-1', 'Mr. Jude Byarugaba', 'paper1@uiri.go.ug'),
(7, 'Paper Finishing and Coating Unit', 'PAPER-2', 'Ms. Fiona Nakayima', 'paper2@uiri.go.ug'),
-- Agro-Production Lab (Section 8)
(8, 'Product Development Unit', 'AGRO-1', 'Dr. Martin Ojobo', 'agro1@uiri.go.ug'),
(8, 'Quality and Safety Testing', 'AGRO-2', 'Ms. Belinda Kibedi', 'agro2@uiri.go.ug'),
-- Chemistry Lab (Section 9)
(9, 'Quantitative Analysis Lab', 'CHEM-1', 'Dr. Samuel Mukose', 'chem1@uiri.go.ug'),
(9, 'Qualitative Analysis Lab', 'CHEM-2', 'Ms. Diana Nakyubo', 'chem2@uiri.go.ug'),
-- Microbiology Lab (Section 10)
(10, 'Microbial Identification Unit', 'MICRO-1', 'Dr. Patience Nabunya', 'micro1@uiri.go.ug'),
(10, 'Biotech Research Unit', 'MICRO-2', 'Mr. Livingstone Kato', 'micro2@uiri.go.ug'),
-- Mineral Testing (Section 11)
(11, 'Ore Testing and Analysis', 'MINERAL-1', 'Eng. David Sentongo', 'mineral1@uiri.go.ug'),
(11, 'Mineral Processing Unit', 'MINERAL-2', 'Mr. Arthur Okumu', 'mineral2@uiri.go.ug'),
-- Instrumentation Lab (Section 12)
(12, 'Circuit Design Unit', 'INSTR-1', 'Eng. Wilfred Kiwumulo', 'instr1@uiri.go.ug'),
(12, 'Device Testing and Calibration', 'INSTR-2', 'Mr. Moses Sseninde', 'instr2@uiri.go.ug'),
-- PCB Production (Section 13)
(13, 'PCB Design Unit', 'PCB-1', 'Eng. Yusuf Kalyango', 'pcb1@uiri.go.ug'),
(13, 'PCB Manufacturing Unit', 'PCB-2', 'Mr. John Mwebaze', 'pcb2@uiri.go.ug'),
-- Wood Technology (Section 14)
(14, 'Furniture Design Unit', 'WOOD-1', 'Mr. Phillip Semanda', 'wood1@uiri.go.ug'),
(14, 'Wood Finishing and Treatment', 'WOOD-2', 'Ms. Helen Kampala', 'wood2@uiri.go.ug'),
-- ICT Software (Section 15)
(15, 'Software Development Unit', 'ICT-1', 'Eng. Timothy Kabanda', 'ict1@uiri.go.ug'),
(15, 'Network and Systems Support', 'ICT-2', 'Mr. Ronald Katende', 'ict2@uiri.go.ug'),
-- In-House Incubation (Section 16)
(16, 'Business Planning Unit', 'INCUB-1', 'Mr. Godfrey Nassali', 'incub1@uiri.go.ug'),
(16, 'Mentorship and Coaching Unit', 'INCUB-2', 'Ms. Rebecca Muwonge', 'incub2@uiri.go.ug'),
-- Virtual Incubation (Section 17)
(17, 'Digital Marketing Unit', 'VHUB-1', 'Ms. Winnie Nabagesera', 'vhub1@uiri.go.ug'),
(17, 'E-Commerce Solutions Unit', 'VHUB-2', 'Mr. Felix Mutumba', 'vhub2@uiri.go.ug'),

-- DEPARTMENTS FOR NAMANVE SECTIONS
-- CNC Milling (Section 18)
(18, 'CNC Milling Operations', 'CNMILL-1', 'Eng. Moses Kabuye', 'cnmill1@uiri.go.ug'),
(18, 'Tool Design and Maintenance', 'CNMILL-2', 'Mr. Steven Nakabugo', 'cnmill2@uiri.go.ug'),
-- Conventional Machining (Section 19)
(19, 'Lathe Operations Unit', 'LATHE-1', 'Mr. Peter Kaliisa', 'lathe1@uiri.go.ug'),
(19, 'Milling Operations Unit', 'LATHE-2', 'Mr. Julius Apecu', 'lathe2@uiri.go.ug'),
-- Precision Parts (Section 20)
(20, 'Precision Machining', 'PREC-1', 'Eng. Robert Sekandi', 'prec1@uiri.go.ug'),
(20, 'Quality Control and Measurement', 'PREC-2', 'Mr. Alfred Kasenene', 'prec2@uiri.go.ug'),
-- Robotics (Section 21)
(21, 'Robot Programming Unit', 'ROBO-1', 'Eng. Brian Kamara', 'robo1@uiri.go.ug'),
(21, 'Automation Systems Design', 'ROBO-2', 'Eng. Lawrence Mulondo', 'robo2@uiri.go.ug'),
-- Pneumatics (Section 22)
(22, 'Pneumatic Systems Unit', 'PNEUM-1', 'Eng. Richard Kasekende', 'pneum1@uiri.go.ug'),
(22, 'Hydraulic Systems Unit', 'PNEUM-2', 'Eng. Charles Mugenyi', 'pneum2@uiri.go.ug'),
-- PLC Lab (Section 23)
(23, 'PLC Programming Unit', 'PLC-1', 'Eng. Daniel Lubwama', 'plc1@uiri.go.ug'),
(23, 'Industrial Control Systems', 'PLC-2', 'Eng. James Kyakulaga', 'plc2@uiri.go.ug'),
-- Systems Integration (Section 24)
(24, 'Systems Design Unit', 'SYS-1', 'Eng. Thomas Mutyaba', 'sys1@uiri.go.ug'),
(24, 'Technical Advisory Services', 'SYS-2', 'Eng. Paul Kasolo', 'sys2@uiri.go.ug'),
-- CAD/CAM Lab (Section 25)
(25, 'CAD Design Unit', 'CAD-1', 'Eng. Simon Katamanya', 'cad1@uiri.go.ug'),
(25, 'CAM and CNC Programming', 'CAD-2', 'Eng. Henry Owino', 'cad2@uiri.go.ug'),
-- Foundry (Section 26)
(26, 'Metal Melting and Casting', 'FOUND-1', 'Mr. Amos Kyewalabye', 'found1@uiri.go.ug'),
(26, 'Finishing and Inspection', 'FOUND-2', 'Mr. John Nanteza', 'found2@uiri.go.ug'),
-- Mechanical Assembly (Section 27)
(27, 'Assembly Operations Unit', 'ASSEM-1', 'Mr. Enoch Muwanga', 'assem1@uiri.go.ug'),
(27, 'Tool Design Unit', 'ASSEM-2', 'Eng. Mark Ssenabulya', 'assem2@uiri.go.ug'),
-- Maintenance Hub (Section 28)
(28, 'Preventive Maintenance Unit', 'MAINT-1', 'Eng. Samson Kasirye', 'maint1@uiri.go.ug'),
(28, 'Corrective Repairs Unit', 'MAINT-2', 'Eng. Vincent Walukale', 'maint2@uiri.go.ug'),
-- Heavy Industry Vocational (Section 29)
(29, 'Heavy Equipment Training', 'HVOC-1', 'Mr. Gerald Nsubuga', 'hvoc1@uiri.go.ug'),
(29, 'Skills Certification Unit', 'HVOC-2', 'Mr. Godfrey Mtazi', 'hvoc2@uiri.go.ug'),
-- Training and Development (Section 30)
(30, 'Curriculum Development', 'TRAIN-1', 'Dr. Sheila Mwenya', 'train1@uiri.go.ug'),
(30, 'Instructor Professional Development', 'TRAIN-2', 'Ms. Margery Kibula', 'train2@uiri.go.ug');

INSERT INTO categories (branch_id, name, description) VALUES
-- NAKAWA CAMPUS CATEGORIES
(1, 'Food Processing Equipment', 'Machinery and equipment for food processing sections'),
(1, 'Laboratory Equipment', 'Scientific instruments, lab tools and research equipment'),
(1, 'Processing Chemicals', 'Chemicals and additives for food/industrial processing'),
(1, 'Packaging Materials', 'Packaging boxes, bags, labels and wrapping materials'),
(1, 'ICT Equipment', 'Computers, printers, networking devices and accessories'),
(1, 'Ceramics and Craft Tools', 'Tools and materials for ceramics and craft sections'),
(1, 'Safety Equipment', 'PPE, fire extinguishers and safety gear'),
(1, 'Office Supplies', 'Stationery, paper, pens and everyday office consumables'),
(1, 'Furniture', 'Desks, chairs, cabinets and office furniture'),

-- NAMANVE CAMPUS CATEGORIES
(2, 'Heavy Machinery', 'Industrial machines, CNC machines, generators and heavy equipment'),
(2, 'Precision Tools', 'Precision measurement tools, gauges and testing equipment'),
(2, 'Machining Equipment', 'Lathe, milling, drilling and conventional machinery'),
(2, 'Robotics and Automation', 'Industrial robots, automation systems and components'),
(2, 'Electrical Components', 'PLC controllers, electrical panels and power systems'),
(2, 'Hydraulic/Pneumatic Equipment', 'Hydraulic pumps, cylinders, pneumatic systems'),
(2, 'Safety Equipment', 'PPE, fire extinguishers and safety gear'),
(2, 'ICT Equipment', 'Computers, printers, networking devices and accessories'),
(2, 'Office Supplies', 'Stationery, paper, pens and everyday office consumables'),
(2, 'Furniture', 'Desks, chairs, cabinets and office furniture');

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

-- (Categories have been moved above after departments)

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

-- NAKAWA CAMPUS INVENTORY ITEMS - Food Processing & Services
INSERT INTO inventory_items (branch_id, section_id, department_id, category_id, supplier_id, item_code, asset_code, name, description, unit, unit_price, current_stock, minimum_stock, created_by) VALUES
-- Meat Processing (Section 1, Dept 1-2)
(1, 1, 1, 1, 1, 'NK-MEAT-001', 'NK-MEAT-001-A', 'Commercial Meat Grinder', 'Heavy duty meat grinding machine 20kg/hour', 'piece', 3500000, 2, 1, 1),
(1, 1, 1, 1, 1, 'NK-MEAT-002', 'NK-MEAT-002-A', 'Meat Vacuum Sealer Machine', 'Professional vacuum sealing equipment', 'piece', 1200000, 4, 1, 1),
(1, 1, 2, 1, 1, 'NK-MEAT-003', 'NK-MEAT-003-A', 'Packaging Film Rolls', '150mm x 200m food grade vacuum film', 'roll', 45000, 50, 10, 1),
-- Dairy Processing (Section 2, Dept 3-5)
(1, 2, 3, 1, 2, 'NK-DAIRY-001', 'NK-DAIRY-001-A', 'Milk Pasteurizer Unit', 'Electric pasteurization equipment for milk', 'piece', 8500000, 1, 1, 1),
(1, 2, 3, 2, 2, 'NK-DAIRY-002', 'NK-DAIRY-002-A', 'Lactometer', 'Digital lactometer for milk testing', 'piece', 280000, 6, 2, 1),
(1, 2, 4, 1, 1, 'NK-DAIRY-003', 'NK-DAIRY-003-A', 'Cheese Making Vat', 'Stainless steel cheese making vat 30L', 'piece', 2500000, 3, 1, 1),
(1, 2, 5, 1, 2, 'NK-DAIRY-004', 'NK-DAIRY-004-A', 'Butter Churn Machine', 'Traditional and modern butter production equipment', 'piece', 1800000, 2, 1, 1),
-- Bakery (Section 3, Dept 6-8)
(1, 3, 6, 1, 1, 'NK-BAKERY-001', 'NK-BAKERY-001-A', 'Industrial Bread Oven', 'Gas-fired commercial bread oven 8-tray', 'piece', 12000000, 1, 1, 1),
(1, 3, 6, 1, 2, 'NK-BAKERY-002', 'NK-BAKERY-002-A', 'Dough Mixer Machine', '50kg capacity industrial dough mixer', 'piece', 4500000, 2, 1, 1),
(1, 3, 7, 1, 1, 'NK-BAKERY-003', 'NK-BAKERY-003-A', 'Pastry Sheeter', 'Automatic pastry dough sheeting machine', 'piece', 3200000, 1, 1, 1),
(1, 3, 8, 3, 2, 'NK-BAKERY-004', 'NK-BAKERY-004-A', 'Baking Powder', 'Food grade baking powder 25kg bag', 'bag', 85000, 30, 5, 1),
-- Fruits & Vegetables (Section 4, Dept 9-11)
(1, 4, 9, 1, 1, 'NK-FRUITS-001', 'NK-FRUITS-001-A', 'Juice Extraction Machine', 'Commercial juice extractor 200L/hour capacity', 'piece', 5500000, 1, 1, 1),
(1, 4, 9, 2, 2, 'NK-FRUITS-002', 'NK-FRUITS-002-A', 'Refractometer', 'Digital refractometer for brix measurement', 'piece', 320000, 4, 1, 1),
(1, 4, 10, 1, 1, 'NK-FRUITS-003', 'NK-FRUITS-003-A', 'Jam Cooking Kettle', 'Jacketed steam kettle 50L capacity', 'piece', 2800000, 2, 1, 1),
(1, 4, 11, 1, 2, 'NK-FRUITS-004', 'NK-FRUITS-004-A', 'Food Dehydrator', 'Commercial fruit and vegetable dryer', 'piece', 4200000, 1, 1, 1),
-- Bamboo Processing (Section 5, Dept 12-13)
(1, 5, 12, 1, 1, 'NK-BAMBOO-001', 'NK-BAMBOO-001-A', 'Bamboo Splitting Tools Set', 'Traditional and modern bamboo processing tools', 'set', 580000, 5, 2, 1),
(1, 5, 13, 1, 1, 'NK-BAMBOO-002', 'NK-BAMBOO-002-A', 'Bamboo Drying Rack', 'Large capacity bamboo drying system', 'piece', 950000, 3, 1, 1),
-- Ceramics (Section 6, Dept 14-15)
(1, 6, 14, 1, 1, 'NK-CERAMICS-001', 'NK-CERAMICS-001-A', 'Pottery Wheel', 'Electric pottery wheel 25cm diameter', 'piece', 1500000, 4, 1, 1),
(1, 6, 14, 7, 2, 'NK-CERAMICS-002', 'NK-CERAMICS-002-A', 'Clay (Per Bag)', 'Food-safe ceramic clay 25kg bag', 'bag', 95000, 20, 5, 1),
(1, 6, 15, 1, 1, 'NK-CERAMICS-003', 'NK-CERAMICS-003-A', 'Kiln (Industrial)', 'Electric ceramic kiln 30L chamber', 'piece', 18000000, 1, 1, 1),
-- Handmade Paper (Section 7, Dept 16-17)
(1, 7, 16, 1, 1, 'NK-PAPER-001', 'NK-PAPER-001-A', 'Paper Pulping Machine', 'Industrial paper pulping equipment', 'piece', 6800000, 1, 1, 1),
(1, 7, 17, 1, 2, 'NK-PAPER-002', 'NK-PAPER-002-A', 'Paper Drying Frame', 'Large paper drying frames (10-pack)', 'set', 380000, 3, 1, 1),
-- Labs, ICT, and Incubation sections
(1, 8, 18, 2, 2, 'NK-AGRO-001', 'NK-AGRO-001-A', 'pH Meter (Digital)', 'Professional pH testing equipment', 'piece', 450000, 8, 2, 1),
(1, 9, 19, 2, 2, 'NK-CHEM-001', 'NK-CHEM-001-A', 'Chemistry Glassware Set', 'Complete lab glassware and equipment set', 'set', 620000, 5, 2, 1),
(1, 10, 20, 2, 2, 'NK-MICRO-001', 'NK-MICRO-001-A', 'Microscope (Binocular)', 'Professional binocular laboratory microscope', 'piece', 2200000, 2, 1, 1),
(1, 11, 21, 2, 2, 'NK-MINERAL-001', 'NK-MINERAL-001-A', 'Rock Tumbler', 'Electric rock tumbler for mineral processing', 'piece', 780000, 2, 1, 1),
(1, 12, 22, 1, 1, 'NK-INSTR-001', 'NK-INSTR-001-A', 'Soldering Iron Station', 'Professional electronic soldering equipment', 'piece', 350000, 6, 2, 1),
(1, 13, 23, 1, 1, 'NK-PCB-001', 'NK-PCB-001-A', 'PCB Etching Machine', 'Chemical PCB etching equipment', 'piece', 1200000, 1, 1, 1),
(1, 14, 24, 1, 1, 'NK-WOOD-001', 'NK-WOOD-001-A', 'Carpentry Hand Tools Set', 'Complete carpentry hand tools (50-piece)', 'set', 850000, 4, 1, 1),
(1, 14, 25, 1, 2, 'NK-WOOD-002', 'NK-WOOD-002-A', 'Wood Finish Varnish', 'Professional wood varnish 20L container', 'container', 280000, 15, 3, 1),
(1, 15, 26, 5, 1, 'NK-ICT-001', 'NK-ICT-001-A', 'Desktop Computer', 'Core i5 8GB RAM 256GB SSD', 'piece', 2500000, 8, 2, 1),
(1, 15, 27, 5, 1, 'NK-ICT-002', 'NK-ICT-002-A', 'Network Printer', 'Color network printer 40ppm', 'piece', 1200000, 3, 1, 1),
(1, 16, 28, 8, 3, 'NK-INCUB-001', 'NK-INCUB-001-A', 'Business Planning Software License', 'Annual software license seat', 'license', 450000, 10, 5, 1),
(1, 17, 29, 8, 1, 'NK-VHUB-001', 'NK-VHUB-001-A', 'Web Server (Annual Hosting)', 'Cloud hosting service annual subscription', 'service', 850000, 5, 2, 1),
(1, 1, NULL, 8, 3, 'NK-OFF-001', 'NK-OFF-001-A', 'A4 Paper (Ream)', '80gsm white A4 paper ream of 500 sheets', 'ream', 22000, 100, 20, 1),
(1, 1, NULL, 8, 3, 'NK-OFF-002', 'NK-OFF-002-A', 'Office Chairs', 'Ergonomic office chairs', 'piece', 450000, 15, 5, 1),

-- NAMANVE CAMPUS INVENTORY ITEMS - Heavy Manufacturing
-- CNC Operations (Section 18, Dept 31-32)
(2, 18, 31, 2, 4, 'NM-CNC-001', 'NM-CNC-001-A', 'CNC Milling Machine', '3-axis CNC vertical milling center', 'piece', 45000000, 1, 1, 3),
(2, 18, 31, 2, 4, 'NM-CNC-002', 'NM-CNC-002-A', 'Cutting Tool Set (CNC)', 'Premium carbide cutting tools assorted set', 'set', 2200000, 8, 2, 3),
(2, 18, 32, 2, 4, 'NM-CNC-003', 'NM-CNC-003-A', 'CNC Tool Holder', 'Precision tool holder and collet set', 'set', 880000, 6, 2, 3),
-- Conventional Machining (Section 19, Dept 33-34)
(2, 19, 33, 3, 4, 'NM-LATHE-001', 'NM-LATHE-001-A', 'Industrial Lathe Machine', 'Heavy duty metal lathe 500mm swing', 'piece', 28000000, 1, 1, 3),
(2, 19, 33, 2, 4, 'NM-LATHE-002', 'NM-LATHE-002-A', 'Lathe Cutting Tools', 'HSS and carbide turning tool set', 'set', 580000, 10, 3, 3),
(2, 19, 34, 3, 4, 'NM-MILLING-001', 'NM-MILLING-001-A', 'Milling Machine', 'Universal milling machine heavy duty', 'piece', 22000000, 1, 1, 3),
-- Precision Parts (Section 20, Dept 35-36)
(2, 20, 35, 2, 4, 'NM-PREC-001', 'NM-PREC-001-A', 'Precision Measuring Caliper', 'Digital precision calipers 0.01mm accuracy', 'piece', 450000, 12, 3, 3),
(2, 20, 36, 2, 4, 'NM-PREC-002', 'NM-PREC-002-A', 'Micrometer Set', 'Precision micrometers 0-25mm range (6 piece)', 'set', 1800000, 5, 1, 3),
(2, 20, 36, 2, 4, 'NM-PREC-003', 'NM-PREC-003-A', 'Height Gauge (Digital)', 'Digital height measurement gauge', 'piece', 980000, 4, 1, 3),
-- Robotics (Section 21, Dept 37-38)
(2, 21, 37, 4, 4, 'NM-ROBOT-001', 'NM-ROBOT-001-A', 'Industrial Robot Arm', '6-axis collaborative robot 10kg payload', 'piece', 85000000, 1, 1, 3),
(2, 21, 37, 4, 4, 'NM-ROBOT-002', 'NM-ROBOT-002-A', 'Robot End-of-Arm Tool', 'Gripper and tool change system', 'piece', 8500000, 2, 1, 3),
(2, 21, 38, 5, 4, 'NM-AUTO-001', 'NM-AUTO-001-A', 'Servo Motor', '2kW servo motor with encoder', 'piece', 1200000, 8, 2, 3),
-- Pneumatics & Hydraulics (Section 22, Dept 39-40)
(2, 22, 39, 6, 4, 'NM-PNEUM-001', 'NM-PNEUM-001-A', 'Air Compressor', 'Rotary screw compressor 22kW', 'piece', 18000000, 1, 1, 3),
(2, 22, 39, 6, 4, 'NM-PNEUM-002', 'NM-PNEUM-002-A', 'Pneumatic Cylinders', 'Double-acting pneumatic cylinders (5 piece)', 'set', 950000, 6, 2, 3),
(2, 22, 40, 6, 4, 'NM-HYDRAUL-001', 'NM-HYDRAUL-001-A', 'Hydraulic Pump', 'Variable displacement hydraulic pump', 'piece', 8800000, 1, 1, 3),
(2, 22, 40, 6, 4, 'NM-HYDRAUL-002', 'NM-HYDRAUL-002-A', 'Hydraulic Hose Kit', 'Complete hydraulic hose fitting kit', 'kit', 1200000, 4, 1, 3),
-- PLC Lab (Section 23, Dept 41-42)
(2, 23, 41, 5, 4, 'NM-PLC-001', 'NM-PLC-001-A', 'PLC Controller', 'Compact PLC 16I/16O with communication', 'piece', 2200000, 5, 2, 3),
(2, 23, 41, 5, 4, 'NM-PLC-002', 'NM-PLC-002-A', 'Industrial HMI Panel', '7-inch industrial touch screen HMI', 'piece', 3800000, 3, 1, 3),
(2, 23, 42, 5, 4, 'NM-CTRL-001', 'NM-CTRL-001-A', 'Electrical Panel Box', 'Industrial control panel enclosure', 'piece', 580000, 10, 3, 3),
-- Systems Integration (Section 24, Dept 43-44)
(2, 24, 43, 5, 4, 'NM-SYS-001', 'NM-SYS-001-A', 'System Integration Software', 'Automation software engineering tools', 'license', 4500000, 3, 1, 3),
(2, 24, 44, 5, 4, 'NM-SYS-002', 'NM-SYS-002-A', 'Communication Module', 'Industrial communication gateway module', 'piece', 1800000, 4, 1, 3),
-- CAD/CAM Lab (Section 25, Dept 45-46)
(2, 25, 45, 5, 4, 'NM-CAD-001', 'NM-CAD-001-A', 'CAD Software License', 'Professional CAD design software annual', 'license', 6500000, 2, 1, 3),
(2, 25, 46, 5, 4, 'NM-CAM-001', 'NM-CAM-001-A', 'CAM Software License', 'CAM programming software annual', 'license', 5200000, 2, 1, 3),
-- Foundry (Section 26, Dept 47-48)
(2, 26, 47, 3, 4, 'NM-FOUND-001', 'NM-FOUND-001-A', 'Induction Furnace', '50kg capacity induction melting furnace', 'piece', 38000000, 1, 1, 3),
(2, 26, 47, 3, 4, 'NM-FOUND-002', 'NM-FOUND-002-A', 'Foundry Mold Sand', 'High-quality foundry sand 1000kg', 'kg', 8000, 100, 20, 3),
(2, 26, 48, 2, 4, 'NM-FOUND-003', 'NM-FOUND-003-A', 'Surface Grinder', 'Precision surface grinding machine', 'piece', 15000000, 1, 1, 3),
-- Mechanical Assembly (Section 27, Dept 49-50)
(2, 27, 49, 3, 4, 'NM-ASSEM-001', 'NM-ASSEM-001-A', 'Assembly Workbench', 'Heavy-duty assembly workstation', 'piece', 1800000, 4, 1, 3),
(2, 27, 50, 3, 4, 'NM-TOOL-001', 'NM-TOOL-001-A', 'Tool Design Set', 'Complete mechanical tool design set', 'set', 2200000, 2, 1, 3),
-- Maintenance Hub (Section 28, Dept 51-52)
(2, 28, 51, 3, 4, 'NM-MAINT-001', 'NM-MAINT-001-A', 'Hydraulic Jack Set', '100-ton hydraulic jack set', 'set', 950000, 3, 1, 3),
(2, 28, 51, 7, 4, 'NM-MAINT-002', 'NM-MAINT-002-A', 'Safety Harness (10-Pack)', 'Industrial safety harnesses for heavy work', 'pack', 650000, 5, 2, 3),
(2, 28, 52, 3, 4, 'NM-MAINT-003', 'NM-MAINT-003-A', 'Welding Equipment Set', 'MIG/TIG welding machine 250A', 'piece', 8800000, 1, 1, 3),
-- Vocational & Training (Section 29-30, Dept 53-60)
(2, 29, 53, 3, 4, 'NM-TRAIN-001', 'NM-TRAIN-001-A', 'Heavy Equipment Models', 'Training models and mockups', 'set', 850000, 4, 1, 3),
(2, 30, 55, 8, 3, 'NM-EDU-001', 'NM-EDU-001-A', 'Training Manuals (Mechanical)', 'Technical reference and training materials', 'set', 420000, 8, 2, 3),
(2, 1, NULL, 8, 3, 'NM-OFF-001', 'NM-OFF-001-A', 'A4 Paper (Ream)', '80gsm white A4 paper ream of 500 sheets', 'ream', 22000, 80, 20, 3),
(2, 1, NULL, 9, 3, 'NM-OFF-002', 'NM-OFF-002-A', 'Office Chairs', 'Ergonomic office chairs', 'piece', 450000, 12, 5, 3);

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
