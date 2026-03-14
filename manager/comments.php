<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('staff');

$comments = $pdo->query("
    SELECT c.*, f.category, f.message AS feedback_message
    FROM comments c JOIN feedback f ON c.feedback_id=f.feedback_id
    ORDER BY c.created_at DESC
")->fetchAll();

renderHeader('Comments – Staff');
renderSidebar('staff','Comments');
?>
<div class="topbar"><span class="topbar-title">Comments</span></div>
<div class="content">
  <div class="page-header"><h1>Comments</h1><p>Anonymous comments posted on feedback.</p></div>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Feedback</th><th>Anonymous ID</th><th>Comment</th><th>Status</th><th>Posted</th></tr></thead>
        <tbody>
          <?php foreach ($comments as $i=>$c): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td><div style="font-size:12px;color:var(--text-muted);"><?= sanitize(categoryLabel($c['category'])) ?></div><span class="msg-truncate" style="font-size:12.5px;"><?= sanitize($c['feedback_message']) ?></span></td>
            <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--text-muted);"><?= sanitize($c['anonymous_id']) ?></td>
            <td><span class="msg-truncate"><?= sanitize($c['content']) ?></span></td>
            <td><?php $sc=['active'=>'badge-active','deleted'=>'badge-inactive','flagged'=>'badge-high']; echo "<span class='badge ".($sc[$c['status']]??'badge-inactive')."'>".ucfirst($c['status'])."</span>"; ?></td>
            <td class="text-muted"><?= timeAgo($c['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>