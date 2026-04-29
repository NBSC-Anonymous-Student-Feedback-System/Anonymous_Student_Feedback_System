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

    if (in_array($category, $allowed_cats) && in_array($priority, $allowed_pri) && strlen($message) >= 10 && strlen($message) <= 1000) {
        $encMessage  = encryptMessage($message);
        $hashMessage = hashMessage($message);
        $pdo->prepare("INSERT INTO feedback (category, priority, message_enc, message_hash, submitted_by) VALUES (?,?,?,?,?)")
            ->execute([$category, $priority, $encMessage, $hashMessage, $_SESSION['user_id']]);
        $notifyUsers = $pdo->query("SELECT user_id FROM users WHERE role IN ('admin','staff')")->fetchAll();
        foreach ($notifyUsers as $nu) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                ->execute([$nu['user_id'], "New $priority Feedback", "A new $priority priority $category feedback was submitted."]);
        }
        header("Location: " . BASE_URL . "/app/user/index.php?submitted=1");
        exit;
    } else {
        $err = 'Please fill all fields. Message must be 10–1000 characters.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_feedback'])) {
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $message     = trim($_POST['updated_message'] ?? '');
    $check = $pdo->prepare("SELECT * FROM feedback WHERE feedback_id = ? AND submitted_by = ?");
    $check->execute([$feedback_id, $_SESSION['user_id']]);
    $existing = $check->fetch();
    if ($existing && strlen($message) >= 10 && strlen($message) <= 1000) {
        $encMessage  = encryptMessage($message);
        $hashMessage = hashMessage($message);
        $pdo->prepare("UPDATE feedback SET message_enc = ?, message_hash = ? WHERE feedback_id = ? AND submitted_by = ?")
            ->execute([$encMessage, $hashMessage, $feedback_id, $_SESSION['user_id']]);
        header("Location: " . BASE_URL . "/app/user/index.php?updated=1");
        exit;
    } else {
        $err = 'Update failed. Message must be 10–1000 characters.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $check = $pdo->prepare("SELECT feedback_id FROM feedback WHERE feedback_id = ? AND submitted_by = ?");
    $check->execute([$feedback_id, $_SESSION['user_id']]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM feedback_reviews WHERE feedback_id = ?")->execute([$feedback_id]);
        $pdo->prepare("DELETE FROM review_requests  WHERE feedback_id = ?")->execute([$feedback_id]);
        $pdo->prepare("DELETE FROM feedback         WHERE feedback_id = ? AND submitted_by = ?")->execute([$feedback_id, $_SESSION['user_id']]);
        header("Location: " . BASE_URL . "/app/user/index.php?deleted=1");
        exit;
    } else {
        $err = 'Delete failed. You do not own this feedback.';
    }
}

$mySubmissions = $pdo->prepare("
    SELECT f.feedback_id, f.category, f.priority, f.submitted_at, f.message_enc, f.message_hash,
           r.review_notes,
           rr.status AS request_status
    FROM feedback f
    LEFT JOIN feedback_reviews r ON f.feedback_id = r.feedback_id
    LEFT JOIN review_requests rr ON f.feedback_id = rr.feedback_id
        AND rr.status IN ('pending','approved')
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
  <title>NBSC Student Feedback</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    *, *::before, *::after { box-sizing: border-box; }
    body { background: #f0f2f5; margin: 0; font-family: 'Inter', sans-serif; }

    /* ── Navbar ── */
    .stu-navbar {
      position: sticky; top: 0; z-index: 200;
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px; height: 56px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    }
    .navbar-left  { display: flex; align-items: center; gap: 14px; }
    .navbar-right { display: flex; align-items: center; gap: 12px; }
    .brand-name { color: #fff; font-size: 15px; font-weight: 700; }
    .brand-sub  { color: rgba(255,255,255,0.65); font-size: 11px; }

    .hamburger-btn {
      background: none; border: none; cursor: pointer;
      display: flex; flex-direction: column; gap: 5px; padding: 4px;
    }
    .hamburger-btn span {
      display: block; width: 22px; height: 2px;
      background: rgba(255,255,255,0.8); border-radius: 2px;
      transition: all 0.2s;
    }

    .hamburger-menu {
      position: fixed; top: 56px; right: 0;
      background: #1a1f2e; width: 220px;
      border-right: 1px solid rgba(255,255,255,0.08);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      border-radius: 0 0 0 12px;
      padding: 10px 0 16px;
      display: none; z-index: 300;
      box-shadow: -4px 4px 16px rgba(0,0,0,0.2);
    }
    .hamburger-menu.open { display: block; }

    .menu-section {
      font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.35);
      text-transform: uppercase; letter-spacing: 0.08em;
      padding: 10px 20px 4px; display: block;
    }
    .menu-link {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 20px; font-size: 13.5px; font-weight: 500;
      color: rgba(255,255,255,0.65); text-decoration: none;
      transition: all 0.15s; cursor: pointer;
      border: none; background: none; width: 100%; text-align: left;
    }
    .menu-link:hover  { color: #fff; background: rgba(255,255,255,0.06); }
    .menu-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left: 3px solid #7dd3fc; }
    .menu-link svg { width: 16px; height: 16px; flex-shrink: 0; }
    .menu-divider { border-color: rgba(255,255,255,0.08); margin: 8px 0; }

    .user-chip {
      display: flex; align-items: center; gap: 8px;
      color: rgba(255,255,255,0.85); font-size: 13px;
    }
    .user-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: rgba(255,255,255,0.2);
      border: 2px solid rgba(255,255,255,0.4);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
    }

    /* ── Page ── */
    .page-wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px; }

    /* Page sections */
    .page-section { display: none; }
    .page-section.active { display: block; }

    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #111827; }
    .page-header p  { color: #6b7280; font-size: 13.5px; margin: 0; }

    /* Stats */
    .stats-row {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 14px; margin-bottom: 24px;
    }
    .stat-card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e5e7eb; padding: 18px 20px;
    }
    .stat-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 700; }
    .stat-card.blue   .stat-value { color: #1d4ed8; }
    .stat-card.red    .stat-value { color: #dc2626; }
    .stat-card.orange .stat-value { color: #d97706; }
    .stat-card.purple .stat-value { color: #7c3aed; font-size: 14px; margin-top: 4px; }

  /* Charts */
.charts-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; padding: 20px 24px; margin-bottom: 24px; }
.charts-card-title { font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 16px; }
.chart-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 32px; }
.chart-title { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 10px; }
.pie-wrap { display: flex; flex-direction: column; align-items: center; }
.pie-wrap canvas { cursor: pointer; }
.pie-legend { list-style: none; padding: 0; margin: 10px 0 0; width: 100%; }
.pie-legend li { display: flex; align-items: center; gap: 7px; font-size: 12px; color: #374151; margin-bottom: 5px; }
.pie-legend li span.dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.pie-legend li span.lbl { flex: 1; }
.pie-legend li span.val { color: #6b7280; font-size: 11px; }

    /* Submit card */
    .submit-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; overflow: hidden; margin-bottom: 24px; }
    .submit-card-header { background: linear-gradient(135deg, #1e40af, #0ea5e9); padding: 18px 24px; color: #fff; }
    .submit-card-header h2 { font-size: 16px; font-weight: 700; margin: 0 0 4px; }
    .submit-card-header p  { font-size: 12.5px; opacity: 0.85; margin: 0; }
    .submit-card-body { padding: 24px; }

    .category-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 8px; margin-bottom: 18px; }
    .cat-btn { border: 2px solid #e5e7eb; border-radius: 10px; padding: 10px 8px; text-align: center; cursor: pointer; background: #fafafa; transition: all 0.15s; font-size: 12px; font-weight: 500; color: #6b7280; user-select: none; }
    .cat-btn:hover    { border-color: #1e40af; color: #1e40af; background: #eff6ff; }
    .cat-btn.selected { border-color: #1e40af; background: #eff6ff; color: #1e40af; font-weight: 600; }
    .cat-btn .cat-icon { font-size: 20px; display: block; margin-bottom: 4px; }

    .priority-row { display: flex; gap: 8px; margin-bottom: 16px; }
    .pri-btn { flex: 1; padding: 9px; border-radius: 8px; border: 2px solid #e5e7eb; background: #fafafa; font-size: 12px; font-weight: 600; cursor: pointer; text-align: center; transition: all 0.15s; color: #6b7280; user-select: none; }
    .pri-btn.sel-Low    { border-color: #16a34a; background: #f0fdf4; color: #16a34a; }
    .pri-btn.sel-Medium { border-color: #d97706; background: #fffbeb; color: #d97706; }
    .pri-btn.sel-High   { border-color: #ea580c; background: #fff7ed; color: #ea580c; }
    .pri-btn.sel-Urgent { border-color: #dc2626; background: #fef2f2; color: #dc2626; }

    .section-label { font-size: 11.5px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 8px; }
    .msg-area { width: 100%; border: 2px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; font-family: 'Inter', sans-serif; font-size: 13.5px; resize: none; outline: none; transition: border-color 0.15s; min-height: 200px; line-height: 1.6; }
    .msg-area:focus { border-color: #1e40af; }
    .char-count { font-size: 11.5px; color: #6b7280; text-align: right; margin-top: 5px; }
    .submit-btn { width: 100%; padding: 13px; background: linear-gradient(135deg, #1e40af, #0ea5e9); color: #fff; border: none; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 16px; font-family: 'Inter', sans-serif; transition: opacity 0.15s; }
    .submit-btn:hover { opacity: 0.92; }

    .success-box { text-align: center; padding: 32px 20px; }
    .success-icon { font-size: 52px; margin-bottom: 14px; }
    .success-box h3 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
    .success-box p  { font-size: 13.5px; color: #6b7280; line-height: 1.6; }

    /* Submissions */
    .section-header { display: flex; align-items: center; gap: 10px; margin-bottom: 16px; }
    .section-header h2 { font-size: 16px; font-weight: 700; margin: 0; }
    .section-count { background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; padding: 2px 9px; border-radius: 99px; }
    .fb-card { background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; margin-bottom: 12px; overflow: hidden; }
    .fb-card-body { padding: 18px 20px; }
    .fb-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
    .fb-cat { display: flex; align-items: center; gap: 5px; background: #f3f4f6; padding: 3px 10px; border-radius: 99px; font-size: 12px; font-weight: 600; color: #6b7280; }
    .fb-message { font-size: 14px; line-height: 1.7; background: #f9fafb; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px; }
    .fb-footer { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: #6b7280; }
    .review-note { margin: 0 20px 16px; background: #f0fdf4; border-left: 3px solid #16a34a; padding: 10px 14px; border-radius: 0 8px 8px 0; font-size: 12.5px; color: #166534; }
    .review-note strong { font-size: 12px; display: block; margin-bottom: 2px; }
    .empty-state { text-align: center; padding: 48px 20px; background: #fff; border-radius: 14px; border: 1px solid #e5e7eb; }
    .empty-icon { font-size: 44px; margin-bottom: 12px; }
    .empty-state p { font-size: 14px; color: #6b7280; }

    .fb-action-btn { padding: 5px 14px; border-radius: 7px; font-size: 12px; font-weight: 600; border: none; cursor: pointer; font-family: 'Inter', sans-serif; transition: opacity 0.15s; }
    .fb-action-btn:hover { opacity: 0.82; }
    .btn-edit   { background: #eff6ff; color: #1d4ed8; }
    .btn-save   { background: #f0fdf4; color: #16a34a; }
    .btn-cancel { background: #f3f4f6; color: #6b7280; }
    .btn-delete { background: #fef2f2; color: #dc2626; }

    .alert-success-box { background: #f0fdf4; border: 1px solid #16a34a; color: #166534; border-radius: 10px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

    @media (max-width: 640px) {
      .stats-row { grid-template-columns: repeat(2, 1fr); }
      .chart-cols { grid-template-columns: 1fr; }
      .user-chip .user-name { display: none; }
    }

    .pagination-wrap {
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 10px;
  padding: 16px 20px 20px; margin-top: 12px;
}
.pagination-info { font-size: 12px; color: #6b7280; }
.pagination-btns { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; justify-content: center; }
.page-btn {
  min-width: 34px; height: 34px; border-radius: 8px;
  border: 1.5px solid #e5e7eb; background: #fff;
  font-size: 13px; font-weight: 600; color: #374151;
  cursor: pointer; font-family: 'Inter', sans-serif;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.15s; padding: 0 8px;
}
.page-btn:hover    { border-color: #1e40af; color: #1e40af; background: #eff6ff; }
.page-btn.active   { background: linear-gradient(135deg, #1e40af, #0ea5e9); color: #fff; border-color: transparent; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
  </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="stu-navbar">
  <div class="navbar-left">
    <img src="<?= BASE_URL ?>/media/logoweb.svg" alt="NBSC Logo"
      style="width:36px;height:36px;object-fit:contain;flex-shrink:0;border-radius:8px;">
    <div>
      <div class="brand-name">NBSC Feedback</div>
      <div class="brand-sub">Anonymous · Safe · Heard</div>
    </div>
  </div>
  <div class="navbar-right">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION['first_name'], 0, 1)) ?></div>
      <span class="user-name"><?= sanitize($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></span>
    </div>
    <button class="hamburger-btn" onclick="toggleMenu()" id="hamburgerBtn" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<!-- ── Hamburger Dropdown ── -->
<div class="hamburger-menu" id="hamburgerMenu">
  <span class="menu-section">Menu</span>
  <button class="menu-link active" id="nav-dashboard" onclick="showSection('dashboard'); closeMenu();">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
    Dashboard
  </button>
  <button class="menu-link" id="nav-submit" onclick="showSection('submit'); closeMenu();">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    Submit Feedback
  </button>
  <button class="menu-link" id="nav-submissions" onclick="showSection('submissions'); closeMenu();">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
    My Submissions
    <?php if (count($mySubmissions) > 0): ?>
      <span style="margin-left:auto;background:#1e40af;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;"><?= count($mySubmissions) ?></span>
    <?php endif; ?>
  </button>
  <hr class="menu-divider">
  <a href="<?= BASE_URL ?>/app/auth/logout.php" class="menu-link" style="color:rgba(252,165,165,0.9);">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    Logout
  </a>
</div>

<!-- ── Page Content ── -->
<div class="page-wrap">

  <?php if ($err): ?>
    <div style="background:#fef2f2;border:1px solid #dc2626;color:#991b1b;border-radius:10px;padding:12px 16px;font-size:13px;margin-bottom:20px;">
      ⚠️ <?= sanitize($err) ?>
    </div>
  <?php endif; ?>

  <!-- SECTION: Dashboard -->
  <div class="page-section active" id="section-dashboard">
    <div class="page-header">
      <h1>Student Dashboard</h1>
      <p>Welcome, <?= sanitize($_SESSION['first_name']) ?>. Here's the current feedback overview.</p>
    </div>

    <div class="stats-row">
      <div class="stat-card blue">
        <div class="stat-label">Total Feedback</div>
        <div class="stat-value"><?= $totalFeedback ?></div>
      </div>
      <div class="stat-card red">
        <div class="stat-label">Urgent</div>
        <div class="stat-value"><?= $urgentCount ?></div>
      </div>
      <div class="stat-card orange">
        <div class="stat-label">High Priority</div>
        <div class="stat-value"><?= $highCount ?></div>
      </div>
      <div class="stat-card purple">
        <div class="stat-label">Top Category</div>
        <div class="stat-value"><?= categoryIcon($topCategory) ?> <?= sanitize(categoryLabel($topCategory)) ?></div>
      </div>
    </div>

   <div class="charts-card">
  <div class="charts-card-title">Feedback Distribution</div>
  <div class="chart-cols">
    <div>
      <div class="chart-title">By Priority</div>
      <div class="pie-wrap">
        <canvas id="priPie" width="160" height="160"></canvas>
        <ul class="pie-legend" id="priLegend"></ul>
      </div>
    </div>
    <div>
      <div class="chart-title">By Category</div>
      <div class="pie-wrap">
        <canvas id="catPie" width="160" height="160"></canvas>
        <ul class="pie-legend" id="catLegend"></ul>
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
            <textarea name="message" id="msg-input" class="msg-area" maxlength="1000"
              placeholder="Describe your concern clearly. Avoid sharing personal details about yourself or others..."
              required oninput="updateCount()"></textarea>
            <div class="char-count"><span id="char-count">0</span>/1000</div>
            <button type="submit" name="submit_feedback" class="submit-btn">Send Anonymously 🔒</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  
  <!-- SECTION: My Submissions -->
   <div id="submissionsList">
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
          <div class="fb-message" id="view-<?= $fb['feedback_id'] ?>">
            <?= sanitize($plain ?: '[Could not decrypt]') ?>
          </div>
          <form method="POST" id="edit-form-<?= $fb['feedback_id'] ?>" style="display:none;">
            <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
            <textarea name="updated_message" id="edit-msg-<?= $fb['feedback_id'] ?>"
              class="msg-area" maxlength="1000" minlength="10" required
              oninput="updateEditCount(<?= $fb['feedback_id'] ?>)"
              style="margin-bottom:4px;"><?= sanitize($plain) ?></textarea>
            <div class="char-count"><span id="edit-count-<?= $fb['feedback_id'] ?>"><?= strlen($plain) ?></span>/1000
          </div>
          </form>
        <?php $isAddressed = !empty($fb['request_status']); ?>
          <div class="fb-footer">
            <span><?= timeAgo($fb['submitted_at']) ?></span>
            <?php if ($isAddressed): ?>
              <span style="font-size:12px;font-weight:600;color:#16a34a;display:flex;align-items:center;gap:5px;">
                ✅ Feedback Addressed
              </span>
            <?php else: ?>
              <div style="display:flex;gap:8px;align-items:center;">
                <button class="fb-action-btn btn-edit" id="edit-btn-<?= $fb['feedback_id'] ?>" onclick="startEdit(<?= $fb['feedback_id'] ?>)">Edit</button>
                <button class="fb-action-btn btn-save" id="save-btn-<?= $fb['feedback_id'] ?>" style="display:none;" onclick="confirmUpdate(<?= $fb['feedback_id'] ?>)">Update</button>
                <button class="fb-action-btn btn-cancel" id="cancel-btn-<?= $fb['feedback_id'] ?>" style="display:none;" onclick="cancelEdit(<?= $fb['feedback_id'] ?>)">Cancel</button>
                <form method="POST" id="delete-form-<?= $fb['feedback_id'] ?>" style="display:inline;">
                  <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
                  <button type="button" class="fb-action-btn btn-delete" id="delete-btn-<?= $fb['feedback_id'] ?>" onclick="confirmDelete(<?= $fb['feedback_id'] ?>)">Delete</button>
                </form>
              </div>
            <?php endif; ?>
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
    
     <!-- Pagination -->
    <div class="pagination-wrap" id="paginationWrap" style="display:none;">
      <span class="pagination-info" id="paginationInfo"></span>
      <div class="pagination-btns" id="pageButtons"></div>
    </div>
  </div>

</div>

<script>
  // ── Hamburger ──
  function toggleMenu() { document.getElementById('hamburgerMenu').classList.toggle('open'); }
  function closeMenu()  { document.getElementById('hamburgerMenu').classList.remove('open'); }
  document.addEventListener('click', function(e) {
    const menu = document.getElementById('hamburgerMenu');
    const btn  = document.getElementById('hamburgerBtn');
    if (!menu.contains(e.target) && !btn.contains(e.target)) menu.classList.remove('open');
  });

  // ── Sections ──
  const sections = ['dashboard','submit','submissions'];
  function showSection(name) {
    sections.forEach(s => {
      document.getElementById('section-' + s).classList.toggle('active', s === name);
      const nav = document.getElementById('nav-' + s);
      if (nav) nav.classList.toggle('active', s === name);
    });
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
  <?php if (isset($_GET['updated']) || isset($_GET['deleted'])): ?>showSection('submissions');<?php endif; ?>

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

  // ── Edit/Delete ──
  let activeEditId = null;

  function startEdit(id) {
    if (activeEditId !== null && activeEditId !== id) { alert('Please finish or cancel your current edit first.'); return; }
    activeEditId = id;
    document.getElementById('view-' + id).style.display       = 'none';
    document.getElementById('edit-form-' + id).style.display  = 'block';
    document.getElementById('edit-btn-' + id).style.display   = 'none';
    document.getElementById('save-btn-' + id).style.display   = 'inline-block';
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
    if (msg.length < 10 || msg.length > 1000) { alert('Message must be 10–1000 characters.'); return; }
    if (confirm('Are you sure you want to update this feedback?')) {
      const form  = document.getElementById('edit-form-' + id);
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'update_feedback'; input.value = '1';
      form.appendChild(input); form.submit();
    }
  }

  function confirmDelete(id) {
    if (activeEditId !== null) { alert('Please finish or cancel your current edit before deleting.'); return; }
    if (confirm('Are you sure you want to delete this feedback? This cannot be undone.')) {
      const form  = document.getElementById('delete-form-' + id);
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'delete_feedback'; input.value = '1';
      form.appendChild(input); form.submit();
    }
  }

  function updateEditCount(id) {
    document.getElementById('edit-count-' + id).textContent = document.getElementById('edit-msg-' + id).value.length;
  }

  // ── Submissions Pagination ──
  const CARDS_PER_PAGE = 5;
  let subCurrentPage   = 1;

  function initSubmissionPagination() {
    subCurrentPage = 1;
    renderSubPage();
  }

  function renderSubPage() {
    const cards = Array.from(document.querySelectorAll('.fb-card'));
    if (cards.length === 0) return;

    cards.forEach(c => c.style.display = 'none');

    const start     = (subCurrentPage - 1) * CARDS_PER_PAGE;
    const end       = start + CARDS_PER_PAGE;
    cards.slice(start, end).forEach(c => c.style.display = '');

    renderSubPagination(cards.length);
  }

  function renderSubPagination(total) {
    const totalPages = Math.ceil(total / CARDS_PER_PAGE);
    const wrap       = document.getElementById('paginationWrap');
    const btns       = document.getElementById('pageButtons');

    if (totalPages <= 1) { wrap.style.display = 'none'; return; }

    wrap.style.display = 'flex';
    btns.innerHTML     = '';

    // Prev
    const prev       = document.createElement('button');
    prev.className   = 'page-btn';
    prev.textContent = '←';
    prev.disabled    = subCurrentPage === 1;
    prev.onclick     = () => { subCurrentPage--; renderSubPage(); };
    btns.appendChild(prev);

    // Page numbers with ellipsis
    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= subCurrentPage - 1 && i <= subCurrentPage + 1)) {
        const btn       = document.createElement('button');
        btn.className   = 'page-btn' + (i === subCurrentPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick     = (function(page) {
          return function() { subCurrentPage = page; renderSubPage(); };
        })(i);
        btns.appendChild(btn);
      } else if (i === subCurrentPage - 2 || i === subCurrentPage + 2) {
        const dots           = document.createElement('span');
        dots.textContent     = '…';
        dots.style.cssText   = 'color:#9ca3af;font-size:13px;padding:0 4px;';
        btns.appendChild(dots);
      }
    }

    // Next
    const next       = document.createElement('button');
    next.className   = 'page-btn';
    next.textContent = '→';
    next.disabled    = subCurrentPage === totalPages;
    next.onclick     = () => { subCurrentPage++; renderSubPage(); };
    btns.appendChild(next);

    document.getElementById('paginationInfo').textContent =
      'Page ' + subCurrentPage + ' of ' + totalPages;
  }

  // Hook into existing showSection
  const _origShowSection = showSection;
  showSection = function(name) {
    _origShowSection(name);
    if (name === 'submissions') initSubmissionPagination();
  };

  // Init if auto-redirected to submissions
  <?php if (isset($_GET['updated']) || isset($_GET['deleted'])): ?>
    initSubmissionPagination();
  <?php endif; ?>


// Priority data from PHP
const priData = [
  { label: 'Urgent', count: <?= (int)$urgentCount ?>, color: '#dc2626' },
  { label: 'High',   count: <?= (int)$highCount ?>,   color: '#ea580c' },
  { label: 'Medium', count: <?= (int)$mediumCount ?>, color: '#d97706' },
  { label: 'Low',    count: <?= (int)$lowCount ?>,    color: '#16a34a' }
];

// Category data from PHP — random distinct colors
const catColors = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#ef4444','#6366f1'];
const catData = [
  <?php foreach ($catStats as $i => $cs): ?>
  { label: '<?= addslashes(categoryLabel($cs['category'])) ?>', count: <?= (int)$cs['total'] ?>, color: catColors[<?= $i ?> % catColors.length] },
  <?php endforeach; ?>
];

function drawPie(canvasId, legendId, data) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const cx = canvas.width / 2, cy = canvas.height / 2, r = 68;
  const total = data.reduce((s, d) => s + d.count, 0);

  // Build slices
  const slices = [];
  let angle = -Math.PI / 2;
  data.forEach(d => {
    const sweep = total > 0 ? (d.count / total) * 2 * Math.PI : 0;
    slices.push({ ...d, start: angle, end: angle + sweep });
    angle += sweep;
  });

  // Build legend
  const legend = document.getElementById(legendId);
  legend.innerHTML = '';
  data.forEach(d => {
    const pct = total > 0 ? Math.round((d.count / total) * 100) : 0;
    legend.innerHTML += `<li>
      <span class="dot" style="background:${d.color}"></span>
      <span class="lbl">${d.label}</span>
      <span class="val">${d.count} (${pct}%)</span>
    </li>`;
  });

 function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (total === 0) {
      ctx.beginPath();
      ctx.arc(cx, cy, r, 0, 2 * Math.PI);
      ctx.fillStyle = '#f3f4f6';
      ctx.fill();
      ctx.fillStyle = '#9ca3af';
      ctx.font = '12px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('No data', cx, cy + 4);
      return;
    }

    slices.forEach(s => {
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, s.start, s.end);
      ctx.closePath();
      ctx.fillStyle = s.color;
      ctx.fill();
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();
    });
  }

  draw();

}

drawPie('priPie', 'priLegend', priData);
drawPie('catPie', 'catLegend', catData);
</script>
</body>
</html>