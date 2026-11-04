<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h4 class="mb-0"><i class="bi bi-people me-2"></i>Users &amp; Roles</h4>
    <span class="text-muted small">
        Assign a <b>role</b> to warehouse and QC users — each role controls their landing page, menu, and allowed features.
        The <b>Warehouse Admin</b> role automatically gates MRP draft approval (step 1).
        System Admin approval (step 2) is always any <code>admin</code> department user. New signups start with no role.
    </span>
</div>

<div class="card data-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Department</th>
                    <th>Login Status</th>
                    <th style="width:260px">Role</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
                <?php else: ?>
                <?php foreach ($users as $index => $u): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                    <td><?= htmlspecialchars($u['full_name'] ?? '') ?></td>
                    <td><?= ucfirst(htmlspecialchars($u['department'])) ?></td>
                    <td>
                        <?php if ((int) $u['status'] === 1): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $isSelf = ((int) $u['user_id'] === ($_SESSION['user_id'] ?? 0)); ?>
                        <?php $allowedRoles = $departmentRoles[$u['department']] ?? []; ?>
                        <?php if ($isSelf): ?>
                            <span class="text-muted small"><?= htmlspecialchars(\App\Helpers\RoleHelper::label($u['role'] ?? null)) ?> (current session)</span>
                        <?php elseif (empty($allowedRoles)): ?>
                            <span class="text-muted small">n/a (department-managed)</span>
                        <?php else: ?>
                            <form method="POST" action="?controller=admin&action=updateUserRole" class="d-flex gap-2">
                                <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                <select name="role" class="form-select form-select-sm">
                                    <option value="">— No role —</option>
                                    <?php foreach ($allowedRoles as $roleKey): ?>
                                    <option value="<?= htmlspecialchars($roleKey) ?>" <?= ($u['role'] ?? '') === $roleKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($roleOptions[$roleKey] ?? $roleKey) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap"
                                    data-confirm="Update role for <?= htmlspecialchars($u['username']) ?>?">
                                    <i class="bi bi-check-lg me-1"></i>Save
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
