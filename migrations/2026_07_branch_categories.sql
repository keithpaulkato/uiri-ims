-- Add branch support to categories for branch-specific category lists
ALTER TABLE categories
  ADD COLUMN branch_id INT NOT NULL DEFAULT 1 AFTER id,
  ADD CONSTRAINT fk_categories_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE;

-- Assign existing categories to a branch based on inventory items when possible
UPDATE categories c
LEFT JOIN (
  SELECT category_id, branch_id
  FROM inventory_items
  WHERE branch_id IS NOT NULL
  GROUP BY category_id
) i ON i.category_id = c.id
SET c.branch_id = COALESCE(i.branch_id, 1);

-- Add index for branch-specific category operations
CREATE INDEX idx_categories_branch ON categories(branch_id);

-- Ensure branch-specific category names are unique within each branch
ALTER TABLE categories ADD UNIQUE INDEX ux_categories_branch_name (branch_id, name);
