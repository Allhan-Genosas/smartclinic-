<?php
require_once __DIR__ . '/includes/functions.php';
require_role(['doctor', 'nurse', 'admin']);

$u = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if (in_array($u['role'], ['doctor', 'admin'], true) && $action === 'create') {
        $assigned = (int)($_POST['assigned_to'] ?? 0);
        $patient = (int)($_POST['patient_id'] ?? 0) ?: null;
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'normal';
        $due = $_POST['due_at'] ?? null;

        if ($assigned && $title && in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $s = $pdo->prepare('INSERT INTO tasks(assigned_to, assigned_by, patient_id, title, description, priority, due_at, status) VALUES(?, ?, ?, ?, ?, ?, ?, "pending")');
            $s->execute([$assigned, $u['id'], $patient, $title, $desc, $priority, $due ?: null]);

            notify($assigned, 'New staff task', $title, 'task');
            audit('CREATE_TASK', $title);
            flash('Task assigned.');
        }
        redirect('tasks.php');
    }

    if ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if (in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            $sql = 'UPDATE tasks SET status = ?, completed_at = IF(? = "completed", NOW(), NULL) WHERE id = ?';
            $args = [$status, $status, $id];

            if ($u['role'] === 'nurse') {
                $sql .= ' AND assigned_to = ?';
                $args[] = $u['id'];
            }

            $pdo->prepare($sql)->execute($args);
            audit('UPDATE_TASK', 'Task ' . $id . ' -> ' . $status);
            flash('Task updated.');
        }
        redirect('tasks.php');
    }
}

$pageTitle = 'Staff Tasks';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <div>
        <span class="kicker">Clinical coordination</span>
        <h1>Task assignment</h1>
        <p>Coordinate vitals checks, room preparation, follow-ups and lab preparation across clinic staff.</p>
    </div>
    <?php if (in_array($u['role'], ['doctor', 'admin'], true)): ?>
        <button class="btn btn-primary" data-modal="taskModal">＋ Assign task</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Patient</th>
                    <th>Assigned to</th>
                    <th>Priority</th>
                    <th>Due</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sql = 'SELECT t.*, CONCAT(au.first_name, " ", au.last_name) assignee, CONCAT(pu.first_name, " ", pu.last_name) patient_name FROM tasks t JOIN users au ON au.id = t.assigned_to LEFT JOIN patients p ON p.id = t.patient_id LEFT JOIN users pu ON pu.id = p.user_id';
                $args = [];

                if ($u['role'] === 'nurse') {
                    $sql .= ' WHERE t.assigned_to = ?';
                    $args[] = $u['id'];
                }

                $sql .= ' ORDER BY FIELD(t.status, "pending", "in_progress", "completed"), FIELD(t.priority, "urgent", "high", "normal", "low"), t.due_at';
                $s = $pdo->prepare($sql);
                $s->execute($args);
                $rows = $s->fetchAll();

                if (!$rows):
                ?>
                    <tr>
                        <td colspan="7">
                            <div class="empty">No tasks found.</div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>
                                <b><?= e($r['title']) ?></b><br>
                                <span class="muted"><?= e($r['description']) ?></span>
                            </td>
                            <td><?= e($r['patient_name'] ?: 'General') ?></td>
                            <td><?= e($r['assignee']) ?></td>
                            <td><span class="pill"><?= e($r['priority']) ?></span></td>
                            <td><?= $r['due_at'] ? date('d M, g:i A', strtotime($r['due_at'])) : '—' ?></td>
                            <td><?= status_badge($r['status']) ?></td>
                            <td>
                                <form method="post" style="display:flex;gap:6px">
                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="action" value="status">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <select name="status" class="btn btn-ghost btn-sm">
                                        <option <?= $r['status'] === 'pending' ? 'selected' : '' ?>>pending</option>
                                        <option value="in_progress" <?= $r['status'] === 'in_progress' ? 'selected' : '' ?>>in progress</option>
                                        <option <?= $r['status'] === 'completed' ? 'selected' : '' ?>>completed</option>
                                    </select>
                                    <button class="btn btn-primary btn-sm">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (in_array($u['role'], ['doctor', 'admin'], true)): ?>
    <?php
    $staff = $pdo->query('SELECT id, first_name, last_name, role FROM users WHERE role = "nurse" AND status = "active"')->fetchAll();
    $patients = $pdo->query('SELECT p.id, CONCAT(u.first_name, " ", u.last_name) name FROM patients p JOIN users u ON u.id = p.user_id ORDER BY u.last_name')->fetchAll();
    ?>
    <div class="modal" id="taskModal">
        <div class="modal-card">
            <div class="modal-head">
                <div>
                    <span class="kicker">Coordinate staff</span>
                    <h2>Assign a clinical task</h2>
                </div>
                <button class="modal-close" data-close-modal>×</button>
            </div>
            <form method="post" class="form-grid">
                <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="create">
                <div class="field">
                    <label>Assign to</label>
                    <select name="assigned_to" required>
                        <?php foreach ($staff as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Patient (optional)</label>
                    <select name="patient_id">
                        <option value="">General task</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full">
                    <label>Task title</label>
                    <input name="title" required>
                </div>
                <div class="field full">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>
                <div class="field">
                    <label>Priority</label>
                    <select name="priority">
                        <option>normal</option>
                        <option>high</option>
                        <option>urgent</option>
                        <option>low</option>
                    </select>
                </div>
                <div class="field">
                    <label>Due</label>
                    <input type="datetime-local" name="due_at">
                </div>
                <div class="field full">
                    <button class="btn btn-primary">Assign task</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>