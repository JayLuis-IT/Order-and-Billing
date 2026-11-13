<?php
namespace App\Helpers;

use App\Core\BaseModel;

/**
 * Central role definitions + permission map for the per-role landing pages.
 *
 * Warehouse roles: whse_admin, whse_officer, whse_ppic, whse_procurement, whse_receiving
 * QC roles:        qc_admin, qc_inspector
 *
 * Menus and controller gates both read from this class — a feature is
 * reachable only when its action is listed for the user's role here.
 */
class RoleHelper
{
    public const ROLES = [
        'whse_admin'       => 'Warehouse Admin',
        'whse_officer'     => 'Warehouse Officer',
        'whse_ppic'        => 'Warehouse PPIC',
        'whse_procurement' => 'Warehouse Procurement',
        'whse_receiving'   => 'Warehouse Receiving',
        'qc_admin'         => 'QC Admin',
        'qc_inspector'     => 'QC Inspector',
    ];

    public const DEPARTMENT_ROLES = [
        'warehouse' => ['whse_admin', 'whse_officer', 'whse_ppic', 'whse_procurement', 'whse_receiving'],
        'qc'        => ['qc_admin', 'qc_inspector'],
    ];

    // ─── Warehouse action groups ──────────────────────────────────────────────

    private const WHSE_GROUPS = [
        'dashboard' => ['index'],
        'customer_po' => ['purchaseOrders', 'createPO', 'editPO', 'viewPO'],
        'deliveries' => [
            'deliveries', 'createMultipleDelivery', 'readyToDeliver', 'updateDRNumber',
            'saveDRNumberForLots', 'checkDRNumber', 'uploadDRPhoto', 'deleteDRPhoto',
            'printDR', 'printDRPreview', 'backloadDelivery', 'viewBackloads',
            'reportDelivery', 'getDeliveryLotsForBackload', 'getAvailableItemsForDelivery',
            'getPOsContainingItem', 'getLotsForPrint', 'getPOItemsForAssignment',
            'getLotsForTransfer', 'getActivePOsForAssignment',
        ],
        'items_view' => ['items', 'getItemBalances'],
        'items_manage' => ['storeItem', 'adjustItemSoh', 'itemImportForm', 'itemImportPreview', 'itemImportConfirm'],
        'purchasing_po' => [
            'purchasingPo', 'createPurchasingPo', 'processPurchasingPo', 'batchProcessPurchasingPo',
            'processMrpGroupPo',
            'cancelPurchasingPo', 'updatePurchasingPo', 'deletePurchasingPo',
            'approveManualPo', 'rejectManualPo', 'printPurchasingPo',
        ],
        'supplier_orders' => [
            'supplierOrders', 'createSupplierOrder', 'receiveSupplierOrder', 'cancelSupplierOrder',
            'deleteSupplierOrder', 'processSupplierOrder', 'batchProcessSupplierOrders',
        ],
        'mrp_approval' => [
            'mrpApproval', 'approveMrpDraft', 'rejectMrpDraft', 'approveMrpRunDraft', 'rejectMrpRunDraft',
        ],
        'mrp_sheet' => [
            'mrp', 'mrpPDF', 'mrpSheetPDF', 'saveMrpCalculation', 'getNextMrpRef', 'saveMrpSnapshot',
            'mrpHistory', 'mrpSnapshotPDF', 'mrpSnapshotPrint', 'mrpRunDetail', 'deleteMrpRun',
        ],
        'receiving_po' => ['receivingPo', 'receivePurchasingPo', 'updateReceivingStaging'],
        'stock_movement' => ['stockMovement', 'saveStockMovement'],
        'activity_logs' => ['activityLogs'],
        'reports' => ['reports', 'deliveryReport', 'exportDeliveryReport', 'exportReports'],
        'lmr' => ['lmrEntry'],
    ];

    /** Read-only lookup endpoints any warehouse role may call (pages depend on them). */
    private const WHSE_SHARED_ACTIONS = [
        'getPODetails', 'getItemsByCustomer', 'getCustomerPOs', 'getItemBomInfo',
        'getItemBalances', 'getSuppliers', 'searchItems', 'getItemBoms', 'getBomComponents',
        'getLotsByPOItem', 'getPoFullDetails', 'mrpRunDetail', 'printPurchasingPo',
    ];

    private const WHSE_ROLE_GROUPS = [
        'whse_admin'       => '*',
        'whse_officer'     => ['dashboard', 'deliveries', 'receiving_po', 'items_view', 'activity_logs', 'reports'],
        'whse_ppic'        => ['dashboard', 'customer_po', 'mrp_sheet', 'lmr', 'activity_logs'],
        'whse_procurement' => ['dashboard', 'purchasing_po', 'supplier_orders', 'items_view'],
        'whse_receiving'   => ['dashboard', 'stock_movement', 'items_view'],
    ];

