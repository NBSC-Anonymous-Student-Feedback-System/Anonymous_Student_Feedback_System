<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('staff');

$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='pending'")->fetchColumn();
$reviewedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='reviewed'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='resolved'")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();
$totalComments = $pdo->query("SELECT COUNT(*) FROM comments WHERE status='active'")->fetchColumn();

$recent = $pdo->query("SELECT * FROM feedback ORDER BY submitted_at DESC LIMIT 8")->fetchAll();

renderHeader('Staff Dashboard');
renderSidebar('staff','Dashboard');
?>
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <div class="topbar-actions">
    <a href="<?= BASE_URL ?>/app/manager/notifications.php" class="notif-btn">
      <?= svgIcon('bell') ?>
      <?php if (getUnreadNotifCount($pdo,$_SESSION['user_id'])>0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
  </div>
</div>
<div class="content">
  <div class="page-header"><h1>Staff Dashboard</h1><p>Welcome, <?= sanitize($_SESSION['first_name']) ?>. Review and respond to student feedback.</p></div>
  <div class="stats-grid">
    <div class="stat-card blue"><div class="stat-label">Total Feedback</div><div class="stat-value"><?= $totalFeedback ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingCount ?></div></div>
    <div class="stat-card purple"><div class="stat-label">Reviewed</div><div class="stat-value"><?= $reviewedCount ?></div></div>
    <div class="stat-card green"><div class="stat-label">Resolved</div><div class="stat-value"><?= $resolvedCount ?></div></div>
    <div class="stat-card red"><div class="stat-label">Urgent</div><div class="stat-value"><?= $urgentCount ?></div></div>
    <div class="stat-card blue"><div class="stat-label">Comments</div><div class="stat-value"><?= $totalComments ?></div></div>
  </div>
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Feedback</span>
      <a href="<?= BASE_URL ?>/app/manager/feedback.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Category</th><th>Message</th><th>Priority</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $fb): ?>
          <tr>
            <td><?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td><span class="msg-truncate"><?= sanitize($fb['message']) ?></span></td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td><?= statusBadge($fb['status']) ?></td>
            <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
            <td><a href="feedback.php?view=<?= $fb['feedback_id'] ?>" class="btn btn-outline btn-sm">Review</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>