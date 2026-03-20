<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

// Already logged in as student
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    redirect(BASE_URL . '/app/user/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!isNbscEmail($email)) {
        $error = 'Only @nbsc.edu.ph email addresses are allowed.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='student' AND status='active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, trim($user['password']))) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['department'] = $user['department'];

            logActivity($pdo, 'LOGIN', $user['first_name'] . ' ' . $user['last_name'] . ' (student) logged in', $user['user_id']);
            redirect(BASE_URL . '/app/user/index.php');
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Login — NBSC Feedback</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body style="background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh;">
  <div style="width:100%;max-width:420px;padding:16px;">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:28px;">
      <div style="width:56px;height:56px;background:linear-gradient(135deg,#1a56db,#7e3af2);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:24px;">💬</div>
      <h4 style="font-weight:700;margin-bottom:4px;font-size:20px;">NBSC Feedback</h4>
      <p style="color:#6b7280;font-size:13px;margin:0;">Anonymous · Safe · Heard</p>
    </div>

    <div class="card" style="border-radius:16px;padding:32px 36px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
      <h5 style="font-weight:700;margin-bottom:4px;font-size:16px;">Student Login</h5>
      <p style="color:#6b7280;font-size:12.5px;margin-bottom:20px;">Sign in with your NBSC account to submit feedback</p>

      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:13px;border-radius:8px;"><?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label" style="font-size:13px;font-weight:500;">
            School Email <span style="color:#9ca3af;font-size:11px;">(@nbsc.edu.ph only)</span>
          </label>
          <input type="email" name="email" class="form-control" placeholder="yourname@nbsc.edu.ph" required autofocus
            style="border-radius:8px;font-size:13.5px;">
        </div>
        <div class="mb-4">
          <label class="form-label" style="font-size:13px;font-weight:500;">Password</label>
          <input type="password" name="password" class="form-control" required style="border-radius:8px;font-size:13.5px;">
        </div>
        <button type="submit" style="width:100%;padding:11px;background:linear-gradient(135deg,#1a56db,#7e3af2);color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">
          Login & Submit Feedback
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:12px;color:#9ca3af;">
        🔒 Your identity is protected — feedback is fully anonymous
      </p>
    </div>
  </div>
</body>
</html>