-- LMR lot-level allocation: operators pick specific inventory lots per BOM
-- component in the LMR Component Breakdown (FIFO batch picking / traceability).
CREATE TABLE IF NOT EXISTS `lmr_lot_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lmr_id` int(11) NOT NULL,
  `lri_id` int(11) DEFAULT NULL,
  `item_id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL,
  `lot_number` varchar(100) NOT NULL,
  `allocated_qty` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lmr_lot_alloc_lmr` (`lmr_id`),
  KEY `idx_lmr_lot_alloc_item_lot` (`item_id`, `lot_number`, `status`),
  CONSTRAINT `fk_lmr_lot_alloc_lmr` FOREIGN KEY (`lmr_id`) REFERENCES `lmr_orders` (`lmr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
