<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('staff');

requireRole('staff');

// ── Submit review request ──────────────────────────────────────────
$requestMsg = ''; $requestErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $fid     = (int)$_POST['feedback_id'];
    $purpose = trim($_POST['purpose'] ?? '');

    if (!isWithinOfficeHours()) {
        $requestErr = offHoursMessage();
    } elseif (strlen($purpose) < 10) {
        $requestErr = 'Please provide a detailed purpose (at least 10 characters).';
    } else {
        $existing = $pdo->prepare("SELECT request_id, status FROM review_requests WHERE feedback_id=? AND requested_by=? AND status IN ('pending','approved')");
        $existing->execute([$fid, $_SESSION['user_id']]);
        $ex = $existing->fetch();

        if ($ex && $ex['status'] === 'pending') {
            $requestErr = 'You already have a pending review request for this feedback.';
        } elseif ($ex && $ex['status'] === 'approved') {
            header("Location: " . BASE_URL . "/app/manager/view-feedback.php?id=$fid&rid=" . $ex['request_id']);
            exit;
        } else {
            $pdo->prepare("INSERT INTO review_requests (feedback_id, requested_by, purpose) VALUES (?,?,?)")
                ->execute([$fid, $_SESSION['user_id'], $purpose]);

            $admins = $pdo->query("SELECT user_id FROM users WHERE role='admin' AND status='active'")->fetchAll();
            foreach ($admins as $a) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                    ->execute([$a['user_id'], 'New Review Request',
                        $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] . " submitted a review request for Feedback #$fid."]);
            }
            logActivity($pdo, 'REVIEW_REQUEST', "Manager requested review of Feedback #$fid. Purpose: $purpose", $_SESSION['user_id']);

            // Refresh dashboard after submission
            header("Location: " . BASE_URL . "/app/manager/dashboard.php?requested=1");
            exit;
        }
    }
}

$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();
$unreadNotif   = getUnreadNotifCount($pdo, $_SESSION['user_id']);

