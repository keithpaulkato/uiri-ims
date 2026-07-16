from datetime import datetime
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile
from xml.sax.saxutils import escape


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "documentation" / "final_report" / "UIRI_IMS_Progress_Report.docx"

NAVY = "0A1628"
BLUE = "0F4C81"
GOLD = "C9A227"
LIGHT_BLUE = "EAF3FA"
LIGHT_GOLD = "F8F1D8"
LIGHT_GREY = "F4F6F8"
WHITE = "FFFFFF"
TEXT = "1F2933"
MUTED = "526174"
WARN = "FFF2CC"


def x(text):
    return escape(str(text), {"'": "&apos;", '"': "&quot;"})


def r(text, bold=False, italic=False, color=TEXT, size=21):
    props = [
        '<w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/>',
        f'<w:color w:val="{color}"/>',
        f'<w:sz w:val="{size}"/>',
        f'<w:szCs w:val="{size}"/>',
    ]
    if bold:
        props.insert(0, "<w:b/>")
    if italic:
        props.insert(0, "<w:i/>")
    return f"<w:r><w:rPr>{''.join(props)}</w:rPr><w:t xml:space=\"preserve\">{x(text)}</w:t></w:r>"


def p(text="", style=None, align=None, bold=False, italic=False, color=TEXT, size=21, before=0, after=120, indent=None, page_before=False):
    pr = []
    if style:
        pr.append(f'<w:pStyle w:val="{style}"/>')
    if page_before:
        pr.append("<w:pageBreakBefore/>")
    if align:
        pr.append(f'<w:jc w:val="{align}"/>')
    if before or after:
        pr.append(f'<w:spacing w:before="{before}" w:after="{after}" w:line="260" w:lineRule="auto"/>')
    if indent:
        pr.append(f'<w:ind w:left="{indent}"/>')
    return f"<w:p><w:pPr>{''.join(pr)}</w:pPr>{r(text, bold=bold, italic=italic, color=color, size=size)}</w:p>"


def heading(text, level=1, page_before=False):
    return p(
        text,
        style=f"Heading{level}",
        bold=True,
        color=NAVY if level == 1 else BLUE,
        size=30 if level == 1 else 24,
        before=220 if level == 1 else 140,
        after=90,
        page_before=page_before,
    )


def page_break():
    return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'


def spacer(after=1200):
    return p(" ", color=WHITE, size=2, after=after)


def cell(text, width=2400, fill=WHITE, bold=False, color=TEXT, size=18, align="left"):
    margins = (
        '<w:tcMar>'
        '<w:top w:w="80" w:type="dxa"/><w:left w:w="100" w:type="dxa"/>'
        '<w:bottom w:w="80" w:type="dxa"/><w:right w:w="100" w:type="dxa"/>'
        "</w:tcMar>"
    )
    borders = (
        '<w:tcBorders>'
        '<w:top w:val="single" w:sz="6" w:space="0" w:color="D9E2EC"/>'
        '<w:left w:val="single" w:sz="6" w:space="0" w:color="D9E2EC"/>'
        '<w:bottom w:val="single" w:sz="6" w:space="0" w:color="D9E2EC"/>'
        '<w:right w:val="single" w:sz="6" w:space="0" w:color="D9E2EC"/>'
        "</w:tcBorders>"
    )
    return (
        "<w:tc>"
        f'<w:tcPr><w:tcW w:w="{width}" w:type="dxa"/><w:shd w:fill="{fill}"/><w:vAlign w:val="center"/>{margins}{borders}</w:tcPr>'
        f'{p(text, align=align, bold=bold, color=color, size=size, after=0)}'
        "</w:tc>"
    )


