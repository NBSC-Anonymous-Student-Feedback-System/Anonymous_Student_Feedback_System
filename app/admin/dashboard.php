<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');

$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalStaff    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='pending'")->fetchColumn();
$reviewedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='reviewed'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='resolved'")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();
$totalWarnings = $pdo->query("SELECT COUNT(*) FROM user_warnings")->fetchColumn();
$totalComments = $pdo->query("SELECT COUNT(*) FROM comments WHERE status='active'")->fetchColumn();

$recentFeedback = $pdo->query("SELECT * FROM feedback ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
$recentLogs = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS full_name
    FROM activity_logs a JOIN users u ON a.user_id=u.user_id
    ORDER BY a.created_at DESC LIMIT 6
")->fetchAll();

renderHeader('Admin Dashboard');
renderSidebar('admin', 'Dashboard');
?>
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <div class="topbar-actions">
    <a href="<?= BASE_URL ?>/app/admin/notifications.php" class="notif-btn">
      <?= svgIcon('bell') ?>
      <?php if (getUnreadNotifCount($pdo, $_SESSION['user_id']) > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </a>
  </div>
</div>
<div class="content">
  <div class="page-header">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <?= sanitize($_SESSION['first_name']) ?>. System overview.</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card purple"><div class="stat-label">Total Users</div><div class="stat-value"><?= $totalUsers ?></div></div>
    <div class="stat-card blue"><div class="stat-label">Admins</div><div class="stat-value"><?= $totalAdmins ?></div></div>
    <div class="stat-card green"><div class="stat-label">Staff</div><div class="stat-value"><?= $totalStaff ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Students</div><div class="stat-value"><?= $totalStudents ?></div></div>
  </div>
  <div class="stats-grid">
    <div class="stat-card blue"><div class="stat-label">Total Feedback</div><div class="stat-value"><?= $totalFeedback ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingCount ?></div></div>
    <div class="stat-card purple"><div class="stat-label">Reviewed</div><div class="stat-value"><?= $reviewedCount ?></div></div>
    <div class="stat-card green"><div class="stat-label">Resolved</div><div class="stat-value"><?= $resolvedCount ?></div></div>
    <div class="stat-card red"><div class="stat-label">Urgent</div><div class="stat-value"><?= $urgentCount ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Warnings</div><div class="stat-value"><?= $totalWarnings ?></div></div>
    <div class="stat-card blue"><div class="stat-label">Comments</div><div class="stat-value"><?= $totalComments ?></div></div>
  </div>

  <div class="row">
    <div class="col-8">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Feedback</span>
          <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Category</th><th>Message</th><th>Priority</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
              <?php foreach ($recentFeedback as $fb): ?>
              <tr>
                <td><?= sanitize(categoryLabel($fb['category'])) ?></td>
                <td><span class="msg-truncate"><?= sanitize($fb['message']) ?></span></td>
                <td><?= priorityBadge($fb['priority']) ?></td>
                <td><?= statusBadge($fb['status']) ?></td>
                <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Activity</span>
          <a href="<?= BASE_URL ?>/app/admin/activity-logs.php" class="btn btn-outline btn-sm">All</a>
        </div>
        <div class="card-body" style="padding:0;">
          <?php foreach ($recentLogs as $log): ?>
          <div style="padding:11px 20px;border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13px;font-weight:500;"><?= sanitize($log['full_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);"><?= sanitize($log['action']) ?> · <?= timeAgo($log['created_at']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>