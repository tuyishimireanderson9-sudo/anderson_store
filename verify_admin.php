<?php
require __DIR__ . '/config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['admin_user']) || $_SESSION['admin_user'] !== ADMIN_USERNAME) {
    header('Location: login.php');
    exit;
}

if (!empty($_SESSION['admin_mfa_verified'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['admin_mfa_code'])) {
    begin_admin_mfa();
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['mfa_code'] ?? '');

    if (verify_mfa_code($code)) {
        complete_admin_mfa();
        header('Location: dashboard.php');
        exit;
    }

    $message = 'Invalid security code. Please use the code shown below.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin MFA | anderson_store</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet" />
  <style>
    :root { --bg:#f4efe9; --panel:#fff; --line:#e5dfd8; --text:#171717; --muted:#5c5854; --accent:#111; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:'Inter',sans-serif; background:linear-gradient(180deg,#f4efe9,#fbfaf7); color:var(--text); }
    .wrap { min-height:100vh; display:grid; place-items:center; padding:24px; }
    .card { width:min(500px,100%); background:var(--panel); border:1px solid var(--line); border-radius:28px; box-shadow:0 18px 50px rgba(17,17,17,.05); padding:32px; }
    .brand { font-size:1.2rem; font-weight:800; letter-spacing:.18em; margin-bottom:12px; display:block; text-transform:lowercase; }
    h1 { margin:0 0 8px; font-size:2.3rem; letter-spacing:-.05em; }
    p { color:var(--muted); margin:0 0 18px; }
    form { display:grid; gap:14px; }
    input { width:100%; padding:14px 16px; border-radius:14px; border:1px solid var(--line); font:inherit; }
    button { border:none; border-radius:999px; background:var(--accent); color:#fff; min-height:50px; font-weight:700; cursor:pointer; }
    .error { margin-bottom:14px; padding:12px 14px; border-radius:12px; background:#fff2f2; border:1px solid #f1c5c7; color:#8d2a2a; }
    .code-box { margin:14px 0 18px; padding:14px; border:1px solid var(--line); border-radius:14px; background:#faf8f5; font-size:1.8rem; letter-spacing:.35rem; text-align:center; font-weight:800; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">anderson_store</div>
      <h1>Admin verification</h1>
      <p>A 6-digit verification code has been sent to <strong><?= htmlspecialchars(ADMIN_EMAIL) ?></strong>. Enter it below.</p>

      <?php if (!empty($_SESSION['admin_mfa_code'])): ?>
        <div class="code-box"><?= htmlspecialchars($_SESSION['admin_mfa_code']) ?></div>
      <?php endif; ?>

      <?php if ($message !== ''): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="text" name="mfa_code" placeholder="Enter 6-digit code" maxlength="6" inputmode="numeric" required />
        <button type="submit">Verify</button>
      </form>
    </div>
  </div>
</body>
</html>