    /** Extra per-role action grants beyond the groups above (kept for future use). */
    private const WHSE_ROLE_EXTRA = [
        'whse_receiving' => [],
    ];

    // ─── QC action groups ─────────────────────────────────────────────────────

    private const QC_ROLE_ACTIONS = [
        'qc_admin'     => '*',
        'qc_inspector' => ['index', 'deliveryInspection', 'updateRemark'],
    ];

    // ─── Role metadata ────────────────────────────────────────────────────────

    public static function roles(): array
    {
        return self::ROLES;
    }

    public static function label(?string $role): string
    {
        return self::ROLES[$role] ?? 'No role assigned';
    }

    public static function rolesForDepartment(string $department): array
    {
        return self::DEPARTMENT_ROLES[$department] ?? [];
    }

    public static function isWarehouseRole(?string $role): bool
    {
        return in_array($role, self::DEPARTMENT_ROLES['warehouse'], true);
    }

    public static function isQcRole(?string $role): bool
    {
        return in_array($role, self::DEPARTMENT_ROLES['qc'], true);
    }

    /**
     * Current user's role from session; lazily loads from DB (and caches in
     * session) for users logged in before the role migration.
     */
    public static function currentRole(): ?string
    {
        if (isset($_SESSION['role'])) {
            return $_SESSION['role'] !== '' ? $_SESSION['role'] : null;
        }
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }
        try {
            $stmt = BaseModel::getConnection()->prepare(
                "SELECT role FROM users WHERE user_id = :id AND `remove` = 0"
            );
            $stmt->execute(['id' => $userId]);
            $role = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $role = false;
        }
        $role = ($role === false || $role === null || $role === '') ? null : (string) $role;
        $_SESSION['role'] = $role ?? '';
        return $role;
    }

    /** Safe landing URL for the current session user's department + role. */
    public static function homeUrl(): string
    {
        $department = $_SESSION['department'] ?? '';
        if ($department === 'warehouse' || $department === 'qc') {
            return '?controller=' . $department;
        }
        $known = ['admin', 'production', 'finance', 'qa', 'rnd'];
        if ($department !== '' && in_array($department, $known, true)) {
            return '?controller=' . $department;
        }
        // Unknown/empty department means a stale or corrupted session —
        // sending the user to admin would create a redirect loop.
        return '?controller=auth&action=logout';
    }

    // ─── Permission checks ────────────────────────────────────────────────────

    /** Can this warehouse role run this WarehouseController action? */
    public static function warehouseCan(?string $role, string $action): bool
    {
        // Item master editing (create/edit/import/adjust SOH) is System Admin
        // only — no warehouse role may use these actions.
        if (in_array($action, self::WHSE_GROUPS['items_manage'], true)) {
            return false;
        }
        if ($role === 'whse_admin') {
            return true;
        }
        if (!isset(self::WHSE_ROLE_GROUPS[$role])) {
            return false;
        }
        if (in_array($action, self::WHSE_SHARED_ACTIONS, true)) {
            return true;
        }
        if (in_array($action, self::WHSE_ROLE_EXTRA[$role] ?? [], true)) {
            return true;
        }
        foreach (self::WHSE_ROLE_GROUPS[$role] as $group) {
            if (in_array($action, self::WHSE_GROUPS[$group] ?? [], true)) {
                return true;
            }
        }
        return false;
    }

    /** Can this QC role run this QcController action? */
    public static function qcCan(?string $role, string $action): bool
    {
        if ($role === 'qc_admin') {
            return true;
        }
        $allowed = self::QC_ROLE_ACTIONS[$role] ?? [];
        return in_array($action, $allowed, true);
    }

    /** Can this role use the LmrController (LMR entry module)? */
    public static function lmrCan(?string $role): bool
    {
        return in_array($role, ['whse_admin', 'whse_ppic'], true);
    }

    // ─── Sidebar menus ────────────────────────────────────────────────────────

    /**
     * Menu items for a warehouse role. Each item:
     *   label, href, icon, activeActions (for highlight), optional 'sub' => [[label, href, action]]
     */
    public static function warehouseMenu(?string $role): array
    {
        $all = [
            ['label' => 'Dashboard', 'href' => '?controller=warehouse', 'icon' => 'bi-speedometer2',
             'activeActions' => ['']],
            ['label' => 'Purchase Request', 'href' => '?controller=pr', 'icon' => 'bi-file-earmark-text',
             'activeActions' => ['index']],
            ['label' => 'Customer PO', 'href' => '?controller=warehouse&action=purchaseOrders', 'icon' => 'bi-cart3',
             'activeActions' => ['purchaseOrders', 'createPO', 'editPO', 'viewPO']],
            ['label' => 'Deliveries', 'href' => '?controller=warehouse&action=deliveries', 'icon' => 'bi-truck',
             'activeActions' => ['deliveries', 'createDelivery', 'createMultipleDelivery', 'readyToDeliver', 'printDR', 'printDRPreview', 'backloadDelivery', 'viewBackloads', 'reportDelivery']],
            ['label' => 'Item Master List', 'href' => '?controller=warehouse&action=items', 'icon' => 'bi-box-seam',
             'activeActions' => ['items', 'itemImportForm', 'itemImportPreview', 'itemImportConfirm']],
            ['label' => 'Purchasing PO', 'href' => '?controller=warehouse&action=purchasingPo', 'icon' => 'bi-cart3',
             'activeActions' => ['purchasingPo', 'createPurchasingPo']],
            ['label' => 'Purchase Requests', 'href' => '?controller=pr&action=procurement', 'icon' => 'bi-file-earmark-text',
             'activeActions' => ['procurement']],
            ['label' => 'MRP Approval', 'href' => '?controller=warehouse&action=mrpApproval', 'icon' => 'bi-shield-check',
             'activeActions' => ['mrpApproval'], 'badge' => 'mrpApproval'],
            ['label' => 'Receiving Purchasing PO', 'href' => '?controller=warehouse&action=receivingPo', 'icon' => 'bi-box-arrow-in-down',
             'activeActions' => ['receivingPo', 'receivePurchasingPo']],
            ['label' => 'Stock In/Out', 'href' => '?controller=warehouse&action=stockMovement', 'icon' => 'bi-arrow-left-right',
             'activeActions' => ['stockMovement']],
            ['label' => 'LMR Entry', 'href' => '?controller=lmr&action=index', 'icon' => 'bi-clipboard-check',
             'activeActions' => ['index', 'create', 'edit', 'printLmr', 'view'], 'controller' => 'lmr'],
            ['label' => 'MRP Sheet', 'href' => '?controller=warehouse&action=mrp', 'icon' => 'bi-calculator',
             'activeActions' => ['mrp', 'mrpHistory', 'mrpRunDetail']],
            ['label' => 'Activity Logs', 'href' => '?controller=warehouse&action=activityLogs', 'icon' => 'bi-clock-history',
             'activeActions' => ['activityLogs']],
            ['label' => 'Reports', 'href' => '#', 'icon' => 'bi-graph-up', 'collapse' => true,
             'activeActions' => ['reports', 'deliveryReport'],
             'sub' => [
                 ['label' => 'Overview', 'href' => '?controller=warehouse&action=reports', 'action' => 'reports', 'icon' => 'bi-bar-chart'],
                 ['label' => 'Deliveries', 'href' => '?controller=warehouse&action=deliveryReport', 'action' => 'deliveryReport', 'icon' => 'bi-truck'],
             ]],
        ];

        $visible = self::visibleWarehouseLabels($role);
        return array_values(array_filter($all, function ($item) use ($visible) {
            return in_array($item['label'], $visible, true);
        }));
    }

    private static function visibleWarehouseLabels(?string $role): array
    {
        switch ($role) {
            case 'whse_admin':
                return ['Dashboard', 'Purchase Request', 'Customer PO', 'Deliveries', 'Item Master List',
                    'Purchasing PO', 'Purchase Requests', 'MRP Approval', 'Receiving Purchasing PO',
                    'Stock In/Out', 'LMR Entry', 'MRP Sheet', 'Activity Logs', 'Reports'];
            case 'whse_officer':
                return ['Dashboard', 'Purchase Request', 'Deliveries', 'Receiving Purchasing PO',
                    'Item Master List', 'Activity Logs', 'Reports'];
            case 'whse_ppic':
                return ['Dashboard', 'Purchase Request', 'Customer PO', 'LMR Entry', 'MRP Sheet', 'Activity Logs'];
            case 'whse_procurement':
                return ['Dashboard', 'Purchase Request', 'Purchasing PO', 'Purchase Requests', 'Item Master List'];
            case 'whse_receiving':
                return ['Dashboard', 'Purchase Request', 'Stock In/Out', 'Item Master List'];
            default:
                return ['Dashboard', 'Purchase Request'];
        }
    }

    /** Menu items for a QC role.
     *  (MRP Check retired — runs release to Procurement at System Admin
     *  approval; raw material inspection happens at Receiving Inspection.) */
    public static function qcMenu(?string $role): array
    {
        $all = [
            ['label' => 'Dashboard', 'href' => '?controller=qc', 'icon' => 'bi-speedometer2'],
            ['label' => 'Receiving Inspection', 'href' => '?controller=qc&action=receivingInspection', 'icon' => 'bi-box-seam'],
            ['label' => 'For Delivery Inspection', 'href' => '?controller=qc&action=deliveryInspection', 'icon' => 'bi-truck'],
        ];
        $visible = $role === 'qc_inspector'
            ? ['Dashboard', 'For Delivery Inspection']
            : ['Dashboard', 'Receiving Inspection', 'For Delivery Inspection'];
        return array_values(array_filter($all, function ($item) use ($visible) {
            return in_array($item['label'], $visible, true);
        }));
    }
}
