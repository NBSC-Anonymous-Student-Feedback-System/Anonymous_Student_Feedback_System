<?php
function redirect($url) {
    header("Location: $url");
    exit;
}

function requireRole($role) {
    if (!isset($_SESSION)) session_start();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        if ($role === 'student') {
            redirect(BASE_URL . '/app/auth/student-login.php');
        } else {
            redirect(BASE_URL . '/app/auth/admin-login.php');
        }
    }
}

function currentUser() {
    return [
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name'  => $_SESSION['last_name']  ?? '',
        'role'       => $_SESSION['role']        ?? '',
        'email'      => $_SESSION['email']       ?? '',
    ];
}

function sanitize($val) {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function isNbscEmail($email) {
    return preg_match('/@nbsc\.edu\.ph$/i', trim($email));
}

function logActivity($pdo, $action, $description, $userId = null) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $_SERVER['REMOTE_ADDR']
       ?? '0.0.0.0';
    if ($ip === '::1') $ip = '127.0.0.1';

    if ($userId !== null) {
        $check = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $check->execute([$userId]);
        if (!$check->fetchColumn()) {
            $userId = null;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $action, $description, $ip]);
}

function generateAnonymousId() {
    return 'anon_' . substr(md5(uniqid(rand(), true)), 0, 12);
}

function encryptUserId($userId) {
    return 'encrypted_' . $userId . '_' . substr(md5($userId . 'nbsc_salt'), 0, 8);
}

function encryptMessage($plaintext) {
    $iv  = openssl_random_pseudo_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', ENCRYPT_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function decryptMessage($ciphertext) {
    if (empty($ciphertext)) return '';
    $raw = base64_decode($ciphertext);
    if (strlen($raw) < 16) return '';
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', ENCRYPT_KEY, OPENSSL_RAW_DATA, $iv) ?: '';
}

function hashMessage($plaintext) {
    return hash('sha256', $plaintext);
}

function verifyIntegrity($plaintext, $storedHash) {
    return hash('sha256', $plaintext) === $storedHash;
}

function isWithinOfficeHours() {
    $tz   = new DateTimeZone('Asia/Manila');
    $now  = new DateTime('now', $tz);
    $day  = (int)$now->format('N');
    $time = (int)$now->format('H') * 60 + (int)$now->format('i');
    return ($day >= 1 && $day <= 5) && ($time >= 480) && ($time < 1020);
}

function offHoursMessage() {
    return 'Feedback reviews are only permitted Monday–Friday, 8:00 AM – 5:00 PM (Philippine Time). Please try again during office hours.';
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'Just now';
    if ($diff < 3600)    { $m = floor($diff/60);    return $m.' minute'.($m!=1?'s':'').' ago'; }
    if ($diff < 86400)   { $h = floor($diff/3600);  return $h.' hour'.($h!=1?'s':'').' ago'; }
    if ($diff < 604800)  { $d = floor($diff/86400); return $d.' day'.($d!=1?'s':'').' ago'; }
    if ($diff < 2592000) { $w = floor($diff/604800);return $w.' week'.($w!=1?'s':'').' ago'; }
    return date('M j, Y', strtotime($datetime));
}

function priorityBadge($p) {
    $map = ['Low'=>'badge-low','Medium'=>'badge-medium','High'=>'badge-high','Urgent'=>'badge-urgent'];
    $cls = $map[$p] ?? 'badge-low';
    return "<span class='badge $cls'>$p</span>";
}

function roleBadge($r) {
    $map = ['admin'=>'badge-admin','manager'=>'badge-staff','student'=>'badge-student'];
    $cls = $map[$r] ?? 'badge-student';
    return "<span class='badge $cls'>".ucfirst($r)."</span>";
}

function requestStatusBadge($s) {
    $map = ['pending'=>'badge-pending','approved'=>'badge-resolved','rejected'=>'badge-urgent'];
    $cls = $map[$s] ?? 'badge-pending';
    return "<span class='badge $cls'>".ucfirst($s)."</span>";
}

function categoryLabel($c) {
    return ucfirst(str_replace('_', ' ', $c));
}

function categoryIcon($cat) {
    $icons = [
        'academic'       => '📚',
        'facilities'     => '🏫',
        'services'       => '🛎️',
        'faculty'        => '👨‍🏫',
        'administration' => '🏛️',
        'suggestion'     => '💡',
        'complaint'      => '⚠️',
        'general'        => '💬',
        'other'          => '📝',
    ];
    return $icons[$cat] ?? '💬';
}

function getUnreadNotifCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}