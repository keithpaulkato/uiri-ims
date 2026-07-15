<?php
// Migration: align inventory filters to the recommended UIRI campus -> department -> section/unit structure.
$cliSessionPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uiri-ims-cli-sessions';
if (PHP_SAPI === 'cli' && !is_dir($cliSessionPath)) {
    mkdir($cliSessionPath, 0775, true);
}
if (PHP_SAPI === 'cli') {
    session_save_path($cliSessionPath);
}

require_once __DIR__ . '/../includes/config.php';

$pdo = db();

$departmentsByCampus = [
    'UIRI Nakawa' => [
        ['Agro-Production and Value Addition Laboratory', 'AGRO', 'Agricultural product development and testing', 'Agricultural Product Development and Testing Unit'],
        ['Bakery and Confectionery Technology Section', 'BAKERY', 'Bread, pastry and confectionery production', 'Bread, Pastry and Confectionery Production Unit'],
        ['Bamboo Processing Section', 'BAMBOO', 'Bamboo crafts and material processing', 'Bamboo Crafts and Material Processing Unit'],
        ['Central Warehouse & General Stores', 'STORES', 'Central receiving, stores control, warehousing, stock custody and institutional issue coordination', 'Central Receiving and Stock Custody Unit'],
        ['Ceramics and Materials Processing Section', 'CERAMICS', 'Pottery, ceramics and advanced materials', 'Pottery, Ceramics and Advanced Materials Unit'],
        ['Chemistry Analytical Laboratory', 'CHEM', 'Chemical analysis and quality testing', 'Chemical Analysis and Quality Testing Lab'],
        ['Civil Works & Estate Management', 'CIVIL', 'Estate management, facilities maintenance, civil works coordination and infrastructure support', 'Estate Management and Facilities Maintenance Unit'],
        ['Dairy Processing Technology Section', 'DAIRY', 'Milk and dairy products processing', 'Milk and Dairy Products Processing Unit'],
        ['Executive Directorate', 'EXEC', 'Executive leadership, institutional strategy, governance and senior administrative oversight', 'Office of the Executive Director'],
        ['Finance and Accounts Department', 'FIN', 'Financial control, accounting, budget monitoring, payments and statutory reporting', 'Financial Control and Accounts Unit'],
        ['Fruits and Vegetables Processing Section', 'FRUITS', 'Fruit and vegetable preservation and processing', 'Fruit and Vegetable Preservation and Processing Unit'],
        ['Handmade Paper Technology Section', 'PAPER', 'Paper production and paper craft technology', 'Paper Production and Craft Unit'],
        ['Human Resources & Administration', 'ADMIN', 'Human resource management, staff administration, records, welfare and institutional support services', 'Human Resource Management and Records Unit'],
        ['ICT Software Development Section', 'ICT', 'Software development and IT support', 'Systems Development and IT Support Unit'],
        ['In-House Business Incubation Hub', 'INCUB', 'Business development and entrepreneurship', 'On-Site Business Incubation Unit'],
        ['Instrumentation Design and Electronics Prototyping Laboratory', 'INSTR', 'Electronic device design and testing', 'Electronic Device Design and Testing Unit'],
        ['Meat Processing Technology Section', 'MEAT', 'Meat butchering, processing and value addition', 'Meat Butchering, Processing and Value Addition Unit'],
        ['Microbiology and Biotechnology Laboratory', 'MICRO', 'Microbial analysis and biotech research', 'Microbial Analysis and Biotech Research Lab'],
        ['Mineral Testing Laboratory', 'MINERAL', 'Mineral and ore analysis', 'Mineral and Ore Analysis Unit'],
        ['Printed Circuit Board Production Unit', 'PCB', 'PCB design and manufacturing', 'PCB Design and Manufacturing Unit'],
        ['Procurement and Disposal Unit', 'PDU', 'Procurement planning, supplier coordination, disposal governance and contract administration', 'PDU Secretariat and Contract Administration Unit'],
        ['Virtual Business Incubation Hub', 'VHUB', 'Digital business incubation and support', 'Digital Business Incubation and Support Unit'],
        ['Wood Technology and Carpentry Unit', 'WOOD', 'Wood processing and furniture production', 'Wood Processing and Furniture Production Unit'],
    ],
    'UIRI Namanve' => [
        ['CNC Milling and Drilling Section', 'CNC-MILL', 'Precision milling and drilling operations', 'Precision CNC Milling and Drilling Unit'],
        ['Computer-Aided Design and Manufacture Lab', 'CAD', 'CAD/CAM design and manufacturing', 'CAD/CAM Design and Manufacturing Unit'],
        ['Conventional Machining and Lathe Operations Section', 'LATHE', 'Traditional lathe work and machining', 'Traditional Machining and Lathe Operations Unit'],
        ['Curriculum Development and Training Unit', 'TRAINING', 'Course development and instructor training', 'Curriculum Development and Instructor Training Unit'],
        ['Engineering Stores and Warehouse (Namanve)', 'STORES-NAM', 'Namanve receiving, bulk raw materials custody, engineering stores control and workshop issue coordination', 'Bulk Raw Materials and Workshop Issue Coordination Unit'],
        ['Facilities and Admin Support Unit (Namanve)', 'ADMIN-NAM', 'Namanve facilities support, local administration, safety gear, PPE, office supplies and campus-wide maintenance coordination', 'Local Administration, Safety Gear and PPE Stores Unit'],
        ['Heavy-Industry Technical Vocational Skilling Centre', 'HVOC', 'Heavy industry training and skills', 'Heavy Industry Practical Workshops'],
        ['ICT Infrastructure and Technical Support (Namanve)', 'ICT-NAM', 'ICT infrastructure support for CAD/CAM workstations, CNC controllers, networks, software licenses and automation interfaces', 'CNC Network and CAD/CAM Software Licensing Unit'],
        ['Industrial Foundry and Metal Casting Section', 'FOUNDRY', 'Metal casting and foundry operations', 'Metallurgy and Metal Casting Workshop'],
        ['Industrial Plant Maintenance and Repair Hub', 'MAINT', 'Equipment maintenance and repair', 'Mechanical and Plant Equipment Repair Hub'],
        ['Industrial Robotics and Automation Section', 'ROBOTICS', 'Robot programming and automation systems', 'Robot Programming and Mechatronics Lab'],
        ['Mechanical Assembly and Tooling Area', 'ASSEMBLY', 'Assembly operations and tool design', 'Assembly Operations and Tool Design Area'],
        ['Pneumatics and Hydraulics Systems Unit', 'PNEUMATIC', 'Pneumatic and hydraulic systems', 'Fluid Power Control Workshop'],
        ['Precision Parts Fabrication Shop', 'PRECISION', 'Precision manufacturing and assembly', 'Precision Components Manufacturing Shop'],
        ['Programmable Logic Controllers Laboratory', 'PLC', 'PLC programming and industrial control', 'PLC Systems and Industrial Automation Controls Unit'],
        ['Systems Integration and Technical Advisory Unit', 'SYSTEMS', 'Systems integration and technical consulting', 'Technical Consulting and Advisory Unit'],
    ],
];

