<?php
function renderSidebar($role, $active = '') {
    $base = BASE_URL;
    $user = currentUser();
    $initial  = strtoupper(substr($user['first_name'] ?? 'U', 0, 1));
    $fullname = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');

    $nav = '';

    if ($role === 'admin') {
        $nav .= navItem("$base/app/admin/dashboard.php",    svgIcon('grid'),           'Dashboard',     $active);
        $nav .= '<span class="nav-section-label">Management</span>';
        $nav .= navItem("$base/app/admin/feedback.php",     svgIcon('message-square'), 'Feedback',      $active);
        $nav .= navItem("$base/app/admin/users.php",        svgIcon('users'),          'Users',         $active);
        $nav .= navItem("$base/app/admin/review-requests.php",svgIcon('shield'),         'Review Requests',$active);
        $nav .= '<span class="nav-section-label">Reports</span>';
        $nav .= navItem("$base/app/admin/activity-logs.php",svgIcon('file-text'),      'Activity Logs', $active);
        $nav .= navItem("$base/app/admin/notifications.php",svgIcon('bell'),           'Notifications', $active);
    } elseif ($role === 'manager') {
        $nav .= navItem("$base/app/manager/dashboard.php",    svgIcon('grid'),           'Dashboard',    $active);
        $nav .= '<span class="nav-section-label">Feedback</span>';
        $nav .= navItem("$base/app/manager/feedback.php",     svgIcon('message-square'), 'All Feedback', $active);
        $nav .= navItem("$base/app/manager/notifications.php",svgIcon('bell'),           'Notifications',$active);
    }

    echo '
    <div class="layout">
      <aside class="sidebar">
        <div class="sidebar-brand">
          <div class="brand-name">NBSC Feedback</div>
          <div class="brand-sub">Anonymous Feedback System</div>
        </div>
        <nav class="sidebar-nav">' . $nav . '</nav>
        <div class="sidebar-footer">
          <div class="sidebar-user">
            <div class="sidebar-avatar">' . $initial . '</div>
            <div class="sidebar-user-info">
              <div class="name">' . sanitize($fullname) . '</div>
              <div class="role">' . ucfirst($role) . '</div>
            </div>
          </div>
          <a href="' . $base . '/app/auth/logout.php" class="btn btn-outline" style="width:100%;justify-content:center;">
            ' . svgIcon('log-out') . ' Logout
          </a>
        </div>
      </aside>
      <div class="main">';
}

function renderSidebarClose() {
    echo '</div></div>';
}

function navItem($href, $icon, $label, $active) {
    $cls = ($active === $label) ? 'nav-item active' : 'nav-item';
    return "<a href='$href' class='$cls'>$icon $label</a>";
}

function svgIcon($name) {
    $icons = [
        'grid'           => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>',
        'message-square' => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>',
        'chat'           => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"/></svg>',
        'users'          => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'warning'        => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        'file-text'      => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
        'bell'           => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>',
        'log-out'        => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>',
        'plus'           => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
        'flag'           => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 2H21l-3 6 3 6h-8.5l-1-2H5a2 2 0 00-2 2zm9-13.5V9"/></svg>',
        'shield'         => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
    ];
    return $icons[$name] ?? '';
}