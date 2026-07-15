ALTER TABLE inventory_items
  ADD COLUMN IF NOT EXISTS serial_number VARCHAR(120) DEFAULT NULL AFTER asset_code;