def table(headers, rows, widths=None, header_fill=BLUE, font_size=18):
    widths = widths or [2400] * len(headers)
    grid = "".join(f'<w:gridCol w:w="{w}"/>' for w in widths)
    tbl_pr = (
        '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLook w:val="04A0"/></w:tblPr>'
        f"<w:tblGrid>{grid}</w:tblGrid>"
    )
    out = ["<w:tbl>", tbl_pr]
    out.append("<w:tr><w:trPr><w:tblHeader/></w:trPr>" + "".join(cell(h, widths[i], header_fill, True, WHITE, font_size) for i, h in enumerate(headers)) + "</w:tr>")
    for idx, row in enumerate(rows):
        fill = LIGHT_GREY if idx % 2 else WHITE
        out.append("<w:tr>" + "".join(cell(row[i], widths[i], fill, False, TEXT, font_size) for i in range(len(headers))) + "</w:tr>")
    out.append("</w:tbl>")
    out.append(p("", after=80))
    return "".join(out)


def callout(title, body, fill=LIGHT_BLUE):
    text = f"{title}: {body}"
    return table(["Progress Note"], [[text]], widths=[9000], header_fill=GOLD, font_size=18).replace(f'<w:shd w:fill="{WHITE}"/>', f'<w:shd w:fill="{fill}"/>')


def bullets(items):
    return "".join(p("- " + item, indent=420, after=45, size=20) for item in items)


def styles_xml():
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docDefaults>
    <w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="21"/><w:color w:val="{TEXT}"/></w:rPr></w:rPrDefault>
    <w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="260" w:lineRule="auto"/></w:pPr></w:pPrDefault>
  </w:docDefaults>
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>
  <w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:rPr><w:b/><w:color w:val="{NAVY}"/><w:sz w:val="40"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="9"/><w:qFormat/><w:rPr><w:b/><w:color w:val="{NAVY}"/><w:sz w:val="30"/></w:rPr></w:style>
  <w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="9"/><w:qFormat/><w:rPr><w:b/><w:color w:val="{BLUE}"/><w:sz w:val="24"/></w:rPr></w:style>
</w:styles>'''


def footer_xml():
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p>
    <w:pPr><w:jc w:val="center"/></w:pPr>
    {r("UIRI Inventory Management System Progress Report | Page ", color=MUTED, size=16)}
    <w:r><w:fldChar w:fldCharType="begin"/></w:r>
    <w:r><w:instrText xml:space="preserve">PAGE</w:instrText></w:r>
    <w:r><w:fldChar w:fldCharType="end"/></w:r>
  </w:p>
</w:ftr>'''


def document_xml(body):
    sect = (
        '<w:sectPr>'
        '<w:footerReference w:type="default" r:id="rId1"/>'
        '<w:pgSz w:w="11906" w:h="16838"/>'
        '<w:pgMar w:top="850" w:right="900" w:bottom="850" w:left="900" w:header="567" w:footer="567" w:gutter="0"/>'
        "</w:sectPr>"
    )
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:body>{body}{sect}</w:body>
</w:document>'''


def rels_xml():
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>'''


def doc_rels_xml():
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
</Relationships>'''


def content_types_xml():
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>'''


def core_xml():
    now = datetime.utcnow().strftime("%Y-%m-%dT%H:%M:%SZ")
    return f'''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>UIRI IMS Progress Report</dc:title>
  <dc:creator>OpenAI Codex</dc:creator>
  <cp:lastModifiedBy>OpenAI Codex</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">{now}</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">{now}</dcterms:modified>
</cp:coreProperties>'''


def app_xml():
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>OpenAI Codex</Application>
</Properties>'''


def settings_xml():
    return '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:defaultTabStop w:val="720"/>
</w:settings>'''


