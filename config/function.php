<?php
// ─── Encryption Key ───────────────────────────────────────────────
define('ENCRYPT_KEY', 'nbsc_secret_key_2024');

// ─── Redirect ─────────────────────────────────────────────────────
function redirect($url) {
    header("Location: $url");
    exit;
}

// ─── Auth ─────────────────────────────────────────────────────────
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireRole($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        redirect(BASE_URL . '/app/auth/login.php');
    }
}

function requireAnyRole(array $roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        redirect(BASE_URL . '/app/auth/login.php');
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

// ─── Email Validation (only @nbsc.edu.ph) ─────────────────────────
function isNbscEmail($email) {
    return preg_match('/@nbsc\.edu\.ph$/i', trim($email));
}

// ─── Sanitize ─────────────────────────────────────────────────────
function sanitize($val) {
    return htmlspecialchars(trim((string)$val), ENT_QUOTES, 'UTF-8');
}

// ─── Encryption / Decryption (AES-256-CBC via OpenSSL) ────────────
function encryptMessage($plaintext) {
    $iv  = openssl_random_pseudo_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', ENCRYPT_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $enc);
}

function decryptMessage($ciphertext) {
    $raw = base64_decode($ciphertext);
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', ENCRYPT_KEY, OPENSSL_RAW_DATA, $iv);
}

function hashMessage($plaintext) {
    return hash('sha256', $plaintext);
}

function verifyIntegrity($plaintext, $storedHash) {
    return hash('sha256', $plaintext) === $storedHash;
}

// ─── Time Constraint (off-hours check) ────────────────────────────
// Allowed: every day, 8:00 AM – 5:00 PM (Philippine Time)
function isWithinOfficeHours() {
    $tz   = new DateTimeZone('Asia/Manila');
    $now  = new DateTime('now', $tz);
    $hour = (int)$now->format('H');
    $min  = (int)$now->format('i');
    $time = $hour * 60 + $min;

    return ($time >= 8 * 60) && ($time < 17 * 60);
}

function offHoursMessage() {
    return 'Feedback reviews are only permitted every day, 8:00 AM – 5:00 PM (Philippine Time). Please try again during office hours.';
}

// ─── Activity Logger ──────────────────────────────────────────────
function logActivity($pdo, $action, $description, $userId = null) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if ($ip === '::1') $ip = '127.0.0.1';
    $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?,?,?,?)")
        ->execute([$userId, $action, $description, $ip]);
}

// ─── Anonymous ID helpers ─────────────────────────────────────────
function generateAnonymousId() {
    return 'anon_' . substr(md5(uniqid(rand(), true)), 0, 12);
}

function encryptUserId($userId) {
    return 'encrypted_' . $userId . '_' . substr(md5($userId . 'nbsc_salt'), 0, 8);
}

// ─── Time ago ─────────────────────────────────────────────────────
function timeAgo($datetime) {
    $now  = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $then = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 0)         return 'Just now';
    if ($diff < 60)        return $diff . ' second' . ($diff != 1 ? 's' : '') . ' ago';
    if ($diff < 3600)      { $m  = floor($diff / 60);       return $m  . ' minute' . ($m  != 1 ? 's' : '') . ' ago'; }
    if ($diff < 86400)     { $h  = floor($diff / 3600);     return $h  . ' hour'   . ($h  != 1 ? 's' : '') . ' ago'; }
    if ($diff < 604800)    { $d  = floor($diff / 86400);    return $d  . ' day'    . ($d  != 1 ? 's' : '') . ' ago'; }
    if ($diff < 2592000)   { $w  = floor($diff / 604800);   return $w  . ' week'   . ($w  != 1 ? 's' : '') . ' ago'; }
    if ($diff < 31536000)  { $mo = floor($diff / 2592000);  return $mo . ' month'  . ($mo != 1 ? 's' : '') . ' ago'; }
    if ($diff < 315360000) { $y  = floor($diff / 31536000); return $y  . ' year'   . ($y  != 1 ? 's' : '') . ' ago'; }
    $dec = floor($diff / 315360000);
    return $dec . ' decade' . ($dec != 1 ? 's' : '') . ' ago';
}

// ─── Badges ───────────────────────────────────────────────────────
function priorityBadge($p) {
    $map = ['Low'=>'badge-low','Medium'=>'badge-medium','High'=>'badge-high','Urgent'=>'badge-urgent'];
    return "<span class='badge ".($map[$p]??'badge-low')."'>$p</span>";
}

function roleBadge($r) {
    $map = ['admin'=>'badge-admin','staff'=>'badge-staff','student'=>'badge-student'];
    return "<span class='badge ".($map[$r]??'badge-student')."'>".ucfirst($r)."</span>";
}

function requestStatusBadge($s) {
    $map = ['pending'=>'badge-pending','approved'=>'badge-resolved','rejected'=>'badge-urgent'];
    return "<span class='badge ".($map[$s]??'badge-pending')."'>".ucfirst($s)."</span>";
}

// ─── Category helpers ─────────────────────────────────────────────
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

// ─── Notifications ────────────────────────────────────────────────
function getUnreadNotifCount($pdo, $userId) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $s->execute([$userId]);
    return $s->fetchColumn();
}