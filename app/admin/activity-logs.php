<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $cid    = (int)$_POST['comment_id'];
    $status = $_POST['new_status'];
    if (in_array($status, ['active','deleted','flagged'])) {
        $pdo->prepare("UPDATE comments SET status=? WHERE comment_id=?")->execute([$status, $cid]);
        logActivity($pdo, $_SESSION['user_id'], 'COMMENT_UPDATED', "Comment #$cid status set to $status");
        $msg = 'Comment updated.';
    }
}

$comments = $pdo->query("
    SELECT c.*, f.category, f.message AS feedback_message
    FROM comments c
    JOIN feedback f ON c.feedback_id = f.feedback_id
    ORDER BY c.created_at DESC
")->fetchAll();

renderHeader('Comments');
renderSidebar('admin', 'Comments');
?>
<div class="topbar"><span class="topbar-title">Comments</span></div>
<div class="content">
  <div class="page-header"><h1>Comments</h1><p>Manage anonymous comments on feedback submissions.</p></div>
  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Feedback</th><th>Anonymous ID</th><th>Comment</th><th>Status</th><th>Posted</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($comments)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No comments yet.</td></tr>
          <?php else: foreach ($comments as $i => $c): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td>
              <div style="font-size:12px;color:var(--text-muted);"><?= sanitize(categoryLabel($c['category'])) ?></div>
              <span class="msg-truncate" style="font-size:12.5px;"><?= sanitize($c['feedback_message']) ?></span>
            </td>
            <td style="font-family:'DM Mono',monospace;font-size:11px;color:var(--text-muted);"><?= sanitize($c['anonymous_id']) ?></td>
            <td><span class="msg-truncate"><?= sanitize($c['content']) ?></span></td>
            <td>
              <?php
                $sc = ['active'=>'badge-active','deleted'=>'badge-inactive','flagged'=>'badge-high'];
                echo "<span class='badge " . ($sc[$c['status']] ?? 'badge-inactive') . "'>" . ucfirst($c['status']) . "</span>";
              ?>
            </td>
            <td class="text-muted"><?= timeAgo($c['created_at']) ?></td>
            <td>
              <form method="POST" style="display:flex;gap:6px;">
                <input type="hidden" name="comment_id" value="<?= $c['comment_id'] ?>">
                <select name="new_status" class="form-control" style="width:auto;padding:4px 8px;font-size:12px;">
                  <option value="active"   <?= $c['status']==='active'   ?'selected':''?>>Active</option>
                  <option value="flagged"  <?= $c['status']==='flagged'  ?'selected':''?>>Flagged</option>
                  <option value="deleted"  <?= $c['status']==='deleted'  ?'selected':''?>>Deleted</option>
                </select>
                <button type="submit" name="update_status" class="btn btn-primary btn-sm">Save</button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>