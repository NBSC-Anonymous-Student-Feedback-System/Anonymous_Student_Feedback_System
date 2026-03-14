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
    $fid    = (int)$_POST['feedback_id'];
    $status = $_POST['new_status'];
    $notes  = trim($_POST['review_notes'] ?? '');
    if (in_array($status, ['pending','reviewed','resolved'])) {
        $pdo->prepare("UPDATE feedback SET status=? WHERE feedback_id=?")->execute([$status, $fid]);
        $pdo->prepare("INSERT INTO feedback_reviews (feedback_id, reviewed_by, review_notes, status_changed) VALUES (?,?,?,?)")
            ->execute([$fid, $_SESSION['user_id'], $notes, $status]);
       logActivity($pdo, 'STATUS_CHANGED', "Feedback #$fid marked as $status", $_SESSION['user_id']);
        $msg = 'Feedback updated successfully.';
    }
}

$fs = $_GET['status'] ?? '';
$fp = $_GET['priority'] ?? '';
$fc = $_GET['category'] ?? '';

$where = []; $params = [];
if ($fs) { $where[] = 'status=?'; $params[] = $fs; }
if ($fp) { $where[] = 'priority=?'; $params[] = $fp; }
if ($fc) { $where[] = 'category=?'; $params[] = $fc; }

$sql = "SELECT * FROM feedback";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY submitted_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$feedbacks = $stmt->fetchAll();

$categories = ['general','academic','facilities','services','faculty','administration','suggestion','complaint','other'];

renderHeader('Feedback');
renderSidebar('admin', 'Feedback');
?>
<div class="topbar"><span class="topbar-title">Feedback Management</span></div>
<div class="content">
  <div class="page-header"><h1>Feedback</h1><p>Review and manage all submitted feedback.</p></div>
  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

  <form method="GET" class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
      <div class="flex gap-3 items-center flex-wrap">
        <select name="status" class="form-control" style="width:auto;">
          <option value="">All Status</option>
          <option value="pending"  <?= $fs==='pending'  ?'selected':''?>>Pending</option>
          <option value="reviewed" <?= $fs==='reviewed' ?'selected':''?>>Reviewed</option>
          <option value="resolved" <?= $fs==='resolved' ?'selected':''?>>Resolved</option>
        </select>
        <select name="priority" class="form-control" style="width:auto;">
          <option value="">All Priority</option>
          <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
            <option value="<?= $p ?>" <?= $fp===$p?'selected':''?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
        <select name="category" class="form-control" style="width:auto;">
          <option value="">All Categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat ?>" <?= $fc===$cat?'selected':''?>><?= categoryLabel($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="feedback.php" class="btn btn-outline">Reset</a>
      </div>
    </div>
  </form>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>Category</th><th>Message</th><th>Priority</th><th>Status</th><th>Submitted</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($feedbacks)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No feedback found.</td></tr>
          <?php else: foreach ($feedbacks as $i => $fb): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td><span class="msg-truncate"><?= sanitize($fb['message']) ?></span></td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td><?= statusBadge($fb['status']) ?></td>
            <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
            <td>
              <button class="btn btn-outline btn-sm"
                onclick="openReview(<?= $fb['feedback_id'] ?>,'<?= addslashes(sanitize($fb['message'])) ?>','<?= $fb['status'] ?>','<?= $fb['priority'] ?>','<?= $fb['category'] ?>')">
                Review
              </button>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="reviewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;width:520px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
      <span style="font-weight:600;font-size:15px;">Review Feedback</span>
      <button onclick="closeReview()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;">×</button>
    </div>
    <div style="padding:20px 24px;">
      <div style="margin-bottom:14px;"><div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Category / Priority</div><div id="modal-meta" style="font-size:13px;"></div></div>
      <div style="margin-bottom:14px;"><div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Message</div><div id="modal-msg" style="font-size:13.5px;background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb;"></div></div>
      <form method="POST">
        <input type="hidden" name="feedback_id" id="modal-fid">
        <div class="form-group">
          <label class="form-label">Update Status</label>
          <select name="new_status" id="modal-status" class="form-control">
            <option value="pending">Pending</option>
            <option value="reviewed">Reviewed</option>
            <option value="resolved">Resolved</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Review Notes</label>
          <textarea name="review_notes" class="form-control" rows="3" placeholder="Add notes..."></textarea>
        </div>
        <div class="flex gap-2" style="justify-content:flex-end;">
          <button type="button" onclick="closeReview()" class="btn btn-outline">Cancel</button>
          <button type="submit" name="update_status" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openReview(id,msg,status,priority,category){
  document.getElementById('reviewModal').style.display='flex';
  document.getElementById('modal-fid').value=id;
  document.getElementById('modal-msg').textContent=msg;
  document.getElementById('modal-meta').textContent=category+' · '+priority;
  document.getElementById('modal-status').value=status;
}
function closeReview(){document.getElementById('reviewModal').style.display='none';}
</script>
<?php renderSidebarClose(); renderFooter(); ?>