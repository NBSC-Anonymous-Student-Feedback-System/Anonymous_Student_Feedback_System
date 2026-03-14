<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('student');

$err = '';
$submitted = false;

// ── Handle feedback submission ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $category = $_POST['category'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $message  = trim($_POST['message'] ?? '');
    $allowed_cats = ['general','academic','facilities','services','faculty','administration','suggestion','complaint','other'];
    $allowed_pri  = ['Low','Medium','High','Urgent'];

    if (in_array($category, $allowed_cats) && in_array($priority, $allowed_pri) && strlen($message) >= 10 && strlen($message) <= 200) {
        $encMessage  = encryptMessage($message);
        $hashMessage = hashMessage($message);
        $pdo->prepare("INSERT INTO feedback (category, priority, message_enc, message_hash, status, submitted_by) VALUES (?,?,?,?,'pending',?)")
            ->execute([$category, $priority, $encMessage, $hashMessage, $_SESSION['user_id']]);

        // Notify admins and managers
        $notifyUsers = $pdo->query("SELECT user_id FROM users WHERE role IN ('admin','manager') AND status='active'")->fetchAll();
        foreach ($notifyUsers as $nu) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                ->execute([$nu['user_id'], "New $priority Feedback", "A new $priority priority $category feedback was submitted."]);
        }

        $submitted = true;
    } else {
        $err = 'Please fill all fields. Message must be 10–200 characters.';
    }
}

