<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // Enforce @nbsc.edu.ph only
    if (!isNbscEmail($email)) {
        $error = 'Only @nbsc.edu.ph email addresses are allowed.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role IN ('admin','manager') AND status='active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, trim($user['password']))) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['email']      = $user['email'];

            logActivity($pdo, 'LOGIN', $user['first_name'] . ' ' . $user['last_name'] . ' logged in', $user['user_id']);

            if ($user['role'] === 'admin') redirect(BASE_URL . '/app/admin/dashboard.php');
            else                           redirect(BASE_URL . '/app/manager/dashboard.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Login — NBSC</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body style="background:#f3f4f6;display:flex;align-items:center;justify-content:center;min-height:100vh;">
  <div class="card" style="width:100%;max-width:400px;padding:36px 40px;">
    <div style="text-align:center;margin-bottom:24px;">
      <div style="width:52px;height:52px;background:linear-gradient(135deg,#1a56db,#7e3af2);border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;font-size:22px;">🔐</div>
      <h4 style="font-weight:700;margin-bottom:4px;">NBSC Feedback System</h4>
      <p style="color:#6b7280;font-size:13px;margin:0;">Admin / Manager Login</p>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger" style="font-size:13px;"><?= sanitize($error) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="mb-3">
        <label class="form-label" style="font-size:13px;font-weight:500;">Email <span style="color:#6b7280;font-size:11px;">(@nbsc.edu.ph only)</span></label>
        <input type="email" name="email" class="form-control" placeholder="yourname@nbsc.edu.ph" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label" style="font-size:13px;font-weight:500;">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <p style="text-align:center;margin-top:20px;font-size:12px;color:#9ca3af;">
      🔒 Restricted to authorized NBSC personnel only
    </p>
  </div>
</body>
</html>