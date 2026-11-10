<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Production Dashboard' ?></title>
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
        .alert { animation: slideInDown 0.3s ease; }
        @keyframes slideInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { border-left: 4px solid #22c55e; background: #f0fdf4; color: #166534; }
        .alert-danger { border-left: 4px solid #ef4444; background: #fef2f2; color: #991b1b; }
        .notif-dropdown { position: absolute; right: 0; top: calc(100% + 4px); z-index: 1050; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.12); width: 380px; max-height: 500px; overflow-y: auto; }
        .notif-item:hover { background: #f8fafc; }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- SIDEBAR NAV - Slides in/out via CSS transform -->
            <nav class="sidebar" id="sidebar">
                <div class="text-center py-3 border-bottom" style="border-color: rgba(255,255,255,0.08) !important">
                    <h5 class="mb-0" style="color: #fff; font-weight: 700; letter-spacing: 0.5px; font-size: 1rem;"><?= ucfirst($_SESSION['department'] ?? 'Production') ?></h5>
                    <small style="color: #64748b; font-size: 0.7rem;"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></small>
                </div>
                <ul class="nav flex-column sidebar-menu">
                    <li class="nav-item">
                        <a class="nav-link <?= $page_title == 'Production Dashboard' ? 'active' : '' ?>" href="?controller=production">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($page_title ?? '', 'FG Inventory') !== false ? 'active' : '' ?>" href="?controller=production&action=fgInventory">
                            <i class="bi bi-box-seam me-2"></i>FG Inventory
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($page_title ?? '', 'Lots') !== false && strpos($page_title ?? '', 'FG Input') === false ? 'active' : '' ?>" href="?controller=production&action=lots">
                            <i class="bi bi-stack me-2"></i>Lots
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($page_title ?? '', 'FG Input') !== false ? 'active' : '' ?>" href="?controller=production&action=fgInput">
                            <i class="bi bi-box-seam me-2"></i>Finished Goods Input
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= strpos($page_title ?? '', 'Production History') !== false ? 'active' : '' ?>" href="?controller=production&action=history">
                            <i class="bi bi-clock-history me-2"></i>Production History
                            <?php if (!empty($reportsCount) && $reportsCount > 0): ?>
                                <span class="badge bg-warning text-dark float-end"><?= $reportsCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($currentAction ?? '') === 'activityLogs' ? 'active' : '' ?>" href="?controller=production&action=activityLogs">
                            <i class="bi bi-clock-history me-2"></i>Activity Logs
                        </a>
                    </li>
                    <li class="nav-item mt-auto">
                        <a class="nav-link" href="?controller=auth&action=logout" style="color: #64748b; transition: all 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">
                            <i class="bi bi-box-arrow-left me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- MAIN CONTENT - Full width always -->
            <div class="main-wrapper" id="mainWrapper">
            <main class="p-4">
                <!-- Header with toggle button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="mb-0"><?= $page_title ?? 'Dashboard' ?></h4>
                    <!-- TOGGLE BUTTON - Click to slide sidebar -->
                    <div class="d-flex align-items-center gap-2">
                        <!-- NOTIFICATION BELL -->
                        <div class="position-relative">
                            <button class="btn btn-outline-dark position-relative" id="notifBell" title="Notifications">
                                <i class="bi bi-bell"></i>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" id="notifBadge" style="font-size:0.6rem">0</span>
                            </button>
                            <div class="notif-dropdown d-none" id="notifDropdown">
                                <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                                    <strong style="font-size:0.9rem">Notifications</strong>
                                    <button class="btn btn-sm btn-link text-decoration-none" id="markAllReadBtn" style="font-size:0.75rem">Mark all read</button>
                                </div>
                                <div id="notifList"></div>
                            </div>
                        </div>
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
    <script>var URL_ROOT = '<?= URL_ROOT ?>';</script>
    <script src="public/js/notifications.js"></script>
    <script src="public/js/network-status.js"></script>
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

    // Auto-dismiss flash alerts after 4 seconds
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