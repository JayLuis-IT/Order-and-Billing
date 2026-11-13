<?php
namespace App\Models;

use App\Core\BaseModel;
use PDO;

/**
 * Procurement's formal Purchase Requisition (PR###### ERP format).
 * Separate from the requestor's Purchase Request Form (PRF / MPF-001-01)
 * stored in purchase_requests — one PR can be generated per approved PRF.
 */
class ProcurementPrModel extends BaseModel
{
    protected $table = 'procurement_prs';

    /** PO Site / Ship To options (site code => name + address). */
    public const PO_SITES = [
        '001' => [
            'name'    => '001 - Sterling Technopark',
            'address' => 'Block 7, Lot 7, Springbook St. Sterling Technopark, Brgy. Maguyam, Silang, Cavite',
        ],
    ];

    /**
     * Next global sequential PR number: PR + 6 digits (PR000001, PR000002, ...).
     * Counters are continuous across all procurement users and years;
     * soft-deleted rows are counted so numbers are never reused.
     */
    public static function nextPrNumber(): string
    {
        $rows = self::getConnection()
            ->query('SELECT pr_number FROM procurement_prs')
            ->fetchAll(PDO::FETCH_COLUMN);
        $max = 0;
        foreach ($rows as $num) {
            if (preg_match('/^PR(\d+)$/', (string) $num, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return 'PR' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    /** All PRs, newest first. */
    public function getPrs(): array
    {
        $sql = "SELECT pp.*, u.username AS creator_name, u.full_name AS creator_full_name
                FROM procurement_prs pp
                LEFT JOIN users u ON u.user_id = pp.created_by
                WHERE pp.remove = 0
                ORDER BY pp.pr_id DESC";
        return self::getConnection()->query($sql)->fetchAll();
    }

    /** Map of prf_id => PR row (for the Procurement queue actions). */
    public function getByPrfIds(array $prfIds): array
    {
        $map = [];
        if (!$prfIds) {
            return $map;
        }
        $in = implode(',', array_fill(0, count($prfIds), '?'));
        $stmt = self::getConnection()->prepare(
            "SELECT * FROM procurement_prs WHERE remove = 0 AND prf_id IN ($in)"
        );
        $stmt->execute(array_map('intval', $prfIds));
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['prf_id']] = $row;
        }
        return $map;
    }

    public function getById(int $prId): ?array
    {
        $stmt = self::getConnection()->prepare(
            "SELECT pp.*, u.username AS creator_name, u.full_name AS creator_full_name
             FROM procurement_prs pp
             LEFT JOIN users u ON u.user_id = pp.created_by
             WHERE pp.pr_id = :id AND pp.remove = 0"
        );
        $stmt->execute(['id' => $prId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getItems(int $prId): array
    {
        $stmt = self::getConnection()->prepare(
            "SELECT * FROM procurement_pr_items
             WHERE pr_id = :pr_id
             ORDER BY line_no ASC, pri_id ASC"
        );
        $stmt->execute(['pr_id' => $prId]);
        return $stmt->fetchAll();
    }

    public function markPrinted(int $prId): void
    {
        self::getConnection()->prepare(
            "UPDATE procurement_prs SET printed = 1 WHERE pr_id = :id"
        )->execute(['id' => $prId]);
    }

    /**
     * Create a PR from an approved PRF: header + numbered line items in one
     * transaction. Item numbers follow the PR######-0000000000NN pattern.
     */
    public function createPr(array $header, array $items): int
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO procurement_prs
                    (pr_number, prf_id, po_site_code, ship_to_code, priority, pr_status,
                     pr_date, department, requisitioner, purpose, created_by)
                 VALUES
                    (:pr_number, :prf_id, :po_site_code, :ship_to_code, :priority, 'Open',
                     :pr_date, :department, :requisitioner, :purpose, :created_by)"
            );
            $stmt->execute($header);
            $prId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                "INSERT INTO procurement_pr_items
                    (pr_id, line_no, item_number, description, account_charged, unit, qty, date_required)
                 VALUES
                    (:pr_id, :line_no, :item_number, :description, :account_charged, :unit, :qty, :date_required)"
            );
            $line = 0;
            $uncoded = 0;
            foreach ($items as $item) {
                if (trim((string) ($item['description'] ?? '')) === '') {
                    continue;
                }
                $line++;
                // ERP numbering rule: coded PRF items keep their Item Code
                // verbatim; uncoded items get {PR_NUMBER}-{n}, with n counting
                // only uncoded items on the PR (1, 2, 3, ...).
                $itemCode = trim((string) ($item['item_code'] ?? ''));
                if ($itemCode !== '') {
                    $itemNumber = $itemCode;
                } else {
                    $uncoded++;
                    $itemNumber = $header['pr_number'] . '-' . $uncoded;
                }
                $itemStmt->execute([
                    'pr_id'           => $prId,
                    'line_no'         => $line,
                    'item_number'     => $itemNumber,
                    'description'     => trim((string) $item['description']),
                    'account_charged' => trim((string) ($item['account_charged'] ?? '')) ?: null,
                    'unit'            => trim((string) ($item['unit'] ?? '')) ?: null,
                    'qty'             => (float) ($item['qty'] ?? 0),
                    'date_required'   => ($item['date_required'] ?? '') !== '' ? $item['date_required'] : null,
                ]);
            }
            $pdo->commit();
            return $prId;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
