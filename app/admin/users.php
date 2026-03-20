<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('admin');

$unreadNotif = getUnreadNotifCount($pdo, $_SESSION['user_id']);
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $school_id = $_POST['school_id']; $first = $_POST['first_name']; $last = $_POST['last_name'];
    $email = $_POST['email']; $pass = $_POST['password']; $role = $_POST['role'];
    $dept = $_POST['department'];
    if ($school_id && $first && $last && $email && $pass && $role && $dept) {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email=?"); $chk->execute([$email]);
        if ($chk->fetch()) { $err = 'Email already exists.'; }
        else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (school_id,first_name,last_name,email,password,role,department) VALUES (?,?,?,?,?,?,?)")
                ->execute([$school_id, $first, $last, $email, $hash, $role, $dept]);
            logActivity($pdo, 'USER_CREATED', "Created account for $first $last", $_SESSION['user_id']);
            $msg = 'User created successfully.';
        }
    } else { $err = 'Please fill all required fields.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]);
        $msg = 'User deleted.';
    } else { $err = 'Cannot delete your own account.'; }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, last_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Users — NBSC Admin</title>
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

    .page-wrap { max-width: 1200px; margin: 0 auto; padding: 32px 20px; }

    .page-header-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
    }
    .page-header-row h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #111827; }
    .page-header-row p  { color: #6b7280; font-size: 13.5px; margin: 0; }

    .btn-add-user {
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border: none; border-radius: 9px;
      padding: 9px 18px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      display: flex; align-items: center; gap: 6px;
      transition: opacity 0.15s; white-space: nowrap;
    }
    .btn-add-user:hover { opacity: 0.88; }

    .alert-success-box {
      background: #f0fdf4; border: 1px solid #16a34a; color: #166534;
      border-radius: 10px; padding: 12px 16px; font-size: 13px;
      font-weight: 500; margin-bottom: 20px;
    }
    .alert-error-box {
      background: #fef2f2; border: 1px solid #dc2626; color: #991b1b;
      border-radius: 10px; padding: 12px 16px; font-size: 13px;
      font-weight: 500; margin-bottom: 20px;
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

    .school-id { font-family: 'DM Mono', monospace; font-size: 12px; color: #6b7280; }
    .full-name  { font-weight: 500; color: #111827; }
    .email-cell { font-size: 12.5px; color: #6b7280; }
    .dept-cell  { font-size: 13px; color: #374151; }
    .date-cell  { font-size: 12px; color: #9ca3af; white-space: nowrap; }

    .btn-delete {
      background: #fef2f2; color: #dc2626;
      border: 1.5px solid #fca5a5; border-radius: 7px;
      padding: 5px 12px; font-size: 12px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      transition: all 0.15s;
    }
    .btn-delete:hover { background: #fee2e2; }

    /* Modal */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,0.5); z-index: 400;
      align-items: center; justify-content: center;
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
      background: #fff; border-radius: 16px;
      width: 580px; max-width: 92vw;
      max-height: 90vh; overflow-y: auto;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
      font-family: 'Inter', sans-serif;
    }
    .modal-header {
      padding: 20px 24px; border-bottom: 1px solid #e5e7eb;
      display: flex; justify-content: space-between; align-items: center;
      position: sticky; top: 0; background: #fff; z-index: 1;
    }
    .modal-title { font-weight: 700; font-size: 15px; color: #111827; }
    .modal-close { background: none; border: none; cursor: pointer; font-size: 22px; color: #9ca3af; line-height: 1; padding: 0; }
    .modal-close:hover { color: #374151; }
    .modal-body { padding: 24px; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-grid .full-width { grid-column: 1 / -1; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-label-custom { font-size: 13px; font-weight: 600; color: #374151; }
    .form-input {
      border: 1.5px solid #e5e7eb; border-radius: 9px;
      padding: 9px 14px; font-size: 13.5px;
      font-family: 'Inter', sans-serif; color: #111827;
      outline: none; transition: border-color 0.15s;
      background: #fafafa;
    }
    .form-input:focus { border-color: #1e40af; background: #fff; }

    .modal-footer {
      display: flex; justify-content: flex-end; gap: 10px;
      margin-top: 20px; padding-top: 16px;
      border-top: 1px solid #f3f4f6;
    }
    .btn-modal-cancel {
      background: #f3f4f6; color: #374151; border: none;
      border-radius: 8px; padding: 9px 18px; font-size: 13px;
      font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;
    }
    .btn-modal-cancel:hover { background: #e5e7eb; }
    .btn-modal-create {
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border: none; border-radius: 8px;
      padding: 9px 18px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      transition: opacity 0.15s;
    }
    .btn-modal-create:hover { opacity: 0.88; }

    @media (max-width: 640px) {
      .user-chip .user-name { display: none; }
      .form-grid { grid-template-columns: 1fr; }
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
  <a href="<?= BASE_URL ?>/app/admin/dashboard.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
    Dashboard
  </a>
  <span class="menu-section">Management</span>
  <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="menu-link">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
    Feedback
  </a>
  <a href="<?= BASE_URL ?>/app/admin/users.php" class="menu-link active">
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
  <div class="page-header-row">
    <div>
      <h1>Users</h1>
      <p>Manage students, managers, and admins.</p>
    </div>
    <button class="btn-add-user" onclick="openCreateModal()">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add User
    </button>
  </div>

  <?php if ($msg): ?>
    <div class="alert-success-box">✅ <?= sanitize($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
    <div class="alert-error-box">⚠️ <?= sanitize($err) ?></div>
  <?php endif; ?>

  <div class="table-card">
    <div class="table-card-header">
      <span class="table-card-title">All Users</span>
      <span style="font-size:12px;color:#6b7280;"><?= count($users) ?> total</span>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>School ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Department</th>
            <th>Joined</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td class="school-id"><?= sanitize($u['school_id']) ?></td>
            <td class="full-name"><?= sanitize($u['first_name'] . ' ' . $u['last_name']) ?></td>
            <td class="email-cell"><?= sanitize($u['email']) ?></td>
            <td><?= roleBadge($u['role']) ?></td>
            <td class="dept-cell"><?= sanitize($u['department']) ?></td>
            <td class="date-cell"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
            <td>
              <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                <form method="POST" style="display:inline;"
                  onsubmit="return confirm('Are you sure you want to delete this user?')">
                  <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                  <button type="submit" name="delete_user" class="btn-delete">Delete</button>
                </form>
              <?php else: ?>
                <span style="font-size:12px;color:#9ca3af;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Create User Modal ── -->
<div class="modal-overlay" id="createModal">
  <div class="modal-box">
    <div class="modal-header">
      <span class="modal-title">Add New User</span>
      <button class="modal-close" onclick="closeCreateModal()">×</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label-custom">School ID *</label>
            <input type="text" name="school_id" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label-custom">Role *</label>
            <select name="role" class="form-input" required>
              <option value="student">Student</option>
              <option value="staff">Manager</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label-custom">First Name *</label>
            <input type="text" name="first_name" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label-custom">Last Name *</label>
            <input type="text" name="last_name" class="form-input" required>
          </div>
          <div class="form-group full-width">
            <label class="form-label-custom">Email *</label>
            <input type="email" name="email" class="form-input" placeholder="user@nbsc.edu.ph" required>
          </div>
          <div class="form-group">
            <label class="form-label-custom">Password *</label>
            <input type="password" name="password" class="form-input" required>
          </div>
          <div class="form-group">
            <label class="form-label-custom">Department *</label>
            <input type="text" name="department" class="form-input" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-modal-cancel" onclick="closeCreateModal()">Cancel</button>
          <button type="submit" name="create_user" class="btn-modal-create">Create User</button>
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

  function openCreateModal() {
    document.getElementById('createModal').classList.add('open');
  }
  function closeCreateModal() {
    document.getElementById('createModal').classList.remove('open');
  }
  document.getElementById('createModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateModal();
  });

  <?php if ($msg || $err): ?>
    // Keep modal open if there was a create error
    <?php if ($err && str_contains($err, 'Email')): ?>
      openCreateModal();
    <?php endif; ?>
  <?php endif; ?>
</script>
</body>
</html>