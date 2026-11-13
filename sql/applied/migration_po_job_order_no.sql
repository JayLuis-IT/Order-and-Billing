-- Auto-generated Job Order (JO) number on customer POs.
-- Generated at PO creation (JO-YYYYMMDD-###) and pre-filled into the
-- LMR entry when a PO is selected. Nullable: legacy POs without a linked
-- LMR keep NULL and display a placeholder.
-- Idempotent. Tracked via schema_migrations ('po-job-order-no-v1').

ALTER TABLE `purchase_orders`
  ADD COLUMN `job_order_no` VARCHAR(100) NULL DEFAULT NULL AFTER `customer_po_number`;

-- Backfill legacy POs from their linked LMR Job Orders.
UPDATE `purchase_orders` po
  JOIN `lmr_orders` lmr
      ON lmr.po_number = po.customer_po_number
     AND lmr.customer_id = po.customer_id
  SET po.job_order_no = lmr.job_order_no
  WHERE po.job_order_no IS NULL AND lmr.job_order_no IS NOT NULL;

-- One JO per PO.
ALTER TABLE `purchase_orders`
  ADD UNIQUE KEY `uk_purchase_orders_job_order` (`job_order_no`);
