<?php
/**
 * Live-server lot reconciliation (safe).
 *
 * Fixes inflated lot stock (e.g. lot 157-208 showing 44 CS when only 1 CS remains)
 * caused by duplicated production_lots rows created by the old edit bug.
 *
 * Strategy per (item_id, lot_number) group:
 *   1. production_history (is_removed=0) is the source of truth for produced qty.
 *   2. If group has multiple active rows, keep ONE holder row (prefer a row referenced
 *      by deliveries, else the newest) and zero the duplicates so totals never multiply.
 *   3. Duplicate rows that are referenced by no delivery/backload are deleted;
 *      referenced duplicates stay active with qty 0 (their DR references keep counting).
 *   4. Rows with NO backing history at all and no references are deleted (orphans).
 *   5. Recalculate produced_quantity on purchase_order_items and purchase_orders.
 *
 * Run:  php reconcile_lots_live.php        (dry-run preview, recommended first)
 *       php reconcile_lots_live.php --apply
 */

require_once __DIR__ . '/../../core/BaseModel.php';

// $argv can be NULL on some PHP ini setups (register_argc_argv=0); fall back to superglobals
$cliArgs = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $cliArgs, true) || (isset($_GET['apply']) && $_GET['apply'] === '1');
$conn = \App\Core\BaseModel::getConnection();

echo $apply ? "APPLY MODE: changes will be written.\n" : "DRY RUN: no changes will be written (use --apply to write).\n";
echo "=========================================================\n\n";

