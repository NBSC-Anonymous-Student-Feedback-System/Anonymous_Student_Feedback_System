<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('admin');

$unreadNotif = getUnreadNotifCount($pdo, $_SESSION['user_id']);

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback — NBSC Admin</title>
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

    .page-wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }

    .page-header { margin-bottom: 24px; }
    .page-header h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; color: #111827; }
    .page-header p  { color: #6b7280; font-size: 13.5px; margin: 0; }

    .privacy-notice {
      background: #eff6ff; border: 1px solid #bfdbfe;
      border-radius: 12px; padding: 13px 18px;
      font-size: 13px; color: #1e40af;
      display: flex; align-items: flex-start; gap: 10px;
      margin-bottom: 20px;
    }
    .privacy-notice a { color: #1d4ed8; font-weight: 600; }

    .filter-bar {
      background: #fff; border-radius: 12px;
      border: 1px solid #e5e7eb; padding: 14px 18px;
      display: flex; align-items: center; gap: 12px;
      margin-bottom: 20px; flex-wrap: wrap;
    }
    .filter-bar label { font-size: 12px; font-weight: 600; color: #6b7280; white-space: nowrap; }
    .filter-select {
      border: 1.5px solid #e5e7eb; border-radius: 8px;
      padding: 7px 12px; font-size: 13px; font-family: 'Inter', sans-serif;
      color: #374151; background: #fafafa; cursor: pointer;
      outline: none; transition: border-color 0.15s;
    }
    .filter-select:focus { border-color: #1e40af; }

    .btn-filter {
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border: none; border-radius: 8px;
      padding: 8px 18px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      transition: opacity 0.15s;
    }
    .btn-filter:hover { opacity: 0.88; }

    .btn-reset {
      background: #f3f4f6; color: #374151;
      border: none; border-radius: 8px;
      padding: 8px 16px; font-size: 13px; font-weight: 600;
      cursor: pointer; font-family: 'Inter', sans-serif;
      text-decoration: none; transition: background 0.15s;
    }
    .btn-reset:hover { background: #e5e7eb; color: #374151; }

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
    .time-cell  { color: #9ca3af; font-size: 12px; white-space: nowrap; }
    .id-cell    { color: #9ca3af; font-size: 12px; }
    .enc-tag {
      font-family: 'DM Mono', monospace; font-size: 11.5px;
      background: #f3f4f6; padding: 3px 9px;
      border-radius: 5px; color: #6b7280;
    }
    .empty-row td { text-align: center; padding: 40px; color: #9ca3af; }

    @media (max-width: 640px) {
      .user-chip .user-name { display: none; }
      .filter-bar { flex-direction: column; align-items: flex-start; }
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
  <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="menu-link active">
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
    <h1>Feedback</h1>
    <p>Admin view — metadata only. Feedback content is encrypted and accessible only through authorized review requests.</p>
  </div>

  <div class="privacy-notice">
    🔒
    <div>
      <strong>Data Privacy Mode:</strong> Feedback messages are encrypted. To authorize a manager to read specific feedback, go to
      <a href="<?= BASE_URL ?>/app/admin/review-requests.php">Review Requests</a>.
    </div>
  </div>

  <!-- Filter -->
  <div class="filter-bar">
    <label>Filter by:</label>
    <select class="filter-select" id="filterPriority" onchange="applyFilter()">
      <option value="">All Priorities</option>
      <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
        <option value="<?= $p ?>"><?= $p ?></option>
      <?php endforeach; ?>
    </select>
    <select class="filter-select" id="filterCategory" onchange="applyFilter()">
      <option value="">All Categories</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= $cat ?>"><?= categoryLabel($cat) ?></option>
      <?php endforeach; ?>
    </select>
    <span id="resultCount" style="font-size:12px;color:#6b7280;"></span>
  </div>
       
  <div class="table-card">
    <div class="table-card-header">
     <span class="table-card-title" id="tableTitle">All Feedback</span>
      <span style="font-size:12px;color:#6b7280;"><?= count($feedbacks) ?> entries</span>
    </div>
    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Category</th>
            <th>Priority</th>
            <th>Submitted</th>
            <th>Content</th>
          </tr>
        </thead>
        <tbody id="feedbackBody">
          <?php if (empty($feedbacks)): ?>
            <tr class="empty-row"><td colspan="5">No feedback found.</td></tr>
          <?php else: foreach ($feedbacks as $fb): ?>
             <tr data-priority="<?= $fb['priority'] ?>" data-category="<?= $fb['category'] ?>">
            <td class="id-cell"><?= $fb['feedback_id'] ?></td>
            <td><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></td>
            <td><?= priorityBadge($fb['priority']) ?></td>
            <td class="time-cell"><?= timeAgo($fb['submitted_at']) ?></td>
            <td><span class="enc-tag">🔒 Encrypted</span></td>
          </tr>
          <?php endforeach; endif; ?>
     <tbody id="feedbackBody">
      </table>
    </div>

      <!-- Pagination -->
    <div class="pagination-wrap" id="paginationWrap" style="display:none;">
      <span class="pagination-info" id="paginationInfo"></span>
      <div class="pagination-btns" id="pageButtons"></div>
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

  // ── Filter + Pagination ──
  const ROWS_PER_PAGE = 10;
  let currentPage    = 1;
  let filteredRows   = [];

  function applyFilter() {
    const priority = document.getElementById('filterPriority').value;
    const category = document.getElementById('filterCategory').value;
    const allRows  = Array.from(document.querySelectorAll('#feedbackBody tr[data-priority]'));

    filteredRows = allRows.filter(row => {
      const matchPri = !priority || row.dataset.priority === priority;
      const matchCat = !category || row.dataset.category === category;
      return matchPri && matchCat;
    });

    // Update title
    let title = 'All Feedback';
    if (priority && category)  title = priority + ' · ' + category.charAt(0).toUpperCase() + category.slice(1);
    else if (priority)          title = priority + ' Priority Feedback';
    else if (category)          title = category.charAt(0).toUpperCase() + category.slice(1) + ' Feedback';
    document.getElementById('tableTitle').textContent = title;

    currentPage = 1;
    renderPage();
  }

  function renderPage() {
    const allRows = Array.from(document.querySelectorAll('#feedbackBody tr[data-priority]'));

    // Hide all first
    allRows.forEach(r => r.style.display = 'none');

    // Remove old empty state
    const oldEmpty = document.getElementById('emptyFilterRow');
    if (oldEmpty) oldEmpty.remove();

    if (filteredRows.length === 0) {
      const emptyRow = document.createElement('tr');
      emptyRow.id        = 'emptyFilterRow';
      emptyRow.className = 'empty-row';
      emptyRow.innerHTML = '<td colspan="5">No feedback matches this filter.</td>';
      document.getElementById('feedbackBody').appendChild(emptyRow);
      document.getElementById('paginationWrap').style.display = 'none';
      document.getElementById('resultCount').textContent = '0 results';
      return;
    }

    // Slice for current page
    const start  = (currentPage - 1) * ROWS_PER_PAGE;
    const end    = start + ROWS_PER_PAGE;
    const pageRows = filteredRows.slice(start, end);
    pageRows.forEach(r => r.style.display = '');

    // Update result count
    document.getElementById('resultCount').textContent =
      'Showing ' + (start + 1) + '–' + Math.min(end, filteredRows.length) + ' of ' + filteredRows.length + ' results';

    // Render pagination
    renderPagination();
  }

  function renderPagination() {
    const totalPages = Math.ceil(filteredRows.length / ROWS_PER_PAGE);
    const wrap       = document.getElementById('paginationWrap');
    const btns       = document.getElementById('pageButtons');

    if (totalPages <= 1) {
      wrap.style.display = 'none';
      return;
    }

    wrap.style.display = 'flex';
    btns.innerHTML     = '';

    // Prev
    const prev = document.createElement('button');
    prev.className = 'page-btn';
    prev.textContent = '←';
    prev.disabled  = currentPage === 1;
    prev.onclick   = () => { currentPage--; renderPage(); };
    btns.appendChild(prev);

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
      // Show first, last, current ±1, and ellipsis
      if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
        const btn = document.createElement('button');
        btn.className = 'page-btn' + (i === currentPage ? ' active' : '');
        btn.textContent = i;
        btn.onclick = (function(page) {
          return function() { currentPage = page; renderPage(); };
        })(i);
        btns.appendChild(btn);
      } else if (i === currentPage - 2 || i === currentPage + 2) {
        const dots = document.createElement('span');
        dots.textContent = '…';
        dots.style.cssText = 'color:#9ca3af;font-size:13px;padding:0 4px;';
        btns.appendChild(dots);
      }
    }

    // Next
    const next = document.createElement('button');
    next.className = 'page-btn';
    next.textContent = '→';
    next.disabled  = currentPage === totalPages;
    next.onclick   = () => { currentPage++; renderPage(); };
    btns.appendChild(next);

    // Info
    document.getElementById('paginationInfo').textContent =
      'Page ' + currentPage + ' of ' + totalPages;
  }

  // Init on load
  applyFilter();

</script>
</body>
</html>