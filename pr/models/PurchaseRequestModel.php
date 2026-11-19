<?php
namespace App\Models;

use App\Core\BaseModel;
use PDO;

class PurchaseRequestModel extends BaseModel
{
    protected $table = 'purchase_requests';

    /**
     * Next PR number: PR-YYYY-#### with a single global, continuous
     * sequence across ALL departments/users (and across years — only the
     * YYYY part reflects the creation year, the counter never resets).
     * Soft-deleted rows are counted too so numbers are never reused.
     */
    public static function nextPrNumber(string $year): string
    {
        $rows = self::getConnection()
            ->query('SELECT pr_number FROM purchase_requests')
            ->fetchAll(PDO::FETCH_COLUMN);
        $max = 0;
        foreach ($rows as $num) {
            if (preg_match('/(\d+)\s*$/', (string) $num, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return sprintf('PR-%s-%04d', $year, $max + 1);
    }

    /**
     * List PRs, newest first. $mineOnly limits to one creator.
     * $status accepts a single value or an array (IN clause) — the
     * procurement queue needs approved + in_process together.
     */
    public function getRequests(?int $createdBy = null, $status = null): array
    {
        $sql = "SELECT pr.*, u.username AS creator_name, u.full_name AS creator_full_name,
                       (SELECT COUNT(*) FROM purchase_request_items pri WHERE pri.pr_id = pr.pr_id) AS item_count
                FROM purchase_requests pr
                LEFT JOIN users u ON u.user_id = pr.created_by
                WHERE pr.remove = 0";
        $params = [];
        if ($createdBy !== null) {
            $sql .= " AND pr.created_by = :created_by";
            $params['created_by'] = $createdBy;
        }
        if (is_array($status)) {
            $status = array_values(array_filter($status, static fn ($s) => $s !== null && $s !== ''));
            if ($status) {
                $placeholders = [];
                foreach ($status as $i => $s) {
                    $key = 'status' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $s;
                }
                $sql .= ' AND pr.status IN (' . implode(', ', $placeholders) . ')';
            }
        } elseif ($status !== null && $status !== '') {
            $sql .= " AND pr.status = :status";
            $params['status'] = $status;
        }
        $sql .= " ORDER BY pr.pr_id DESC";
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getById(int $prId): ?array
    {
        $stmt = self::getConnection()->prepare(
            "SELECT pr.*, u.username AS creator_name, u.full_name AS creator_full_name
             FROM purchase_requests pr
             LEFT JOIN users u ON u.user_id = pr.created_by
             WHERE pr.pr_id = :id AND pr.remove = 0"
        );
        $stmt->execute(['id' => $prId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getItems(int $prId): array
    {
        $stmt = self::getConnection()->prepare(
            "SELECT pri.*, i.item_description AS master_description, i.item_uom AS master_uom
             FROM purchase_request_items pri
             LEFT JOIN items i ON i.item_id = pri.item_id
             WHERE pri.pr_id = :pr_id
             ORDER BY pri.sort_order ASC, pri.pri_id ASC"
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->fetchAll();
    }

    // ─── Supplier + price quotes (procurement, while pending) ────────────────

    /** Supplier quotes for a PR; LEFT JOIN keeps rows visible even if the
     *  supplier master is later soft-deleted. Rows carry pri_id so the modal
     *  and print can merge quotes into their requested-item row. */
    public function getSuppliersForPr(int $prId): array
    {
        $stmt = self::getConnection()->prepare(
            "SELECT prs.id, prs.pri_id, prs.supplier_id, prs.price, prs.date_created, s.supplier_name, s.status AS supplier_status
             FROM purchase_request_suppliers prs
             LEFT JOIN suppliers s ON s.supplier_id = prs.supplier_id
             WHERE prs.pr_id = :pr_id AND prs.remove = 0
             ORDER BY prs.id ASC"
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->fetchAll();
    }

    /** Replace all quotes for a PR (delete + insert) in one transaction.
     *  Each row: pri_id (line item), supplier_id, price. */
    public function replaceSuppliers(int $prId, array $rows, ?int $userId): void
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM purchase_request_suppliers WHERE pr_id = :pr_id')
                ->execute(['pr_id' => $prId]);
            $stmt = $pdo->prepare(
                "INSERT INTO purchase_request_suppliers (pr_id, pri_id, supplier_id, price, created_by)
                 VALUES (:pr_id, :pri_id, :supplier_id, :price, :created_by)"
            );
            foreach ($rows as $row) {
                $stmt->execute([
                    'pr_id'       => $prId,
                    'pri_id'      => (int) $row['pri_id'],
                    'supplier_id' => (int) $row['supplier_id'],
                    'price'       => round((float) $row['price'], 2),
                    'created_by'  => $userId,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Procurement submitted quotes: pending -> submitted. Returns true when a
     *  row actually changed (only the pending transition is allowed). */
    public function markSubmitted(int $prId): bool
    {
        $stmt = self::getConnection()->prepare(
            "UPDATE purchase_requests SET status = 'submitted'
             WHERE pr_id = :pr_id AND remove = 0 AND status = 'pending'"
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->rowCount() > 0;
    }

    /** Create header + line items in one transaction. Returns new pr_id. */
    public function createRequest(array $header, array $items): int
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO purchase_requests
                    (pr_number, department, purpose, date_created, classification, priority,
                     status, prepared_by, checked_by, approved_by_name, received_by, notes, created_by)
                 VALUES
                    (:pr_number, :department, :purpose, :date_created, :classification, :priority,
                     'pending', :prepared_by, :checked_by, :approved_by_name, :received_by, :notes, :created_by)"
            );
            $stmt->execute($header);
            $prId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO purchase_request_items
                    (pr_id, item_id, item_code, item_name, specs, dimension, qty, uom, preferred_supplier, sort_order)
                 VALUES
                    (:pr_id, :item_id, :item_code, :item_name, :specs, :dimension, :qty, :uom, :preferred_supplier, :sort_order)"
            );
            foreach ($items as $idx => $item) {
                if (trim((string) ($item['item_name'] ?? '')) === '') {
                    continue;
                }
                $itemStmt->execute([
                    'pr_id'              => $prId,
                    'item_id'            => $item['item_id'] ?: null,
                    'item_code'          => $item['item_code'] !== '' ? $item['item_code'] : null,
                    'item_name'          => $item['item_name'],
                    'specs'              => $item['specs'] !== '' ? $item['specs'] : null,
                    'dimension'          => $item['dimension'] !== '' ? $item['dimension'] : null,
                    'qty'                => $item['qty'],
                    'uom'                => $item['uom'] !== '' ? $item['uom'] : null,
                    'preferred_supplier' => $item['preferred_supplier'] !== '' ? $item['preferred_supplier'] : null,
                    'sort_order'         => $idx,
                ]);
            }
            $pdo->commit();
            return $prId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Update header fields and replace all line items in one transaction.
     * pr_number, department, status and created_by are intentionally left
     * untouched — editing only fixes content errors before approval.
     */
    public function updateRequest(int $prId, array $header, array $items): void
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE purchase_requests
                 SET purpose = :purpose, date_created = :date_created,
                     classification = :classification, priority = :priority,
                     prepared_by = :prepared_by, checked_by = :checked_by,
                     approved_by_name = :approved_by_name, received_by = :received_by,
                     notes = :notes
                 WHERE pr_id = :pr_id"
            );
            $stmt->execute($header + ['pr_id' => $prId]);

            $pdo->prepare("DELETE FROM purchase_request_items WHERE pr_id = :pr_id")
                ->execute(['pr_id' => $prId]);

            $itemStmt = $pdo->prepare(
                "INSERT INTO purchase_request_items
                    (pr_id, item_id, item_code, item_name, specs, dimension, qty, uom, preferred_supplier, sort_order)
                 VALUES
                    (:pr_id, :item_id, :item_code, :item_name, :specs, :dimension, :qty, :uom, :preferred_supplier, :sort_order)"
            );
            foreach ($items as $idx => $item) {
                if (trim((string) ($item['item_name'] ?? '')) === '') {
                    continue;
                }
                $itemStmt->execute([
                    'pr_id'              => $prId,
                    'item_id'            => $item['item_id'] ?: null,
                    'item_code'          => $item['item_code'] !== '' ? $item['item_code'] : null,
                    'item_name'          => $item['item_name'],
                    'specs'              => $item['specs'] !== '' ? $item['specs'] : null,
                    'dimension'          => $item['dimension'] !== '' ? $item['dimension'] : null,
                    'qty'                => $item['qty'],
                    'uom'                => $item['uom'] !== '' ? $item['uom'] : null,
                    'preferred_supplier' => $item['preferred_supplier'] !== '' ? $item['preferred_supplier'] : null,
                    'sort_order'         => $idx,
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Attach the signed hardcopy scan; flips status to approved. */
    public function approveWithAttachment(int $prId, array $file, int $userId): void
    {
        $stmt = self::getConnection()->prepare(
            "UPDATE purchase_requests
             SET status = 'approved',
                 signed_file_name = :file_name,
                 signed_file_path = :file_path,
                 signed_file_type = :file_type,
                 signed_uploaded_by = :uploaded_by,
                 signed_uploaded_at = NOW(),
                 approved_by = :approved_by,
                 approved_at = NOW()
             WHERE pr_id = :pr_id AND remove = 0 AND status = 'submitted'"
        );
        $stmt->execute([
            'file_name'   => $file['name'],
            'file_path'   => $file['path'],
            'file_type'   => $file['type'],
            'uploaded_by' => $userId,
            'approved_by' => $userId,
            'pr_id'       => $prId,
        ]);
    }

    /** Procurement clicks Process: approved -> in_process. Returns true when
     *  a row actually changed (only the approved transition is allowed). */
    public function markInProcess(int $prId): bool
    {
        $stmt = self::getConnection()->prepare(
            "UPDATE purchase_requests
             SET status = 'in_process'
             WHERE pr_id = :pr_id AND remove = 0 AND status = 'approved'"
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->rowCount() > 0;
    }

    public function softDelete(int $prId): void
    {
        $stmt = self::getConnection()->prepare(
            "UPDATE purchase_requests SET remove = 1 WHERE pr_id = :pr_id"
        );
        $stmt->execute(['pr_id' => $prId]);
    }

    public function countForStatus(string $status): int
    {
        $stmt = self::getConnection()->prepare(
            "SELECT COUNT(*) FROM purchase_requests WHERE remove = 0 AND status = :status"
        );
        $stmt->execute(['status' => $status]);
        return (int) $stmt->fetchColumn();
    }
}
