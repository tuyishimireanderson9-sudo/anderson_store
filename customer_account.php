<?php
require __DIR__ . '/config.php';

$redirect = $_GET['redirect'] ?? 'index.php';
$allowedRedirects = ['index.php', 'checkout.php'];
if (!in_array($redirect, $allowedRedirects, true)) {
    $redirect = 'index.php';
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout_customer'])) {
        logout_customer();
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['register_customer'])) {
        $result = register_customer(
            trim((string) ($_POST['first_name'] ?? '')),
            trim((string) ($_POST['last_name'] ?? '')),
            trim((string) ($_POST['email'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );
        if ($result['success']) {
            header('Location: ' . $redirect);
            exit;
        }
        $message = $result['error'];
    } else {
        $result = verify_customer_login(
            trim((string) ($_POST['email'] ?? '')),
            (string) ($_POST['password'] ?? '')
        );
        if ($result['success']) {
            header('Location: ' . $redirect);
            exit;
        }
        $message = $result['error'];
    }
}

$customer = current_customer();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Customer Account | anderson_store</title>
  <link rel="stylesheet" href="styles.css?v=20261102" />
  <style>
    :root{--account-bg:#f4efe9}
    body{min-height:100vh;background:linear-gradient(135deg,#f4efe9,#fff 58%,#eee8df)}
    .account-shell{min-height:100vh;display:grid;grid-template-columns:minmax(280px,42%) 1fr}
    .account-visual{position:relative;background:#171717;color:#fff;padding:42px;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden}
    .account-visual:after{content:"";position:absolute;width:420px;height:420px;border:1px solid rgba(255,255,255,.13);border-radius:50%;right:-230px;bottom:-180px;box-shadow:0 0 0 60px rgba(255,255,255,.025),0 0 0 120px rgba(255,255,255,.018)}
    .account-brand{font-weight:900;letter-spacing:.18em;text-transform:lowercase}
    .account-visual-copy{position:relative;z-index:1;max-width:430px}
    .account-visual-copy h1{margin:0;font-size:clamp(3rem,7vw,6rem);line-height:.86;letter-spacing:-.08em}
    .account-visual-copy p{margin-top:22px;color:rgba(255,255,255,.68);line-height:1.7}
    .account-main{display:grid;place-items:center;padding:40px 24px}
    .account-card{width:min(480px,100%)}
    .account-card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
    .account-card-top a{font-size:.86rem;font-weight:700}
    .account-tabs{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:5px;margin-bottom:22px;border-radius:14px;background:#ebe5dc}
    .account-tab{border:0;border-radius:10px;background:transparent;padding:12px;font-weight:800;color:#777167}
    .account-tab.active{background:#fff;color:#171717;box-shadow:0 4px 16px rgba(17,17,17,.06)}
    .account-form{display:none;grid-template-columns:1fr 1fr;gap:12px}
    .account-form.active{display:grid}
    .account-form input{width:100%;grid-column:auto;border:1px solid #ddd5ca;border-radius:12px;background:#fff;padding:14px;color:#171717}
    .account-form input.full,.account-form button{grid-column:1/-1}
    .account-form button{min-height:50px;border:0;border-radius:999px;background:#171717;color:#fff;font-weight:800}
    .account-error{margin-bottom:16px;padding:12px 14px;border-radius:12px;background:#fff1f0;border:1px solid #efc8c3;color:#8b3026;font-size:.88rem}
    .account-profile{text-align:center;border:1px solid #dfd7cc;border-radius:24px;background:rgba(255,255,255,.72);padding:36px}
    .account-avatar{width:72px;height:72px;margin:0 auto 14px;border-radius:50%;display:grid;place-items:center;background:#171717;color:#fff;font-size:1.5rem;font-weight:800;text-transform:uppercase}
    @media(max-width:760px){.account-shell{grid-template-columns:1fr}.account-visual{min-height:220px;padding:24px}.account-visual-copy h1{font-size:3rem}.account-visual-copy p{display:none}.account-main{padding:28px 18px}}
  </style>
</head>
<body>
  <main class="account-shell">
    <section class="account-visual">
      <a class="account-brand" href="index.php">anderson_store</a>
      <div class="account-visual-copy">
        <h1>Your<br />account.</h1>
        <p>Sign in to access secure checkout, save your shipping details, and follow your latest store orders.</p>
      </div>
    </section>
    <section class="account-main">
      <div class="account-card">
        <?php if ($customer): ?>
          <div class="account-profile">
            <div class="account-avatar"><?= htmlspecialchars(substr($customer['first_name'], 0, 1) . substr($customer['last_name'], 0, 1)) ?></div>
            <h2 style="margin:0 0 6px">Hello, <?= htmlspecialchars($customer['first_name']) ?></h2>
            <p style="color:#777167"><?= htmlspecialchars($customer['email']) ?></p>
            <a href="<?= htmlspecialchars($redirect) ?>" class="primary-button full" style="display:inline-flex;margin-top:12px">Continue to <?= $redirect === 'checkout.php' ? 'checkout' : 'store' ?></a>
            <form method="POST" style="margin-top:12px"><button class="account-tab" type="submit" name="logout_customer" value="1">Sign out</button></form>
          </div>
        <?php else: ?>
          <div class="account-card-top"><strong>Customer access</strong><a href="index.php">Back to store</a></div>
          <?php if ($message !== ''): ?><div class="account-error"><?= htmlspecialchars($message) ?></div><?php endif; ?>
          <div class="account-tabs"><button class="account-tab active" type="button" data-account-tab="login">Sign in</button><button class="account-tab" type="button" data-account-tab="register">Create account</button></div>
          <form class="account-form active" method="POST" data-account-form="login">
            <input class="full" type="email" name="email" placeholder="Email address" autocomplete="email" required />
            <input class="full" type="password" name="password" placeholder="Password" autocomplete="current-password" required />
            <button type="submit">Sign in to checkout</button>
          </form>
          <form class="account-form" method="POST" data-account-form="register">
            <input type="text" name="first_name" placeholder="First name" autocomplete="given-name" required />
            <input type="text" name="last_name" placeholder="Last name" autocomplete="family-name" required />
            <input class="full" type="email" name="email" placeholder="Email address" autocomplete="email" required />
            <input class="full" type="password" name="password" placeholder="Create password (6+ characters)" minlength="6" autocomplete="new-password" required />
            <button type="submit" name="register_customer" value="1">Create account & continue</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script>
    document.querySelectorAll('[data-account-tab]').forEach(function(tab){
      tab.addEventListener('click',function(){
        document.querySelectorAll('[data-account-tab]').forEach(function(item){item.classList.remove('active')});
        tab.classList.add('active');
        document.querySelectorAll('[data-account-form]').forEach(function(form){form.classList.remove('active')});
        document.querySelector('[data-account-form="'+tab.dataset.accountTab+'"]').classList.add('active');
      });
    });
  </script>
</body>
</html>
