<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('manager');
$msg = ''; $err = '';

// ── Submit a review request (state purpose) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $fid     = (int)$_POST['feedback_id'];
    $purpose = trim($_POST['purpose'] ?? '');

    // Off-hours check
    if (!isWithinOfficeHours()) {
        $err = offHoursMessage();
    } elseif (strlen($purpose) < 10) {
        $err = 'Please provide a detailed purpose (at least 10 characters).';
    } else {
        // Check if there is already a pending/approved request for this feedback by this manager
        $existing = $pdo->prepare("SELECT request_id, status FROM review_requests WHERE feedback_id=? AND requested_by=? AND status IN ('pending','approved')");
        $existing->execute([$fid, $_SESSION['user_id']]);
        $ex = $existing->fetch();

        if ($ex) {
            if ($ex['status'] === 'pending') {
                $err = 'You already have a pending review request for this feedback. Please wait for admin approval.';
            } else {
                // approved — redirect to view
                redirect(BASE_URL . '/app/manager/view-feedback.php?id=' . $fid . '&rid=' . $ex['request_id']);
            }
        } else {
            $pdo->prepare("INSERT INTO review_requests (feedback_id, requested_by, purpose) VALUES (?,?,?)")
                ->execute([$fid, $_SESSION['user_id'], $purpose]);
            $reqId = $pdo->lastInsertId();

            // Notify admins
            $admins = $pdo->query("SELECT user_id FROM users WHERE role='admin' AND status='active'")->fetchAll();
            foreach ($admins as $a) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                    ->execute([$a['user_id'], 'New Review Request', $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . " submitted a review request for Feedback #$fid."]);
            }

            logActivity($pdo, 'REVIEW_REQUEST', "Manager requested review of Feedback #$fid. Purpose: $purpose", $_SESSION['user_id']);
            $msg = 'Review request submitted. Waiting for admin approval.';
        }
    }
}

// ── Filters ───────────────────────────────────────────────────────
$fp = $_GET['priority'] ?? '';
$where = []; $params = [];
if ($fp) { $where[] = 'priority=?'; $params[] = $fp; }
$sql = "SELECT * FROM feedback" . ($where ? ' WHERE '.implode(' AND ',$where) : '') . ' ORDER BY FIELD(priority,"Urgent","High","Medium","Low"), submitted_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Get this manager's existing requests
$myRequests = [];
$rq = $pdo->prepare("SELECT feedback_id, request_id, status FROM review_requests WHERE requested_by=?");
$rq->execute([$_SESSION['user_id']]);
foreach ($rq->fetchAll() as $r) {
    $myRequests[$r['feedback_id']] = $r;
}

$offHours = !isWithinOfficeHours();

renderHeader('Feedback — Manager');
renderSidebar('manager', 'All Feedback');
?>
<div class="topbar"><span class="topbar-title">All Feedback</span></div>
<div class="content">
  <div class="page-header">
    <h1>Feedback</h1>
    <p>Submit a review request to read encrypted feedback content.</p>
  </div>

  <?php if ($offHours): ?>
    <div class="alert alert-warning" style="display:flex;align-items:center;gap:10px;">
      🕐 <strong>Off-Hours Notice:</strong> &nbsp;<?= offHoursMessage() ?>
    </div>
  <?php endif; ?>

  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <!-- Filter -->
  <form method="GET" class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:14px 20px;">
      <div class="flex gap-3 items-center flex-wrap">
        <select name="priority" class="form-control" style="width:auto;">
          <option value="">All Priority</option>
          <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
            <option value="<?= $p ?>" <?= $fp===$p?'selected':''?>><?= $p ?></option>
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
        <thead>
          <tr><th>#</th><th>Category</th><th>Priority</th><th>Submitted</th><th>Request Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php if (empty($feedbacks)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No feedback found.</td></tr>
          <?php else: foreach ($feedbacks as $i => $fb):
            $req = $myRequests[$fb['feedback_id']] ?? null;
          ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td><?= sanitize(categoryIcon($fb['category'])) ?> <?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
            <td>
              <?php if (!$req): ?>
                <span class="badge badge-inactive">No Request</span>
              <?php else: ?>
                <?= requestStatusBadge($req['status']) ?>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($offHours): ?>
                <span style="font-size:12px;color:var(--text-muted);">Off-hours 🕐</span>
              <?php elseif (!$req || $req['status'] === 'rejected'): ?>
                <button class="btn btn-primary btn-sm"
                  onclick="openPurposeModal(<?= $fb['feedback_id'] ?>, '<?= sanitize(categoryLabel($fb['category'])) ?>', '<?= $fb['priority'] ?>')">
                  Request Access
                </button>
              <?php elseif ($req['status'] === 'pending'): ?>
                <span style="font-size:12px;color:#d97706;">⏳ Awaiting Approval</span>
              <?php elseif ($req['status'] === 'approved'): ?>
                <a href="view-feedback.php?id=<?= $fb['feedback_id'] ?>&rid=<?= $req['request_id'] ?>" class="btn btn-outline btn-sm">
                  🔓 View Feedback
                </a>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Purpose Modal -->
<div id="purposeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:200;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;width:520px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
      <div>
        <span style="font-weight:700;font-size:15px;">🔍 Request Feedback Access</span>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">This request will be logged and reviewed by the Admin.</div>
      </div>
      <button onclick="closePurposeModal()" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;">×</button>
    </div>
    <div style="padding:20px 24px;">
      <div id="modal-fb-info" style="background:#f9fafb;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:var(--text-muted);"></div>
      <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12.5px;color:#92400e;">
        ⚠️ <strong>Data Privacy Notice:</strong> Your access request, stated purpose, and the time of access will be permanently logged for audit purposes.
      </div>
      <form method="POST">
        <input type="hidden" name="feedback_id" id="modal-fid">
        <div class="form-group">
          <label class="form-label" style="font-weight:600;">Why do you need to review this feedback? *</label>
          <textarea name="purpose" class="form-control" rows="4" minlength="10" required
            placeholder="State your official purpose clearly. Example: Investigating a reported academic integrity concern related to grading in the IT department..."></textarea>
          <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Minimum 10 characters. Be specific — vague purposes will be rejected.</div>
        </div>
        <div class="flex gap-2" style="justify-content:flex-end;">
          <button type="button" onclick="closePurposeModal()" class="btn btn-outline">Cancel</button>
          <button type="submit" name="submit_request" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openPurposeModal(fid, category, priority) {
  document.getElementById('purposeModal').style.display = 'flex';
  document.getElementById('modal-fid').value = fid;
  document.getElementById('modal-fb-info').textContent = 'Feedback #' + fid + ' · ' + category + ' · Priority: ' + priority;
}
function closePurposeModal() {
  document.getElementById('purposeModal').style.display = 'none';
}
</script>
<?php renderSidebarClose(); renderFooter(); ?>