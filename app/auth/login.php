<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

// Redirect if already logged in
if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'student': redirect(BASE_URL . '/app/user/index.php'); break;
        case 'admin':   redirect(BASE_URL . '/app/admin/dashboard.php'); break;
        case 'staff':   redirect(BASE_URL . '/app/manager/dashboard.php'); break;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!isNbscEmail($email)) {
        $error = 'Only @nbsc.edu.ph email addresses are allowed.';
    } else {
        // No role filter — let the DB return whoever matches the email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, trim($user['password']))) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['department'] = $user['department'];

            logActivity($pdo, 'LOGIN', $user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['role'] . ') logged in', $user['user_id']);

            switch ($user['role']) {
                case 'student': redirect(BASE_URL . '/app/user/index.php'); break;
                case 'admin':   redirect(BASE_URL . '/app/admin/dashboard.php'); break;
                case 'staff':   redirect(BASE_URL . '/app/manager/dashboard.php'); break;
                default:        $error = 'Unauthorized role.';
            }
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
  <title>Login — NBSC Feedback System</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    body {
      background: #f0f2f5;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      background: #fff;
      border-radius: 18px;
      padding: 40px 40px 32px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.09);
    }

    .brand-icon {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, #1a56db, #7e3af2);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 14px;
      font-size: 26px;
    }

    .form-label {
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 6px;
    }

    .form-control {
      border-radius: 9px;
      font-size: 13.5px;
      padding: 10px 14px;
      border: 1.5px solid #e5e7eb;
    }

    .form-control:focus {
      border-color: #1a56db;
      box-shadow: 0 0 0 3px rgba(26,86,219,0.10);
    }

    .btn-login {
      width: 100%;
      padding: 12px;
      background: linear-gradient(135deg, #1a56db, #7e3af2);
      color: #fff;
      border: none;
      border-radius: 11px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: opacity 0.2s;
    }

    .btn-login:hover { opacity: 0.90; }

    .eye-btn {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      font-size: 16px;
      color: #9ca3af;
      padding: 0;
      line-height: 1;
    }
  </style>
</head>
<body>
  <div style="width:100%;max-width:420px;padding:16px;">

    <!-- Brand -->
    <div style="text-align:center;margin-bottom:28px;">
      <div class="brand-icon">💬</div>
      <h4 style="font-weight:700;margin-bottom:4px;font-size:20px;">NBSC Feedback System</h4>
      <p style="color:#6b7280;font-size:13px;margin:0;">Anonymous · Safe · Heard</p>
    </div>

    <div class="login-card">
      <h5 style="font-weight:700;font-size:16px;margin-bottom:4px;">Welcome back</h5>
      <p style="color:#6b7280;font-size:12.5px;margin-bottom:22px;">Sign in with your NBSC account to continue</p>

      <?php if ($error): ?>
        <div class="alert alert-danger" style="font-size:13px;border-radius:9px;">
          <?= sanitize($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">
            School Email
            <span style="color:#9ca3af;font-size:11px;">(@nbsc.edu.ph only)</span>
          </label>
          <input
            type="email"
            name="email"
            class="form-control"
            placeholder="yourname@nbsc.edu.ph"
            value="<?= sanitize($_POST['email'] ?? '') ?>"
            required
            autofocus
          >
        </div>

        <div class="mb-4">
          <label class="form-label">Password</label>
          <div style="position:relative;">
            <input
              type="password"
              name="password"
              id="passwordInput"
              class="form-control"
              placeholder="••••••••"
              required
              style="padding-right:42px;"
            >
            <button type="button" class="eye-btn" onclick="togglePassword()" title="Show/hide password">👁</button>
          </div>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:12px;color:#9ca3af;">
        🔒 Your identity is protected — feedback is fully anonymous
      </p>
    </div>

  </div>

  <script>
    function togglePassword() {
      const input = document.getElementById('passwordInput');
      input.type = input.type === 'password' ? 'text' : 'password';
    }
  </script>
</body>
</html>