def build_body():
    b = []
    b.append(p("MAKERERE UNIVERSITY", align="center", bold=True, color=NAVY, size=31, after=40))
    b.append(p("Bachelor of Science in Computer Science", align="center", color=TEXT, size=22, after=220))
    b.append(p("UGANDA INDUSTRIAL RESEARCH INSTITUTE", align="center", bold=True, color=BLUE, size=29, after=20))
    b.append(p("Namanve Industrial and Business Park, Mukono District, Uganda", align="center", color=MUTED, size=20, after=260))
    b.append(p("PROGRESS REPORT", align="center", bold=True, color=NAVY, size=42, after=80))
    b.append(p("Design and Development of a Web Based Inventory Management System for Uganda Industrial Research Institute (UIRI)", align="center", bold=True, color=BLUE, size=27, after=70))
    b.append(p("Nakawa and Namanve Campuses", align="center", color=TEXT, size=22, after=260))
    b.append(table(
        ["Name", "Registration Number", "Project Responsibility"],
        [
            ["Keith Paul Kato", "24/U/26593/EVE", "Lead reporting, database design and documentation coordination"],
            ["Komukama Tracy", "24/U/06151/EXT", "Requirements review, user needs analysis and supporting documentation"],
            ["Atuhiire Deo Kamate", "24/U/14365/PS", "System analysis, testing support and validation of database design decisions"],
        ],
        widths=[2600, 2100, 5000],
        header_fill=NAVY,
        font_size=17,
    ))
    b.append(table(
        ["Project Detail", "Description"],
        [
            ["Host Organisation", "Uganda Industrial Research Institute (UIRI)"],
            ["Host Department", "Information and Communications Technology (ICT)"],
            ["Field Supervisor", "Mr. William Kakooza, IT Engineer at UIRI"],
            ["Prepared For", "ICT Team, UIRI Namanve"],
            ["Report Type", "Internship project progress report"],
            ["Reporting Period", "June-July 2026"],
            ["Preparation Date", "16 July 2026"],
            ["Development Environment", "PHP, MySQL/MariaDB, HTML, CSS, JavaScript and XAMPP"],
        ],
        widths=[2800, 6900],
        header_fill=GOLD,
        font_size=17,
    ))
    b.append(p("Submitted in reference to the ICT Team, Namanve internship project question.", align="center", italic=True, color=MUTED, size=19))
    b.append(spacer(5000))
    b.append(heading("Document Control", 1, page_before=True))
    b.append(table(
        ["Item", "Description"],
        [
            ["Document Title", "Progress Report for the UIRI Inventory Management System"],
            ["Document Version", "1.0"],
            ["Document Basis", "Project brief, proposal, SRS, database design, system design diagrams, source code and live database snapshot"],
            ["Main Purpose", "To report the current state of analysis, design, database implementation, coding, testing evidence, challenges and remaining work"],
            ["Current Project Status", "Working PHP/MySQL prototype with implemented database, inventory, stock, reports, dashboard, audit and security features"],
        ],
        widths=[2700, 7000],
        header_fill=NAVY,
        font_size=17,
    ))
    b.append(heading("Contents", 1))
    b.append(bullets([
        "1.0 Executive Summary",
        "2.0 Introduction and Background",
        "3.0 Objectives and Progress Status",
        "4.0 Methodology and Technical Approach",
        "5.0 Progress Against Deliverables",
        "6.0 Database Design and Implementation Progress",
        "7.0 Functional Progress Against the Project Brief",
        "8.0 Testing and Validation Evidence",
        "9.0 Challenges, Changes and Mitigation",
        "10.0 Remaining Work Plan",
        "11.0 Conclusion",
        "12.0 References",
        "Appendix A: Live Database Snapshot",
    ]))

    b.append(heading("1.0 Executive Summary", 1))
    b.append(p("This progress report presents the current state of the internship project titled Design and Development of a Web Based Inventory Management System for Uganda Industrial Research Institute (UIRI). The report follows a Makerere-style academic progress-report structure by linking the original project question, proposal, SRS, database design, system design diagrams and current implementation evidence."))
    b.append(p("The project has progressed from proposal and requirements analysis into a working PHP/MySQL prototype. The implemented system now supports authentication, role based access, branch/campus structure, departments and sections, inventory item registration, item image upload, suppliers, stock-in, stock-out, stock adjustment, requests, transfers, notifications, dashboards, analytics, reports, audit logs and security hardening."))
    b.append(p("The live database named uiri_ims currently contains 24 tables, including 2 branches, 39 departments, 111 sections/units, 70 categories, 1,043 inventory items, 68 stock transactions, 20 inventory requests, 15 users and 283 audit log entries. These figures show that the database is no longer only a proposed design; it has been implemented and populated with realistic demonstration data for testing and reporting."))
    b.append(callout("Current Assessment", "The project is substantially implemented as a prototype. The strongest areas are inventory records, stock movement, dashboard reporting, database population and security. The main remaining work is user acceptance testing, completion of user manual and final report, improved asset-instance normalization, more serial-number data, SMTP configuration and presentation preparation.", fill=LIGHT_GOLD))

    b.append(heading("2.0 Introduction and Background", 1))
    b.append(p("Uganda Industrial Research Institute operates in an environment where ICT equipment, laboratory equipment, engineering tools, office supplies, furniture, machinery, consumables and safety equipment must be recorded, monitored and reported accurately. The original project question required a web based Inventory Management System capable of authentication, inventory management, stock management, supplier management, reporting, search, filtering, database design and security."))
    b.append(p("The preliminary documents identified the main problem as the difficulty of obtaining a reliable and quick picture of what UIRI owns, where items are located, their current condition and how stock has moved over time. The proposal and SRS therefore emphasized centralized records, secure access, current stock visibility, low stock alerts, supplier transaction history and reports for management decision making."))
    b.append(p("The implementation has followed the approved database and web application direction, using PHP, MySQL/MariaDB and XAMPP. During coding, the physical schema was adjusted to support a practical prototype with branch-specific categories, section and department hierarchy, item-level asset decision fields and reporting views suitable for demonstration."))

    b.append(heading("3.0 Objectives and Progress Status", 1, page_before=True))
    b.append(table(
        ["Objective", "Progress Status", "Evidence"],
        [
            ["Study the inventory problem and identify users, records, reports and rules.", "Complete", "Proposal and SRS define problem, stakeholders, scope, user classes, functional requirements and acceptance criteria."],
            ["Design a normalized relational database.", "Complete with implementation refinement", "Database design exists; SQL schema and live MySQL database contain users, roles, branches, sections, departments, categories, suppliers, inventory_items, stock_transactions and audit tables."],
            ["Implement secure authentication and role based access control.", "Implemented", "Login, roles, permissions, session controls, password hashing, account lockout, rate limiting and audit logging are present in code and database."],
            ["Enable inventory item registration, editing, classification and tracking.", "Implemented", "Item CRUD, categories, images, supplier link, serial number, asset code, brand/model, status, condition, funding source and storage location fields are implemented."],
            ["Support stock receipt, issue, transfer, return and low-stock alerts.", "Substantially implemented", "Stock-in, stock-out, stock adjustment, transaction history, transfer workflow and low-stock notifications exist. Return handling is not yet a separate visible workflow."],
            ["Provide supplier management and supplier transaction history.", "Implemented", "Supplier CRUD is implemented; supplier-based filtering is available in inventory and reports."],
            ["Generate dashboard summaries and reports.", "Implemented", "Dashboard, analytics workspace, printable reports, low-stock report, valuation report, movement report and CSV exports are implemented."],
            ["Enforce validation, password protection, sessions and audit logs.", "Implemented", "CSRF helper, prepared SQL statements, clean output helper, security headers, login history, rate limits and audit_log are implemented."],
        ],
        widths=[3300, 2150, 4250],
        header_fill=NAVY,
        font_size=16,
    ))

    b.append(heading("4.0 Methodology and Technical Approach", 1))
    b.append(p("The project followed an incremental database-driven approach. The first stage involved problem analysis and requirements documentation. The second stage converted requirements into a database design, data dictionary and system design diagrams. The third stage implemented the application using PHP pages, shared configuration and helper functions, MySQL tables and XAMPP for local development."))
    b.append(bullets([
        "Requirements analysis was guided by the ICT Team project question, the system proposal and the SRS.",
        "Database work used a relational MySQL/MariaDB design with primary keys, foreign keys, indexes and controlled status values.",
        "Implementation used procedural PHP modules organized into pages, shared includes, upload folders, migrations and SQL seed files.",
        "Security work used password hashing, prepared statements, session hardening, CSRF tokens, security headers, login history, rate limiting and audit logging.",
        "Testing evidence was collected from the live uiri_ims database, implemented pages, migrations and source-code inspection.",
    ]))

    b.append(heading("5.0 Progress Against Deliverables", 1))
    b.append(table(
        ["Deliverable", "Current Status", "Progress Note"],
        [
            ["System Proposal", "Complete", "Located in documentation/proposal and final_report. Defines problem, objectives, scope, methodology and expected deliverables."],
            ["System Requirements Specification", "Complete", "Located in documentation/srs. Defines functional, non-functional, database and acceptance requirements."],
            ["Database Design, ERD and Data Dictionary", "Complete", "Located in documentation/database_design. Current implementation refined some table names and combined some asset/stock fields for prototype delivery."],
            ["System Design Diagrams", "Complete", "Located in documentation/system_design. Includes use case, activity and class design coverage."],
            ["Source Code", "Substantially complete", "PHP pages, includes, assets, migrations, SQL seed files and upload support are present."],
            ["Fully Functional Inventory Management System", "Working prototype", "Core modules run against the live uiri_ims database. Additional data cleanup and UAT remain."],
            ["User Manual", "Pending", "Needs to be written after screens and workflows stabilize."],
            ["Final Project Report", "Pending/In progress", "This progress report prepares the implementation evidence needed for the final report."],
            ["Presentation and Demonstration", "Pending", "Database and system screens are ready for demonstration; presentation slides still need final assembly."],
        ],
        widths=[2900, 2100, 4700],
        header_fill=BLUE,
        font_size=16,
    ))

    b.append(heading("6.0 Database Design and Implementation Progress", 1, page_before=True))
    b.append(p("The original database design proposed a strongly normalized schema with separate entities for campuses, departments, locations, inventory categories, units of measure, item types, inventory items, suppliers, supplier_items, acquisition_batches, asset_statuses, asset_instances, stock_balances, stock_transactions, requisitions, maintenance_records, audit_logs and report_logs."))
    b.append(p("The implemented database keeps the same core intention but uses a practical prototype structure. For example, campuses are represented as branches, inventory locations are partly represented through section, department and storage_location fields, while current stock and key asset decision fields are stored directly on inventory_items. This makes the prototype easier to demonstrate while still preserving transaction history through stock_transactions."))
    b.append(heading("6.1 Live Database Snapshot", 2))
    b.append(table(
        ["Database Area", "Current Evidence"],
        [
            ["Database name", "uiri_ims"],
            ["Core table count", "24 tables"],
            ["Branches/campuses", "2: UIRI Nakawa and UIRI Namanve"],
            ["Departments and sections/units", "39 sections and 111 departments/units"],
            ["Categories", "70 category records: 33 for Nakawa and 37 for Namanve"],
            ["Inventory items", "1,043 active inventory records"],
            ["Stock units and value", "8,484 units with a current stock value of UGX 21,205,901,200"],
            ["Stock movement history", "68 stock transactions: 42 stock-in, 22 stock-out, 2 transfer-in and 2 transfer-out"],
            ["Low-stock exposure", "425 items at or below the minimum stock threshold"],
            ["Users and roles", "15 users across 6 roles"],
            ["Audit evidence", "283 audit log entries and 90 login history records"],
            ["Security evidence", "rate_limits table exists; current rate limit count is 0 at snapshot time"],
        ],
        widths=[3300, 6400],
        header_fill=NAVY,
        font_size=17,
    ))
    b.append(heading("6.2 Implemented Schema Groups", 2))
    b.append(table(
        ["Schema Group", "Implemented Tables", "Purpose"],
        [
            ["Access control", "users, roles, permissions, role_permissions, user_permissions, login_history, rate_limits", "Controls login, roles, permissions, sign-in monitoring and brute-force protection."],
            ["Organizational structure", "branches, sections, departments", "Represents UIRI Nakawa/Namanve and their departments or units."],
            ["Inventory master data", "categories, suppliers, inventory_items", "Stores item classifications, suppliers and item records with asset and stock fields."],
            ["Stock workflow", "stock_transactions, inventory_requests, transfers, transfer_items", "Records stock movement, request approvals and inter-branch transfer workflow."],
            ["Reporting and operations", "reports, notifications, settings, audit_log", "Supports report metadata, alerts, application settings and accountability logs."],
            ["Extended workflow", "procurement_requests, purchase_orders, goods_received_notes, equipment_maintenance", "Adds procurement and maintenance support beyond the minimum project brief."],
        ],
        widths=[2300, 3700, 3700],
        header_fill=BLUE,
        font_size=15,
    ))
    b.append(heading("6.3 Implementation Changes From Preliminary Design", 2, page_before=True))
    b.append(table(
        ["Preliminary Design Idea", "Current Implementation", "Progress Report Interpretation"],
        [
            ["campuses table", "branches table", "The naming changed, but the same concept is implemented for Nakawa and Namanve."],
            ["Separate locations table", "sections, departments and inventory_items.storage_location", "The prototype captures organizational placement and storage location without a full location master table."],
            ["asset_instances table", "asset fields on inventory_items, including asset_code, serial_number, asset_status and asset_condition", "Acceptable for prototype; future version should separate individual asset instances for stronger normalization."],
            ["stock_balances table", "inventory_items.current_stock and stock_transactions history", "Current stock is fast to read, while movement history is still preserved. A future stock_balances table can improve multi-location quantities."],
            ["report_logs table", "audit_log records report generation; reports table exists but has no saved rows yet", "Report access is audited, but saved report metadata is still incomplete."],
            ["Chart.js graphs", "CSS/SVG dashboard visuals and tabular reports", "Management visuals are present; dedicated Chart.js graphs remain optional enhancement."],
            ["Executive role support", "Code references Administrator/Executive, but live roles table currently has six roles and no Executive role", "Migration exists; live DB should be aligned before final demonstration if Executive view is required."],
        ],
        widths=[2800, 3300, 3600],
        header_fill=NAVY,
        font_size=15,
    ))

    b.append(heading("7.0 Functional Progress Against the Project Brief", 1))
    b.append(table(
        ["Project Requirement", "Current Progress", "Evidence From Repository and Database"],
        [
            ["User authentication and authorization", "Implemented", "Login, registration, password hashing, roles, permissions, admin user management, profile photos, login history and security controls are present."],
            ["Inventory management", "Implemented", "items.php supports item management, categorization, image upload, asset code, serial number, brand/model, asset status, condition and print views."],
            ["Stock management", "Substantially implemented", "stock_in.php, stock_out.php, stock_adjustment.php and transactions.php record stock movement and update current stock. Low-stock notifications are implemented."],
            ["Supplier management", "Implemented", "suppliers.php supports supplier add, edit, status toggle and deletion safeguards; reports can filter by supplier."],
            ["Reporting dashboard", "Implemented", "dashboard.php and analytics.php provide summary KPIs, stock risk, request queues, movement visuals, supplier exposure, campus comparison and decision signals."],
            ["Monthly and annual reports", "Partly implemented", "reports.php supports date filters, movement report, valuation, low-stock report, print mode and CSV export. Annual views can be produced through date range filters."],
            ["Search and filtering", "Implemented", "Items, reports, transactions, login history and notifications include filters and search fields."],
            ["Database design", "Implemented", "database.sql, migrations and live uiri_ims database are available. Data dictionary exists in documentation."],
            ["Security features", "Implemented", "Input validation, prepared statements, CSRF helper, output escaping, session hardening, email verification, rate limiting, security headers and audit logging are present."],
        ],
        widths=[2850, 2100, 4750],
        header_fill=BLUE,
        font_size=15,
    ))

    b.append(heading("8.0 Testing and Validation Evidence", 1, page_before=True))
    b.append(p("Testing at this progress stage focused on verifying that the database exists, implemented tables can be queried, and source modules correspond to the requirements in the SRS and project brief. The live database was queried through XAMPP PHP using the same root credentials defined in includes/config.php."))
    b.append(table(
        ["Test Area", "Evidence", "Current Result"],
        [
            ["Database connection", "PDO connection to localhost/uiri_ims", "Successful"],
            ["Schema completeness", "SHOW TABLES and table counts", "24 tables present"],
            ["Inventory data", "COUNT inventory_items and stock summary", "1,043 items and 8,484 units"],
            ["Stock transaction history", "Grouped stock_transactions by transaction_type", "Stock-in, stock-out and transfer records exist"],
            ["Access control data", "Roles, users and permissions tables queried", "6 roles, 15 users and 34 role-permission mappings"],
            ["Audit trail", "audit_log count and login_history count", "283 audit entries and 90 login history records"],
            ["Report functions", "Source inspection of reports.php, dashboard.php and analytics.php", "Reports, filters, print views and CSV export are implemented"],
        ],
        widths=[2800, 3900, 3000],
        header_fill=NAVY,
        font_size=16,
    ))
    b.append(callout("Testing Gap", "Formal user acceptance testing, browser-by-browser screenshots, complete security test logs and a final checklist still need to be added before the final report and demonstration.", fill=WARN))

    b.append(heading("9.0 Challenges, Changes and Mitigation", 1))
    b.append(table(
        ["Challenge or Change", "Effect on Project", "Mitigation or Recommendation"],
        [
            ["Preliminary database design was more normalized than the working prototype.", "Some planned entities, such as asset_instances and stock_balances, are not separate live tables.", "Document the prototype decision and add these tables in a future refinement if individual asset tracking becomes the main assessment focus."],
            ["Real UIRI inventory data may be incomplete or sensitive.", "Demonstration data has to be used for some screens and reports.", "Continue using realistic dummy/demo records while keeping real institutional data protected."],
            ["Many items lack serial numbers or purchase dates.", "Reports on acquisition year, warranty and asset aging are less complete.", "Prioritize data cleaning for serial_number, purchase_date, brand_model, asset_condition and storage_location."],
            ["Transfers and procurement structures exist but live transfer/procurement records are empty.", "Demonstration may not show those workflows with real examples.", "Seed a small number of approved demonstration transfers and procurement records before presentation."],
            ["SMTP/PHPMailer setup may not be fully configured.", "Email verification and reset links may depend on fallback display during local demo.", "Run composer install if needed and configure SMTP in settings before final deployment."],
            ["Reports table exists but report rows are not saved.", "Report generation is audited, but saved report metadata is incomplete.", "Either populate reports table on report generation or explain that audit_log currently provides accountability."],
        ],
        widths=[3100, 3300, 3300],
        header_fill=BLUE,
        font_size=15,
    ))

    b.append(heading("10.0 Remaining Work Plan", 1))
    b.append(table(
        ["Task", "Priority", "Expected Output"],
        [
            ["Complete user manual with login, item management, stock in/out, reports and admin workflows.", "High", "User manual ready for submission."],
            ["Run end-to-end module testing for authentication, item CRUD, stock in, stock out, adjustment, reports and audit logs.", "High", "Test results table and screenshots for final report."],
            ["Clean demonstration data by adding serial numbers, purchase dates, item images and asset conditions.", "High", "Stronger report evidence for computers, equipment and asset status."],
            ["Align live roles with code, especially the optional Executive role if needed.", "Medium", "Consistent role list before demonstration."],
            ["Seed transfer, maintenance and procurement demonstration records.", "Medium", "Complete workflow examples for presentation."],
            ["Decide whether to implement separate asset_instances and stock_balances now or document them as future enhancements.", "High", "Clear database-design explanation for assessors."],
            ["Configure SMTP/PHPMailer or prepare fallback demonstration steps.", "Medium", "Reliable email verification and password reset demo."],
            ["Prepare final report and presentation slides.", "High", "Final submission package and demonstration flow."],
        ],
        widths=[4400, 1650, 3650],
        header_fill=NAVY,
        font_size=16,
    ))

    b.append(heading("11.0 Conclusion", 1))
    b.append(p("The UIRI Inventory Management System has moved from requirements analysis and database design into a working database-backed PHP prototype. The current system already satisfies most of the core project requirements: secure access, inventory item management, supplier management, stock movement, reporting, dashboard summaries, search/filtering, low-stock monitoring and audit logging."))
    b.append(p("The most important academic point to document in the final report is the difference between the preliminary ideal database design and the implemented prototype schema. The implementation remains suitable for demonstration, but the final report should clearly explain that some highly normalized asset and stock-balance entities were simplified into inventory_items for the prototype. With additional testing, data cleaning and user documentation, the project will be ready for final assessment and demonstration."))

    b.append(heading("12.0 References", 1))
    refs = [
        "[1] ICT Team, UIRI Namanve, Internship Project Question: Design and Development of an Inventory Management System for Uganda Industrial Research Institute, 2026.",
        "[2] K. P. Kato, K. Tracy and A. D. Kamate, System Proposal: Design and Development of a Web Based Inventory Management System for UIRI, 2026.",
        "[3] K. P. Kato, K. Tracy and A. D. Kamate, Software Requirements Specification for the UIRI Inventory Management System, 2026.",
        "[4] K. P. Kato, K. Tracy and A. D. Kamate, Database Design Document and Data Dictionary for the UIRI Inventory Management System, 2026.",
        "[5] K. P. Kato, K. Tracy and A. D. Kamate, System Design Diagrams for the UIRI Inventory Management System, 2026.",
        "[6] UIRI IMS repository source code, database.sql, migrations, SECURITY_IMPROVEMENTS.md and live uiri_ims database snapshot, accessed 16 July 2026.",
        "[7] T. M. Connolly and C. E. Begg, Database Systems: A Practical Approach to Design, Implementation, and Management, 4th ed., Pearson Education, 2005.",
        "[8] IEEE Computer Society, IEEE Recommended Practice for Software Requirements Specifications, IEEE Std 830-1998, 1998.",
    ]
    for ref in refs:
        b.append(p(ref, indent=360, size=19, after=70))

    b.append(heading("Appendix A: Live Database Snapshot", 1))
    b.append(table(
        ["Table", "Rows", "Table", "Rows"],
        [
            ["audit_log", "283", "branches", "2"],
            ["categories", "70", "departments", "111"],
            ["equipment_maintenance", "3", "goods_received_notes", "0"],
            ["inventory_items", "1,043", "inventory_requests", "20"],
            ["login_history", "90", "notifications", "11"],
            ["permissions", "16", "procurement_requests", "0"],
            ["purchase_orders", "0", "rate_limits", "0"],
            ["reports", "0", "role_permissions", "34"],
            ["roles", "6", "sections", "39"],
            ["settings", "4", "stock_transactions", "68"],
            ["suppliers", "13", "transfer_items", "0"],
            ["transfers", "0", "user_permissions", "0"],
            ["users", "15", "", ""],
        ],
        widths=[2600, 1600, 2600, 1600],
        header_fill=BLUE,
        font_size=16,
    ))
    b.append(p("Snapshot source: live uiri_ims database queried through XAMPP PHP on 16 July 2026. The snapshot should be regenerated before final submission if new records are added."))
    return "".join(b)


def write_docx():
    OUT.parent.mkdir(parents=True, exist_ok=True)
    body = build_body()
    files = {
        "[Content_Types].xml": content_types_xml(),
        "_rels/.rels": rels_xml(),
        "docProps/core.xml": core_xml(),
        "docProps/app.xml": app_xml(),
        "word/document.xml": document_xml(body),
        "word/styles.xml": styles_xml(),
        "word/settings.xml": settings_xml(),
        "word/footer1.xml": footer_xml(),
        "word/_rels/document.xml.rels": doc_rels_xml(),
    }
    with ZipFile(OUT, "w", ZIP_DEFLATED) as zf:
        for name, data in files.items():
            zf.writestr(name, data)
    print(OUT)


if __name__ == "__main__":
    write_docx()
