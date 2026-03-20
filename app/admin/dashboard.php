<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('admin');

$unreadNotif   = getUnreadNotifCount($pdo, $_SESSION['user_id']);
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalManagers = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();

$recentFeedback = $pdo->query("SELECT * FROM feedback ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
$recentLogs     = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS full_name
    FROM activity_logs a JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC LIMIT 6
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — NBSC Feedback</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    *, *::before, *::after { box-sizing: border-box; }
    body { background: #f0f2f5; margin: 0; font-family: 'Inter', sans-serif; }

    /* ── Navbar ── */
    .adm-navbar {
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
      position: absolute; top: 56px; right: 0;
      background: #1a1f2e; width: 230px;
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
      padding: 9px 20px; font-size: 13px; font-weight: 500;
      color: rgba(255,255,255,0.65); text-decoration: none;
      transition: all 0.15s;
    }
    .menu-link:hover  { color: #fff; background: rgba(255,255,255,0.06); }
    .menu-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left: 3px solid #7dd3fc; }
    .menu-link svg { width: 16px; height: 16px; flex-shrink: 0; }
    .menu-divider { border-color: rgba(255,255,255,0.08); margin: 8px 0; }

    .notif-btn {
      position: relative; color: rgba(255,255,255,0.8);
      background: none; border: none; cursor: pointer;
      display: flex; align-items: center; padding: 4px;
      text-decoration: none;
    }
    .notif-btn:hover { color: #fff; }
    .notif-btn svg { width: 20px; height: 20px; }
    .notif-dot {
      position: absolute; top: 2px; right: 2px;
      width: 8px; height: 8px; background: #ef4444;
      border-radius: 50%; border: 2px solid #1e40af;
    }

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
    .page-wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }

    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #111827; }
    .page-header p  { color: #6b7280; font-size: 13.5px; margin: 0; }

    /* Stats */
    .stats-row {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 14px; margin-bottom: 16px;
    }
    .stats-row-2 {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 14px; margin-bottom: 24px;
    }
    .stat-card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e5e7eb; padding: 20px 22px;
    }
    .stat-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 700; }
    .stat-card.purple .stat-value { color: #7c3aed; }
    .stat-card.blue   .stat-value { color: #1d4ed8; }
    .stat-card.green  .stat-value { color: #16a34a; }
    .stat-card.orange .stat-value { color: #d97706; }
    .stat-card.red    .stat-value { color: #dc2626; }

    /* Two column layout */
    .two-col { display: grid; grid-template-columns: 1fr 380px; gap: 20px; }

    /* Table card */
    .table-card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e5e7eb; overflow: hidden;
    }
    .table-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
    }
    .table-card-title { font-size: 14px; font-weight: 600; color: #111827; }

    .btn-view-all {
      font-size: 12px; font-weight: 600; color: #1d4ed8;
      text-decoration: none; padding: 5px 12px;
      border: 1.5px solid #dbeafe; border-radius: 7px;
      background: #eff6ff; transition: all 0.15s;
    }
    .btn-view-all:hover { background: #dbeafe; color: #1d4ed8; }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      font-size: 11px; font-weight: 600; color: #6b7280;
      text-transform: uppercase; letter-spacing: 0.05em;
      padding: 10px 16px; background: #f9fafb;
      border-bottom: 1px solid #e5e7eb; text-align: left;
    }
    tbody td { padding: 12px 16px; font-size: 13px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #fafafa; }
    .time-cell { color: #9ca3af; font-size: 12px; white-space: nowrap; }

    /* Activity log card */
    .activity-card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e5e7eb; overflow: hidden;
    }
    .activity-item {
      padding: 12px 18px; border-bottom: 1px solid #f3f4f6;
    }
    .activity-item:last-child { border-bottom: none; }
    .activity-name   { font-size: 13px; font-weight: 500; color: #111827; }
    .activity-detail { font-size: 12px; color: #6b7280; margin-top: 2px; }

    @media (max-width: 900px) {
      .two-col { grid-template-columns: 1fr; }
      .stats-row { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
      .stats-row { grid-template-columns: 1fr 1fr; }
      .stats-row-2 { grid-template-columns: 1fr; }
      .user-chip .user-name { display: none; }
    }
  </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="adm-navbar">
  <div class="navbar-left">
    <img src="<?= BASE_URL ?>/media/logoweb.svg" alt="NBSC Logo"
      style="width:36px;height:36px;object-fit:contain;flex-shrink:0;border-radius:8px;">
    <div>
      <div class="brand-name">NBSC Feedback</div>
      <div class="brand-sub">Anonymous Feedback System</div>
    </div>
  </div>
  <div class="navbar-right">
    <a href="<?= BASE_URL ?>/app/admin/notifications.php" class="notif-btn">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      <?php if ($unreadNotif > 0): ?><span class="notif-dot"></span><?php endif; ?>
    </a>
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
  <a href="<?= BASE_URL ?>/app/admin/dashboard.php" class="menu-link active">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
    Dashboard
  </a>
  <span class="menu-section">Management</span>
  <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    Feedback
  </a>
  <a href="<?= BASE_URL ?>/app/admin/users.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    Users
  </a>
  <a href="<?= BASE_URL ?>/app/admin/review-requests.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    Review Requests
  </a>
  <span class="menu-section">Reports</span>
  <a href="<?= BASE_URL ?>/app/admin/activity-logs.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    Activity Logs
  </a>
  <a href="<?= BASE_URL ?>/app/admin/notifications.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    Notifications
    <?php if ($unreadNotif > 0): ?>
      <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;"><?= $unreadNotif ?></span>
    <?php endif; ?>
  </a>
  <hr class="menu-divider">
  <a href="<?= BASE_URL ?>/app/auth/logout.php" class="menu-link" style="color:rgba(252,165,165,0.9);">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    Logout
  </a>
</div>

<!-- ── Page Content ── -->
<div class="page-wrap">
  <div class="page-header">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <?= sanitize($_SESSION['first_name']) ?>. System overview.</p>
  </div>

  <!-- User Stats -->
  <div class="stats-row">
    <div class="stat-card purple">
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?= $totalUsers ?></div>
    </div>
    <div class="stat-card blue">
      <div class="stat-label">Admins</div>
      <div class="stat-value"><?= $totalAdmins ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Managers</div>
      <div class="stat-value"><?= $totalManagers ?></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Students</div>
      <div class="stat-value"><?= $totalStudents ?></div>
    </div>
  </div>

  <!-- Feedback Stats -->
  <div class="stats-row-2">
    <div class="stat-card blue">
      <div class="stat-label">Total Feedback</div>
      <div class="stat-value"><?= $totalFeedback ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Urgent</div>
      <div class="stat-value"><?= $urgentCount ?></div>
    </div>
  </div>

  <!-- Two Column -->
  <div class="two-col">

    <!-- Recent Feedback Table -->
    <div class="table-card">
      <div class="table-card-header">
        <span class="table-card-title">Recent Feedback</span>
        <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="btn-view-all">View All</a>
      </div>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th>Category</th>
              <th>Message</th>
              <th>Priority</th>
              <th>Submitted</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentFeedback as $fb): ?>
            <tr>
              <td><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></td>
              <td><span style="color:#6b7280;font-size:13px;">🔒 Encrypted</span></td>
              <td><?= priorityBadge($fb['priority']) ?></td>
              <td class="time-cell"><?= timeAgo($fb['submitted_at']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="activity-card">
      <div class="table-card-header">
        <span class="table-card-title">Recent Activity</span>
        <a href="<?= BASE_URL ?>/app/admin/activity-logs.php" class="btn-view-all">All</a>
      </div>
      <?php foreach ($recentLogs as $log): ?>
      <div class="activity-item">
        <div class="activity-name"><?= sanitize($log['full_name']) ?></div>
        <div class="activity-detail"><?= sanitize($log['action']) ?> · <?= timeAgo($log['created_at']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<script>
  function toggleMenu() {
    document.getElementById('hamburgerMenu').classList.toggle('open');
  }
  document.addEventListener('click', function(e) {
    const menu = document.getElementById('hamburgerMenu');
    const btn  = document.getElementById('hamburgerBtn');
    if (!menu.contains(e.target) && !btn.contains(e.target)) {
      menu.classList.remove('open');
    }
  });
</script>
</body>
</html>