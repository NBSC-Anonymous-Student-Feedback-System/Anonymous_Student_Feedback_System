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
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    *, *::before, *::after { box-sizing: border-box; }

    body {
      background: #f0f2f5;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      margin: 0;
      font-family: 'Inter', sans-serif;
    }

    .login-wrap {
      width: 100%;
      max-width: 420px;
      padding: 16px;
    }

    /* Brand */
    .brand-header {
      text-align: center;
      margin-bottom: 28px;
    }
   .brand-logo {
  width: 80px; height: 80px;
  border-radius: 22px;
  overflow: hidden;
  margin: 0 auto 14px;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 16px rgba(30,64,175,0.25);
}
.brand-logo img {
  width: 80px; height: 80px;
  object-fit: cover;
  border-radius: 22px;
  mix-blend-mode: multiply;
}
    .brand-title {
      font-size: 20px; font-weight: 700;
      color: #111827; margin: 0 0 4px;
    }
    .brand-sub {
      font-size: 13px; color: #6b7280; margin: 0;
    }

    /* Card */
    .login-card {
      background: #fff;
      border-radius: 18px;
      padding: 36px 40px 32px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.09);
    }
    .login-card-title {
      font-size: 16px; font-weight: 700;
      color: #111827; margin: 0 0 4px;
    }
    .login-card-sub {
      font-size: 12.5px; color: #6b7280;
      margin: 0 0 22px;
    }

    /* Form */
    .form-label {
      font-size: 13px; font-weight: 500;
      color: #374151; margin-bottom: 6px;
    }
    .form-label span {
      color: #9ca3af; font-size: 11px; font-weight: 400;
    }
    .form-control {
      border-radius: 9px;
      font-size: 13.5px;
      padding: 10px 14px;
      border: 1.5px solid #e5e7eb;
      font-family: 'Inter', sans-serif;
      transition: border-color 0.15s, box-shadow 0.15s;
      width: 100%;
    }
    .form-control:focus {
      outline: none;
      border-color: #1e40af;
      box-shadow: 0 0 0 3px rgba(30,64,175,0.10);
    }

    .pw-wrap { position: relative; }
    .eye-btn {
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      font-size: 16px; color: #9ca3af; padding: 0; line-height: 1;
    }
    .eye-btn:hover { color: #6b7280; }

    .btn-login {
      width: 100%; padding: 12px;
      background: linear-gradient(135deg, #1e40af, #0ea5e9);
      color: #fff; border: none;
      border-radius: 11px;
      font-size: 14px; font-weight: 700;
      cursor: pointer;
      font-family: 'Inter', sans-serif;
      transition: opacity 0.2s;
      margin-top: 4px;
    }
    .btn-login:hover { opacity: 0.90; }

    .login-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 12px;
      color: #9ca3af;
    }

    .alert-error {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      border-radius: 9px;
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 18px;
    }

    @media (max-width: 480px) {
      .login-card { padding: 28px 24px; }
    }
  </style>
</head>
<body>
  <div class="login-wrap">

    <!-- Brand Header -->
    <div class="brand-header">
      <div class="brand-logo">
        <img src="<?= BASE_URL ?>/media/logoweb.svg" alt="NBSC Logo">
      </div>
      <h4 class="brand-title">NBSC Feedback System</h4>
      <p class="brand-sub">Anonymous · Safe · Heard</p>
    </div>

    <!-- Login Card -->
    <div class="login-card">
      <p class="login-card-title">Welcome back</p>
      <p class="login-card-sub">Sign in with your NBSC account to continue</p>

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= sanitize($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="form-label">
            School Email <span>(@nbsc.edu.ph only)</span>
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
          <div class="pw-wrap">
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

      <div class="login-footer">
        🔒 Your identity is protected — feedback is fully anonymous
      </div>
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