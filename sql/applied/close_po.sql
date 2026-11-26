-- Close PO feature: manual close/reopen by admin (PO-level and item-level)
ALTER TABLE purchase_orders
  ADD COLUMN manually_closed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN closed_at DATETIME NULL;

ALTER TABLE purchase_order_items
  ADD COLUMN manually_closed TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN closed_at DATETIME NULL;