// 1. Fetch all active lot rows
$rows = $conn->query("SELECT lot_id, poi_id, item_id, lot_number, quantity_produced, date_created
    FROM production_lots WHERE `is_removed` = 0")->fetchAll(PDO::FETCH_ASSOC);

// 2. History totals per (item_id, lot_number)
$hist = [];
foreach ($conn->query("SELECT item_id, lot_number, SUM(added_quantity) AS total
    FROM production_history WHERE `is_removed` = 0 AND item_id IS NOT NULL
    GROUP BY item_id, lot_number") as $h) {
    $hist[(int)$h['item_id'] . '|' . $h['lot_number']] = (int)$h['total'];
}

// 3. Which lot rows are referenced by deliveries / backloads?
$referenced = [];
foreach ($conn->query("SELECT lot_id, COUNT(*) c FROM deliveries WHERE lot_id IS NOT NULL AND `remove` = 0 GROUP BY lot_id") as $r) {
    $referenced[(int)$r['lot_id']] = true;
}
foreach ($conn->query("SELECT lot_id, COUNT(*) c FROM backloads WHERE `remove` = 0 GROUP BY lot_id") as $r) {
    $referenced[(int)$r['lot_id']] = true;
}
// deliveries.lot_items JSON references
foreach ($conn->query("SELECT lot_items FROM deliveries WHERE `remove` = 0 AND lot_items IS NOT NULL AND lot_items != ''") as $r) {
    $items = json_decode($r['lot_items'], true);
    if (!is_array($items)) continue;
    foreach ($items as $li) {
        if (isset($li['lot_id'])) $referenced[(int)$li['lot_id']] = true;
    }
}

// 4. Group rows by (item_id, lot_number)
$groups = [];
foreach ($rows as $row) {
    if ($row['item_id'] === null) continue; // legacy unlinked rows left untouched
    $key = (int)$row['item_id'] . '|' . $row['lot_number'];
    $groups[$key][] = $row;
}

$fixedGroups = 0;
$deletedRows = 0;
$zeroedRows = 0;

foreach ($groups as $key => $groupRows) {
    list($itemId, $lotNumber) = explode('|', $key, 2);
    $itemId = (int)$itemId;
    $historyTotal = $hist[$key] ?? null;

    $groupProduced = array_sum(array_map(fn($r) => (int)$r['quantity_produced'], $groupRows));

    // Case A: no history at all -> whole group is an orphan
    if ($historyTotal === null) {
        $hasRef = false;
        foreach ($groupRows as $r) {
            if (isset($referenced[(int)$r['lot_id']])) { $hasRef = true; break; }
        }
        if (!$hasRef && $groupProduced > 0) {
            echo "ORPHAN  item={$itemId} lot={$lotNumber}: qty={$groupProduced} with no production history"
               . ($apply ? " -> DELETED\n" : " -> would be deleted\n");
            if ($apply) {
                $ph = implode(',', array_map(fn($r) => (int)$r['lot_id'], $groupRows));
                $conn->exec("DELETE FROM production_lots WHERE lot_id IN ($ph)");
            }
            $deletedRows += count($groupRows);
            $fixedGroups++;
        }
        continue;
    }

    // Case B: quantities already correct and only one row -> nothing to do
    if (count($groupRows) === 1 && $groupProduced === $historyTotal) continue;

    // Case C: consolidate
    // holder: a referenced row (so DR references keep counting), else the newest
    usort($groupRows, function($a, $b) use ($referenced) {
        $ra = isset($referenced[(int)$a['lot_id']]) ? 1 : 0;
        $rb = isset($referenced[(int)$b['lot_id']]) ? 1 : 0;
        if ($ra !== $rb) return $rb - $ra;
        return strcmp($b['date_created'], $a['date_created']);
    });
    $holder = $groupRows[0];
    $dups = array_slice($groupRows, 1);

    echo "FIX     item={$itemId} lot={$lotNumber}: rows=" . count($groupRows)
       . " group_qty={$groupProduced} history_total={$historyTotal}"
       . ($apply ? " -> holder lot#{$holder['lot_id']} set to {$historyTotal}\n"
                 : " -> would set holder lot#{$holder['lot_id']} to {$historyTotal}\n");
    $fixedGroups++;

    if (!$apply) continue;

    $conn->prepare("UPDATE production_lots SET quantity_produced = :qty WHERE lot_id = :lot_id")
        ->execute(['qty' => $historyTotal, 'lot_id' => $holder['lot_id']]);

    foreach ($dups as $dup) {
        $dupId = (int)$dup['lot_id'];
        if (!isset($referenced[$dupId])) {
            $conn->prepare("DELETE FROM production_lots WHERE lot_id = :lot_id")
                ->execute(['lot_id' => $dupId]);
            $deletedRows++;
        } else {
            // keep the row (a DR references it) but zero it so totals don't multiply
            $conn->prepare("UPDATE production_lots SET quantity_produced = 0 WHERE lot_id = :lot_id")
                ->execute(['lot_id' => $dupId]);
            $zeroedRows++;
        }
    }
}

echo "\nSummary: groups_to_fix={$fixedGroups}, rows_deleted={$deletedRows}, rows_zeroed={$zeroedRows}\n\n";

// ============================================================
// 6. Orphan lot_id references in deliveries.lot_items JSON.
//    These make the DR form UNDER-count delivered stock (inflating
//    availability) because the referenced lot row no longer exists.
//    Fix: re-point them to the surviving holder row of the same
//    (item, lot_number) taken from production history.
// ============================================================
echo "Checking deliveries for lot_id references that no longer exist in production_lots...\n";

$allLotRows = [];
foreach ($conn->query("SELECT lot_id, lot_number, item_id, poi_id, `is_removed` FROM production_lots") as $lr) {
    $allLotRows[(int)$lr['lot_id']] = $lr;
}
// holder lookup by (item, lot_number) -> newest active row
$holderByGroup = [];
foreach ($conn->query("SELECT lot_id, lot_number, item_id, date_created FROM production_lots WHERE `is_removed` = 0 ORDER BY date_created DESC, lot_id DESC") as $lr) {
    $gk = (int)$lr['item_id'] . '|' . $lr['lot_number'];
    if (!isset($holderByGroup[$gk])) $holderByGroup[$gk] = (int)$lr['lot_id'];
}
// history fallback: item for a lot_number when no active row exists
$histItemByLot = [];
foreach ($conn->query("SELECT DISTINCT item_id, lot_number FROM production_history WHERE `is_removed` = 0") as $h) {
    $histItemByLot[(int)$h['item_id'] . '|' . $h['lot_number']] = true;
}

$deliveryStmt = $conn->query("SELECT delivery_id, dr_number, lot_items FROM deliveries WHERE `remove` = 0 AND lot_items IS NOT NULL AND lot_items != ''");
$remapDeliveries = 0;
$remapEntries = 0;
while ($d = $deliveryStmt->fetch()) {
    $items = json_decode($d['lot_items'], true);
    if (!is_array($items) || empty($items)) continue;
    $changed = false;
    $seenGroups = [];
    $newItems = [];
    $deliveryFixes = [];
    foreach ($items as $li) {
        if (!isset($li['lot_id'])) { $newItems[] = $li; continue; }
        $lid = (int)$li['lot_id'];
        $lotNumber = $li['lot_number'] ?? '';
        if (isset($allLotRows[$lid])) {
            // existing row: merge duplicate group entries within the same delivery
            $rowItem = $allLotRows[$lid]['item_id'] !== null ? (int)$allLotRows[$lid]['item_id'] : '';
            $gk = $rowItem . '|' . $lotNumber;
            if (isset($seenGroups[$gk])) {
                $newItems[$seenGroups[$gk]]['qty'] += intval($li['qty'] ?? 0);
                if (!empty($li['returned_qty'])) {
                    $newItems[$seenGroups[$gk]]['returned_qty'] = ($newItems[$seenGroups[$gk]]['returned_qty'] ?? 0) + intval($li['returned_qty']);
                }
                $changed = true;
                $remapEntries++;
                $deliveryFixes[] = "merged duplicate entry '{$lotNumber}'";
                continue;
            }
            $seenGroups[$gk] = count($newItems);
            $newItems[] = $li;
            continue;
        }
        // orphan lot_id: resolve by lot_number through history/holder maps
        $newHolder = null;
        $newHolderItem = null;
        foreach ($holderByGroup as $gk => $holderId) {
            $parts = explode('|', $gk, 2);
            if ($parts[1] === $lotNumber) { $newHolder = $holderId; $newHolderItem = (int)$parts[0]; break; }
        }
        if ($newHolder === null) {
            echo "  WARNING delivery #{$d['delivery_id']} ({$d['dr_number']}): lot '{$lotNumber}' lot_id={$lid} is orphaned and cannot be auto-repaired - review manually.\n";
            $newItems[] = $li;
            continue;
        }
        $li['lot_id'] = $newHolder;
        if (!empty($li['item_code'])) {
            $codeStmt = $conn->prepare("SELECT item_id FROM items WHERE item_code = :code LIMIT 1");
            $codeStmt->execute(['code' => $li['item_code']]);
            $iid = $codeStmt->fetchColumn();
            if ($iid) {
                $poiStmt = $conn->prepare("SELECT poi_id FROM purchase_order_items WHERE po_id = (SELECT po_id FROM deliveries WHERE delivery_id = :did) AND item_id = :iid LIMIT 1");
                $poiStmt->execute(['did' => $d['delivery_id'], 'iid' => $iid]);
                $np = $poiStmt->fetchColumn();
                if ($np) $li['poi_id'] = (int)$np;
            }
        }
        $newItems[] = $li;
        $seenGroups[$newHolderItem . '|' . $lotNumber] = count($newItems) - 1;
        $changed = true;
        $remapEntries++;
        $deliveryFixes[] = "lot '{$lotNumber}' lot_id {$lid} -> {$newHolder}";
    }
    if ($changed) {
        $remapDeliveries++;
        echo "  delivery #{$d['delivery_id']} ({$d['dr_number']}): " . implode('; ', $deliveryFixes) . "\n";
        if ($apply) {
            $upd = $conn->prepare("UPDATE deliveries SET lot_items = :json WHERE delivery_id = :delivery_id");
            $upd->execute(['json' => json_encode(array_values($newItems)), 'delivery_id' => $d['delivery_id']]);
        }
    }
}
echo "\nSummary: deliveries_with_orphan_refs={$remapDeliveries}, entries_repaired={$remapEntries}\n\n";

if (!$apply) {
    echo "This was a DRY RUN. Re-run with --apply to write changes.\n";
    exit(0);
}

// 5. Recalculate produced_quantity cascade
echo "Recalculating produced_quantity on purchase_order_items...\n";
$conn->exec("UPDATE purchase_order_items poi SET produced_quantity = (
    SELECT COALESCE(SUM(pl.quantity_produced), 0) FROM production_lots pl
    WHERE pl.poi_id = poi.poi_id AND pl.`is_removed` = 0
)");
echo "Recalculating produced_quantity on purchase_orders...\n";
$conn->exec("UPDATE purchase_orders po SET produced_quantity = (
    SELECT COALESCE(SUM(produced_quantity), 0) FROM purchase_order_items WHERE po_id = po.po_id
)");

echo "Done. Verify Stock on Hand and the DR lot dropdown now match reality.\n";
