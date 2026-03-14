<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');
$msg = ''; $err = '';

// Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decide'])) {
    $rid        = (int)$_POST['request_id'];
    $decision   = $_POST['decision'];
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (in_array($decision, ['approved', 'rejected'])) {
        $pdo->prepare("UPDATE review_requests SET status=?, reviewed_by=?, admin_notes=?, resolved_at=NOW() WHERE request_id=?")
            ->execute([$decision, $_SESSION['user_id'], $adminNotes, $rid]);

        // Get request info to notify manager
        $rInfo = $pdo->prepare("SELECT rr.requested_by, rr.feedback_id, CONCAT(u.first_name,' ',u.last_name) AS mname
            FROM review_requests rr JOIN users u ON rr.requested_by=u.user_id WHERE rr.request_id=?");
        $rInfo->execute([$rid]);
        $ri = $rInfo->fetch();

        if ($ri) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                ->execute([$ri['requested_by'], 'Review Request ' . ucfirst($decision),
                    "Your review request for Feedback #{$ri['feedback_id']} has been $decision." . ($adminNotes ? " Note: $adminNotes" : '')]);
        }

        logActivity($pdo, 'REQUEST_' . strtoupper($decision), "Admin $decision review request #$rid", $_SESSION['user_id']);
        $msg = "Request #$rid has been $decision.";
    }
}

$filter = $_GET['filter'] ?? 'pending';
$where  = $filter !== 'all' ? "WHERE rr.status=?" : "";
$params = $filter !== 'all' ? [$filter] : [];

$requests = $pdo->prepare("
    SELECT rr.*,
           CONCAT(u.first_name,' ',u.last_name)  AS manager_name,
           u.email AS manager_email, u.department,
           f.category, f.priority,
           CONCAT(a.first_name,' ',a.last_name)  AS admin_name
    FROM review_requests rr
    JOIN users u  ON rr.requested_by = u.user_id
    JOIN feedback f ON rr.feedback_id = f.feedback_id
    LEFT JOIN users a ON rr.reviewed_by = a.user_id
    $where
    ORDER BY rr.requested_at DESC
");
$requests->execute($params);
$requests = $requests->fetchAll();

$pendingCount = $pdo->query("SELECT COUNT(*) FROM review_requests WHERE status='pending'")->fetchColumn();

renderHeader('Review Requests');
renderSidebar('admin', 'Review Requests');
?>
<div class="topbar">
  <span class="topbar-title">Review Requests</span>
  <?php if ($pendingCount > 0): ?>
    <span class="badge badge-urgent" style="margin-left:10px;"><?= $pendingCount ?> Pending</span>
  <?php endif; ?>
</div>
<div class="content">
  <div class="page-header">
    <h1>Review Requests</h1>
    <p>Authorize or reject manager requests to access encrypted feedback.</p>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <!-- Filter tabs -->
  <div style="display:flex;gap:8px;margin-bottom:20px;">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $val => $label): ?>
      <a href="?filter=<?= $val ?>" class="btn <?= $filter===$val ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Manager</th><th>Feedback</th><th>Purpose</th><th>Requested</th><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if (empty($requests)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No requests found.</td></tr>
          <?php else: foreach ($requests as $r): ?>
          <tr>
            <td class="text-muted">#<?= $r['request_id'] ?></td>
            <td>
              <div style="font-weight:600;font-size:13px;"><?= sanitize($r['manager_name']) ?></div>
              <div style="font-size:11.5px;color:var(--text-muted);"><?= sanitize($r['manager_email']) ?></div>
              <div style="font-size:11px;color:var(--text-light);"><?= sanitize($r['department']) ?></div>
            </td>
            <td>
              <div><?= categoryIcon($r['category']) ?> <?= sanitize(categoryLabel($r['category'])) ?></div>
              <div style="margin-top:3px;"><?= priorityBadge($r['priority']) ?></div>
            </td>
            <td style="max-width:260px;">
              <div style="font-size:13px;line-height:1.5;"><?= sanitize($r['purpose']) ?></div>
            </td>
            <td class="text-muted"><?= timeAgo($r['requested_at']) ?></td>
            <td>
              <?= requestStatusBadge($r['status']) ?>
              <?php if ($r['admin_name']): ?>
                <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">by <?= sanitize($r['admin_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <button class="btn btn-primary btn-sm" onclick="openDecide(<?= $r['request_id'] ?>,'<?= sanitize($r['manager_name']) ?>','<?= sanitize(categoryLabel($r['category'])) ?>')">
                  Review
                </button>
              <?php else: ?>
                <span style="font-size:12px;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Decision Modal -->
<div id="decideModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:480px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;">
      <span style="font-weight:700;font-size:15px;">Authorize Review Request</span>
      <div id="decide-info" style="font-size:12.5px;color:var(--text-muted);margin-top:3px;"></div>
    </div>
    <div style="padding:20px 24px;">
      <form method="POST">
        <input type="hidden" name="request_id" id="decide-rid">
        <div class="form-group">
          <label class="form-label">Admin Notes <span style="font-size:11px;color:var(--text-muted);">(optional)</span></label>
          <textarea name="admin_notes" class="form-control" rows="3" placeholder="Provide a reason for your decision..."></textarea>
        </div>
        <div class="flex gap-2" style="justify-content:flex-end;">
          <button type="button" onclick="closeDecide()" class="btn btn-outline">Cancel</button>
          <button type="submit" name="decide" value="decide" onclick="setDecision('rejected')" class="btn btn-danger">Reject</button>
          <button type="submit" name="decide" value="decide" onclick="setDecision('approved')" class="btn btn-primary">Approve</button>
        </div>
        <input type="hidden" name="decision" id="decision-val">
      </form>
    </div>
  </div>
</div>

<script>
function openDecide(rid, manager, category) {
  document.getElementById('decideModal').style.display = 'flex';
  document.getElementById('decide-rid').value = rid;
  document.getElementById('decide-info').textContent = 'Request #' + rid + ' — ' + manager + ' → ' + category;
}
function closeDecide() { document.getElementById('decideModal').style.display = 'none'; }
function setDecision(val) { document.getElementById('decision-val').value = val; }
</script>
<?php renderSidebarClose(); renderFooter(); ?>