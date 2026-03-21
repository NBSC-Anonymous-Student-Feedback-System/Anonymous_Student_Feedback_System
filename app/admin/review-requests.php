<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('admin');

$unreadNotif = getUnreadNotifCount($pdo, $_SESSION['user_id']);
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decide'])) {
    $rid        = (int)$_POST['request_id'];
    $decision   = $_POST['decision'];
    $adminNotes = trim($_POST['admin_notes'] ?? '');

    if (in_array($decision, ['approved', 'rejected'])) {
        $pdo->prepare("UPDATE review_requests SET status=?, reviewed_by=?, admin_notes=?, resolved_at=NOW() WHERE request_id=?")
            ->execute([$decision, $_SESSION['user_id'], $adminNotes, $rid]);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review Requests — NBSC Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    *, *::before, *::after { box-sizing: border-box; }
    body { background: #f0f2f5; margin: 0; font-family: 'Inter', sans-serif; }

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
      position: fixed; top: 56px; right: 0;
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

    .page-wrap { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }

    .page-header { margin-bottom: 20px; }
    .page-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #111827; }
    .page-header p  { color: #6b7280; font-size: 13.5px; margin: 0; }

    .page-title-row {
      display: flex; align-items: center; gap: 10px; margin-bottom: 20px;
    }
    .page-title-row h1 { font-size: 22px; font-weight: 700; margin: 0; color: #111827; }
    .pending-badge {
      background: #fef2f2; color: #dc2626;
      font-size: 11px; font-weight: 700;
      padding: 3px 10px; border-radius: 99px;
      border: 1px solid #fca5a5;
    }

    .alert-success-box {
      background: #f0fdf4; border: 1px solid #16a34a; color: #166534;
      border-radius: 10px; padding: 12px 16px; font-size: 13px;
      font-weight: 500; margin-bottom: 20px;
    }

    /* Filter tabs */
    .filter-tabs { display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap; }
    .filter-tab {
      padding: 7px 16px; border-radius: 8px;
      font-size: 13px; font-weight: 600; cursor: pointer;
      text-decoration: none; transition: all 0.15s;
      border: 1.5px solid #e5e7eb; background: #fff; color: #6b7280;
    }
    .filter-tab:hover { border-color: #1e40af; color: #1e40af; }
    .filter-tab.active {
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border-color: transparent;
    }

    .table-card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e5e7eb; overflow: hidden;
    }
    .table-card-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 20px; border-bottom: 1px solid #f3f4f6;
    }
    .table-card-title { font-size: 14px; font-weight: 600; color: #111827; }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      font-size: 11px; font-weight: 600; color: #6b7280;
      text-transform: uppercase; letter-spacing: 0.05em;
      padding: 10px 16px; background: #f9fafb;
      border-bottom: 1px solid #e5e7eb; text-align: left;
    }
    tbody td {
      padding: 13px 16px; font-size: 13px;
      border-bottom: 1px solid #f3f4f6; vertical-align: middle;
    }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #fafafa; }

    .manager-name  { font-weight: 600; font-size: 13px; color: #111827; }
    .manager-email { font-size: 11.5px; color: #6b7280; }
    .manager-dept  { font-size: 11px; color: #9ca3af; }
    .purpose-cell  { max-width: 240px; font-size: 13px; color: #374151; line-height: 1.5; }
    .time-cell     { color: #9ca3af; font-size: 12px; white-space: nowrap; }
    .id-cell       { color: #9ca3af; font-size: 12px; }
    .reviewed-by   { font-size: 11px; color: #9ca3af; margin-top: 3px; }
    .empty-row td  { text-align: center; padding: 40px; color: #9ca3af; }

    .btn-review {
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border: none; border-radius: 7px;
      padding: 6px 14px; font-size: 12px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      transition: opacity 0.15s; white-space: nowrap;
    }
    .btn-review:hover { opacity: 0.88; }

    /* Modal */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.5); z-index: 400;
      align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #fff; border-radius: 14px;
      width: 500px; max-width: 90vw;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
      font-family: 'Inter', sans-serif;
    }
    .modal-header {
      padding: 20px 24px; border-bottom: 1px solid #e5e7eb;
      display: flex; justify-content: space-between; align-items: flex-start;
    }
    .modal-title { font-weight: 700; font-size: 15px; color: #111827; }
    .modal-sub   { font-size: 12px; color: #6b7280; margin-top: 3px; }
    .modal-close { background: none; border: none; cursor: pointer; font-size: 22px; color: #9ca3af; line-height: 1; padding: 0; }
    .modal-close:hover { color: #374151; }
    .modal-body  { padding: 20px 24px; }
    .modal-label {
      font-size: 13px; font-weight: 600; color: #374151;
      margin-bottom: 6px; display: block;
    }
    .modal-textarea {
      width: 100%; border: 1.5px solid #e5e7eb; border-radius: 9px;
      padding: 10px 14px; font-family: 'Inter', sans-serif;
      font-size: 13px; resize: vertical; min-height: 90px;
      outline: none; transition: border-color 0.15s; color: #111827;
    }
    .modal-textarea:focus { border-color: #1e40af; }
    .modal-footer {
      display: flex; justify-content: flex-end; gap: 10px;
      margin-top: 18px;
    }
    .btn-modal-cancel {
      background: #f3f4f6; color: #374151; border: none;
      border-radius: 8px; padding: 9px 18px; font-size: 13px;
      font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;
    }
    .btn-modal-cancel:hover { background: #e5e7eb; }
    .btn-modal-reject {
      background: #fef2f2; color: #dc2626;
      border: 1.5px solid #fca5a5; border-radius: 8px;
      padding: 9px 18px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      transition: all 0.15s;
    }
    .btn-modal-reject:hover { background: #fee2e2; }
    .btn-modal-approve {
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border: none; border-radius: 8px;
      padding: 9px 18px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      transition: opacity 0.15s;
    }
    .btn-modal-approve:hover { opacity: 0.88; }

    @media (max-width: 640px) {
      .user-chip .user-name { display: none; }
      .filter-tabs { flex-wrap: wrap; }
    }

    /* Pagination */
.pagination-wrap {
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 10px;
  padding: 16px 20px 20px; border-top: 1px solid #f3f4f6;
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
.page-btn:hover   { border-color: #1e40af; color: #1e40af; background: #eff6ff; }
.page-btn.active  { background: linear-gradient(135deg, #1e40af, #0ea5e9); color: #fff; border-color: transparent; }
.page-btn:disabled { opacity: 0.4; cursor: not-allowed; }

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
  <a href="<?= BASE_URL ?>/app/admin/dashboard.php" class="menu-link">
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
  <a href="<?= BASE_URL ?>/app/admin/review-requests.php" class="menu-link active">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
    Review Requests
    <?php if ($pendingCount > 0): ?>
      <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;"><?= $pendingCount ?></span>
    <?php endif; ?>
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

  <div class="page-title-row">
    <h1>Review Requests</h1>
    <?php if ($pendingCount > 0): ?>
      <span class="pending-badge"><?= $pendingCount ?> Pending</span>
    <?php endif; ?>
  </div>
  <p style="color:#6b7280;font-size:13.5px;margin:-12px 0 20px;">Authorize or reject manager requests to access encrypted feedback.</p>

  <?php if ($msg): ?>
    <div class="alert-success-box">✅ <?= sanitize($msg) ?></div>
  <?php endif; ?>

  <!-- Filter Tabs -->
  <div class="filter-tabs">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','all'=>'All'] as $val => $label): ?>
      <a href="?filter=<?= $val ?>" class="filter-tab <?= $filter===$val ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>

  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title">
        <?= ucfirst($filter) ?> Requests
      </span>
      <span style="font-size:12px;color:#6b7280;"><?= count($requests) ?> entries</span>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Manager</th>
            <th>Feedback</th>
            <th>Purpose</th>
            <th>Requested</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($requests)): ?>
            <tr class="empty-row"><td colspan="7">No requests found.</td></tr>
          <?php else: foreach ($requests as $r): ?>
          <tr>
            <td class="id-cell">#<?= $r['request_id'] ?></td>
            <td>
              <div class="manager-name"><?= sanitize($r['manager_name']) ?></div>
              <div class="manager-email"><?= sanitize($r['manager_email']) ?></div>
              <div class="manager-dept"><?= sanitize($r['department']) ?></div>
            </td>
            <td>
              <div><?= categoryIcon($r['category']) ?> <?= sanitize(categoryLabel($r['category'])) ?></div>
              <div style="margin-top:4px;"><?= priorityBadge($r['priority']) ?></div>
            </td>
            <td class="purpose-cell"><?= sanitize($r['purpose']) ?></td>
            <td class="time-cell"><?= timeAgo($r['requested_at']) ?></td>
            <td>
              <?= requestStatusBadge($r['status']) ?>
              <?php if ($r['admin_name']): ?>
                <div class="reviewed-by">by <?= sanitize($r['admin_name']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($r['status'] === 'pending'): ?>
                <button class="btn-review"
                  onclick="openDecide(<?= $r['request_id'] ?>, '<?= sanitize($r['manager_name']) ?>', '<?= sanitize(categoryLabel($r['category'])) ?>')">
                  Review
                </button>
              <?php else: ?>
                <span style="font-size:12px;color:#9ca3af;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    
 <!-- Pagination -->
    <div class="pagination-wrap" id="paginationWrap" style="display:none;">
      <span class="pagination-info" id="paginationInfo"></span>
      <div class="pagination-btns" id="pageButtons"></div>
    </div>

  </div>
</div>

<!-- ── Decision Modal ── -->
<div class="modal-overlay" id="decideModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title">Authorize Review Request</div>
        <div class="modal-sub" id="decide-info"></div>
      </div>
      <button class="modal-close" onclick="closeDecide()">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <input type="hidden" name="request_id" id="decide-rid">
        <input type="hidden" name="decision"   id="decision-val">
        <label class="modal-label">
          Admin Notes <span style="font-size:11px;color:#9ca3af;font-weight:400;">(optional)</span>
        </label>
        <textarea name="admin_notes" class="modal-textarea"
          placeholder="Provide a reason for your decision..."></textarea>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" onclick="closeDecide()">Cancel</button>
          <button type="submit" name="decide" value="decide" class="btn-modal-reject"
            onclick="setDecision('rejected')">Reject</button>
          <button type="submit" name="decide" value="decide" class="btn-modal-approve"
            onclick="setDecision('approved')">Approve</button>
        </div>
      </form>
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

  function openDecide(rid, manager, category) {
    document.getElementById('decideModal').classList.add('open');
    document.getElementById('decide-rid').value   = rid;
    document.getElementById('decide-info').textContent = 'Request #' + rid + ' — ' + manager + ' → ' + category;
  }
  function closeDecide() {
    document.getElementById('decideModal').classList.remove('open');
  }
  function setDecision(val) {
    document.getElementById('decision-val').value = val;
  }
  document.getElementById('decideModal').addEventListener('click', function(e) {
    if (e.target === this) closeDecide();
  });

  // ── Review Requests Pagination ──
  const ROWS_PER_PAGE = 10;
  let currentPage     = 1;

  function initReqPagination() {
    currentPage = 1;
    renderReqPage();
  }

  function renderReqPage() {
    const rows = Array.from(document.querySelectorAll('tbody tr:not(.empty-row)'))
                      .filter(r => !r.querySelector('td[colspan]'));
    if (rows.length === 0) return;

    rows.forEach(r => r.style.display = 'none');

    const start = (currentPage - 1) * ROWS_PER_PAGE;
    const end   = start + ROWS_PER_PAGE;
    rows.slice(start, end).forEach(r => r.style.display = '');

    renderReqPagination(rows.length);
  }

  function renderReqPagination(total) {
    const totalPages = Math.ceil(total / ROWS_PER_PAGE);
    const wrap       = document.getElementById('paginationWrap');
    const btns       = document.getElementById('pageButtons');

    if (totalPages <= 1) { wrap.style.display = 'none'; return; }

    wrap.style.display = 'flex';
    btns.innerHTML     = '';

    const prev       = document.createElement('button');
    prev.className   = 'page-btn';
    prev.textContent = '←';
    prev.disabled    = currentPage === 1;
    prev.onclick     = () => { currentPage--; renderReqPage(); };
    btns.appendChild(prev);

    for (let i = 1; i <= totalPages; i++) {
      if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
        const btn       = document.createElement('button');
        btn.className   = 'page-btn' + (i === currentPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick     = (function(page) {
          return function() { currentPage = page; renderReqPage(); };
        })(i);
        btns.appendChild(btn);
      } else if (i === currentPage - 2 || i === currentPage + 2) {
        const dots         = document.createElement('span');
        dots.textContent   = '…';
        dots.style.cssText = 'color:#9ca3af;font-size:13px;padding:0 4px;';
        btns.appendChild(dots);
      }
    }

    const next       = document.createElement('button');
    next.className   = 'page-btn';
    next.textContent = '→';
    next.disabled    = currentPage === totalPages;
    next.onclick     = () => { currentPage++; renderReqPage(); };
    btns.appendChild(next);

    document.getElementById('paginationInfo').textContent =
      'Page ' + currentPage + ' of ' + totalPages;
  }

  // Init on load
  initReqPagination();
</script>
</body>
</html>