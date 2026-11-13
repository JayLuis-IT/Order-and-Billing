-- LMR Job Order decoupling: independent JO number on lmr_orders.
-- po_number stays nullable (already is) and becomes optional; job_order_no
-- becomes the primary internal production reference.
-- Idempotent. Tracked via schema_migrations ('lmr-job-order-no-v1').

ALTER TABLE `lmr_orders`
  ADD COLUMN `job_order_no` VARCHAR(100) NULL DEFAULT NULL AFTER `po_number`;

-- Backfill: ensure every existing LMR has a JO reference derived from its LMR number.
UPDATE `lmr_orders`
  SET `job_order_no` = CONCAT('JO-', lmr_number)
  WHERE `job_order_no` IS NULL;

-- One JO per LMR.
ALTER TABLE `lmr_orders`
  ADD UNIQUE KEY `uk_lmr_orders_job_order` (`job_order_no`);
