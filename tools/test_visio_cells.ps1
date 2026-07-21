$ErrorActionPreference = "Stop"
try {
    $v = New-Object -ComObject Visio.Application
    $v.Visible = $false
    $doc = $v.Documents.Add("")
    $page = $v.ActivePage
    $box = $page.DrawRectangle(1, 1, 4, 3)

    Write-Host "Testing FillForegnd RGB with spaces..."
    try { $box.CellsU("FillForegnd").FormulaU = "RGB(15, 23, 42)" } catch { Write-Host "Failed RGB spaces: $($_.Exception.Message)" }

    Write-Host "Testing FillForegnd RGB without spaces..."
    try { $box.CellsU("FillForegnd").FormulaU = "RGB(15,23,42)" } catch { Write-Host "Failed RGB no spaces: $($_.Exception.Message)" }

    Write-Host "Testing FillForegnd FormulaForceU..."
    try { $box.CellsU("FillForegnd").FormulaForceU = "RGB(15,23,42)" } catch { Write-Host "Failed FormulaForceU: $($_.Exception.Message)" }

    Write-Host "Testing Rounding..."
    try { $box.CellsU("Rounding").FormulaU = "0.1 in" } catch { Write-Host "Failed Rounding FormulaU: $($_.Exception.Message)" }

    Write-Host "Testing Rounding numeric string..."
    try { $box.CellsU("Rounding").FormulaU = "0.1" } catch { Write-Host "Failed Rounding numeric: $($_.Exception.Message)" }

    Write-Host "Testing Char.Size..."
    try { $box.CellsU("Char.Size").FormulaU = "15 pt" } catch { Write-Host "Failed Char.Size: $($_.Exception.Message)" }

    Write-Host "Testing Char.Font..."
    try { $box.CellsU("Char.Font").FormulaU = "Segoe UI" } catch { Write-Host "Failed Char.Font: $($_.Exception.Message)" }
    try { $box.CellsU("Char.Font").FormulaForceU = "FONT('Segoe UI')" } catch { Write-Host "Failed Char.Font FONT(): $($_.Exception.Message)" }

    $doc.Close($true)
    $v.Quit()
    [System.Runtime.InteropServices.Marshal]::ReleaseComObject($v) | Out-Null
    Write-Host "Test finished cleanly!"
} catch {
    Write-Host "Fatal error: $($_.Exception.Message)"
    if ($v) { $v.Quit() }
}
