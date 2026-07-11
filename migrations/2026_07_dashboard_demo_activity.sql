-- Optional demo activity for enterprise dashboard presentation.
-- Run only in a demonstration database, not in production.

INSERT INTO users (branch_id, section_id, department_id, role_id, full_name, email, username, password, phone)
SELECT 1, 15, 26, r.id, 'Sarah Nakato', 'sarah.nakato@uiri.go.ug', 'snakato.demo',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+256 700 100101'
FROM roles r
WHERE r.name = 'Staff'
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'snakato.demo');

INSERT INTO users (branch_id, section_id, department_id, role_id, full_name, email, username, password, phone)
SELECT 2, 18, 31, r.id, 'Moses Kabuye', 'moses.kabuye@uiri.go.ug', 'mkabuye.demo',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+256 700 100102'
FROM roles r
WHERE r.name = 'Section Manager'
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'mkabuye.demo');

INSERT INTO users (branch_id, section_id, department_id, role_id, full_name, email, username, password, phone)
SELECT 2, 23, 41, r.id, 'Daniel Lubwama', 'daniel.lubwama@uiri.go.ug', 'dlubwama.demo',
       '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+256 700 100103'
FROM roles r
WHERE r.name = 'Staff'
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'dlubwama.demo');

INSERT INTO inventory_requests (user_id, branch_id, department_id, item_id, quantity, reason, status, requested_at)
SELECT u.id, i.branch_id, i.department_id, i.id, 12, 'Dashboard demo: ICT refresh request for training lab', 'Pending', NOW() - INTERVAL 2 HOUR
FROM users u
JOIN inventory_items i ON i.item_code = 'NK-ICT-001'
WHERE u.username = 'snakato.demo'
  AND NOT EXISTS (SELECT 1 FROM inventory_requests WHERE item_id = i.id AND reason LIKE 'Dashboard demo:%ICT refresh%');

INSERT INTO inventory_requests (user_id, branch_id, department_id, item_id, quantity, reason, status, requested_at)
SELECT u.id, i.branch_id, i.department_id, i.id, 6, 'Dashboard demo: precision tool replacement for Namanve operations', 'Pending', NOW() - INTERVAL 4 HOUR
FROM users u
JOIN inventory_items i ON i.item_code = 'NM-PREC-001'
WHERE u.username = 'mkabuye.demo'
  AND NOT EXISTS (SELECT 1 FROM inventory_requests WHERE item_id = i.id AND reason LIKE 'Dashboard demo:%precision tool%');

INSERT INTO inventory_requests (user_id, branch_id, department_id, item_id, quantity, reason, status, requested_at, approved_by, approved_at)
SELECT u.id, i.branch_id, i.department_id, i.id, 3, 'Dashboard demo: PLC lab replenishment', 'Approved', NOW() - INTERVAL 1 DAY, admin.id, NOW() - INTERVAL 3 HOUR
FROM users u
JOIN users admin ON admin.username = 'admin'
JOIN inventory_items i ON i.item_code = 'NM-PLC-001'
WHERE u.username = 'dlubwama.demo'
  AND NOT EXISTS (SELECT 1 FROM inventory_requests WHERE item_id = i.id AND reason LIKE 'Dashboard demo:%PLC lab%');
