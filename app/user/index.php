<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('student');

$err = '';
$submitted = isset($_GET['submitted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $category = $_POST['category'] ?? '';
    $priority = $_POST['priority'] ?? '';
    $message  = trim($_POST['message'] ?? '');
    $allowed_cats = ['general','academic','facilities','services','faculty','administration','suggestion','complaint','other'];
    $allowed_pri  = ['Low','Medium','High','Urgent'];

    if (in_array($category, $allowed_cats) && in_array($priority, $allowed_pri) && strlen($message) >= 10 && strlen($message) <= 200) {
        $encMessage  = encryptMessage($message);
        $hashMessage = hashMessage($message);
        $pdo->prepare("INSERT INTO feedback (category, priority, message_enc, message_hash, submitted_by) VALUES (?,?,?,?,?)")
            ->execute([$category, $priority, $encMessage, $hashMessage, $_SESSION['user_id']]);

        $notifyUsers = $pdo->query("SELECT user_id FROM users WHERE role IN ('admin','staff')")->fetchAll();
        foreach ($notifyUsers as $nu) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                ->execute([$nu['user_id'], "New $priority Feedback", "A new $priority priority $category feedback was submitted."]);
        }

        // Redirect immediately, no $submitted = true before this
        header("Location: " . BASE_URL . "/app/user/index.php?submitted=1");
        exit;
    } else {
        $err = 'Please fill all fields. Message must be 10–200 characters.';
    }
}

// ─── UPDATE FEEDBACK ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feedback'])) {
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $message     = trim($_POST['updated_message'] ?? '');

    // Verify ownership
    $check = $pdo->prepare("SELECT * FROM feedback WHERE feedback_id = ? AND submitted_by = ?");
    $check->execute([$feedback_id, $_SESSION['user_id']]);
    $existing = $check->fetch();

    if ($existing && strlen($message) >= 10 && strlen($message) <= 200) {
        $encMessage  = encryptMessage($message);
        $hashMessage = hashMessage($message);
        $pdo->prepare("UPDATE feedback SET message_enc = ?, message_hash = ? WHERE feedback_id = ? AND submitted_by = ?")
            ->execute([$encMessage, $hashMessage, $feedback_id, $_SESSION['user_id']]);
        header("Location: " . BASE_URL . "/app/user/index.php?updated=1");
        exit;
    } else {
        $err = 'Update failed. Message must be 10–200 characters.';
    }
}

// ─── DELETE FEEDBACK ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);

    // Verify ownership before deleting
    $check = $pdo->prepare("SELECT feedback_id FROM feedback WHERE feedback_id = ? AND submitted_by = ?");
    $check->execute([$feedback_id, $_SESSION['user_id']]);

    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM feedback_reviews WHERE feedback_id = ?")->execute([$feedback_id]);
        $pdo->prepare("DELETE FROM feedback WHERE feedback_id = ? AND submitted_by = ?")->execute([$feedback_id, $_SESSION['user_id']]);
        header("Location: " . BASE_URL . "/app/user/index.php?deleted=1");
        exit;
    } else {
        $err = 'Delete failed. You do not own this feedback.';
    }
}

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

