<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';
requireRole('staff');
if (isset($_POST['mark_all_read'])) { $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$_SESSION['user_id']]); }
$notifs=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC"); $notifs->execute([$_SESSION['user_id']]); $notifs=$notifs->fetchAll();
renderHeader('Notifications'); renderSidebar('staff','Notifications');
?>
<div class="topbar"><span class="topbar-title">Notifications</span><div class="topbar-actions"><form method="POST"><button type="submit" name="mark_all_read" class="btn btn-outline btn-sm">Mark All Read</button></form></div></div>
<div class="content"><div class="page-header"><h1>Notifications</h1></div>
<div class="card">
<?php if (empty($notifs)): ?><div class="empty-state"><?= svgIcon('bell') ?><p>No notifications.</p></div>
<?php else: foreach ($notifs as $n): ?>
<div style="padding:14px 20px;border-bottom:1px solid #f3f4f6;display:flex;gap:12px;align-items:flex-start;background:<?= $n['is_read']?'#fff':'var(--primary-light)' ?>;">
<div style="width:8px;height:8px;border-radius:50%;background:<?= $n['is_read']?'#d1d5db':'var(--primary)' ?>;margin-top:6px;flex-shrink:0;"></div>
<div><div style="font-weight:600;font-size:13.5px;"><?= sanitize($n['title']) ?></div><div style="font-size:13px;color:var(--text-muted);margin-top:2px;"><?= sanitize($n['message']) ?></div><div style="font-size:11.5px;color:var(--text-light);margin-top:4px;"><?= timeAgo($n['created_at']) ?></div></div></div>
<?php endforeach; endif; ?></div></div>
<?php renderSidebarClose(); renderFooter(); ?>