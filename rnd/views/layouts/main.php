<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'R&D Dashboard' ?></title>
    <link href="public/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="public/css/sweetalert2.min.css" rel="stylesheet">
    <script src="public/js/sweetalert2.min.js"></script>
    <script src="public/js/dialogs.js"></script>
    <link href="public/css/style.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-bg: #1a2332;
            --sidebar-hover: #2d3a4f;
            --accent: #4a90d9;
            --accent-hover: #3a7bc8;
            --sidebar-width: 250px;
        }
        body { background: #f1f5f9; font-family: 'Inter', sans-serif; }

        .sidebar {
            min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            width: var(--sidebar-width);
        }
        .sidebar.collapsed { transform: translateX(-100%); }

        .main-wrapper {
            width: 100%;
            transition: padding-left 0.3s ease;
            padding-left: var(--sidebar-width);
        }
        .main-wrapper.sidebar-closed { padding-left: 0; }

        #sidebarToggle { z-index: 1001; }

        .sidebar .sidebar-menu { flex: 1; padding-top: 0.5rem; }
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 0.65rem 1.25rem;
            margin: 2px 10px;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }
        .sidebar .nav-link:hover { background: var(--sidebar-hover); color: #e2e8f0; }
        .sidebar .nav-link.active {
            background: rgba(74,144,217,0.15);
            color: #fff;
            font-weight: 600;
            border-left: 3px solid var(--accent);
            margin-left: 7px;
            padding-left: 10px;
        }
        .sidebar .nav-link .badge { font-size: 0.65rem; padding: 0.25em 0.55em; }

        .stat-card {
            background: #fff;
            border: none;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
            border-left: 4px solid #e2e8f0;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

        .data-card {
            background: #fff;
            border: none;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        .data-card .card-header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.85rem 1.25rem;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }
        .form-control:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(74,144,217,0.12); }
        .btn-primary { background: var(--accent); border-color: var(--accent); }
        .btn-primary:hover { background: var(--accent-hover); }
        .quick-add { border-left: 4px solid var(--accent); }
        .search-box { position: relative; }
        .search-box i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; z-index: 2; }
        .search-box input { padding-left: 38px; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable:hover { background-color: #f1f5f9; }
        th.sortable i { font-size: 10px; margin-left: 4px; }
        .table tbody tr:hover > td { background: #e8f2fc !important; }
        .table tbody tr:nth-child(even) > td { background: #f1f5f9 !important; }
        .table tbody tr:nth-child(odd) > td { background: #ffffff !important; }
        .alert { animation: slideInDown 0.3s ease; }
        @keyframes slideInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { border-left: 4px solid #22c55e; background: #f0fdf4; color: #166534; }
        .alert-danger { border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; }
        .alert-warning { border-left: 4px solid #f97316; background: #fff7ed; color: #9a3412; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- SIDEBAR NAV -->
            <nav class="sidebar" id="sidebar">
                <div class="text-center py-3 border-bottom" style="border-color: rgba(255,255,255,0.08) !important">
                    <h5 class="mb-0" style="color: #fff; font-weight: 700; letter-spacing: 0.5px; font-size: 1rem;">R&D</h5>
                    <small style="color: #64748b; font-size: 0.7rem;"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></small>
                </div>
<ul class="nav flex-column sidebar-menu">
<?php $currentAction = $_GET['action'] ?? ''; ?>
<li class="nav-item">
<a class="nav-link <?= in_array($currentAction, ['boms', 'bomCreate', 'bomEdit', 'bomUpdate', 'bomDelete', 'bomAddItem', 'bomUpdateItem', 'bomRemoveItem', 'bomImportPreview', 'bomImportConfirm']) ? 'active' : '' ?>" href="?controller=rnd&action=boms">
<i class="bi bi-diagram-3 me-2"></i>BOM Components
</a>
</li>
<li class="nav-item">
<a class="nav-link <?= (($_GET['controller'] ?? '') === 'pr') ? 'active' : '' ?>" href="?controller=pr">
<i class="bi bi-file-earmark-text me-2"></i>Purchase Request
</a>
</li>
<li class="nav-item">
<a class="nav-link <?= $currentAction === 'items' ? 'active' : '' ?>" href="?controller=rnd&action=items">
<i class="bi bi-box-seam me-2"></i>Item Master List
</a>
</li>
<li class="nav-item">
<a class="nav-link <?= in_array($currentAction, ['suppliers', 'supplierCreate', 'supplierUpdate', 'supplierToggleStatus']) ? 'active' : '' ?>" href="?controller=rnd&action=suppliers">
<i class="bi bi-truck me-2"></i>Suppliers
</a>
</li>
</ul>
            <div class="mt-auto p-3 border-top" style="border-color: rgba(255,255,255,0.08) !important">
                    <a href="?controller=auth&action=logout" class="nav-link text-center" style="color: #64748b; font-size: 0.875rem; transition: all 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">
                        <i class="bi bi-box-arrow-left me-2"></i>Logout
                    </a>
                </div>
            </nav>

            <!-- MAIN CONTENT WRAPPER -->
            <div class="main-wrapper" id="mainWrapper">
            <main class="p-4">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="mb-0"><?= $page_title ?? 'R&D Dashboard' ?></h4>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-outline-dark" id="sidebarToggle" title="Toggle Sidebar">
                            <i class="bi bi-list fs-5"></i>
                        </button>
                    </div>
                </div>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-circle me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?= $content ?? '' ?>
            </main>
            </div>
        </div>
    </div>
    <script src="public/js/bootstrap.bundle.min.js"></script>
    <script src="public/js/app.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    const toggleBtn = document.getElementById('sidebarToggle');

    toggleBtn.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        mainWrapper.classList.toggle('sidebar-closed');
        const icon = toggleBtn.querySelector('i');
        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('bi-list');
            icon.classList.add('bi-layout-text-sidebar-reverse');
        } else {
            icon.classList.remove('bi-layout-text-sidebar-reverse');
            icon.classList.add('bi-list');
        }
    });

    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            const form = this.querySelector('form');
            if (form) {
                form.reset();
                form.querySelectorAll('input[type="hidden"]').forEach(function(input) {
                    input.value = '';
                });
            }
        });
    });

    const flashAlerts = document.querySelectorAll('.alert.alert-dismissible.fade.show');
    flashAlerts.forEach(function(alertElement) {
        setTimeout(function() {
            const alert = bootstrap.Alert.getOrCreateInstance(alertElement);
            alert.close();
        }, 4000);
    });
});
</script>
</body>
</html>
