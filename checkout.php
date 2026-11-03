<?php
require __DIR__ . '/config.php';

if (!customer_logged_in()) {
  header('Location: customer_account.php?redirect=checkout.php');
  exit;
}

$customerAccount = current_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $payload = json_decode(file_get_contents('php://input'), true);
  $customer = is_array($payload['customer'] ?? null) ? $payload['customer'] : [];
  $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
  $required = ['first_name', 'last_name', 'email', 'address', 'city', 'postal_code', 'country', 'payment_method', 'payment_phone'];

  foreach ($required as $field) {
    if (trim((string) ($customer[$field] ?? '')) === '') {
      http_response_code(422);
      echo json_encode(['error' => 'Please complete all shipping details.']);
      exit;
    }
  }

  $paymentMethods = ['mtn_momo', 'airtel_money'];
  if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL) || !$items || !in_array($customer['payment_method'], $paymentMethods, true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Please enter valid customer and mobile-money details.']);
    exit;
  }

  $paymentPhone = preg_replace('/[^0-9+]/', '', (string) $customer['payment_phone']);
  if (preg_match('/^07[0-9]{8}$/', $paymentPhone)) {
    $paymentPhone = '+250' . substr($paymentPhone, 1);
  }
  if (!preg_match('/^\+2507[0-9]{8}$/', $paymentPhone)) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter a valid Rwanda mobile number, for example +2507XXXXXXXX.']);
    exit;
  }
  $customer['payment_phone'] = $paymentPhone;

  $total = array_reduce($items, static function (float $sum, array $item): float {
    return $sum + ((float) ($item['price'] ?? 0) * max(1, (int) ($item['quantity'] ?? 1)));
  }, 0.0);
  $currency = strtoupper((string) ($payload['currency'] ?? 'USD'));
  $orderId = create_order($customer, $items, $currency, $total);

  if (!$orderId) {
    http_response_code(500);
    echo json_encode(['error' => 'The order could not be saved. Please try again.']);
    exit;
  }

  echo json_encode(['success' => true, 'order_id' => $orderId]);
  exit;
}

