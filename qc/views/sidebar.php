<?php
// Role-driven menu: qc_admin sees both inspections; qc_inspector only delivery.
if (empty($sideMenu)) {
    $sideMenu = \App\Helpers\RoleHelper::qcMenu(\App\Helpers\RoleHelper::currentRole());
}
$currentQcAction = $_GET['action'] ?? '';
$isActiveItem = function (array $item) use ($currentQcAction) {
    if (strpos($item['href'], 'action=') === false) {
        return $currentQcAction === '';
    }
    parse_str(parse_url($item['href'], PHP_URL_QUERY) ?? '', $q);
    return ($q['action'] ?? '') === $currentQcAction;
};
?>
<ul class="nav flex-column sidebar-menu">
    <?php foreach ($sideMenu as $menuItem): ?>
    <li class="nav-item">
        <a class="nav-link <?= $isActiveItem($menuItem) ? 'active' : '' ?>" href="<?= htmlspecialchars($menuItem['href']) ?>">
            <i class="bi <?= htmlspecialchars($menuItem['icon']) ?> me-2"></i><?= htmlspecialchars($menuItem['label']) ?>
        </a>
    </li>
    <?php endforeach; ?>
    <li class="nav-item mt-auto">
        <a class="nav-link" href="?controller=auth&action=logout" style="color: #64748b; transition: all 0.2s;" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#64748b'">
            <i class="bi bi-box-arrow-left me-2"></i>Logout
        </a>
    </li>
</ul>