// ── Load MY submissions only ──────────────────────────────────────
$mySubmissions = $pdo->prepare("
    SELECT f.feedback_id, f.category, f.priority, f.submitted_at, f.message_enc, f.message_hash,
           r.review_notes
    FROM feedback f
    LEFT JOIN feedback_reviews r ON f.feedback_id = r.feedback_id
    WHERE f.submitted_by = ?
    ORDER BY f.submitted_at DESC
");
$mySubmissions->execute([$_SESSION['user_id']]);
$mySubmissions = $mySubmissions->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NBSC Anonymous Feedback</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .user-app { min-height:100vh; background:#f0f2f5; }

    /* Nav */
    .user-nav {
      background:#fff; border-bottom:1px solid #e5e7eb;
      padding:0 24px; height:56px;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:100;
      box-shadow:0 1px 4px rgba(0,0,0,0.06);
    }
    .user-nav-brand { display:flex; align-items:center; gap:10px; }
    .nav-logo {
      width:36px; height:36px; border-radius:10px;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      display:flex; align-items:center; justify-content:center; font-size:17px;
    }
    .nav-brand-text { font-size:15px; font-weight:700; letter-spacing:-0.3px; }
    .nav-brand-sub  { font-size:11px; color:var(--text-muted); margin-top:-2px; }
    .nav-right { display:flex; align-items:center; gap:12px; }
    .nav-avatar {
      width:32px; height:32px; border-radius:50%;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; color:#fff; flex-shrink:0;
    }
    .nav-user-info { text-align:right; }
    .nav-user-info .name { font-size:13px; font-weight:600; }
    .nav-user-info .dept { font-size:11px; color:var(--text-muted); }

    /* Layout */
    .app-body { max-width:700px; margin:0 auto; padding:24px 16px 60px; }

    /* Submit box */
    .submit-box {
      background:#fff; border-radius:16px;
      box-shadow:0 2px 12px rgba(0,0,0,0.07);
      overflow:hidden; margin-bottom:24px;
    }
    .submit-box-header {
      background:linear-gradient(135deg,#1a56db 0%,#7e3af2 100%);
      padding:22px 28px 18px; color:#fff;
    }
    .submit-box-header h2 { font-size:17px; font-weight:700; margin-bottom:4px; }
    .submit-box-header p  { font-size:12.5px; opacity:0.85; margin:0; }
    .submit-box-body { padding:24px 28px; }

    /* Category grid */
    .category-grid {
      display:grid; grid-template-columns:repeat(3,1fr);
      gap:8px; margin-bottom:18px;
    }
    .cat-btn {
      border:2px solid #e5e7eb; border-radius:10px;
      padding:10px 8px; text-align:center; cursor:pointer;
      background:#fafafa; transition:all 0.15s;
      font-size:12px; font-weight:500; color:var(--text-muted);
      user-select:none;
    }
    .cat-btn:hover  { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
    .cat-btn.selected { border-color:var(--primary); background:var(--primary-light); color:var(--primary); font-weight:600; }
    .cat-btn .cat-icon { font-size:20px; display:block; margin-bottom:4px; }

    /* Priority */
    .priority-row { display:flex; gap:8px; margin-bottom:16px; }
    .pri-btn {
      flex:1; padding:9px; border-radius:8px;
      border:2px solid #e5e7eb; background:#fafafa;
      font-size:12px; font-weight:600; cursor:pointer;
      text-align:center; transition:all 0.15s; color:var(--text-muted);
      user-select:none;
    }
    .pri-btn.sel-Low    { border-color:#16a34a; background:#f0fdf4; color:#16a34a; }
    .pri-btn.sel-Medium { border-color:#d97706; background:#fffbeb; color:#d97706; }
    .pri-btn.sel-High   { border-color:#ea580c; background:#fff7ed; color:#ea580c; }
    .pri-btn.sel-Urgent { border-color:#dc2626; background:#fef2f2; color:#dc2626; }

    .section-label {
      font-size:11.5px; font-weight:600; color:var(--text-muted);
      text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px;
    }
    .msg-area {
      width:100%; border:2px solid #e5e7eb; border-radius:10px;
      padding:12px 14px; font-family:inherit; font-size:13.5px;
      resize:none; outline:none; transition:border-color 0.15s; min-height:96px;
      line-height:1.6;
    }
    .msg-area:focus { border-color:var(--primary); }
    .char-count { font-size:11.5px; color:var(--text-muted); text-align:right; margin-top:5px; }

    .submit-btn {
      width:100%; padding:13px;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      color:#fff; border:none; border-radius:10px;
      font-size:14px; font-weight:700; cursor:pointer;
      margin-top:16px; font-family:inherit; transition:opacity 0.15s;
      letter-spacing:0.01em;
    }
    .submit-btn:hover { opacity:0.92; }

    /* Success */
    .success-box { text-align:center; padding:32px 20px; }
    .success-icon { font-size:52px; margin-bottom:14px; }
    .success-box h3 { font-size:18px; font-weight:700; margin-bottom:6px; }
    .success-box p  { font-size:13.5px; color:var(--text-muted); line-height:1.6; }

    /* My Submissions section */
    .section-header {
      display:flex; align-items:center; gap:10px;
      margin-bottom:16px;
    }
    .section-header h2 { font-size:16px; font-weight:700; margin:0; }
    .section-count {
      background:var(--primary-light); color:var(--primary);
      font-size:12px; font-weight:700;
      padding:2px 9px; border-radius:99px;
    }

    /* Feedback card */
    .fb-card {
      background:#fff; border-radius:14px;
      box-shadow:0 1px 4px rgba(0,0,0,0.07);
      margin-bottom:12px; overflow:hidden;
    }
    .fb-card-body { padding:18px 20px; }
    .fb-meta { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
    .fb-cat {
      display:flex; align-items:center; gap:5px;
      background:#f3f4f6; padding:3px 10px; border-radius:99px;
      font-size:12px; font-weight:600; color:var(--text-muted);
    }
    .fb-message {
      font-size:14px; line-height:1.7; color:var(--text);
      background:#f9fafb; border-radius:8px; padding:12px 14px;
      margin-bottom:12px;
    }
    .fb-footer {
      display:flex; align-items:center; justify-content:space-between;
      font-size:12px; color:var(--text-muted);
    }
    .review-note {
      margin:0 20px 16px;
      background:#f0fdf4; border-left:3px solid #16a34a;
      padding:10px 14px; border-radius:0 8px 8px 0;
      font-size:12.5px; color:#166534;
    }
    .review-note.under-review {
      background:#eff6ff; border-color:#3b82f6; color:#1e40af;
    }
    .review-note strong { font-size:12px; display:block; margin-bottom:2px; }

    /* Empty state */
    .empty-state {
      text-align:center; padding:48px 20px;
      background:#fff; border-radius:14px;
      box-shadow:0 1px 4px rgba(0,0,0,0.07);
    }
    .empty-icon { font-size:44px; margin-bottom:12px; }
    .empty-state p { font-size:14px; color:var(--text-muted); }
  </style>
</head>
<body>
<div class="user-app">

  <!-- Nav -->
  <nav class="user-nav">
    <div class="user-nav-brand">
      <div class="nav-logo">💬</div>
      <div>
        <div class="nav-brand-text">NBSC Feedback</div>
        <div class="nav-brand-sub">Anonymous · Safe · Heard</div>
      </div>
    </div>
    <div class="nav-right">
      <div class="nav-user-info">
        <div class="name"><?= sanitize($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></div>
        <div class="dept"><?= sanitize($_SESSION['department']) ?></div>
      </div>
      <div class="nav-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
      <a href="<?= BASE_URL ?>/app/auth/student-logout.php" class="btn btn-outline btn-sm">Logout</a>
    </div>
  </nav>

  <div class="app-body">

    <?php if ($err): ?>
      <div class="alert alert-danger" style="border-radius:12px;margin-bottom:16px;"><?= sanitize($err) ?></div>
    <?php endif; ?>

    <!-- Submit Box -->
    <div class="submit-box">
      <div class="submit-box-header">
        <h2>Send Anonymous Feedback 💬</h2>
        <p>Your identity is fully protected. No personal data is linked to your submission.</p>
      </div>
      <div class="submit-box-body">
        <?php if ($submitted): ?>
          <div class="success-box">
            <div class="success-icon">✅</div>
            <h3>Feedback Sent!</h3>
            <p>Your anonymous feedback has been submitted successfully.<br>You can track its status below under <strong>My Submissions</strong>.</p>
            <button onclick="window.location.href='<?= BASE_URL ?>/app/user/index.php'"
              class="submit-btn" style="max-width:220px;margin:20px auto 0;display:block;">
              Send Another
            </button>
          </div>
        <?php else: ?>
          <form method="POST" id="feedbackForm">

            <div class="section-label">Category</div>
            <div class="category-grid">
              <?php
              $cats = ['academic','facilities','services','faculty','administration','suggestion','complaint','general','other'];
              foreach ($cats as $cat): ?>
                <div class="cat-btn" onclick="selectCat('<?= $cat ?>')" id="cat-<?= $cat ?>">
                  <span class="cat-icon"><?= categoryIcon($cat) ?></span>
                  <?= categoryLabel($cat) ?>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="category" id="category-input" value="general">

            <div class="section-label">Priority</div>
            <div class="priority-row">
              <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
                <div class="pri-btn" onclick="selectPri('<?= $p ?>')" id="pri-<?= $p ?>"><?= $p ?></div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="priority" id="priority-input" value="Low">

            <div class="section-label">Your Message</div>
            <textarea name="message" id="msg-input" class="msg-area" maxlength="200"
              placeholder="Describe your concern clearly. Avoid sharing personal details about yourself or others..." required
              oninput="updateCount()"></textarea>
            <div class="char-count"><span id="char-count">0</span>/200</div>

            <button type="submit" name="submit_feedback" class="submit-btn">Send Anonymously 🔒</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- My Submissions -->
    <div class="section-header">
      <h2>My Submissions</h2>
      <span class="section-count"><?= count($mySubmissions) ?></span>
    </div>

    <?php if (empty($mySubmissions)): ?>
      <div class="empty-state">
        <div class="empty-icon">📭</div>
        <p>You haven't submitted any feedback yet.<br>Use the form above to get started!</p>
      </div>
    <?php else: foreach ($mySubmissions as $fb):
      $plain = decryptMessage($fb['message_enc']); ?>
      <div class="fb-card">
        <div class="fb-card-body">
          <div class="fb-meta">
            <span class="fb-cat"><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></span>
            <?= priorityBadge($fb['priority']) ?>
          </div>
          <div class="fb-message"><?= sanitize($plain ?: '[Could not decrypt]') ?></div>
          <div class="fb-footer">
            <span><?= timeAgo($fb['submitted_at']) ?></span>
            <span>Feedback #<?= $fb['feedback_id'] ?></span>
          </div>
        </div>

        <?php if ($fb['review_notes']): ?>
          <div class="review-note">
            <strong>✅ Admin Response</strong>
            <?= sanitize($fb['review_notes']) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>

  </div>
</div>

<script>
// Category
document.getElementById('cat-general').classList.add('selected');
function selectCat(cat) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('selected'));
  document.getElementById('cat-' + cat).classList.add('selected');
  document.getElementById('category-input').value = cat;
}

// Priority
document.getElementById('pri-Low').classList.add('sel-Low');
function selectPri(pri) {
  document.querySelectorAll('.pri-btn').forEach(b => { b.className = 'pri-btn'; });
  document.getElementById('pri-' + pri).classList.add('sel-' + pri);
  document.getElementById('priority-input').value = pri;
}

// Char count
function updateCount() {
  document.getElementById('char-count').textContent = document.getElementById('msg-input').value.length;
}
</script>
</body>
</html>