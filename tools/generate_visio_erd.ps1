# ==============================================================================
# UIRI IMS - Conceptual ERD Generator for Microsoft Visio
# ==============================================================================
# This script uses local Microsoft Visio (COM Automation) to construct a
# comprehensive Conceptual Entity-Relationship Diagram (ERD) of the UIRI IMS
# database schema and exports the results to the documentation directory.
# ==============================================================================

$ErrorActionPreference = "Stop"

$DocDir = "c:\xampp\htdocs\uiri-ims\documentation"
if (-not (Test-Path -Path $DocDir)) {
    New-Item -ItemType Directory -Path $DocDir -Force | Out-Null
}

$VsdxPath = Join-Path $DocDir "UIRI_IMS_Conceptual_ERD.vsdx"
$PdfPath  = Join-Path $DocDir "UIRI_IMS_Conceptual_ERD.pdf"
$PngPath  = Join-Path $DocDir "UIRI_IMS_Conceptual_ERD.png"

Write-Host "Starting Microsoft Visio COM Automation (Version check)..." -ForegroundColor Cyan

try {
    $visio = New-Object -ComObject Visio.Application
    $visio.Visible = $false # Run cleanly in background
    Write-Host "Connected to Visio Version: $($visio.Version)" -ForegroundColor Green

    # Create new blank document
    $doc = $visio.Documents.Add("")
    $page = $visio.ActivePage
    $page.Name = "Conceptual ERD"

    # Set page size: 24 in x 17 in (Large Ledger dimensions for generous spacing)
    $page.PageSheet.CellsU("PageWidth").FormulaU  = "24 in"
    $page.PageSheet.CellsU("PageHeight").FormulaU = "17 in"

    # Try loading connector stencil or master
    $connectorMaster = $null
    $stencilNames = @("BASIC_U.vssx", "BASIC_M.vssx", "CONNEC_U.vssx", "DBMODL_U.vssx")
    foreach ($sName in $stencilNames) {
        try {
            $st = $visio.Documents.OpenEx($sName, 64) # 64 = visOpenDocked/Hidden
            if ($st) {
                foreach ($m in $st.Masters) {
                    if ($m.NameU -like "*Dynamic connector*" -or $m.Name -like "*Dynamic connector*") {
                        $connectorMaster = $m
                        break
                    }
                }
                if ($connectorMaster) { break }
            }
        } catch {}
    }

    # Helper function to draw two-tone Entity Card
    function Add-EntityCard {
        param(
            [string]$Name,
            [string[]]$Attributes,
            [double]$X,
            [double]$Y,
            [double]$Width = 2.4,
            [string]$HeaderColor = "RGB(30, 58, 138)",
            [string]$BodyColor   = "RGB(239, 246, 255)",
            [string]$BorderColor = "RGB(59, 130, 246)"
        )

        $attrText = ($Attributes | ForEach-Object { "• $_" }) -join "`r`n"
        $lines = $Attributes.Count
        if ($lines -lt 3) { $lines = 3 }
        $bodyHeight = ($lines * 0.22) + 0.3
        $headerHeight = 0.42

        # 1. Body Box (outer boundary & attributes container)
        $bodyLeft   = $X - ($Width / 2)
        $bodyRight  = $X + ($Width / 2)
        $bodyTop    = $Y + ($bodyHeight / 2)
        $bodyBottom = $Y - ($bodyHeight / 2)

        $bodyShape = $page.DrawRectangle($bodyLeft, $bodyBottom, $bodyRight, $bodyTop)
        $bodyShape.CellsU("FillForegnd").FormulaU = $BodyColor
        $bodyShape.CellsU("LineColor").FormulaU   = $BorderColor
        $bodyShape.CellsU("LineWeight").FormulaU  = "1.5 pt"
        $bodyShape.CellsU("Rounding").FormulaU    = "0.08 in"
        $bodyShape.CellsU("VerticalAlign").FormulaU = "0" # Top align text inside body
        $bodyShape.CellsU("TopMargin").FormulaU   = "0.48 in" # Leave space below header
        $bodyShape.CellsU("LeftMargin").FormulaU  = "0.12 in"
        $bodyShape.CellsU("RightMargin").FormulaU = "0.12 in"
        $bodyShape.CellsU("BottomMargin").FormulaU = "0.12 in"
        
        $bodyShape.Text = $attrText
        $bodyShape.CellsU("Char.Size").FormulaU = "9.5 pt"
        $bodyShape.CellsU("Char.Color").FormulaU = "RGB(30, 41, 59)"

        # 2. Header Box (on top of body)
        $headerBottom = $bodyTop - $headerHeight
        $headerShape = $page.DrawRectangle($bodyLeft, $headerBottom, $bodyRight, $bodyTop)
        $headerShape.CellsU("FillForegnd").FormulaU = $HeaderColor
        $headerShape.CellsU("LineColor").FormulaU   = $BorderColor
        $headerShape.CellsU("LineWeight").FormulaU  = "1.5 pt"
        $headerShape.CellsU("Rounding").FormulaU    = "0.08 in"
        $headerShape.Text = $Name.ToUpper()
        $headerShape.CellsU("Char.Size").FormulaU  = "11 pt"
        $headerShape.CellsU("Char.Color").FormulaU = "RGB(255, 255, 255)"
        try { $headerShape.Characters.CharProps(1) = 17 } catch {}

        # Return the body shape as the anchor for connectors
        return $bodyShape
    }

    # Helper function to connect entities
    function Add-Relationship {
        param(
            $ShapeFrom,
            $ShapeTo,
            [string]$Label = "1 : M",
            [string]$Verb  = "",
            [string]$Color = "RGB(100, 116, 139)"
        )

        $conn = $null
        if ($connectorMaster) {
            $conn = $page.Drop($connectorMaster, 0, 0)
        } else {
            # Draw line and convert to dynamic connector
            $conn = $page.DrawLine(0, 0, 1, 1)
            $conn.CellsU("ObjType").FormulaU = "2" # visTypeDynamicConnector
        }

        # Glue begin to ShapeFrom and end to ShapeTo
        $conn.CellsU("BeginX").GlueTo($ShapeFrom.CellsU("PinX"))
        $conn.CellsU("EndX").GlueTo($ShapeTo.CellsU("PinX"))

        # Styling
        $conn.CellsU("LineColor").FormulaU  = $Color
        $conn.CellsU("LineWeight").FormulaU = "1.5 pt"
        $conn.CellsU("BeginArrow").FormulaU = "0"  # No arrow at source (1 side)
        $conn.CellsU("EndArrow").FormulaU   = "13" # Open arrow at destination (M side)

        # Label text
        $fullText = if ($Verb -ne "") { "$Verb (`r`n$Label)" } else { $Label }
        $conn.Text = $fullText
        $conn.CellsU("Char.Size").FormulaU  = "8.5 pt"
        $conn.CellsU("Char.Color").FormulaU = "RGB(51, 65, 85)"
        $conn.CellsU("TextBkgnd").FormulaU  = "2" # visTxtBlkOpaque
        $conn.CellsU("TextBkgndTrans").FormulaU = "0 %"

        return $conn
    }

    # ==========================================================================
    # 0. TITLE & LEGEND BANNER
    # ==========================================================================
    Write-Host "Drawing Title Banner & Legend..."
    $titleBox = $page.DrawRectangle(1.5, 15.6, 13.5, 16.5)
    $titleBox.CellsU("FillForegnd").FormulaU = "RGB(15, 23, 42)"
    $titleBox.CellsU("LineColor").FormulaU   = "RGB(51, 65, 85)"
    $titleBox.CellsU("Rounding").FormulaU    = "0.1 in"
    $titleBox.Text = "UIRI INVENTORY MANAGEMENT SYSTEM`r`nCONCEPTUAL ENTITY-RELATIONSHIP DIAGRAM (ERD)"
    $titleBox.CellsU("Char.Size").FormulaU   = "15 pt"
    $titleBox.CellsU("Char.Color").FormulaU  = "RGB(255, 255, 255)"

    # Legend box
    $legBox = $page.DrawRectangle(14.2, 15.2, 22.5, 16.5)
    $legBox.CellsU("FillForegnd").FormulaU = "RGB(248, 250, 252)"
    $legBox.CellsU("LineColor").FormulaU   = "RGB(203, 213, 225)"
    $legBox.CellsU("Rounding").FormulaU    = "0.08 in"
    $legBox.CellsU("VerticalAlign").FormulaU = "0"
    $legBox.CellsU("LeftMargin").FormulaU  = "0.15 in"
    $legBox.CellsU("TopMargin").FormulaU   = "0.1 in"
    $legBox.Text = "DOMAIN ZONES & CARDINALITY LEGEND:`r`n• Organization & Security (Blue)  |  • Catalog & Inventory Master (Green)`r`n• Stock Operations & Transfers (Orange)  |  • Procurement & POs (Purple)`r`n• Equipment Maintenance & Audit (Slate)  |  • Relationships: 1 : M (One-to-Many), 1 : 1"
    $legBox.CellsU("Char.Size").FormulaU   = "9.5 pt"
    $legBox.CellsU("Char.Color").FormulaU  = "RGB(30, 41, 59)"

    # ==========================================================================
    # 1. ZONE 1: ORGANIZATION & ACCESS CONTROL (Left Column: X = 2.8 - 7.5)
    # ==========================================================================
    Write-Host "Creating Zone 1: Organization & Access Control Entities..."
    $eBranch = Add-EntityCard -Name "Branch" -Attributes @(
        "id (PK)", "name (Nakawa/Namanve)", "location / address", "is_headquarters (Boolean)", "phone / email"
    ) -X 3.2 -Y 13.5 -Width 2.6 -HeaderColor "RGB(30, 58, 138)" -BodyColor "RGB(239, 246, 255)" -BorderColor "RGB(59, 130, 246)"

    $eSection = Add-EntityCard -Name "Section" -Attributes @(
        "id (PK)", "branch_id (FK)", "name (Meat, Dairy, Bakery...)", "code / description", "is_active"
    ) -X 7.2 -Y 13.5 -Width 2.6 -HeaderColor "RGB(30, 58, 138)" -BodyColor "RGB(239, 246, 255)" -BorderColor "RGB(59, 130, 246)"

    $eDept = Add-EntityCard -Name "Department" -Attributes @(
        "id (PK)", "branch_id (FK)", "section_id (FK)", "name (Production, QC...)", "section/dept_manager_id"
    ) -X 7.2 -Y 10.4 -Width 2.6 -HeaderColor "RGB(30, 58, 138)" -BodyColor "RGB(239, 246, 255)" -BorderColor "RGB(59, 130, 246)"

    $eRole = Add-EntityCard -Name "Role" -Attributes @(
        "id (PK)", "name (Admin, Storekeeper...)", "description", "permissions_map (RBAC)"
    ) -X 2.8 -Y 10.4 -Width 2.5 -HeaderColor "RGB(30, 58, 138)" -BodyColor "RGB(239, 246, 255)" -BorderColor "RGB(59, 130, 246)"

    $eUser = Add-EntityCard -Name "User" -Attributes @(
        "id (PK)", "branch_id (FK)", "department_id (FK)", "role_id (FK)", "username / email", "password_hash / tokens", "is_active / last_login"
    ) -X 5.0 -Y 7.5 -Width 2.8 -HeaderColor "RGB(30, 58, 138)" -BodyColor "RGB(239, 246, 255)" -BorderColor "RGB(59, 130, 246)"

    # ==========================================================================
    # 2. ZONE 2: CATALOG & INVENTORY MASTER (Center Top: X = 11.5 - 17.5)
    # ==========================================================================
    Write-Host "Creating Zone 2: Catalog & Inventory Master Entities..."
    $eCategory = Add-EntityCard -Name "Category" -Attributes @(
        "id (PK)", "parent_id (Self FK)", "name / code", "description"
    ) -X 11.5 -Y 13.8 -Width 2.6 -HeaderColor "RGB(6, 95, 70)" -BodyColor "RGB(236, 253, 245)" -BorderColor "RGB(16, 185, 129)"

    $eSupplier = Add-EntityCard -Name "Supplier" -Attributes @(
        "id (PK)", "name / contact_person", "phone / email", "address / rating", "tax_id / terms"
    ) -X 16.8 -Y 13.8 -Width 2.6 -HeaderColor "RGB(6, 95, 70)" -BodyColor "RGB(236, 253, 245)" -BorderColor "RGB(16, 185, 129)"

    $eItem = Add-EntityCard -Name "Inventory Item" -Attributes @(
        "id (PK)", "item_code / sku / barcode", "name / description", "category_id (FK)", "branch_id (FK)", "supplier_id (FK)", "quantity / unit_of_measure", "reorder_level / min_stock", "unit_price / total_value"
    ) -X 14.2 -Y 10.6 -Width 3.0 -HeaderColor "RGB(6, 95, 70)" -BodyColor "RGB(236, 253, 245)" -BorderColor "RGB(16, 185, 129)"

    # ==========================================================================
    # 3. ZONE 3: STOCK OPERATIONS & MOVEMENTS (Center & Lower: X = 10.0 - 15.5)
    # ==========================================================================
    Write-Host "Creating Zone 3: Stock Operations & Movement Entities..."
    $eStockTx = Add-EntityCard -Name "Stock Transaction" -Attributes @(
        "id (PK)", "item_id (FK)", "branch_id (FK)", "user_id (FK)", "transaction_type (IN/OUT/ADJ)", "quantity / unit_cost", "reference_number", "notes / transaction_date"
    ) -X 14.2 -Y 6.8 -Width 2.9 -HeaderColor "RGB(154, 52, 18)" -BodyColor "RGB(255, 247, 237)" -BorderColor "RGB(249, 115, 22)"

    $eInvReq = Add-EntityCard -Name "Inventory Request" -Attributes @(
        "id (PK)", "user_id (FK)", "department_id (FK)", "item_id (FK)", "quantity_requested / approved", "status (Pending/Approved/Rejected)", "purpose / date_requested"
    ) -X 10.2 -Y 6.8 -Width 2.8 -HeaderColor "RGB(154, 52, 18)" -BodyColor "RGB(255, 247, 237)" -BorderColor "RGB(249, 115, 22)"

    $eTransfer = Add-EntityCard -Name "Transfer" -Attributes @(
        "id (PK)", "transfer_number", "source_branch_id (FK)", "dest_branch_id (FK)", "requested_by / approved_by", "status / transfer_date", "received_date / notes"
    ) -X 10.2 -Y 3.5 -Width 2.8 -HeaderColor "RGB(154, 52, 18)" -BodyColor "RGB(255, 247, 237)" -BorderColor "RGB(249, 115, 22)"

    $eTransItem = Add-EntityCard -Name "Transfer Item" -Attributes @(
        "id (PK)", "transfer_id (FK)", "item_id (FK)", "quantity_sent", "quantity_received"
    ) -X 14.2 -Y 3.5 -Width 2.6 -HeaderColor "RGB(154, 52, 18)" -BodyColor "RGB(255, 247, 237)" -BorderColor "RGB(249, 115, 22)"

    # ==========================================================================
    # 4. ZONE 4: PROCUREMENT & PURCHASING (Right Column: X = 20.0 - 21.5)
    # ==========================================================================
    Write-Host "Creating Zone 4: Procurement & Purchasing Entities..."
    $eProcReq = Add-EntityCard -Name "Procurement Request" -Attributes @(
        "id (PK)", "department_id (FK)", "requested_by (FK)", "item_name / estimated_cost", "quantity / justification", "status (Submitted/Approved)", "created_at"
    ) -X 20.5 -Y 12.0 -Width 2.8 -HeaderColor "RGB(88, 28, 135)" -BodyColor "RGB(250, 245, 255)" -BorderColor "RGB(168, 85, 247)"

    $ePO = Add-EntityCard -Name "Purchase Order" -Attributes @(
        "id (PK)", "po_number", "supplier_id (FK)", "procurement_request_id (FK)", "total_amount / tax", "status / order_date", "expected_delivery_date"
    ) -X 20.5 -Y 8.6 -Width 2.8 -HeaderColor "RGB(88, 28, 135)" -BodyColor "RGB(250, 245, 255)" -BorderColor "RGB(168, 85, 247)"

    $eGRN = Add-EntityCard -Name "Goods Received Note" -Attributes @(
        "id (PK)", "grn_number", "purchase_order_id (FK)", "received_by (FK)", "delivery_note_number", "condition / status", "received_date"
    ) -X 20.5 -Y 5.2 -Width 2.8 -HeaderColor "RGB(88, 28, 135)" -BodyColor "RGB(250, 245, 255)" -BorderColor "RGB(168, 85, 247)"

    # ==========================================================================
    # 5. ZONE 5: EQUIPMENT MAINTENANCE & AUDIT (Bottom Left: X = 4.0 - 6.5)
    # ==========================================================================
    Write-Host "Creating Zone 5: Equipment Maintenance & Audit Entities..."
    $eMaint = Add-EntityCard -Name "Equipment Maintenance" -Attributes @(
        "id (PK)", "item_id (FK: Equipment)", "maintenance_type / vendor", "scheduled_date / completed_date", "cost / technician_name", "findings / next_due_date"
    ) -X 18.2 -Y 2.2 -Width 2.9 -HeaderColor "RGB(51, 65, 85)" -BodyColor "RGB(248, 250, 252)" -BorderColor "RGB(148, 163, 184)"

    $eAudit = Add-EntityCard -Name "Audit & Session Logs" -Attributes @(
        "id (PK)", "user_id (FK)", "action / table_affected", "ip_address / user_agent", "old_values / new_values", "timestamp"
    ) -X 5.0 -Y 3.5 -Width 2.8 -HeaderColor "RGB(51, 65, 85)" -BodyColor "RGB(248, 250, 252)" -BorderColor "RGB(148, 163, 184)"

    # ==========================================================================
    # CONNECTING RELATIONSHIPS (Dynamic Connectors)
    # ==========================================================================
    Write-Host "Connecting conceptual relationships between entities..."

    # Organization & Access
    Add-Relationship -ShapeFrom $eBranch -ShapeTo $eSection -Label "1 : M" -Verb "has sections" -Color "RGB(37, 99, 235)"
    Add-Relationship -ShapeFrom $eSection -ShapeTo $eDept -Label "1 : M" -Verb "contains depts" -Color "RGB(37, 99, 235)"
    Add-Relationship -ShapeFrom $eBranch -ShapeTo $eDept -Label "1 : M" -Verb "houses depts" -Color "RGB(37, 99, 235)"
    Add-Relationship -ShapeFrom $eDept -ShapeTo $eUser -Label "1 : M" -Verb "employs staff" -Color "RGB(37, 99, 235)"
    Add-Relationship -ShapeFrom $eRole -ShapeTo $eUser -Label "1 : M" -Verb "grants role" -Color "RGB(37, 99, 235)"
    Add-Relationship -ShapeFrom $eBranch -ShapeTo $eUser -Label "1 : M" -Verb "assigned staff" -Color "RGB(37, 99, 235)"

    # Catalog & Inventory
    Add-Relationship -ShapeFrom $eCategory -ShapeTo $eItem -Label "1 : M" -Verb "categorizes" -Color "RGB(5, 150, 105)"
    Add-Relationship -ShapeFrom $eSupplier -ShapeTo $eItem -Label "1 : M" -Verb "supplies items" -Color "RGB(5, 150, 105)"
    Add-Relationship -ShapeFrom $eBranch -ShapeTo $eItem -Label "1 : M" -Verb "stocks inventory" -Color "RGB(5, 150, 105)"

    # Stock Operations
    Add-Relationship -ShapeFrom $eItem -ShapeTo $eStockTx -Label "1 : M" -Verb "undergoes tx" -Color "RGB(217, 119, 6)"
    Add-Relationship -ShapeFrom $eUser -ShapeTo $eStockTx -Label "1 : M" -Verb "records tx" -Color "RGB(217, 119, 6)"
    Add-Relationship -ShapeFrom $eUser -ShapeTo $eInvReq -Label "1 : M" -Verb "submits req" -Color "RGB(217, 119, 6)"
    Add-Relationship -ShapeFrom $eDept -ShapeTo $eInvReq -Label "1 : M" -Verb "dept requisition" -Color "RGB(217, 119, 6)"
    Add-Relationship -ShapeFrom $eItem -ShapeTo $eInvReq -Label "1 : M" -Verb "requested item" -Color "RGB(217, 119, 6)"

    # Inter-Branch Transfers
    Add-Relationship -ShapeFrom $eBranch -ShapeTo $eTransfer -Label "1 : M" -Verb "source / dest" -Color "RGB(217, 119, 6)"
    Add-Relationship -ShapeFrom $eTransfer -ShapeTo $eTransItem -Label "1 : M" -Verb "has items" -Color "RGB(217, 119, 6)"
    Add-Relationship -ShapeFrom $eItem -ShapeTo $eTransItem -Label "1 : M" -Verb "transferred item" -Color "RGB(217, 119, 6)"

    # Procurement & Purchasing
    Add-Relationship -ShapeFrom $eDept -ShapeTo $eProcReq -Label "1 : M" -Verb "raises request" -Color "RGB(147, 51, 234)"
    Add-Relationship -ShapeFrom $eProcReq -ShapeTo $ePO -Label "1 : M" -Verb "generates PO" -Color "RGB(147, 51, 234)"
    Add-Relationship -ShapeFrom $eSupplier -ShapeTo $ePO -Label "1 : M" -Verb "receives PO" -Color "RGB(147, 51, 234)"
    Add-Relationship -ShapeFrom $ePO -ShapeTo $eGRN -Label "1 : M" -Verb "verified by GRN" -Color "RGB(147, 51, 234)"

    # Maintenance & Audit
    Add-Relationship -ShapeFrom $eItem -ShapeTo $eMaint -Label "1 : M" -Verb "needs maintenance" -Color "RGB(100, 116, 139)"
    Add-Relationship -ShapeFrom $eUser -ShapeTo $eAudit -Label "1 : M" -Verb "logged in audit" -Color "RGB(100, 116, 139)"

    # ==========================================================================
    # SAVE & EXPORT
    # ==========================================================================
    Write-Host "Saving Visio Drawing to: $VsdxPath" -ForegroundColor Green
    if (Test-Path $VsdxPath) { Remove-Item $VsdxPath -Force }
    $doc.SaveAs($VsdxPath)

    Write-Host "Exporting PDF preview to: $PdfPath" -ForegroundColor Green
    if (Test-Path $PdfPath) { Remove-Item $PdfPath -Force }
    $doc.ExportAsFixedFormat(1, $PdfPath, 1, 0) # 1=visFixedFormatPDF, 1=visDocExIntentPrint

    Write-Host "Exporting PNG preview to: $PngPath" -ForegroundColor Green
    if (Test-Path $PngPath) { Remove-Item $PngPath -Force }
    $page.Export($PngPath)

    Write-Host "Closing Visio..." -ForegroundColor Cyan
    $doc.Saved = $true
    $doc.Close()
    $visio.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($visio) | Out-Null
    [System.GC]::Collect()
    [System.GC]::WaitForPendingFinalizers()

    Write-Host "SUCCESS: Conceptual ERD generated and saved cleanly to documentation folder!" -ForegroundColor Green
}
catch {
    Write-Host "ERROR occurred: $($_.Exception.Message)" -ForegroundColor Red
    if ($visio) {
        try { $visio.Quit() } catch {}
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($visio) | Out-Null
    }
    throw $_
}
