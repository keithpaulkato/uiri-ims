-- Enterprise UIRI department seed for the existing IMS schema.
-- The application uses branches as campuses and sections as departments/directorates.
-- This script is safe to re-run: it only inserts missing active sections.

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Executive Directorate', 'EXEC', 'Executive leadership, institutional strategy, governance and senior administrative oversight.'
FROM branches b
WHERE b.name = 'UIRI Nakawa'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Executive Directorate');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Procurement and Disposal Unit', 'PDU', 'Procurement planning, supplier coordination, disposal governance and contract administration.'
FROM branches b
WHERE b.name = 'UIRI Nakawa'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Procurement and Disposal Unit');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Finance and Accounts Department', 'FIN', 'Financial control, accounting, budget monitoring, payments and statutory reporting.'
FROM branches b
WHERE b.name = 'UIRI Nakawa'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Finance and Accounts Department');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Human Resources & Administration', 'ADMIN', 'Human resource management, staff administration, records, welfare and institutional support services.'
FROM branches b
WHERE b.name = 'UIRI Nakawa'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Human Resources & Administration');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Central Warehouse & General Stores', 'STORES', 'Central receiving, stores control, warehousing, stock custody and institutional issue coordination.'
FROM branches b
WHERE b.name = 'UIRI Nakawa'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Central Warehouse & General Stores');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Civil Works & Estate Management', 'CIVIL', 'Estate management, facilities maintenance, civil works coordination and infrastructure support.'
FROM branches b
WHERE b.name = 'UIRI Nakawa'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Civil Works & Estate Management');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Engineering Stores and Warehouse (Namanve)', 'STORES-NAM', 'Namanve receiving, bulk raw materials custody, engineering stores control and workshop issue coordination.'
FROM branches b
WHERE b.name = 'UIRI Namanve'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Engineering Stores and Warehouse (Namanve)');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'ICT Infrastructure and Technical Support (Namanve)', 'ICT-NAM', 'Local ICT infrastructure support for CAD/CAM workstations, CNC controllers, networks, software licenses and automation interfaces.'
FROM branches b
WHERE b.name = 'UIRI Namanve'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'ICT Infrastructure and Technical Support (Namanve)');

INSERT INTO sections (branch_id, name, code, description)
SELECT b.id, 'Facilities and Admin Support Unit (Namanve)', 'ADMIN-NAM', 'Namanve facilities support, local administration, safety gear, PPE, office supplies and campus-wide maintenance coordination.'
FROM branches b
WHERE b.name = 'UIRI Namanve'
  AND NOT EXISTS (SELECT 1 FROM sections s WHERE s.branch_id = b.id AND s.name = 'Facilities and Admin Support Unit (Namanve)');
