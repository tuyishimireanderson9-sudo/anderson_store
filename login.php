<?php
require __DIR__ . '/config.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (verify_admin_login($username, $password)) {
        login_admin();
        header('Location: dashboard.php');
        exit;
    }

    $message = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | anderson_store</title>
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
    .meta { margin-top:16px; text-align:center; font-size:.85rem; color:var(--muted); }
    .meta a { color:var(--text); font-weight:600; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">anderson_store</div>
      <h1>Admin login</h1>
      <p>Manage products, stock, and updates from one place.</p>

      <?php if ($message !== ''): ?>
        <div class="error"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="text" name="username" placeholder="Username" autocomplete="off" required />
        <input type="password" name="password" placeholder="Password" autocomplete="new-password" required />
        <button type="submit">Login</button>
      </form>

      <div class="meta">
        <a href="index.php">Back to store</a>
      </div>
    </div>
  </div>
</body>
</html>
