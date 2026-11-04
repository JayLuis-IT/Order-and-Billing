<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Role Assigned</title>
    <link href="public/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/bootstrap-icons.min.css" rel="stylesheet">
    <link href="public/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <img src="public/images/logo.png" alt="Cianan Corp Logo" class="logo">
            <h4>No Role Assigned</h4>
            <small class="text-muted">Your account cannot access the system yet</small>
        </div>
        <div class="login-body text-center">
            <i class="bi bi-person-x" style="font-size: 3rem; color: #94a3b8"></i>
            <p class="mt-3 mb-1">Hello <strong><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></strong>,</p>
            <p class="text-muted mb-4">
                Your account does not have a role assigned yet.<br>
                Please contact the system administrator to assign your role.
            </p>
            <a href="?controller=auth&action=logout" class="btn btn-outline-secondary w-100">
                <i class="bi bi-box-arrow-left me-2"></i>Logout
            </a>
        </div>
    </div>
    <script src="public/js/bootstrap.bundle.min.js"></script>
</body>
</html>
