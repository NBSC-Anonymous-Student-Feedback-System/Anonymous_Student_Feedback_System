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

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = trim($_POST['school_id'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = trim($_POST['password'] ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');
    $role      = 'student'; // Always student — admin/manager created by admin only

    if (!$school_id) {
        $error = 'School ID is required.';
    } elseif (!isNbscEmail($email)) {
        $error = 'Only @nbsc.edu.ph email addresses are allowed.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $chk2 = $pdo->prepare("SELECT user_id FROM users WHERE school_id = ?");
            $chk2->execute([$school_id]);
            if ($chk2->fetch()) {
                $error = 'This School ID is already registered.';
            } else {
                $hash       = password_hash($password, PASSWORD_BCRYPT);
                $first_name = 'User';
                $last_name  = $school_id;
                $department = 'General';

                $pdo->prepare("INSERT INTO users (school_id, first_name, last_name, email, password, role, department, status) VALUES (?,?,?,?,?,?,?,'active')")
                    ->execute([$school_id, $first_name, $last_name, $email, $hash, $role, $department]);

                logActivity($pdo, 'USER_REGISTERED', "New student registered with School ID: $school_id", null);

                $success = 'Account created successfully! You can now sign in.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — NBSC Feedback System</title>
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
      padding: 24px 16px;
    }

    .register-wrap {
      width: 100%;
      max-width: 420px;
    }

    /* ── Brand ── */
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

    /* ── Card ── */
    .register-card {
      background: #fff;
      border-radius: 18px;
      padding: 36px 40px 32px;
      box-shadow: 0 4px 24px rgba(0,0,0,0.09);
    }
    .register-card-title {
      font-size: 16px; font-weight: 700;
      color: #111827; margin: 0 0 4px;
    }
    .register-card-sub {
      font-size: 12.5px; color: #6b7280;
      margin: 0 0 22px;
    }

    /* ── Form ── */
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
      color: #111827;
      background: #fff;
    }
    .form-control:focus {
      outline: none;
      border-color: #1e40af;
      box-shadow: 0 0 0 3px rgba(30,64,175,0.10);
    }

    /* ── Password toggle ── */
    .pw-wrap { position: relative; }
    .eye-btn {
      position: absolute; right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      font-size: 16px; color: #9ca3af; padding: 0; line-height: 1;
    }
    .eye-btn:hover { color: #6b7280; }

    /* ── Submit button ── */
    .btn-register {
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
    .btn-register:hover { opacity: 0.90; }

    /* ── Login link ── */
    .login-link {
      text-align: center;
      margin-top: 16px;
      font-size: 12.5px;
      color: #6b7280;
    }
    .login-link a {
      color: #1e40af; font-weight: 600; text-decoration: none;
    }
    .login-link a:hover { text-decoration: underline; }

    /* ── Footer ── */
    .register-footer {
      text-align: center;
      margin-top: 20px;
      font-size: 12px;
      color: #9ca3af;
    }

    /* ── Alerts ── */
    .alert-error {
      background: #fef2f2;
      border: 1px solid #fca5a5;
      color: #991b1b;
      border-radius: 9px;
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 18px;
    }
    .alert-success {
      background: #f0fdf4;
      border: 1px solid #86efac;
      color: #166534;
      border-radius: 9px;
      padding: 10px 14px;
      font-size: 13px;
      margin-bottom: 18px;
    }
    .alert-success a {
      color: #166534;
      font-weight: 600;
    }

    @media (max-width: 480px) {
      .register-card { padding: 28px 24px; }
    }
  </style>
</head>
<body>
  <div class="register-wrap">

    <!-- ── Brand Header ── -->
    <div class="brand-header">
      <div class="brand-logo">
        <img src="<?= BASE_URL ?>/media/logoweb.svg" alt="NBSC Logo">
      </div>
      <h4 class="brand-title">NBSC Feedback System</h4>
      <p class="brand-sub">Anonymous · Safe · Heard</p>
    </div>

    <!-- ── Register Card ── -->
    <div class="register-card">
      <p class="register-card-title">Create an Account</p>
      <p class="register-card-sub">Register with your NBSC school credentials</p>

      <?php if ($error): ?>
        <div class="alert-error">⚠️ <?= sanitize($error) ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert-success">
          ✅ <?= sanitize($success) ?>
          <br><a href="<?= BASE_URL ?>/app/auth/login.php">Click here to Sign In →</a>
        </div>
      <?php else: ?>

        <form method="POST">

          <!-- School ID -->
          <div class="mb-3">
            <label class="form-label">School ID</label>
            <input
              type="text"
              name="school_id"
              class="form-control"
              placeholder="e.g. 20231671"
              value="<?= sanitize($_POST['school_id'] ?? '') ?>"
              required
              autofocus
            >
          </div>

          <!-- Email -->
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
            >
          </div>

          <!-- Password -->
          <div class="mb-3">
            <label class="form-label">Password <span>(min. 6 characters)</span></label>
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
              <button type="button" class="eye-btn" onclick="togglePassword('passwordInput')" title="Show/hide password">👁</button>
            </div>
          </div>

          <!-- Confirm Password -->
          <div class="mb-4">
            <label class="form-label">Confirm Password</label>
            <div class="pw-wrap">
              <input
                type="password"
                name="confirm_password"
                id="confirmInput"
                class="form-control"
                placeholder="••••••••"
                required
                style="padding-right:42px;"
              >
              <button type="button" class="eye-btn" onclick="togglePassword('confirmInput')" title="Show/hide password">👁</button>
            </div>
          </div>

          <button type="submit" class="btn-register">Create Account</button>

        </form>

      <?php endif; ?>

      <!-- ── Login Link ── -->
      <div class="login-link">
        Already have an account? <a href="<?= BASE_URL ?>/app/auth/login.php">Sign In</a>
      </div>

      <!-- ── Footer ── -->
      <div class="register-footer">
        🔒 Your identity is protected — feedback is fully anonymous
      </div>

    </div>
  </div>

  <script>
    function togglePassword(id) {
      const input = document.getElementById(id);
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    // Live email validation — only allow @nbsc.edu.ph
    document.querySelector('input[name="email"]')?.addEventListener('blur', function() {
      const val = this.value.trim();
      if (val && !val.endsWith('@nbsc.edu.ph')) {
        this.style.borderColor = '#ef4444';
        this.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.10)';
      } else {
        this.style.borderColor = '';
        this.style.boxShadow   = '';
      }
    });

    // Live password match check
    document.getElementById('confirmInput')?.addEventListener('input', function() {
      const pass    = document.getElementById('passwordInput').value;
      const confirm = this.value;
      if (confirm && pass !== confirm) {
        this.style.borderColor = '#ef4444';
        this.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.10)';
      } else {
        this.style.borderColor = confirm ? '#16a34a' : '';
        this.style.boxShadow   = confirm ? '0 0 0 3px rgba(22,163,74,0.10)' : '';
      }
    });
  </script>
</body>
</html>