$categories = [
    'Administrative & Office Supplies' => 'General office, stationery and administrative supplies',
    'Laboratory Reagents & Chemicals' => 'Reagents, chemicals and consumables used in laboratory work',
    'Laboratory & Testing Equipment' => 'Scientific instruments, testing tools and laboratory equipment',
    'Industrial Machinery & Pilot Plant Equipment' => 'Machinery and pilot plant production equipment',
    'Engineering Tools & Tool Cabinets' => 'Engineering tools, tool sets, cabinets and workshop tooling',
    'Electronic Components & Prototyping Materials' => 'Electronic parts, boards and prototyping supplies',
    'Raw Materials' => 'Agricultural, biomass, metal, wood and production raw materials',
    'ICT Hardware & Networking Equipment' => 'Computers, printers, networking devices and ICT hardware',
    'Safety Gear & Personal Protective Equipment' => 'PPE, safety wear and protective equipment',
    'Workshop Spares, Hydraulics & Pneumatic Components' => 'Spares, hydraulic parts, pneumatic components and maintenance parts',
    'Packaging Materials' => 'Packaging boxes, bags, films, labels and wrapping materials',
    'Furniture & Fixtures' => 'Office, laboratory and workshop furniture or fixtures',
    'Software Licenses & Digital Services' => 'Software licenses, digital subscriptions and cloud services',
    'Maintenance Supplies' => 'General repair, service and maintenance supplies',
];

$getBranchId = $pdo->prepare("SELECT id FROM branches WHERE name = ? LIMIT 1");
$findSection = $pdo->prepare("SELECT id FROM sections WHERE branch_id = ? AND (name = ? OR code = ?) LIMIT 1");
$insertSection = $pdo->prepare("INSERT INTO sections (branch_id, name, code, description, is_active) VALUES (?, ?, ?, ?, 1)");
$updateSection = $pdo->prepare("UPDATE sections SET name = ?, code = ?, description = ?, is_active = 1 WHERE id = ?");
$findUnit = $pdo->prepare("SELECT id FROM departments WHERE section_id = ? AND name = ? LIMIT 1");
$insertUnit = $pdo->prepare("INSERT INTO departments (section_id, name, code, description, is_active) VALUES (?, ?, ?, ?, 1)");
$updateUnit = $pdo->prepare("UPDATE departments SET code = ?, description = ?, is_active = 1 WHERE id = ?");
$findCategory = $pdo->prepare("SELECT id FROM categories WHERE branch_id = ? AND name = ? LIMIT 1");
$insertCategory = $pdo->prepare("INSERT INTO categories (branch_id, name, description) VALUES (?, ?, ?)");
$updateCategory = $pdo->prepare("UPDATE categories SET description = ? WHERE id = ?");

$pdo->beginTransaction();
try {
    foreach ($departmentsByCampus as $campusName => $departments) {
        $getBranchId->execute([$campusName]);
        $branchId = (int)$getBranchId->fetchColumn();
        if (!$branchId) {
            throw new RuntimeException("Missing branch: {$campusName}");
        }

        foreach ($departments as [$name, $code, $description, $unitName]) {
            $findSection->execute([$branchId, $name, $code]);
            $sectionId = (int)$findSection->fetchColumn();
            if ($sectionId) {
                $updateSection->execute([$name, $code, $description, $sectionId]);
            } else {
                $insertSection->execute([$branchId, $name, $code, $description]);
                $sectionId = (int)$pdo->lastInsertId();
            }

            $findUnit->execute([$sectionId, $unitName]);
            $unitId = (int)$findUnit->fetchColumn();
            if ($unitId) {
                $updateUnit->execute([$code . '-UNIT', $description, $unitId]);
            } else {
                $insertUnit->execute([$sectionId, $unitName, $code . '-UNIT', $description]);
            }
        }

        foreach ($categories as $name => $description) {
            $findCategory->execute([$branchId, $name]);
            $categoryId = (int)$findCategory->fetchColumn();
            if ($categoryId) {
                $updateCategory->execute([$description, $categoryId]);
            } else {
                $insertCategory->execute([$branchId, $name, $description]);
            }
        }
    }

    $pdo->commit();
    echo "Canonical inventory structure aligned.\n";
    exit(0);
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