$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();
$highCount     = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='High'")->fetchColumn();
$mediumCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Medium'")->fetchColumn();
$lowCount      = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Low'")->fetchColumn();
$catStats      = $pdo->query("SELECT category, COUNT(*) as total FROM feedback GROUP BY category ORDER BY total DESC")->fetchAll();
$topCategory   = !empty($catStats) ? $catStats[0]['category'] : 'general';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NBSC Anonymous Feedback</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    *,*::before,*::after{box-sizing:border-box;}
    body{margin:0;padding:0;}

    /* Layout */
    .user-app{display:flex;min-height:100vh;background:#f0f2f5;}

    /* Sidebar */
    .user-sidebar{width:220px;flex-shrink:0;background:#1a1f2e;color:#fff;display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100;overflow-y:auto;}
    .sidebar-brand{padding:20px 20px 16px;border-bottom:1px solid rgba(255,255,255,0.08);}
    .sidebar-brand-name{font-size:15px;font-weight:700;color:#fff;margin:0;}
    .sidebar-brand-sub{font-size:11px;color:rgba(255,255,255,0.45);margin-top:2px;}
    .sidebar-nav{flex:1;padding:16px 0;}
    .sidebar-section-label{font-size:10px;font-weight:600;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:0.08em;padding:0 20px;margin:12px 0 6px;display:block;}
    .sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 20px;font-size:13.5px;font-weight:500;color:rgba(255,255,255,0.65);text-decoration:none;border-left:3px solid transparent;transition:all 0.15s;cursor:pointer;border:none;background:none;width:100%;text-align:left;}
    .sidebar-link:hover{color:#fff;background:rgba(255,255,255,0.06);}
    .sidebar-link.active{color:#fff;background:rgba(255,255,255,0.1);border-left:3px solid #1a56db;}
    .sidebar-footer{padding:16px 20px;border-top:1px solid rgba(255,255,255,0.08);}
    .sidebar-user{display:flex;align-items:center;gap:10px;margin-bottom:12px;}
    .sidebar-avatar{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#1a56db,#7e3af2);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:#fff;flex-shrink:0;}
    .sidebar-user-name{font-size:13px;font-weight:600;color:#fff;}
    .sidebar-user-role{font-size:11px;color:rgba(255,255,255,0.45);}
    .sidebar-logout{display:block;width:100%;padding:8px;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.12);border-radius:8px;color:rgba(255,255,255,0.7);font-size:12.5px;font-weight:500;text-align:center;text-decoration:none;transition:all 0.15s;}
    .sidebar-logout:hover{background:rgba(255,255,255,0.13);color:#fff;}

    /* Main */
    .user-main{margin-left:220px;flex:1;display:flex;flex-direction:column;min-width:0;}
    .user-topbar{background:#fff;border-bottom:1px solid #e5e7eb;padding:0 28px;height:52px;display:flex;align-items:center;position:sticky;top:0;z-index:50;}
    .user-topbar-title{font-size:15px;font-weight:600;color:#111827;}
    .user-content{padding:28px;max-width:900px;width:100%;}

    /* Page sections */
    .page-section{display:none;}
    .page-section.active{display:block;}

    /* Page header */
    .page-header{margin-bottom:24px;}
    .page-header h1{font-size:22px;font-weight:700;margin:0 0 4px;color:#111827;}
    .page-header p{color:#6b7280;font-size:13.5px;margin:0;}

    /* Stat pills */
    .stat-pills{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:24px;}
    .stat-pill{border-radius:12px;padding:16px 18px;display:flex;flex-direction:column;gap:6px;background:#fff;border:1px solid #e5e7eb;}
    .stat-pill-label{font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;}
    .stat-pill-value{font-size:26px;font-weight:700;}
    .stat-pill.blue .stat-pill-value{color:#1d4ed8;}
    .stat-pill.red .stat-pill-value{color:#dc2626;}
    .stat-pill.orange .stat-pill-value{color:#d97706;}
    .stat-pill.purple .stat-pill-value{color:#7c3aed;font-size:14px;margin-top:4px;}

    /* Charts */
    .charts-card{background:#fff;border-radius:14px;border:1px solid #e5e7eb;padding:20px 24px;margin-bottom:24px;}
    .charts-card-title{font-size:14px;font-weight:600;color:#111827;margin-bottom:16px;}
    .chart-cols{display:grid;grid-template-columns:1fr 1fr;gap:32px;}
    .chart-title{font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:10px;}
    .bar-row{display:flex;align-items:center;gap:10px;margin-bottom:7px;}
    .bar-label{font-size:12px;color:#374151;width:100px;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .bar-track{flex:1;background:#f3f4f6;border-radius:99px;height:8px;}
    .bar-fill{height:8px;border-radius:99px;background:linear-gradient(90deg,#1a56db,#7e3af2);}
    .bar-count{font-size:11px;color:#6b7280;width:20px;text-align:right;flex-shrink:0;}
    .pri-bar-row{display:flex;align-items:center;gap:8px;margin-bottom:7px;}
    .pri-bar-label{font-size:12px;font-weight:600;width:60px;flex-shrink:0;}
    .pri-bar-track{flex:1;background:#f3f4f6;border-radius:99px;height:8px;}
    .pri-bar-fill{height:8px;border-radius:99px;}
    .pri-bar-count{font-size:11px;color:#6b7280;width:20px;text-align:right;}

    /* Submit card */
    .submit-card{background:#fff;border-radius:14px;border:1px solid #e5e7eb;overflow:hidden;margin-bottom:24px;}
    .submit-card-header{background:linear-gradient(135deg,#1a56db 0%,#7e3af2 100%);padding:18px 24px;color:#fff;}
    .submit-card-header h2{font-size:16px;font-weight:700;margin:0 0 4px;}
    .submit-card-header p{font-size:12.5px;opacity:0.85;margin:0;}
    .submit-card-body{padding:24px;}

    /* Category */
    .category-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:18px;}
    .cat-btn{border:2px solid #e5e7eb;border-radius:10px;padding:10px 8px;text-align:center;cursor:pointer;background:#fafafa;transition:all 0.15s;font-size:12px;font-weight:500;color:#6b7280;user-select:none;}
    .cat-btn:hover{border-color:#1a56db;color:#1a56db;background:#eff6ff;}
    .cat-btn.selected{border-color:#1a56db;background:#eff6ff;color:#1a56db;font-weight:600;}
    .cat-btn .cat-icon{font-size:20px;display:block;margin-bottom:4px;}

    /* Priority */
    .priority-row{display:flex;gap:8px;margin-bottom:16px;}
    .pri-btn{flex:1;padding:9px;border-radius:8px;border:2px solid #e5e7eb;background:#fafafa;font-size:12px;font-weight:600;cursor:pointer;text-align:center;transition:all 0.15s;color:#6b7280;user-select:none;}
    .pri-btn.sel-Low{border-color:#16a34a;background:#f0fdf4;color:#16a34a;}
    .pri-btn.sel-Medium{border-color:#d97706;background:#fffbeb;color:#d97706;}
    .pri-btn.sel-High{border-color:#ea580c;background:#fff7ed;color:#ea580c;}
    .pri-btn.sel-Urgent{border-color:#dc2626;background:#fef2f2;color:#dc2626;}

    .section-label{font-size:11.5px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.06em;margin-bottom:8px;}
    .msg-area{width:100%;border:2px solid #e5e7eb;border-radius:10px;padding:12px 14px;font-family:inherit;font-size:13.5px;resize:none;outline:none;transition:border-color 0.15s;min-height:96px;line-height:1.6;}
    .msg-area:focus{border-color:#1a56db;}
    .char-count{font-size:11.5px;color:#6b7280;text-align:right;margin-top:5px;}
    .submit-btn{width:100%;padding:13px;background:linear-gradient(135deg,#1a56db,#7e3af2);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;margin-top:16px;font-family:inherit;transition:opacity 0.15s;}
    .submit-btn:hover{opacity:0.92;}

    /* Success */
    .success-box{text-align:center;padding:32px 20px;}
    .success-icon{font-size:52px;margin-bottom:14px;}
    .success-box h3{font-size:18px;font-weight:700;margin-bottom:6px;}
    .success-box p{font-size:13.5px;color:#6b7280;line-height:1.6;}

    /* Submissions */
    .section-header{display:flex;align-items:center;gap:10px;margin-bottom:16px;}
    .section-header h2{font-size:16px;font-weight:700;margin:0;}
    .section-count{background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700;padding:2px 9px;border-radius:99px;}
    .fb-card{background:#fff;border-radius:14px;border:1px solid #e5e7eb;margin-bottom:12px;overflow:hidden;}
    .fb-card-body{padding:18px 20px;}
    .fb-meta{display:flex;align-items:center;gap:8px;margin-bottom:10px;flex-wrap:wrap;}
    .fb-cat{display:flex;align-items:center;gap:5px;background:#f3f4f6;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:600;color:#6b7280;}
    .fb-message{font-size:14px;line-height:1.7;background:#f9fafb;border-radius:8px;padding:12px 14px;margin-bottom:12px;}
    .fb-footer{display:flex;align-items:center;justify-content:space-between;font-size:12px;color:#6b7280;}
    .review-note{margin:0 20px 16px;background:#f0fdf4;border-left:3px solid #16a34a;padding:10px 14px;border-radius:0 8px 8px 0;font-size:12.5px;color:#166534;}
    .review-note strong{font-size:12px;display:block;margin-bottom:2px;}
    .empty-state{text-align:center;padding:48px 20px;background:#fff;border-radius:14px;border:1px solid #e5e7eb;}
    .empty-icon{font-size:44px;margin-bottom:12px;}
    .empty-state p{font-size:14px;color:#6b7280;}

    /* Edit/Delete action buttons */
.fb-action-btn {
  padding: 5px 14px;
  border-radius: 7px;
  font-size: 12px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  font-family: inherit;
  transition: opacity 0.15s;
}
.fb-action-btn:hover { opacity: 0.82; }
.btn-edit   { background: #eff6ff; color: #1d4ed8; }
.btn-save   { background: #f0fdf4; color: #16a34a; }
.btn-cancel { background: #f3f4f6; color: #6b7280; }
.btn-delete { background: #fef2f2; color: #dc2626; }

/* Success/delete alert */
.alert-success-box {
  background: #f0fdf4;
  border: 1px solid #16a34a;
  color: #166534;
  border-radius: 10px;
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 500;
  margin-bottom: 16px;
}
  </style>
</head>
<body>
<div class="user-app">

  <!-- Sidebar -->
  <aside class="user-sidebar">
    <div class="sidebar-brand">
      <div class="sidebar-brand-name">NBSC Feedback</div>
      <div class="sidebar-brand-sub">Anonymous · Safe · Heard</div>
    </div>
    <nav class="sidebar-nav">
      <span class="sidebar-section-label">Menu</span>
      <button class="sidebar-link active" id="nav-dashboard" onclick="showSection('dashboard')">
        <span>📊</span> Dashboard
      </button>
      <button class="sidebar-link" id="nav-submit" onclick="showSection('submit')">
        <span>💬</span> Submit Feedback
      </button>
      <button class="sidebar-link" id="nav-submissions" onclick="showSection('submissions')">
        <span>📋</span> My Submissions
        <?php if (count($mySubmissions) > 0): ?>
          <span style="margin-left:auto;background:#1a56db;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;"><?= count($mySubmissions) ?></span>
        <?php endif; ?>
      </button>
    </nav>
    <div class="sidebar-footer">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
        <div>
          <div class="sidebar-user-name"><?= sanitize($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></div>
          <div class="sidebar-user-role"><?= sanitize($_SESSION['department']) ?></div>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/app/auth/logout.php" class="sidebar-logout">Logout</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="user-main">
    <div class="user-topbar">
      <span class="user-topbar-title" id="topbar-title">Dashboard</span>
    </div>
    <div class="user-content">

      <?php if ($err): ?>
        <div class="alert alert-danger" style="border-radius:12px;margin-bottom:16px;"><?= sanitize($err) ?></div>
      <?php endif; ?>

      <!-- SECTION: Dashboard -->
      <div class="page-section active" id="section-dashboard">
        <div class="page-header">
          <h1>Student Dashboard</h1>
          <p>Welcome, <?= sanitize($_SESSION['first_name']) ?>. Here's the current feedback overview.</p>
        </div>

        <div class="stat-pills">
          <div class="stat-pill blue">
            <div class="stat-pill-label">Total Feedback</div>
            <div class="stat-pill-value"><?= $totalFeedback ?></div>
          </div>
          <div class="stat-pill red">
            <div class="stat-pill-label">Urgent</div>
            <div class="stat-pill-value"><?= $urgentCount ?></div>
          </div>
          <div class="stat-pill orange">
            <div class="stat-pill-label">High Priority</div>
            <div class="stat-pill-value"><?= $highCount ?></div>
          </div>
          <div class="stat-pill purple">
            <div class="stat-pill-label">Top Category</div>
            <div class="stat-pill-value"><?= categoryIcon($topCategory) ?> <?= sanitize(categoryLabel($topCategory)) ?></div>
          </div>
        </div>

        <div class="charts-card">
          <div class="charts-card-title">Feedback Distribution</div>
          <div class="chart-cols">
            <div>
              <div class="chart-title">By Priority</div>
              <?php
              $priData = ['Urgent'=>[$urgentCount,'#dc2626'],'High'=>[$highCount,'#ea580c'],'Medium'=>[$mediumCount,'#d97706'],'Low'=>[$lowCount,'#16a34a']];
              foreach ($priData as $label => [$count, $color]):
                $pct = $totalFeedback > 0 ? round(($count / $totalFeedback) * 100) : 0; ?>
              <div class="pri-bar-row">
                <div class="pri-bar-label" style="color:<?= $color ?>"><?= $label ?></div>
                <div class="pri-bar-track"><div class="pri-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div></div>
                <div class="pri-bar-count"><?= $count ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <div>
              <div class="chart-title">By Category</div>
              <?php foreach ($catStats as $cs):
                $pct = $totalFeedback > 0 ? round(($cs['total'] / $totalFeedback) * 100) : 0; ?>
              <div class="bar-row">
                <div class="bar-label"><?= categoryIcon($cs['category']) ?> <?= sanitize(categoryLabel($cs['category'])) ?></div>
                <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%;"></div></div>
                <div class="bar-count"><?= $cs['total'] ?></div>
              </div>
              <?php endforeach; ?>
              <?php if (empty($catStats)): ?><div style="font-size:13px;color:#6b7280;padding:8px 0;">No data yet.</div><?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION: Submit Feedback -->
      <div class="page-section" id="section-submit">
        <div class="page-header">
          <h1>Submit Feedback</h1>
          <p>Your identity is fully protected. No personal data is linked to your submission.</p>
        </div>
        <div class="submit-card">
          <div class="submit-card-header">
            <h2>Send Anonymous Feedback 💬</h2>
            <p>All messages are encrypted and can only be accessed through an authorized review request.</p>
          </div>
          <div class="submit-card-body">

            <?php if ($submitted): ?>
              <div class="success-box" id="success-box">
                <div class="success-icon">✅</div>
                <h3>Feedback Sent!</h3>
                <p>Your anonymous feedback has been submitted successfully.<br>You can track it under <strong>My Submissions</strong>.</p>
                <button onclick="sendAnother()" class="submit-btn" style="max-width:220px;margin:20px auto 0;display:block;">Send Another</button>
                <button onclick="showSection('submissions')" class="submit-btn" style="max-width:220px;margin:10px auto 0;display:block;background:linear-gradient(135deg,#059669,#10b981);">View My Submissions</button>
              </div>
              <div id="feedback-form-wrap" style="display:none;">
            <?php else: ?>
              <div id="feedback-form-wrap">
            <?php endif; ?>

              <form method="POST" id="feedbackForm">
                <div class="section-label">Category</div>
                <div class="category-grid">
                  <?php $cats = ['academic','facilities','services','faculty','administration','suggestion','complaint','general','other'];
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
                  placeholder="Describe your concern clearly. Avoid sharing personal details about yourself or others..."
                  required oninput="updateCount()"></textarea>
                <div class="char-count"><span id="char-count">0</span>/200</div>
                <button type="submit" name="submit_feedback" class="submit-btn">Send Anonymously 🔒</button>
              </form>

            </div>
          </div>
        </div>
      </div>

      <!-- SECTION: My Submissions -->
     <!-- SECTION: My Submissions -->
<div class="page-section" id="section-submissions">
  <div class="page-header">
    <h1>My Submissions</h1>
    <p>Your personal feedback history. Only you can see this.</p>
  </div>

  <?php if (isset($_GET['updated'])): ?>
    <div class="alert-success-box">✅ Feedback updated successfully.</div>
  <?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert-success-box" style="background:#fef2f2;border-color:#dc2626;color:#991b1b;">🗑️ Feedback deleted successfully.</div>
  <?php endif; ?>

  <div class="section-header">
    <h2>Recent Submissions</h2>
    <span class="section-count"><?= count($mySubmissions) ?></span>
  </div>

  <?php if (empty($mySubmissions)): ?>
    <div class="empty-state">
      <div class="empty-icon">📭</div>
      <p>You haven't submitted any feedback yet.<br>Go to <strong>Submit Feedback</strong> to get started!</p>
    </div>
  <?php else: foreach ($mySubmissions as $fb):
    $plain = decryptMessage($fb['message_enc']); ?>
    <div class="fb-card" id="fb-card-<?= $fb['feedback_id'] ?>">
      <div class="fb-card-body">
        <div class="fb-meta">
          <span class="fb-cat"><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></span>
          <?= priorityBadge($fb['priority']) ?>
        </div>

        <!-- Read mode -->
        <div class="fb-message" id="view-<?= $fb['feedback_id'] ?>">
          <?= sanitize($plain ?: '[Could not decrypt]') ?>
        </div>

        <!-- Edit mode (hidden by default) -->
        <form method="POST" id="edit-form-<?= $fb['feedback_id'] ?>" style="display:none;">
          <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
          <textarea
            name="updated_message"
            id="edit-msg-<?= $fb['feedback_id'] ?>"
            class="msg-area"
            maxlength="200"
            minlength="10"
            required
            oninput="updateEditCount(<?= $fb['feedback_id'] ?>)"
            style="margin-bottom:4px;"
          ><?= sanitize($plain) ?></textarea>
          <div class="char-count"><span id="edit-count-<?= $fb['feedback_id'] ?>"><?= strlen($plain) ?></span>/200</div>
        </form>

        <div class="fb-footer">
          <span><?= timeAgo($fb['submitted_at']) ?></span>
          <div style="display:flex;gap:8px;align-items:center;">
            <!-- Edit button -->
            <button
              class="fb-action-btn btn-edit"
              id="edit-btn-<?= $fb['feedback_id'] ?>"
              onclick="startEdit(<?= $fb['feedback_id'] ?>)"
            >Edit</button>

            <!-- Save button (hidden by default) -->
            <button
              class="fb-action-btn btn-save"
              id="save-btn-<?= $fb['feedback_id'] ?>"
              style="display:none;"
              onclick="confirmUpdate(<?= $fb['feedback_id'] ?>)"
            >Update</button>

            <!-- Cancel button (hidden by default) -->
            <button
              class="fb-action-btn btn-cancel"
              id="cancel-btn-<?= $fb['feedback_id'] ?>"
              style="display:none;"
              onclick="cancelEdit(<?= $fb['feedback_id'] ?>)"
            >Cancel</button>

            <!-- Delete button -->
            <form method="POST" id="delete-form-<?= $fb['feedback_id'] ?>" style="display:inline;">
              <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
              <button
                type="button"
                class="fb-action-btn btn-delete"
                id="delete-btn-<?= $fb['feedback_id'] ?>"
                onclick="confirmDelete(<?= $fb['feedback_id'] ?>)"
              >Delete</button>
            </form>
          </div>
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
  </div>
</div>

<script>
const sections = ['dashboard','submit','submissions'];
const titles   = {dashboard:'Dashboard',submit:'Submit Feedback',submissions:'My Submissions'};

function showSection(name) {
  sections.forEach(s => {
    document.getElementById('section-' + s).classList.toggle('active', s === name);
    document.getElementById('nav-' + s).classList.toggle('active', s === name);
  });
  document.getElementById('topbar-title').textContent = titles[name];
}

function sendAnother() {
  document.getElementById('success-box').style.display = 'none';
  document.getElementById('feedback-form-wrap').style.display = 'block';
  document.getElementById('cat-general').classList.add('selected');
  document.getElementById('pri-Low').classList.add('sel-Low');
  document.getElementById('category-input').value = 'general';
  document.getElementById('priority-input').value = 'Low';
  document.getElementById('msg-input').value = '';
  document.getElementById('char-count').textContent = '0';
}

<?php if ($err): ?>showSection('submit');<?php endif; ?>
<?php if ($submitted): ?>showSection('submit');<?php endif; ?>

document.getElementById('cat-general').classList.add('selected');
function selectCat(cat) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('selected'));
  document.getElementById('cat-' + cat).classList.add('selected');
  document.getElementById('category-input').value = cat;
}

document.getElementById('pri-Low').classList.add('sel-Low');
function selectPri(pri) {
  document.querySelectorAll('.pri-btn').forEach(b => { b.className = 'pri-btn'; });
  document.getElementById('pri-' + pri).classList.add('sel-' + pri);
  document.getElementById('priority-input').value = pri;
}

function updateCount() {
  document.getElementById('char-count').textContent = document.getElementById('msg-input').value.length;
}

let activeEditId  = null;
let activeDeleteId = null;

function startEdit(id) {
  // Block if another edit is already open
  if (activeEditId !== null && activeEditId !== id) {
    alert('Please finish or cancel your current edit before editing another feedback.');
    return;
  }
  // Block if a delete is pending
  if (activeDeleteId !== null) {
    alert('Please cancel your pending delete before editing.');
    return;
  }
  activeEditId = id;
  document.getElementById('view-' + id).style.display      = 'none';
  document.getElementById('edit-form-' + id).style.display = 'block';
  document.getElementById('edit-btn-' + id).style.display  = 'none';
  document.getElementById('save-btn-' + id).style.display  = 'inline-block';
  document.getElementById('cancel-btn-' + id).style.display = 'inline-block';
  document.getElementById('delete-btn-' + id).style.display = 'none';
}

function cancelEdit(id) {
  activeEditId = null;
  document.getElementById('view-' + id).style.display       = 'block';
  document.getElementById('edit-form-' + id).style.display  = 'none';
  document.getElementById('edit-btn-' + id).style.display   = 'inline-block';
  document.getElementById('save-btn-' + id).style.display   = 'none';
  document.getElementById('cancel-btn-' + id).style.display = 'none';
  document.getElementById('delete-btn-' + id).style.display = 'inline-block';
}

function confirmUpdate(id) {
  const msg = document.getElementById('edit-msg-' + id).value.trim();
  if (msg.length < 10 || msg.length > 200) {
    alert('Message must be between 10 and 200 characters.');
    return;
  }
  if (confirm('Are you sure you want to update this feedback?')) {
    const form = document.getElementById('edit-form-' + id);
    // Add hidden submit trigger
    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'update_feedback';
    input.value = '1';
    form.appendChild(input);
    form.submit();
  }
}

function confirmDelete(id) {
  // Block if an edit is active
  if (activeEditId !== null) {
    alert('Please finish or cancel your current edit before deleting.');
    return;
  }
  if (confirm('Are you sure you want to delete this feedback? This cannot be undone.')) {
    const form = document.getElementById('delete-form-' + id);
    const input = document.createElement('input');
    input.type  = 'hidden';
    input.name  = 'delete_feedback';
    input.value = '1';
    form.appendChild(input);
    form.submit();
  }
}

function updateEditCount(id) {
  const len = document.getElementById('edit-msg-' + id).value.length;
  document.getElementById('edit-count-' + id).textContent = len;
}

// Auto-show submissions section on update/delete redirect
<?php if (isset($_GET['updated']) || isset($_GET['deleted'])): ?>
showSection('submissions');
<?php endif; ?>
</script>
</body>
</html>