// Fetch all feedback with approval status
$allFeedback = $pdo->prepare("
    SELECT f.*,
           rr.status AS request_status,
           rr.request_id
    FROM feedback f
    LEFT JOIN review_requests rr
        ON f.feedback_id = rr.feedback_id
        AND rr.requested_by = ?
        AND rr.status = 'approved'
    ORDER BY f.submitted_at DESC
");
$allFeedback->execute([$_SESSION['user_id']]);
$allFeedback = $allFeedback->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manager Dashboard — NBSC Feedback</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
  
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

    /* ── Navbar ── */
    .mgr-navbar {
      position: sticky; top: 0; z-index: 200;
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 24px; height: 56px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.18);
    }
    .navbar-left  { display: flex; align-items: center; gap: 14px; }
    .navbar-right { display: flex; align-items: center; gap: 12px; }

    .brand-name { color: #fff; font-size: 15px; font-weight: 700; }
    .brand-sub  { color: rgba(255,255,255,0.45); font-size: 11px; }

    /* Hamburger */
    .hamburger-btn {
      background: none; border: none; cursor: pointer;
      display: flex; flex-direction: column; gap: 5px; padding: 4px;
    }
    .hamburger-btn span {
      display: block; width: 22px; height: 2px;
      background: rgba(255,255,255,0.8); border-radius: 2px;
      transition: all 0.2s;
    }

    /* Dropdown menu */
    .hamburger-menu {
      position: absolute; top: 56px; right: 0;
      background: #1a1f2e; width: 220px;
      border-right: 1px solid rgba(255,255,255,0.08);
      border-bottom: 1px solid rgba(255,255,255,0.08);
      border-radius: 0 0 12px 0;
      padding: 10px 0 16px;
      display: none; z-index: 300;
      box-shadow: 4px 4px 16px rgba(0,0,0,0.2);
    }
    .hamburger-menu.open { display: block; }

    .menu-section {
      font-size: 10px; font-weight: 600; color: rgba(255,255,255,0.35);
      text-transform: uppercase; letter-spacing: 0.08em;
      padding: 10px 20px 4px;
    }
    .menu-link {
      display: flex; align-items: center; gap: 10px;
      padding: 9px 20px; font-size: 13.5px; font-weight: 500;
      color: rgba(255,255,255,0.65); text-decoration: none;
      transition: all 0.15s;
    }
    .menu-link:hover { color: #fff; background: rgba(255,255,255,0.06); }
    .menu-link.active { color: #fff; background: rgba(255,255,255,0.1); border-left: 3px solid #1a56db; }
    .menu-link svg { width: 16px; height: 16px; flex-shrink: 0; }
    .menu-divider { border-color: rgba(255,255,255,0.08); margin: 8px 0; }

    /* Notification bell */
    .notif-btn {
      position: relative; color: rgba(255,255,255,0.7);
      background: none; border: none; cursor: pointer;
      display: flex; align-items: center; padding: 4px;
      text-decoration: none;
    }
    .notif-btn:hover { color: #fff; }
    .notif-btn svg { width: 20px; height: 20px; }
    .notif-dot {
      position: absolute; top: 2px; right: 2px;
      width: 8px; height: 8px; background: #ef4444;
      border-radius: 50%; border: 2px solid #1a1f2e;
    }

    /* User chip */
    
    .user-chip {
      display: flex; align-items: center; gap: 8px;
      color: rgba(255,255,255,0.85); font-size: 13px;
    }

    .user-avatar {
      width: 30px; height: 30px; border-radius: 50%;
      background: linear-gradient(135deg,#1a56db,#7e3af2);
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0;
    }

    /* Logout */
    .logout-btn {
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.15);
      border-radius: 7px; color: rgba(255,255,255,0.7);
      font-size: 12px; font-weight: 500; padding: 5px 12px;
      cursor: pointer; text-decoration: none; transition: all 0.15s;
      display: flex; align-items: center; gap: 6px;
    }
    .logout-btn:hover { background: rgba(255,255,255,0.14); color: #fff; }
    .logout-btn svg { width: 14px; height: 14px; }

    /* ── Page Content ── */
    .page-wrap {
      max-width: 1100px; margin: 0 auto;
      padding: 32px 20px;
    }

    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #111827; }
    .page-header p  { color: #6b7280; font-size: 13.5px; margin: 0; }

    /* Stats */
    .stats-row {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 16px; margin-bottom: 24px;
    }
    .stat-card {
      background: #fff; border-radius: 14px;
      border: 1px solid #e5e7eb; padding: 22px 24px;
    }
    .stat-label { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
    .stat-value { font-size: 32px; font-weight: 700; }
    .stat-card.blue .stat-value { color: #1d4ed8; }
    .stat-card.red  .stat-value { color: #dc2626; }

    /* Filter bar */
    .filter-bar {
      background: #fff; border-radius: 12px;
      border: 1px solid #e5e7eb; padding: 14px 18px;
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 20px; flex-wrap: wrap;
    }
    .filter-bar label { font-size: 12px; font-weight: 600; color: #6b7280; white-space: nowrap; }
    .filter-select {
      border: 1.5px solid #e5e7eb; border-radius: 8px;
      padding: 7px 12px; font-size: 13px; font-family: inherit;
      color: #374151; background: #fafafa; cursor: pointer;
      outline: none; transition: border-color 0.15s;
    }
    .filter-select:focus { border-color: #1a56db; }

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
    .result-count { font-size: 12px; color: #6b7280; }

    table { width: 100%; border-collapse: collapse; }
    thead th {
      font-size: 11px; font-weight: 600; color: #6b7280;
      text-transform: uppercase; letter-spacing: 0.05em;
      padding: 10px 16px; background: #f9fafb;
      border-bottom: 1px solid #e5e7eb; text-align: left;
    }
    tbody td { padding: 13px 16px; font-size: 13.5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr:hover { background: #fafafa; }

    .msg-cell { color: #6b7280; font-size: 13px; }
    .msg-decrypted { color: #111827; font-size: 13px; max-width: 300px; }
    .time-cell { color: #9ca3af; font-size: 12px; white-space: nowrap; }

    .btn-request {
      background: linear-gradient(135deg,#1a56db,#7e3af2);
      color: #fff; border: none; border-radius: 7px;
      padding: 6px 14px; font-size: 12px; font-weight: 600;
      cursor: pointer; font-family: inherit; white-space: nowrap;
      text-decoration: none; display: inline-block; transition: opacity 0.15s;
    }
    .btn-request:hover { opacity: 0.88; color: #fff; }

    .btn-view {
      background: #f0fdf4; color: #16a34a;
      border: 1.5px solid #16a34a; border-radius: 7px;
      padding: 6px 14px; font-size: 12px; font-weight: 600;
      cursor: pointer; font-family: inherit; white-space: nowrap;
      text-decoration: none; display: inline-block; transition: all 0.15s;
    }
    .btn-view:hover { background: #dcfce7; color: #16a34a; }

    .badge-no-request { background: #f3f4f6; color: #6b7280; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 99px; }
    .badge-pending    { background: #fef3c7; color: #d97706; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 99px; }
    .badge-approved   { background: #d1fae5; color: #065f46; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 99px; }
    .badge-rejected   { background: #fef2f2; color: #dc2626; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 99px; }

    .empty-row td { text-align: center; padding: 40px; color: #9ca3af; font-size: 13.5px; }

    @media (max-width: 640px) {
      .stats-row { grid-template-columns: 1fr; }
      .filter-bar { flex-direction: column; align-items: flex-start; }
      thead th:nth-child(2), tbody td:nth-child(2) { display: none; }
      .user-chip .user-name { display: none; }
    }

    /* ── Request Modal ── */
.modal-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,0.5); z-index: 400;
  align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal-box {
  background: #fff; border-radius: 14px;
  width: 520px; max-width: 90vw;
  box-shadow: 0 20px 60px rgba(0,0,0,0.2);
  font-family: 'Inter', sans-serif;
}
.modal-header {
  padding: 20px 24px; border-bottom: 1px solid #e5e7eb;
  display: flex; justify-content: space-between; align-items: flex-start;
}
.modal-title   { font-weight: 700; font-size: 15px; color: #111827; }
.modal-sub     { font-size: 12px; color: #6b7280; margin-top: 3px; }
.modal-close   { background: none; border: none; cursor: pointer; font-size: 22px; color: #9ca3af; line-height: 1; padding: 0; }
.modal-close:hover { color: #374151; }
.modal-body    { padding: 20px 24px; }
.modal-fb-info {
  background: #f9fafb; border-radius: 8px;
  padding: 12px 14px; margin-bottom: 14px;
  font-size: 13px; color: #6b7280;
}
.modal-privacy {
  background: #fef3c7; border: 1px solid #fde68a;
  border-radius: 8px; padding: 12px 14px;
  margin-bottom: 16px; font-size: 12.5px; color: #92400e;
}
.modal-label { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; display: block; }
.modal-textarea {
  width: 100%; border: 1.5px solid #e5e7eb; border-radius: 9px;
  padding: 10px 14px; font-family: 'Inter', sans-serif;
  font-size: 13px; resize: vertical; min-height: 100px;
  outline: none; transition: border-color 0.15s; color: #111827;
}
.modal-textarea:focus { border-color: #1e40af; }
.modal-hint { font-size: 11.5px; color: #9ca3af; margin-top: 5px; }
.modal-footer {
  display: flex; justify-content: flex-end; gap: 10px;
  margin-top: 18px;
}
.btn-modal-cancel {
  background: #f3f4f6; color: #374151; border: none;
  border-radius: 8px; padding: 9px 18px; font-size: 13px;
  font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;
  transition: background 0.15s;
}
.btn-modal-cancel:hover { background: #e5e7eb; }
.btn-modal-submit {
  background: linear-gradient(135deg, #1e40af, #0ea5e9);
  color: #fff; border: none; border-radius: 8px;
  padding: 9px 18px; font-size: 13px; font-weight: 600;
  cursor: pointer; font-family: 'Inter', sans-serif;
  transition: opacity 0.15s;
}
.btn-modal-submit:hover { opacity: 0.88; }
  </style>
</head>
<body>

<!-- ── Navbar ── -->
<nav class="mgr-navbar">
  <div class="navbar-left">
    <div>
      <div class="brand-name">NBSC Feedback</div>
      <div class="brand-sub">Anonymous Feedback System</div>
    </div>
  </div>
  <div class="navbar-right">
    <a href="<?= BASE_URL ?>/app/manager/notifications.php" class="notif-btn">
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
 <a href="<?= BASE_URL ?>/app/manager/notifications.php" class="menu-link">
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    Notifications
  </a>
 
  <a href="<?= BASE_URL ?>/app/auth/logout.php" class="menu-link" style="color:rgba(255, 0, 0, 0.8);">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
    Logout
  </a>
</div>

<!-- ── Page Content ── -->
<div class="page-wrap">
  <div class="page-header">
    <h1>Manager Dashboard</h1>
    <p>Welcome, <?= sanitize($_SESSION['first_name']) ?>. Review and manage submitted feedback.</p>
  </div>

  <?php if (isset($_GET['requested'])): ?>
  <div style="background:#f0fdf4;border:1px solid #16a34a;color:#166534;border-radius:10px;padding:12px 16px;font-size:13px;font-weight:500;margin-bottom:20px;">
    ✅ Review request submitted. Waiting for admin approval.
  </div>
<?php endif; ?>
<?php if ($requestErr): ?>
  <div style="background:#fef2f2;border:1px solid #dc2626;color:#991b1b;border-radius:10px;padding:12px 16px;font-size:13px;font-weight:500;margin-bottom:20px;">
    ⚠️ <?= sanitize($requestErr) ?>
  </div>
<?php endif; ?>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card blue">
      <div class="stat-label">Total Feedback</div>
      <div class="stat-value"><?= $totalFeedback ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Urgent</div>
      <div class="stat-value"><?= $urgentCount ?></div>
    </div>
  </div>



  <!-- Filter Bar -->
  <div class="filter-bar">
    <label>Filter by:</label>
    <select class="filter-select" id="filterTime" onchange="applyFilter('time')">
      <option value="recent">Recent Feedback</option>
      <option value="older">Previous Feedback</option>
    </select>
    <select class="filter-select" id="filterCategory" onchange="applyFilter('category')">
      <option value="">All Categories</option>
      <option value="general">General</option>
      <option value="academic">Academic</option>
      <option value="facilities">Facilities</option>
      <option value="services">Services</option>
      <option value="faculty">Faculty</option>
      <option value="administration">Administration</option>
      <option value="suggestion">Suggestion</option>
      <option value="complaint">Complaint</option>
      <option value="other">Other</option>
    </select>
    <select class="filter-select" id="filterPriority" onchange="applyFilter('priority')">
      <option value="">All Priorities</option>
      <option value="Urgent">Urgent</option>
      <option value="High">High</option>
      <option value="Medium">Medium</option>
      <option value="Low">Low</option>
    </select>
    <span id="resultCount" class="result-count"></span>
  </div>

  <!-- Table -->
  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title" id="tableTitle">Recent Feedback</span>
    </div>
    <div style="overflow-x:auto;">
      <table id="feedbackTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Category</th>
            <th>Message</th>
            <th>Priority</th>
            <th>Submitted</th>
            <th>Request Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="feedbackBody">
          <?php if (empty($allFeedback)): ?>
            <tr class="empty-row"><td colspan="7">No feedback found.</td></tr>
          <?php else: $i = 1; foreach ($allFeedback as $fb):
            $isApproved = $fb['request_status'] === 'approved';
            $plain      = $isApproved ? sanitize(decryptMessage($fb['message_enc'])) : null;

            // Determine request status for this feedback
            $reqStmt = $pdo->prepare("SELECT status FROM review_requests WHERE feedback_id = ? AND requested_by = ? ORDER BY requested_at DESC LIMIT 1");
            $reqStmt->execute([$fb['feedback_id'], $_SESSION['user_id']]);
            $reqStatus = $reqStmt->fetchColumn();
          ?>
          <tr
            data-category="<?= $fb['category'] ?>"
            data-priority="<?= $fb['priority'] ?>"
            data-time="<?= strtotime($fb['submitted_at']) ?>"
          >
            <td><?= $i++ ?></td>
            <td><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td>
              <?php if ($isApproved): ?>
                <span class="msg-decrypted"><?= $plain ?></span>
              <?php else: ?>
                <span class="msg-cell">🔒 Encrypted</span>
              <?php endif; ?>
            </td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td class="time-cell"><?= timeAgo($fb['submitted_at']) ?></td>
            <td>
              <?php if ($isApproved): ?>
                <span class="badge-approved">Approved</span>
              <?php elseif ($reqStatus === 'pending'): ?>
                <span class="badge-pending">Pending</span>
              <?php elseif ($reqStatus === 'rejected'): ?>
                <span class="badge-rejected">Rejected</span>
              <?php else: ?>
                <span class="badge-no-request">No Request</span>
              <?php endif; ?>
            </td>
            <td>
             <?php if ($isApproved): ?>
  <span style="font-size:12px;font-weight:600;color:#16a34a;">✅ Decrypted</span>
             <?php else: ?>
  <?php if ($reqStatus === 'pending'): ?>
    <span style="font-size:12px;color:#d97706;">⏳ Awaiting Approval</span>
  <?php else: ?>
    <button class="btn-request"
      onclick="openRequestModal(<?= $fb['feedback_id'] ?>, '<?= sanitize(categoryLabel($fb['category'])) ?>', '<?= $fb['priority'] ?>')">
      Request Access
    </button>
  <?php endif; ?>
<?php endif; ?>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Request Access Modal ── -->
<div class="modal-overlay" id="requestModal">
  <div class="modal-box">
    <div class="modal-header">
      <div>
        <div class="modal-title">🔍 Request Feedback Access</div>
        <div class="modal-sub">This request will be logged and reviewed by the Admin.</div>
      </div>
      <button class="modal-close" onclick="closeRequestModal()">×</button>
    </div>
    <div class="modal-body">
      <div class="modal-fb-info" id="modalFbInfo"></div>
      <div class="modal-privacy">
        ⚠️ <strong>Data Privacy Notice:</strong> Your access request, stated purpose, and the time of access will be permanently logged for audit purposes.
      </div>
      <form method="POST">
        <input type="hidden" name="feedback_id" id="modalFid">
        <label class="modal-label">Why do you need to review this feedback? *</label>
        <textarea
          name="purpose"
          class="modal-textarea"
          minlength="10"
          required
          placeholder="State your official purpose clearly. Example: Investigating a reported academic integrity concern related to grading in the IT department..."
        ></textarea>
        <div class="modal-hint">Minimum 10 characters. Be specific — vague purposes will be rejected.</div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" onclick="closeRequestModal()">Cancel</button>
          <button type="submit" name="submit_request" class="btn-modal-submit">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // ── Hamburger ──
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

  // ── Filter logic: only one dropdown active at a time ──
  let activeFilter = 'time'; // default

  function applyFilter(changedBy) {
    // Reset the other two dropdowns when one changes
    if (changedBy === 'time') {
      document.getElementById('filterCategory').value = '';
      document.getElementById('filterPriority').value = '';
      activeFilter = 'time';
    } else if (changedBy === 'category') {
      document.getElementById('filterTime').value     = 'recent';
      document.getElementById('filterPriority').value = '';
      activeFilter = 'category';
    } else if (changedBy === 'priority') {
      document.getElementById('filterTime').value     = 'recent';
      document.getElementById('filterCategory').value = '';
      activeFilter = 'priority';
    }
    renderTable();
  }

  function renderTable() {
    const timeVal     = document.getElementById('filterTime').value;
    const categoryVal = document.getElementById('filterCategory').value;
    const priorityVal = document.getElementById('filterPriority').value;

    const rows   = document.querySelectorAll('#feedbackBody tr[data-time]');
    const now    = Math.floor(Date.now() / 1000);
    const dayAgo = now - (24 * 60 * 60); // last 24 hours = "recent"

    let visible = 0;

    rows.forEach(row => {
      const cat      = row.dataset.category;
      const pri      = row.dataset.priority;
      const rowTime  = parseInt(row.dataset.time);
      let   show     = true;

      if (activeFilter === 'time') {
        if (timeVal === 'recent') show = rowTime >= dayAgo;
        if (timeVal === 'older')  show = rowTime < dayAgo;
      } else if (activeFilter === 'category') {
        if (categoryVal) show = (cat === categoryVal);
      } else if (activeFilter === 'priority') {
        if (priorityVal) show = (pri === priorityVal);
      }

      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    // Update title and count
    const titles = {
      time: timeVal === 'recent' ? 'Recent Feedback' : 'Previous Feedback',
      category: categoryVal ? categoryVal.charAt(0).toUpperCase() + categoryVal.slice(1) + ' Feedback' : 'All Feedback',
      priority: priorityVal ? priorityVal + ' Priority Feedback' : 'All Feedback',
    };
    document.getElementById('tableTitle').textContent  = titles[activeFilter];
    document.getElementById('resultCount').textContent = visible + ' result' + (visible !== 1 ? 's' : '');

    // Show empty state if none visible
    let emptyRow = document.getElementById('emptyRow');
    if (visible === 0) {
      if (!emptyRow) {
        emptyRow = document.createElement('tr');
        emptyRow.id = 'emptyRow';
        emptyRow.className = 'empty-row';
        emptyRow.innerHTML = '<td colspan="7">No feedback matches this filter.</td>';
        document.getElementById('feedbackBody').appendChild(emptyRow);
      }
      emptyRow.style.display = '';
    } else if (emptyRow) {
      emptyRow.style.display = 'none';
    }
  }

  // Run on load to apply default "recent" filter
  renderTable();

  function openRequestModal(fid, category, priority) {
  document.getElementById('requestModal').classList.add('open');
  document.getElementById('modalFid').value   = fid;
  document.getElementById('modalFbInfo').textContent = 'Feedback #' + fid + ' · ' + category + ' · Priority: ' + priority;
}
function closeRequestModal() {
  document.getElementById('requestModal').classList.remove('open');
}
// Close modal on overlay click
document.getElementById('requestModal').addEventListener('click', function(e) {
  if (e.target === this) closeRequestModal();
});
</script>
</body>
</html>