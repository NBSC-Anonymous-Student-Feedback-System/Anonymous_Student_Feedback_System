<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('staff');

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();
$recent        = $pdo->query("SELECT * FROM feedback ORDER BY submitted_at DESC LIMIT 8")->fetchAll();

renderHeader('Manager Dashboard');
renderSidebar('staff', 'Dashboard');
?>
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <div class="topbar-actions">
    <a href="<?= BASE_URL ?>/app/manager/notifications.php" class="notif-btn">
      <?= svgIcon('bell') ?>
      <?php if (getUnreadNotifCount($pdo, $_SESSION['user_id']) > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </a>
  </div>
</div>
<div class="content">
  <div class="page-header">
    <h1>Manager Dashboard</h1>
    <p>Welcome, <?= sanitize($_SESSION['first_name']) ?>. Review and manage submitted feedback.</p>
  </div>
  <div class="stats-grid">
    <div class="stat-card blue"><div class="stat-label">Total Feedback</div><div class="stat-value"><?= $totalFeedback ?></div></div>
    <div class="stat-card red"><div class="stat-label">Urgent</div><div class="stat-value"><?= $urgentCount ?></div></div>
  </div>
  <div class="card">
    <div class="card-header">
      <span class="card-title">Recent Feedback</span>
      <a href="<?= BASE_URL ?>/app/manager/feedback.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Category</th>
            <th>Message</th>
            <th>Priority</th>
            <th>Submitted</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $fb): ?>
          <tr>
            <td><?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td><span class="msg-truncate">🔒 Encrypted</span></td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
            <td><a href="<?= BASE_URL ?>/app/manager/feedback.php?view=<?= $fb['feedback_id'] ?>" class="btn btn-outline btn-sm">Review</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>