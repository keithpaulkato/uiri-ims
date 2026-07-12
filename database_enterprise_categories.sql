-- Enterprise-grade UIRI inventory categories.
-- Safe to re-run: inserts only categories that do not already exist per branch.

INSERT INTO categories (branch_id, name, description)
SELECT b.id, x.name, x.description
FROM branches b
JOIN (
    SELECT 'UIRI Nakawa' AS branch_name, 'Laboratory Reagents & Solvents' AS name, 'Organic and inorganic chemicals, acids, buffers, solvents, and diagnostic assays for analytical and microbiology laboratories.' AS description
    UNION ALL SELECT 'UIRI Nakawa', 'Scientific Glassware & Labware', 'Beakers, pipettes, volumetric flasks, Petri dishes, test tubes, and other recurring laboratory consumables.'
    UNION ALL SELECT 'UIRI Nakawa', 'Food-Grade Ingredients & Additives', 'Processing salts, yeasts, cultures, preservatives, stabilizing enzymes, flavorings, and food-safe additives.'
    UNION ALL SELECT 'UIRI Nakawa', 'Raw Biomass & Industrial Agro-Inputs', 'Bamboo culms, timber stocks, fibers, agricultural biomass, and organic raw materials for processing and craft sections.'
    UNION ALL SELECT 'UIRI Nakawa', 'Clays, Minerals & Glazes', 'Natural clays, kaolin, mineral inputs, metallic glazes, fluxing elements, and ceramic raw materials.'
    UNION ALL SELECT 'UIRI Nakawa', 'Electronic Components & Microcircuitry', 'Microcontrollers, resistors, capacitors, PCB boards, soldering alloys, sensors, and electronics prototyping parts.'
    UNION ALL SELECT 'UIRI Nakawa', 'Product Packaging & Containers', 'Food-grade pouches, pulp bottles, glass jars, caps, label rolls, adhesives, and custom product containers.'

    UNION ALL SELECT 'UIRI Namanve', 'Raw Metals & Structural Stock', 'Mild steel sheets, tool-grade shafts, aluminum plates, copper rods, welding profiles, and structural engineering stock.'
    UNION ALL SELECT 'UIRI Namanve', 'Precision Machining Toolings', 'CNC carbide inserts, milling bits, HSS drill bits, lathe tool holders, collets, and precision tooling consumables.'
    UNION ALL SELECT 'UIRI Namanve', 'Industrial Lubricants & Cutting Fluids', 'Soluble cutting oils, machine bed lubricants, hydraulic oils, thermal fluids, and industrial fluid consumables.'
    UNION ALL SELECT 'UIRI Namanve', 'Mechatronics, PLCs & Automation Gears', 'PLCs, servo and stepper motors, pneumatic valves, wiring harnesses, relays, sensors, and automation control parts.'
    UNION ALL SELECT 'UIRI Namanve', 'Welding, Abrasives & Metal Adhesives', 'Welding electrodes, shielding gases, grinding discs, cutting discs, abrasives, and industrial bonding epoxies.'
    UNION ALL SELECT 'UIRI Namanve', 'Timber Stock & Carpentry Finishing', 'Hardwood planks, blockboards, wood glues, fasteners, sandpapers, varnishes, and carpentry finishing materials.'
    UNION ALL SELECT 'UIRI Namanve', 'NDT Testing Consumables', 'Magnetic inspection powders, dye penetrants, developers, ultrasonic couplants, and industrial inspection films.'

    UNION ALL SELECT 'UIRI Nakawa', 'ICT Hardware & Network Infrastructure', 'Servers, desktop workstations, routers, switches, patch cords, fiber/ethernet accessories, and software license tokens.'
    UNION ALL SELECT 'UIRI Namanve', 'ICT Hardware & Network Infrastructure', 'Servers, desktop workstations, routers, switches, patch cords, fiber/ethernet accessories, and software license tokens.'
    UNION ALL SELECT 'UIRI Nakawa', 'Personal Protective Equipment (PPE) & Safety Gear', 'Heat-resistant gloves, lab coats, steel-toed boots, respirators, masks, and fire suppression cartridges.'
    UNION ALL SELECT 'UIRI Namanve', 'Personal Protective Equipment (PPE) & Safety Gear', 'Heat-resistant gloves, lab coats, steel-toed boots, respirators, masks, and fire suppression cartridges.'
    UNION ALL SELECT 'UIRI Nakawa', 'Facility Maintenance, Repair & Operations (MRO)', 'Plumbing fittings, electrical conduits, bulbs, cement, bricks, fixtures, and general facility hardware.'
    UNION ALL SELECT 'UIRI Namanve', 'Facility Maintenance, Repair & Operations (MRO)', 'Plumbing fittings, electrical conduits, bulbs, cement, bricks, fixtures, and general facility hardware.'
    UNION ALL SELECT 'UIRI Nakawa', 'Administrative & Office Supplies', 'Stationery, toners, paper boxes, files, binders, and general office organization supplies.'
    UNION ALL SELECT 'UIRI Namanve', 'Administrative & Office Supplies', 'Stationery, toners, paper boxes, files, binders, and general office organization supplies.'
) AS x ON x.branch_name = b.name
WHERE NOT EXISTS (
    SELECT 1
    FROM categories c
    WHERE c.branch_id = b.id
      AND c.name = x.name
);
