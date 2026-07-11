ALTER TABLE inventory_items
    ADD COLUMN IF NOT EXISTS brand_model VARCHAR(150) DEFAULT NULL AFTER name,
    ADD COLUMN IF NOT EXISTS asset_status VARCHAR(30) DEFAULT 'Available' AFTER warranty_date,
    ADD COLUMN IF NOT EXISTS asset_condition VARCHAR(30) DEFAULT 'New' AFTER asset_status,
    ADD COLUMN IF NOT EXISTS funding_source VARCHAR(120) DEFAULT NULL AFTER asset_condition,
    ADD COLUMN IF NOT EXISTS storage_location VARCHAR(120) DEFAULT NULL AFTER funding_source;

CREATE INDEX IF NOT EXISTS idx_inventory_items_purchase_date ON inventory_items(purchase_date);
CREATE INDEX IF NOT EXISTS idx_inventory_items_asset_status ON inventory_items(asset_status);
