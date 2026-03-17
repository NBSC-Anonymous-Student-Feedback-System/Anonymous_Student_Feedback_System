<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('manager');

$fid = (int)($_GET['id'] ?? 0);
$rid = (int)($_GET['rid'] ?? 0);

// Verify the request is approved and belongs to this manager
$req = $pdo->prepare("SELECT rr.*, f.category, f.priority, f.message_enc, f.message_hash, f.submitted_at
    FROM review_requests rr
    JOIN feedback f ON rr.feedback_id = f.feedback_id
    WHERE rr.request_id=? AND rr.requested_by=? AND rr.status='approved' AND rr.feedback_id=?");
$req->execute([$rid, $_SESSION['user_id'], $fid]);
$data = $req->fetch();

if (!$data) {
    redirect(BASE_URL . '/app/manager/feedback.php');
}

// Off-hours check
$offHours = !isWithinOfficeHours();

$msg = ''; $err = '';
$plaintext = null;

// Only decrypt if within office hours
if (!$offHours) {
    $plaintext = decryptMessage($data['message_enc']);

    if ($plaintext) {
        $integrityOk = verifyIntegrity($plaintext, $data['message_hash']);
        logActivity($pdo, 'FEEDBACK_ACCESSED', "Manager accessed and read Feedback #$fid (Request #$rid)", $_SESSION['user_id']);
    }
}

renderHeader('View Feedback');
renderSidebar('manager', 'All Feedback');
?>
<div class="topbar">
  <span class="topbar-title">View Feedback</span>
  <a href="feedback.php" class="btn btn-outline btn-sm" style="margin-left:auto;">← Back</a>
</div>
<div class="content" style="max-width:760px;">

  <?php if ($offHours): ?>
    <div class="alert alert-warning">🕐 <strong>Off-Hours:</strong> <?= offHoursMessage() ?></div>
  <?php endif; ?>
  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <!-- Feedback card -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <span class="card-title">Feedback #<?= $fid ?></span>
      <div class="flex gap-2">
        <?= priorityBadge($data['priority']) ?>
      </div>
    </div>
    <div class="card-body">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;">
        <div>
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:4px;">Category</div>
          <div style="font-weight:600;"><?= categoryIcon($data['category']) ?> <?= sanitize(categoryLabel($data['category'])) ?></div>
        </div>
        <div>
          <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:4px;">Submitted</div>
          <div><?= timeAgo($data['submitted_at']) ?></div>
        </div>
      </div>

      <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:8px;">Feedback Message</div>
      <?php if ($offHours): ?>
        <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:16px;font-size:13.5px;color:#92400e;">
          🕐 Content hidden — outside of office hours.
        </div>
      <?php elseif ($plaintext): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:16px;font-size:14px;line-height:1.7;">
          <?= sanitize($plaintext) ?>
        </div>
      <?php else: ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:16px;font-size:13.5px;color:#991b1b;">
          ❌ Could not decrypt feedback content. Please contact the administrator.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Review request info -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header"><span class="card-title">Your Review Request</span><?= requestStatusBadge($data['status']) ?></div>
    <div class="card-body">
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Stated Purpose</div>
      <div style="background:#f9fafb;border-radius:8px;padding:12px;font-size:13.5px;"><?= sanitize($data['purpose']) ?></div>
      <?php if ($data['admin_notes']): ?>
        <div style="font-size:12px;color:var(--text-muted);margin-top:12px;margin-bottom:4px;">Admin Notes</div>
        <div style="background:#eff6ff;border-radius:8px;padding:12px;font-size:13.5px;color:#1e40af;"><?= sanitize($data['admin_notes']) ?></div>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php renderSidebarClose(); renderFooter(); ?>