<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');

$logs = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.role, u.email
    FROM activity_logs a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC
    LIMIT 200
")->fetchAll();

renderHeader('Activity Logs');
renderSidebar('admin', 'Activity Logs');
?>
<div class="topbar">
  <span class="topbar-title">Activity Logs</span>
</div>

<div class="content">
  <div class="page-header">
    <h1>Activity Logs</h1>
    <p>Track all system activity across users and admins.</p>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>User</th>
            <th>Role</th>
            <th>Action</th>
            <th>Description</th>
            <th>IP Address</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No logs found.</td></tr>
          <?php else: foreach ($logs as $i => $log): ?>
            <tr>
              <td class="text-muted"><?= $log['log_id'] ?></td>
              <td>
                <div style="font-weight:500;font-size:13px;"><?= sanitize($log['full_name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted);"><?= sanitize($log['email']) ?></div>
              </td>
              <td><?= roleBadge($log['role']) ?></td>
              <td>
                <span style="font-family:'DM Mono',monospace;font-size:11.5px;background:#f3f4f6;padding:2px 7px;border-radius:4px;">
                  <?= sanitize($log['action']) ?>
                </span>
              </td>
              <td style="max-width:280px;font-size:13px;"><?= sanitize($log['description']) ?></td>
              <td style="font-family:'DM Mono',monospace;font-size:12px;color:var(--text-muted);"><?= sanitize($log['ip_address']) ?></td>
              <td class="text-muted" style="white-space:nowrap;"><?= timeAgo($log['created_at']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>