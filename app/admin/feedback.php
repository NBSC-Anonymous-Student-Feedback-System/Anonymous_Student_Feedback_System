<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');

// Admin sees metadata ONLY — no feedback content decryption
$fp = $_GET['priority'] ?? '';
$fc = $_GET['category'] ?? '';

$where = []; $params = [];
if ($fp) { $where[] = 'priority=?'; $params[] = $fp; }
if ($fc) { $where[] = 'category=?'; $params[] = $fc; }

$sql = "SELECT feedback_id, category, priority, submitted_at FROM feedback";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY submitted_at DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$feedbacks = $stmt->fetchAll();

$categories = ['general','academic','facilities','services','faculty','administration','suggestion','complaint','other'];

renderHeader('Feedback');
renderSidebar('admin', 'Feedback');
?>
<div class="topbar"><span class="topbar-title">Feedback Overview</span></div>
<div class="content">
  <div class="page-header">
    <h1>Feedback</h1>
    <p>Admin view — metadata only. Feedback content is encrypted and accessible only through authorized review requests.</p>
  </div>

  <div class="alert alert-info" style="display:flex;align-items:center;gap:10px;">
    🔒 <div><strong>Data Privacy Mode:</strong> Feedback messages are encrypted. To authorize a manager to read specific feedback, go to <a href="review-requests.php">Review Requests</a>.</div>
  </div>

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
        <thead>
          <tr><th>#</th><th>Category</th><th>Priority</th><th>Submitted</th><th>Content</th></tr>
        </thead>
        <tbody>
          <?php if (empty($feedbacks)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">No feedback found.</td></tr>
          <?php else: foreach ($feedbacks as $i => $fb): ?>
          <tr>
            <td class="text-muted"><?= $fb['feedback_id'] ?></td>
            <td><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
            <td>
              <span style="font-family:'DM Mono',monospace;font-size:11.5px;background:#f3f4f6;padding:2px 8px;border-radius:4px;color:var(--text-muted);">
                🔒 Encrypted
              </span>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>