$merchantPaymentNumber = get_store_setting('merchant_payment_number');
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checkout | anderson_store</title>
    <link rel="stylesheet" href="styles.css?v=20261005" />
    <style>
      .checkout-wrap { max-width: 1200px; margin: 54px auto 80px; padding: 0 20px; }
      .checkout-grid { display:grid; grid-template-columns: 1.1fr .9fr; gap:28px; }
      .checkout-card { background:#fff; border:1px solid rgba(18,18,18,.08); border-radius:28px; padding:24px; box-shadow:0 14px 36px rgba(18,18,18,.04); }
      .checkout-form { display:grid; gap:14px; }
      .two-col { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
      input, select, textarea { width:100%; border:1px solid rgba(18,18,18,.12); border-radius:12px; padding:12px 14px; font:inherit; }
      textarea { min-height:110px; resize:vertical; }
      .summary-list { display:grid; gap:14px; }
      .summary-item { display:grid; grid-template-columns:86px 1fr auto; gap:12px; align-items:center; padding:8px 0; border-bottom:1px solid rgba(18,18,18,.08); }
      .summary-item img { width:86px; height:98px; object-fit:cover; border-radius:16px; }
      .summary-total { display:flex; justify-content:space-between; align-items:center; padding-top:14px; margin-top:6px; font-size:1.2rem; font-weight:700; }
      .primary-button.full { width:100%; }
      @media (max-width: 900px) { .checkout-grid, .two-col { grid-template-columns:1fr; } }
    </style>
  </head>
  <body>
    <nav class="navbar container" style="padding-top: 14px;">
      <div class="brand-wrap">
        <button class="nav-menu" aria-label="Open menu">☰</button>
        <a href="index.php" class="brand">anderson_store</a>
      </div>
      <div class="nav-links">
        <a href="index.php#shop">New Arrivals</a>
        <a href="index.php#shop">Apparel</a>
        <a href="index.php#shop">Accessories</a>
        <a href="index.php#shop">Footwear</a>
        <a href="index.php#shop">Sale</a>
      </div>
      <div class="nav-actions">
        <a class="icon-button header-icon" href="index.php#shop" aria-label="Search products" title="Search"><span class="icon-search" aria-hidden="true"></span></a>
        <button class="icon-button header-icon" id="currencyToggle" aria-label="Choose language and currency" aria-expanded="false" title="Language and currency"><span class="icon-globe" aria-hidden="true"></span></button>
        <a class="icon-button header-icon" href="customer_account.php?redirect=checkout.php" aria-label="Customer account" title="Customer account">♙</a>
        <button class="icon-button header-icon" aria-label="Favorites" title="Favorites">☆</button>
        <button class="icon-button header-icon cart-icon" id="cartToggle" aria-label="Shopping cart" title="Shopping cart"><span class="icon-bag" aria-hidden="true"></span><span id="cartCount">0</span></button>
      </div>
    </nav>
    <div class="currency-panel" id="currencyPanel" aria-hidden="true" hidden>
      <div class="currency-panel-inner">
        <h3>Language</h3>
        <select id="languageSelect" aria-label="Language"><option value="en">English</option></select>
        <h3>Currency</h3>
        <div class="currency-current" id="currencyCurrent"><span>🇺🇸</span><strong>USD</strong><span class="currency-chevron">⌃</span></div>
        <div class="currency-options" id="currencyOptions">
          <button type="button" data-currency="USD"><img src="https://flagcdn.com/w40/us.png" alt="" /> USD <small>US dollar</small></button>
          <button type="button" data-currency="EUR"><img src="https://flagcdn.com/w40/eu.png" alt="" /> EUR <small>Euro</small></button>
          <button type="button" data-currency="GBP"><img src="https://flagcdn.com/w40/gb.png" alt="" /> GBP <small>British pound</small></button>
          <button type="button" data-currency="CAD"><img src="https://flagcdn.com/w40/ca.png" alt="" /> CAD <small>Canadian dollar</small></button>
          <button type="button" data-currency="AUD"><img src="https://flagcdn.com/w40/au.png" alt="" /> AUD <small>Australian dollar</small></button>
          <button type="button" data-currency="SGD"><img src="https://flagcdn.com/w40/sg.png" alt="" /> SGD <small>Singapore dollar</small></button>
          <button type="button" data-currency="CHF"><img src="https://flagcdn.com/w40/ch.png" alt="" /> CHF <small>Swiss franc</small></button>
          <button type="button" data-currency="JPY"><img src="https://flagcdn.com/w40/jp.png" alt="" /> JPY <small>Japanese yen</small></button>
          <button type="button" data-currency="ZAR"><img src="https://flagcdn.com/w40/za.png" alt="" /> ZAR <small>South African rand</small></button>
          <button type="button" data-currency="RWF"><img src="https://flagcdn.com/w40/rw.png" alt="" /> RWF <small>Rwandan franc</small></button>
        </div>
        <button class="currency-change" id="currencyChange" type="button">CHANGE</button>
      </div>
    </div>

    <main class="checkout-wrap">
      <div class="checkout-grid">
        <section class="checkout-card">
          <p class="eyebrow">Checkout</p>
          <h2 style="margin:0 0 18px; font-size: clamp(2.1rem, 5vw, 3.2rem); letter-spacing:-.06em;">Shipping & payment</h2>

          <form class="checkout-form" id="checkoutForm">
            <div class="two-col">
              <input type="text" name="first_name" placeholder="First name" value="<?= htmlspecialchars($customerAccount['first_name'] ?? '') ?>" required />
              <input type="text" name="last_name" placeholder="Last name" value="<?= htmlspecialchars($customerAccount['last_name'] ?? '') ?>" required />
            </div>
            <input type="email" name="email" placeholder="Email address" value="<?= htmlspecialchars($customerAccount['email'] ?? '') ?>" required />
            <input type="text" name="address" placeholder="Street address" required />
            <div class="two-col">
              <input type="text" name="city" placeholder="City" required />
              <input type="text" name="postal_code" placeholder="Postal code" required />
            </div>
            <select name="country" required>
              <option value="Rwanda">Rwanda</option>
              <option>Uganda</option>
              <option>Tanzania</option>
              <option>Burundi</option>
              <option>DRC</option>
              <option>Kenya</option>
            </select>
            <select name="payment_method" required>
              <option value="mtn_momo">MTN MoMo</option>
              <option value="airtel_money">Airtel Money</option>
            </select>
            <input type="tel" name="payment_phone" placeholder="Mobile money number (0798773881)" pattern="(07[0-9]{8}|\+2507[0-9]{8})" required />
            <?php if ($merchantPaymentNumber !== ''): ?>
              <div style="padding:12px 14px; border-radius:12px; background:#f1efed; color:#5f5d57;">Pay to this number: <strong><?= htmlspecialchars($merchantPaymentNumber) ?></strong></div>
            <?php else: ?>
              <small style="color:#a12d2d;">The store payment number has not been configured yet.</small>
            <?php endif; ?>
            <small style="color:#5f5d57;">After payment, your order will remain pending until the payment is confirmed.</small>
            <textarea name="notes" placeholder="Order notes (optional)"></textarea>
          </form>
        </section>

        <aside class="checkout-card">
          <p class="eyebrow">Order summary</p>
          <div id="checkoutSummary" class="summary-list"></div>
          <div class="summary-total">
            <span>Total</span>
            <span id="checkoutTotal">0.00k</span>
          </div>
          <button id="placeOrder" class="primary-button full" style="margin-top:20px;">Place order</button>
        </aside>
      </div>
    </main>

    <aside class="cart-panel" id="cartPanel" aria-live="polite">
      <div class="cart-header">
        <h3>Your cart</h3>
        <button id="closeCart" aria-label="Close cart">✕</button>
      </div>
      <div id="cartItems" class="cart-items"></div>
      <div class="cart-summary">
        <div>
          <span>Subtotal</span>
          <strong id="cartTotal">0.00k</strong>
        </div>
        <a href="checkout.php" class="primary-button full" style="display:inline-flex; width:100%;">Checkout</a>
      </div>
    </aside>

    <script src="app.js?v=20261005"></script>
    <script>
      const summary = document.getElementById('checkoutSummary');
      const total = document.getElementById('checkoutTotal');
      const placeOrder = document.getElementById('placeOrder');
      const checkoutForm = document.getElementById('checkoutForm');

      function getCart() {
        try {
          return JSON.parse(localStorage.getItem('anderson_cart') || '[]');
        } catch {
          return [];
        }
      }

      function renderCheckoutSummary() {
        const cart = getCart();
        if (!cart.length) {
          summary.innerHTML = '<p style="color:#5f5d57; margin:0;">Your cart is empty.</p>';
          total.textContent = '0.00k';
          return;
        }

        summary.innerHTML = cart.map(item => `
          <div class="summary-item">
            <img src="${item.image}" alt="${item.name}" />
            <div>
              <strong>${item.name}</strong>
              ${item.size ? `<div style="color:#5f5d57; font-size: .85rem;">Size ${item.size}</div>` : ''}
              <div style="color:#5f5d57; font-size: .85rem;">Qty ${item.quantity}</div>
            </div>
            <strong>${window.formatStoreCurrency(item.price * item.quantity)}</strong>
          </div>
        `).join('');

        const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
        total.textContent = window.formatStoreCurrency(subtotal);
      }

      renderCheckoutSummary();
      window.addEventListener('anderson:currencychange', renderCheckoutSummary);
      window.addEventListener('anderson:cartchange', renderCheckoutSummary);
      if (window.renderCart) {
        window.renderCart();
      }

      if (placeOrder) {
        placeOrder.addEventListener('click', () => {
          const cart = getCart();
          if (!cart.length) {
            total.textContent = '0.00k';
            summary.innerHTML = '<p style="color:#5f5d57; margin:0;">Your cart is empty.</p>';
            return;
          }

          if (!checkoutForm.reportValidity()) return;
          placeOrder.disabled = true;
          placeOrder.textContent = 'Saving order...';
          const customer = Object.fromEntries(new FormData(checkoutForm).entries());
          fetch('checkout.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customer, items: cart, currency: window.getStoreCurrency() }),
          })
            .then(async response => {
              const responseText = await response.text();
              let result;
              try {
                result = JSON.parse(responseText);
              } catch {
                throw new Error('The checkout server returned an invalid response. Please try again.');
              }
              if (!response.ok) throw new Error(result.error || 'The order could not be saved.');
              return result;
            })
            .then(result => {
              window.clearStoreCart();
              placeOrder.textContent = `Order #${result.order_id} placed`;
              summary.innerHTML = '<p style="color:#214f36; margin:0;">Thank you — your order has been saved.</p>';
              total.textContent = window.formatStoreCurrency(0);
            })
            .catch(error => {
              placeOrder.disabled = false;
              placeOrder.textContent = 'Place order';
              summary.insertAdjacentHTML('afterbegin', `<p style="color:#a12d2d; margin:0 0 12px;">${error.message}</p>`);
            });
        });
      }
    </script>
  </body>
</html>
