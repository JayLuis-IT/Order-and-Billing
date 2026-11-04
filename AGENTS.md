# AGENTS.md

## Project Overview
PHP manufacturing management system. Custom MVC framework, no Composer, no npm. Runs on XAMPP with MySQL.

## Stack
- PHP (no framework), Bootstrap 5.3, MySQL (`manufacturing_mgmt` DB)
- XAMPP: Apache + MySQL, localhost
- No build step, no package manager, no linter, no tests

## Routing
`index.php?controller=<module>&action=<method>` — all requests go through `index.php`.
- Default: `controller=auth`, `action=login`
- Controller map: `auth` → `/auth/`, `warehouse` → `/warehouse/`, `production` → `/production/`, `finance` → `/finance/`, everything else → `/admin/`
- Autoloader maps `App\Controllers\{Module}Controller` to `/{module}/controllers/`

## Architecture
```
core/          - Config (session, BASE_PATH), BaseModel (PDO connection)
app/helpers/   - Pagination helper
admin/         - Admin module (customers, items, POs, delivered)
auth/          - Login/signup (no layout wrapper, standalone views)
warehouse/     - PO creation, deliveries
production/    - Production tracking, history
finance/       - Finance dashboard, delivery receipts, file uploads
public/        - Static assets (Bootstrap, CSS, JS, fonts, images)
sql/           - Schema: schema.sql (generated from live DB), applied/ + pending/ migrations
uploads/       - Receipt files (finance module)
```

## Conventions
- Each module: `controllers/`, `models/`, `views/`, `views/layouts/main.php`
- Models extend `App\Core\BaseModel`, use `self::getConnection()` for PDO
- Soft deletes: `remove = 1` column, never hard delete
- Session flash messages: `$_SESSION['success']`, `$_SESSION['error']`
- Controllers use `render()` method that extracts data, captures view output, wraps in layout
- AuthController is the exception: renders views directly without layout wrapper
- Department-based access control checked in controller constructors

## Roles (warehouse + QC)
- `users.role` (VARCHAR 30, nullable) — sub-role within department; NULL for non-warehouse/QC users or unassigned
- Warehouse roles: `whse_admin` (all + MRP approval auto-approver), `whse_officer` (DRs, receiving, items, logs, reports), `whse_ppic` (customer PO, LMR, MRP sheet, logs), `whse_procurement` (purchasing PO), `whse_receiving` (stock in/out, receiving view, items view)
- QC roles: `qc_admin` (receiving + delivery inspection), `qc_inspector` (delivery inspection only)
- `app/helpers/RoleHelper.php` is the single source of truth: action permission map, sidebar menus, role home URLs (`homeUrl()`, `warehouseCan()`, `qcCan()`, `warehouseMenu()`, `qcMenu()`)
- Warehouse/QC controller constructors enforce `RoleHelper` checks; sidebar menus render from `RoleHelper` (warehouse layout uses `$sideMenu`, QC uses `qc/views/sidebar.php`)
- Each role has its own landing dashboard (`warehouse/views/dashboards/{officer,ppic,procurement,receiving}.php`, `qc/views/dashboard/{admin,inspector}.php`); `whse_admin` keeps the original warehouse dashboard
- Warehouse/QC users without a role land on `?controller=auth&action=noRole`
- Roles are assigned via Admin → Users & Roles (`AdminController::updateUserRole`)
- Admin module (`AdminController`) is now locked to `department = admin`; cross-module "unauthorized" redirects go to `RoleHelper::homeUrl()` instead of `?controller=admin`
- `users.is_approver` is legacy — `WarehouseModel::isWarehouseApprover()` passes for `role = whse_admin` OR the old flag

## Approval flows
- MRP runs (2 gates): PPIC saves → WHSE Admin → System Admin → released to Procurement (`supplier_orders.status` `pending_whse_approval` → `pending_sysadmin_approval` → `requested`; stock reserved at the System Admin release). Zero-lacking runs: `mrp_runs.approval_status` `pending_whse_approval` → `pending_sysadmin_approval` → `approved`
- QC Admin does NOT gate runs (old MRP Check page retired/blocked — `qc&action=mrpCheck` redirects); QC inspects raw material at **Receiving Inspection** before stock is added
- PO drafts (manual cart + MRP group process + batch, all identical): `pending_whse_approval` (WHSE Admin) → `pending_admin_approval` (System Admin, Approvals hub) → `pending` (Active for receiving). `po_group_id` discriminates processed PO drafts (set) from born MRP requisition rows awaiting the run's own WHSE gate (NULL)
- MRP / Purchasing PO separation is enforced in `WarehouseModel`: run gates (`getMrpApprovalRuns`, `decideMrpRun`, `approve/rejectMrpRequisition`) act only on rows with `po_group_id IS NULL` — processed PO drafts can never re-enter an MRP approval queue or be run-approved (a past leak double-approved run #35)

## Database
- DB: `manufacturing_mgmt`, user: `root`, no password
- Schema: `sql/schema.sql` — generated dump of the live DB (33 tables + 3 views), do not hand-edit; regenerate per the command in its file header
- Migrations: `sql/applied/` (done) + `sql/pending/` (run with `sql/run_pending.php` or idempotent `sql/pending/deploy_current.php`; tracked in `schema_migrations`)
- Install admin: `php install-admin.php` (creates admin/admin user)
- Key tables: users, customers, items, inventory_balances, purchase_orders, purchase_order_items, deliveries, supplier_orders, mrp_runs, mrp_run_items, delivery_receipts, production_history

## Key Gotchas
- Hardcoded absolute paths in autoloader (`C:\xampp\htdocs\order-billing-system\...`) — won't work outside XAMPP default install
- `URL_ROOT` hardcoded to `http://localhost/order-billing-system/`
- `WarehouseModel` has methods spanning POs, deliveries, production, and users — it's a god model
- `sql/schema.sql` is a generated dump — regenerate instead of hand-editing; 5 unused-but-live tables + `vw_lmr_available_stock` are annotated "not used by application code"
- No CSRF protection on forms
- File uploads go to `uploads/receipts/` (finance module only)
- `warehouse` and `finance` controllers restrict actions by `$_SESSION['department']`

## Development
- No linting, no typecheck, no tests, no build commands
- Edit PHP files directly, refresh browser
- Bootstrap/Icons CSS/JS must be downloaded manually (see `public/README.txt`)
