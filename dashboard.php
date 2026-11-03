<?php
require __DIR__ . '/config.php';
require_admin_login();

$products = load_products();
$orders = load_orders();
$chatConversations = load_chat_conversations();
$paymentNumber = get_store_setting('merchant_payment_number');
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);
$pendingOrderNotifications = array_filter($orders, function ($order) {
  $status = strtolower((string) ($order['status'] ?? $order['order_status'] ?? 'pending'));
  return in_array($status, ['pending', 'placed', 'new'], true);
});
$notificationCount = count($pendingOrderNotifications) + count($chatConversations);

$storeSettingsKeys = [
  'store_name','store_tagline','store_timing','store_address','store_currency','store_search_text',
  'minimum_order_value','shipping_charge','company_name','company_email','company_phone',
  'shipping_method','opening_time','closing_time','sms_provider','sms_sender','sms_api_key',
  'email_sender','email_reply_to','analytics_code','tracking_enabled','default_region',
  'regional_currency','default_language','seo_title','seo_description','seo_keywords',
];
$storeSettings = [];
foreach ($storeSettingsKeys as $key) {
  $storeSettings[$key] = get_store_setting($key);
}

$totalRevenue = 0.0;
$pendingOrders = 0;
$customerKeys = [];
$totalCustomers = 0;
foreach ($orders as $order) {
  $totalRevenue += (float) ($order['total'] ?? 0);
  if (stripos((string) ($order['status'] ?? ''), 'pending') !== false) $pendingOrders++;
  $customerKey = strtolower(trim((string) (($order['email'] ?? '') ?: (($order['first_name'] ?? '') . '|' . ($order['last_name'] ?? '')))));
  if ($customerKey !== '' && !isset($customerKeys[$customerKey])) {
    $customerKeys[$customerKey] = true;
    $totalCustomers++;
  }
}
$dailyRevenue = array_fill(0, 7, 0.0);
for ($day = 0; $day < 7; $day++) {
  $date = (new DateTime())->modify("-$day days")->format('Y-m-d');
  foreach ($orders as $order) {
    if (substr((string) ($order['created_at'] ?? ''), 0, 10) === $date) {
      $dailyRevenue[6 - $day] += (float) ($order['total'] ?? 0);
    }
  }
}
$chartMax = max($dailyRevenue) ?: 100;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
  logout_admin();
  header('Location: login.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
  $productId = (string) ($_POST['product_id'] ?? '');
  $productToDelete = null;
  foreach ($products as $product) {
    if ((string) ($product['id'] ?? '') === $productId) { $productToDelete = $product; break; }
  }
  if ($productToDelete) {
    $products = array_values(array_filter($products, static fn(array $product): bool => (string) ($product['id'] ?? '') !== $productId));
    delete_product((int) $productId);
    file_put_contents(product_file_path(), json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    $imagePath = (string) ($productToDelete['image'] ?? '');
    $imageFile = __DIR__ . '/' . ltrim($imagePath, '/\\');
    if (str_starts_with($imagePath, 'uploads/product-images/') && basename($imagePath) !== 'default-product.svg' && is_file($imageFile)) unlink($imageFile);
    $_SESSION['flash_message'] = 'Product removed successfully.';
  } else {
    $_SESSION['flash_message'] = 'That product could not be found.';
  }
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_product'])) {
  $sourceId = (int) ($_POST['product_id'] ?? 0);
  $source = null;
  foreach ($products as $product) {
    if ((int) ($product['id'] ?? 0) === $sourceId) { $source = $product; break; }
  }
  if ($source) {
    $copy = [
      'name' => (string) ($source['name'] ?? 'Product') . ' Copy',
      'price' => (float) ($source['price'] ?? 0),
      'category' => (string) ($source['category'] ?? 'tees'),
      'badge' => (string) ($source['badge'] ?? 'New'),
      'rating' => (float) ($source['rating'] ?? 4.8),
      'image' => (string) ($source['image'] ?? 'uploads/product-images/default-product.svg'),
      'description' => (string) ($source['description'] ?? ''),
    ];
    $_SESSION['flash_message'] = insert_product($copy) ? 'Product copied successfully.' : 'Unable to copy that product.';
  } else {
    $_SESSION['flash_message'] = 'That product could not be found.';
  }
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product_edit'])) {
  $productId = (int) ($_POST['product_id'] ?? 0);
  $existing = null;
  foreach ($products as $product) {
    if ((int) ($product['id'] ?? 0) === $productId) { $existing = $product; break; }
  }
  if ($existing) {
    $imageUpload = save_uploaded_product_image($_FILES['edit_image_file'] ?? null, (string) ($existing['image'] ?? 'uploads/product-images/default-product.svg'));
    $updated = [
      'name' => trim((string) ($_POST['edit_name'] ?? $existing['name'])),
      'price' => (float) ($_POST['edit_price'] ?? $existing['price']),
      'category' => trim((string) ($_POST['edit_category'] ?? $existing['category'])),
      'badge' => trim((string) ($_POST['edit_badge'] ?? $existing['badge'])),
      'rating' => (float) ($_POST['edit_rating'] ?? $existing['rating']),
      'image' => $imageUpload['error'] === '' ? $imageUpload['path'] : (string) $existing['image'],
      'description' => trim((string) ($_POST['edit_description'] ?? ($existing['description'] ?? ''))),
    ];
    if ($updated['name'] === '') {
      $_SESSION['flash_message'] = 'Product name is required.';
    } elseif ($imageUpload['error'] !== '' && !empty($_FILES['edit_image_file']['name'])) {
      $_SESSION['flash_message'] = $imageUpload['error'];
    } else {
      $_SESSION['flash_message'] = update_product($productId, $updated) ? 'Product updated successfully.' : 'Unable to update that product.';
    }
  } else {
    $_SESSION['flash_message'] = 'That product could not be found.';
  }
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && isset($_POST['order_status'])) {
  $orderId = (int) ($_POST['order_id'] ?? 0);
  $status = trim((string) ($_POST['order_status'] ?? ''));
  $_SESSION['flash_message'] = update_order_status($orderId, $status) ? 'Order status updated.' : 'Unable to update the order status.';
  header('Location: dashboard.php#orders');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete_products'])) {
  $selected = $_POST['selected_products'] ?? [];
  $countDeleted = 0;
  if (is_array($selected)) {
    foreach ($selected as $selectedId) {
      $productId = (int) $selectedId;
      foreach ($products as $index => $product) {
        if ((int) ($product['id'] ?? 0) === $productId) {
          delete_product($productId);
          unset($products[$index]);
          $countDeleted++;
          break;
        }
      }
    }
    $products = array_values($products);
    file_put_contents(product_file_path(), json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
  }
  $_SESSION['flash_message'] = $countDeleted ? "$countDeleted product(s) removed." : 'Select at least one product.';
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product_price'])) {
  $productId = trim((string) ($_POST['product_id'] ?? ''));
  $priceInput = trim((string) ($_POST['product_price'] ?? ''));
  if (ctype_digit($productId) && preg_match('/^\d+(?:\.\d{1,2})?$/D', $priceInput) && (float) $priceInput > 0 && (float) $priceInput <= 99999999.99) {
    $_SESSION['flash_message'] = update_product_price((int) $productId, (float) $priceInput) ? 'Product price updated.' : 'Unable to update that product price.';
  } else {
    $_SESSION['flash_message'] = 'Enter a valid price greater than zero, with up to two decimal places.';
  }
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_store_settings'])) {
  if (isset($_POST['merchant_payment_number'])) {
    $paymentInput = preg_replace('/[^0-9+]/', '', trim((string) $_POST['merchant_payment_number']));
    if (preg_match('/^07[0-9]{8}$/', $paymentInput)) $paymentInput = '+250' . substr($paymentInput, 1);
    if (preg_match('/^\+2507[0-9]{8}$/', $paymentInput)) save_store_setting('merchant_payment_number', $paymentInput);
  }
  $settingNames = [
    'store_name','store_tagline','store_timing','store_address','store_currency','store_search_text',
    'minimum_order_value','shipping_charge','company_name','company_email','company_phone',
    'shipping_method','opening_time','closing_time','sms_provider','sms_sender','sms_api_key',
    'email_sender','email_reply_to','analytics_code','tracking_enabled','default_region',
    'regional_currency','default_language','seo_title','seo_description','seo_keywords',
  ];
  $saved = true;
  foreach ($settingNames as $key) {
    if (!array_key_exists($key, $_POST)) continue;
    $value = trim((string) $_POST[$key]);
    if (strlen($value) > 255) $value = substr($value, 0, 255);
    if (!save_store_setting($key, $value)) $saved = false;
  }
  $_SESSION['flash_message'] = $saved ? 'Store settings saved successfully.' : 'Some settings could not be saved.';
  header('Location: dashboard.php#settings');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_payment_number'])) {
  $paymentNumber = preg_replace('/[^0-9+]/', '', trim($_POST['merchant_payment_number'] ?? ''));
  if (preg_match('/^07[0-9]{8}$/', $paymentNumber)) $paymentNumber = '+250' . substr($paymentNumber, 1);
  if (!preg_match('/^\+2507[0-9]{8}$/', $paymentNumber)) {
    $_SESSION['flash_message'] = 'Enter a valid Rwanda mobile number, for example 0798773881.';
  } elseif (save_store_setting('merchant_payment_number', $paymentNumber)) {
    $_SESSION['flash_message'] = 'Customer payment number saved.';
    $paymentNumber = get_store_setting('merchant_payment_number');
  } else {
    $_SESSION['flash_message'] = 'Unable to save the customer payment number.';
  }
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_chat'])) {
  $token = trim($_POST['conversation_token'] ?? '');
  $reply = trim($_POST['reply_message'] ?? '');
  $_SESSION['flash_message'] = (strlen($token) >= 16 && $reply !== '' && send_chat_message($token, 'admin', $reply)) ? 'Reply sent to the customer.' : 'The reply could not be sent.';
  header('Location: dashboard.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_name'])) {
  $name = trim($_POST['product_name'] ?? '');
  $price = (float) ($_POST['price'] ?? 0);
  $category = trim($_POST['category'] ?? 'tees');
  $badge = trim($_POST['badge'] ?? 'New');
  $rating = (float) ($_POST['rating'] ?? 4.8);
  $imageUpload = save_uploaded_product_image($_FILES['image_file'] ?? null, 'uploads/product-images/default-product.svg');
  if ($imageUpload['error'] !== '') {
    $_SESSION['flash_message'] = $imageUpload['error'];
  } elseif ($name !== '') {
    $newProduct = ['name' => $name, 'price' => $price, 'category' => $category, 'badge' => $badge, 'rating' => $rating, 'image' => $imageUpload['path'], 'description' => trim($_POST['description'] ?? 'Premium essentials designed for streetwear everyday wear.')];
    $_SESSION['flash_message'] = insert_product($newProduct) ? 'Product added successfully.' : 'Unable to save product.';
  } else {
    $_SESSION['flash_message'] = 'Please add a product name.';
  }
  header('Location: dashboard.php');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Anderson Store</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--sidebar:#10172d;--sidebar-2:#171f40;--nav:#171d38;--nav-bg:#6574e8;--canvas:#090d1c;--panel:#11182c;--ink:#edf2ff;--muted:#8d96ad;--line:#222c4a;--blue:#6574e8;--cyan:#2ec8d8;--violet:#7767d9;--pink:#ed7cbd}
    *{box-sizing:border-box}
    html{scroll-behavior:smooth}
    body{margin:0;font-family:Inter,sans-serif;color:var(--ink);background:var(--canvas)}
    button,input,select,textarea{font:inherit}
    button,a{cursor:pointer}
    .app{min-height:100vh}
    .sidebar{position:fixed;inset:0 auto 0 0;width:210px;background:linear-gradient(180deg,var(--sidebar) 0%,#192b5d 100%);color:#fff;padding:18px 14px;z-index:20}
    .brand{height:58px;display:flex;align-items:center;gap:10px;padding:0 10px;font-weight:800;font-size:18px}
    .brand-mark{width:30px;height:30px;border-radius:8px;background:#fff;color:#3155bb;display:grid;place-items:center;font-size:16px}
    .nav-list{display:grid;gap:4px;margin-top:12px}
    .nav-item{display:flex;align-items:center;gap:11px;height:43px;padding:0 12px;border-radius:7px;color:#c9d2f3;text-decoration:none;font-size:13px;font-weight:600}
    .nav-item:hover,.nav-item.active{background:rgba(83,112,207,.32);color:#fff}
    .nav-icon{width:20px;text-align:center;font-size:15px}
    .sidebar-footer{position:absolute;left:14px;right:14px;bottom:16px}
    .upgrade{margin-bottom:10px;padding:12px;border-radius:10px;background:linear-gradient(135deg,#3b5dbc,#5d4db0);font-size:11px;color:#eef2ff}
    .upgrade strong{display:block;margin-bottom:5px}
    .profile{display:flex;align-items:center;gap:9px;padding:9px;border-radius:8px;background:rgba(255,255,255,.08);font-size:12px}
    .avatar{width:34px;height:34px;border-radius:50%;background:#f1a98b;color:#fff;display:grid;place-items:center;font-weight:800}
    .main{margin-left:210px;min-height:100vh}
    .topbar{height:66px;padding:0 24px;display:flex;align-items:center;justify-content:space-between;background:#fff;border-bottom:1px solid var(--line)}
    .page-search{width:min(330px,40vw);position:relative}
    .page-search input{width:100%;height:38px;border:0;border-radius:8px;padding:0 14px 0 38px;background:#f4f6fb;color:var(--ink)}
    .page-search:before{content:"Q";position:absolute;left:14px;top:9px;color:#9aa3b4;font-size:12px;font-weight:800;z-index:2}
    .top-actions{display:flex;align-items:center;gap:8px}
    .icon-button,.new-order{height:38px;border:0;border-radius:8px;background:#fff;color:var(--ink);box-shadow:0 3px 12px rgba(38,52,94,.06)}
    .back-to-store{height:38px;padding:0 12px;border:1px solid var(--line);border-radius:8px;background:#fff;color:#526ed9;text-decoration:none;display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:700;box-shadow:0 3px 12px rgba(38,52,94,.06)}
    .back-store-icon{width:20px;height:20px;border:1.5px solid currentColor;border-radius:5px;display:grid;place-items:center;font-size:11px}
    .icon-button{width:38px}
    .new-order{padding:0 15px;background:var(--nav-bg);color:#fff;text-decoration:none;display:inline-flex;align-items:center;font-weight:700;font-size:12px}
    .notification-wrap{position:relative}
    .notification-button{position:relative;font-size:15px}
    .notification-badge{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 4px;border:2px solid #fff;border-radius:9px;background:#ef5da8;color:#fff;font-size:9px;font-weight:800;display:grid;place-items:center}
    .notification-panel{position:absolute;top:48px;right:0;width:310px;padding:8px;border:1px solid var(--line);border-radius:12px;background:#fff;box-shadow:0 18px 45px rgba(28,42,82,.16);z-index:50;display:none}
    .notification-panel.open{display:block}
    .notification-head{height:42px;padding:0 8px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line)}
    .notification-head strong{font-size:13px}.notification-head button{border:0;background:none;color:#526ed9;font-size:11px;font-weight:700}
    .notification-item{display:flex;gap:10px;padding:11px 8px;text-decoration:none;color:var(--ink);border-bottom:1px solid #f1f3f8}
    .notification-item:last-child{border-bottom:0}.notification-dot{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;background:#eef2ff;color:#526ed9;font-size:12px;font-weight:800}.notification-copy strong{display:block;font-size:12px;margin-bottom:3px}.notification-copy span{color:var(--muted);font-size:11px}
    .admin-message{position:relative;margin-bottom:14px;padding:12px 42px 12px 14px;border:1px solid #bed0ff;border-radius:9px;background:#f1f5ff;color:#2946a8;font-size:12px;font-weight:700}
    .admin-message button{position:absolute;right:8px;top:7px;width:28px;height:28px;border:0;background:transparent;color:#7182b0;font-size:18px}
    .content{padding:22px 24px 40px}
    h1,h2,h3,p{margin-top:0}
    .welcome-row{display:flex;justify-content:space-between;align-items:end;margin-bottom:17px}
    .welcome-row h1{font-size:20px;margin-bottom:4px}
    .welcome-row p{margin:0;color:var(--muted);font-size:12px}
    .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:14px}
    .stat{height:86px;background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:14px 16px;display:flex;align-items:center;gap:12px}
    .stat-icon{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;background:#edf3ff;color:#5b73c9;font-size:18px}
    .stat:nth-child(2) .stat-icon{background:#edf0ff;color:#6157c9}
    .stat:nth-child(3) .stat-icon{background:#f0edff;color:#7761c9}
    .stat:nth-child(4) .stat-icon{background:#edf9ff;color:#2da4c0}
    .stat-label{font-size:11px;color:var(--muted);margin-bottom:5px}
    .stat strong{font-size:20px}
    .stat small{color:#4eaa75;font-size:10px;margin-left:5px}
    .dashboard-grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(280px,.75fr);gap:14px}
    .card{background:var(--panel);border:1px solid var(--line);border-radius:8px;padding:16px}
    .card-head{height:38px;display:flex;align-items:start;justify-content:space-between}
    .card-head h2{font-size:12px;margin:0}
    .text-link{font-size:11px;color:#667dd1;text-decoration:none}
    .chart-area{height:190px;position:relative;border-left:1px solid var(--line);border-bottom:1px solid var(--line);margin:12px 2px 0 34px;background:repeating-linear-gradient(to bottom,var(--line) 0,var(--line) 1px,transparent 1px,transparent 47px)}
    .chart-label{position:absolute;left:-34px;color:#a0a7b5;font-size:9px;transform:translateY(-4px)}
    .chart-label.one{top:0}.chart-label.two{top:45%}.chart-label.three{bottom:0}
    .bars{position:absolute;inset:0 10px 0 10px;display:flex;align-items:flex-end;justify-content:space-around;gap:5px}
    .bar-wrap{height:100%;flex:1;display:flex;align-items:flex-end;justify-content:center;position:relative}
    .bar{width:min(32px,70%);min-height:2px;border-radius:5px 5px 0 0;background:linear-gradient(180deg,#42bed6,#536edb);box-shadow:0 -4px 12px rgba(83,110,217,.12)}
    .bar-day{position:absolute;bottom:-18px;font-size:9px;color:#9ba3b3}
    .two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
    .side-column{display:grid;gap:14px;align-content:start}
    .table-wrap{overflow-x:auto}
    table{width:100%;border-collapse:collapse;font-size:11px}
    th{height:34px;text-align:left;color:#9ba3b4;font-weight:600;border-bottom:1px solid var(--line)}
    td{height:58px;border-bottom:1px solid var(--line)}
    .product-cell{display:flex;align-items:center;gap:8px;font-weight:700}
    .product-cell img{width:36px;height:42px;border-radius:5px;object-fit:cover;background:#eef1f7}
    .status{display:inline-flex;padding:4px 8px;border-radius:999px;background:#edf8f1;color:#3d9563;font-size:9px;font-weight:700}
    .status.pending{background:#fff4dc;color:#b47c1d}
    .price-form{display:flex;align-items:center;gap:5px}
    .price-form input{width:72px;height:32px;border:1px solid var(--line);border-radius:6px;padding:0 7px;background:#f8f9fc}
    .mini-button,.button{border:0;border-radius:6px;height:32px;padding:0 10px;background:#edf1fd;color:#526ed9;font-size:10px;font-weight:700}
    .mini-button.danger{background:#fff0ef;color:#d46a62}
    .donut{width:146px;height:146px;border-radius:50%;margin:18px auto;background:conic-gradient(#27b7d4 0 34%,#6e5ad9 34% 67%,#ed7cbd 67% 100%);position:relative}
    .donut:after{content:"$<?= number_format($totalRevenue/1000,1) ?>K";position:absolute;inset:24px;border-radius:50%;background:#fff;display:grid;place-items:center;font-size:14px;font-weight:800}
    .legend{display:grid;gap:7px;font-size:10px;color:var(--muted);margin-top:10px}
    .legend div{display:flex;justify-content:space-between}.legend span:first-child:before{content:"";display:inline-block;width:7px;height:7px;border-radius:50%;margin-right:7px;background:#27b7d4}.legend div:nth-child(2) span:first-child:before{background:#6e5ad9}.legend div:nth-child(3) span:first-child:before{background:#ed7cbd}
    .recent-list{display:grid}
    .recent-item{height:58px;display:grid;grid-template-columns:38px 1fr auto;align-items:center;gap:8px;border-bottom:1px solid var(--line)}
    .recent-item:last-child{border-bottom:0}
    .recent-item img{width:38px;height:44px;border-radius:5px;object-fit:cover;background:#eef1f7}
    .recent-item strong{display:block;font-size:11px}.recent-item span{font-size:9px;color:var(--muted)}
    .summary-list{display:grid;gap:9px;font-size:11px;color:var(--muted)}
    .summary-list div{display:flex;justify-content:space-between}.summary-list strong{color:var(--ink)}
    .checkout{width:100%;height:42px;border:0;border-radius:7px;background:#526ed9;color:#fff;font-weight:700;margin-top:12px}
    .promo{min-height:126px;border-radius:8px;padding:16px;background:linear-gradient(135deg,#536ed9,#7153b8);color:#fff;display:flex;justify-content:space-between;overflow:hidden}
    .promo h2{font-size:14px;margin:0 0 6px}.promo p{font-size:10px;line-height:1.5;opacity:.78;margin-bottom:12px}.promo button{height:32px;border:0;border-radius:5px;padding:0 11px;background:#fff;color:#536ed9;font-size:10px;font-weight:800}
    .promo-art{font-size:58px;transform:rotate(-12deg);opacity:.75}
    .section{margin-top:14px}
    .message{margin-bottom:14px;padding:10px 12px;border-radius:7px;background:#edf8f1;color:#34764d;border:1px solid #cce7d4;font-size:12px}
    .add-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .form-grid{display:grid;gap:9px}.form-grid input,.form-grid select,.form-grid textarea{width:100%;height:38px;border:1px solid var(--line);border-radius:7px;background:#f8f9fc;padding:0 10px}.form-grid textarea{height:70px;padding-top:9px;resize:vertical}
    .orders-list{display:grid}.order-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:12px 0;border-bottom:1px solid var(--line);font-size:11px}.order-row p{margin:4px 0;color:var(--muted)}.order-row small{color:var(--muted)}
    .chat-conversation{padding:12px 0;border-bottom:1px solid var(--line);font-size:11px}.chat-message{padding:8px;margin:7px 0;border-radius:7px;background:#f4f6fb}.chat-message.admin{background:#edf8f1}.chat-message small{display:block;color:var(--muted);margin-top:4px}.chat-conversation form{display:flex;gap:7px}.chat-conversation form input{height:34px;border:1px solid var(--line);border-radius:6px;padding:0 9px;flex:1}
    .settings-row{display:flex;gap:8px}.settings-row input{height:38px;border:1px solid var(--line);border-radius:7px;padding:0 10px;flex:1}
    .settings-manager{grid-column:1/-1}.settings-tabs{display:flex;gap:18px;border-bottom:1px solid var(--line);overflow-x:auto;margin-bottom:14px}.settings-tab{height:42px;border:0;border-bottom:2px solid transparent;background:transparent;color:#7e8799;font-size:11px;white-space:nowrap}.settings-tab.active{color:#526ed9;border-bottom-color:#526ed9;font-weight:800}.settings-pane{display:none}.settings-pane.active{display:block}.settings-section{border:1px solid var(--line);border-radius:7px;padding:16px;margin-bottom:12px}.settings-section h3{font-size:13px;margin-bottom:5px}.settings-section>p{font-size:10px;color:#9aa3b4}.settings-form-grid{display:grid;grid-template-columns:170px minmax(0,1fr);gap:8px 14px;align-items:center;margin-top:12px}.settings-form-grid label{font-size:10px;font-weight:700;color:#687286}.settings-form-grid input,.settings-form-grid select,.settings-form-grid textarea{width:100%;height:38px;border:1px solid var(--line);border-radius:6px;background:#f8f9fc;padding:0 9px;color:var(--ink)}.settings-form-grid textarea{height:68px;padding-top:8px;resize:vertical}.settings-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}.settings-actions .button{height:38px;padding:0 15px}.settings-note{font-size:9px;color:#a0a7b5}
    .order-manager{grid-column:1/-1}.order-toolbar{display:flex;align-items:center;gap:4px;border-bottom:1px solid var(--line);margin-top:7px;overflow-x:auto}.order-tab{height:42px;border:0;border-bottom:2px solid transparent;background:transparent;padding:0 13px;color:#929bab;font-size:10px}.order-tab.active{color:#526ed9;border-bottom-color:#526ed9;font-weight:800}.order-search{width:180px;height:34px;border:1px solid var(--line);border-radius:6px;padding:0 9px;margin-left:auto}
    .order-table th,.order-table td{height:52px;text-align:left}.order-table tbody tr:hover{background:#fafbff}.order-status-form{margin:0}.order-status{height:28px;border:0;border-radius:999px;padding:0 8px;font-size:9px;font-weight:700;background:#fff4d8;color:#b27a25}.order-status.pending{background:#fff4d8;color:#b27a25}.order-status.confirmed{background:#edf8f1;color:#3d9563}.order-status.processing{background:#edf4ff;color:#5b73c9}.order-status.shipped{background:#eefbff;color:#2b9bb8}.order-status.delivered{background:#eaf8ef;color:#3d9563}.order-status.cancelled,.order-status.refunded{background:#fff0ef;color:#d46a62}
    .order-view{width:30px;height:30px;border:1px solid var(--line);border-radius:50%;background:#fff;color:#526ed9;font-size:11px}.order-pagination{min-height:48px;display:flex;align-items:center;justify-content:space-between;color:#929bab;font-size:10px}.order-pages{display:flex;gap:5px}.order-page{width:26px;height:26px;border:0;border-radius:5px;background:#f0f3fa;color:#778196}.order-page.active{background:#526ed9;color:#fff}
    .order-modal{position:fixed;inset:0;z-index:90;background:rgba(14,20,43,.38);display:none;align-items:center;justify-content:center;padding:20px}.order-modal.open{display:flex}.order-detail{width:min(520px,100%);background:#fff;border-radius:10px;box-shadow:0 24px 70px rgba(14,20,43,.28)}.order-detail-head{height:58px;display:flex;align-items:center;justify-content:space-between;padding:0 18px;border-bottom:1px solid var(--line)}.order-detail-body{padding:18px;font-size:11px}.order-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.order-detail-grid p{margin:5px 0;color:#7e8799}.order-detail-items{margin-top:14px;border-top:1px solid var(--line);padding-top:10px}.order-detail-item{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--line)}
    .product-manager{grid-column:1/-1}.product-toolbar{display:flex;align-items:center;gap:7px;min-height:48px;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin-top:8px}
    .product-toolbar .mini-button{height:34px}.product-toolbar .filter-link{border:0;background:transparent;color:#7e8799;font-size:10px;padding:5px}.product-toolbar .filter-link.active{color:#526ed9;font-weight:800}.product-search{width:180px;height:34px;border:1px solid var(--line);border-radius:6px;padding:0 9px;margin-left:auto}
    .product-table th,.product-table td{height:52px}.product-table tbody tr{position:relative}.product-table tbody tr:hover{background:#f8faff}.product-table .checkbox{width:14px;height:14px}.product-image{width:38px;height:38px;border-radius:6px;object-fit:cover;background:#eef1f7}
    .product-name-cell{display:flex;align-items:center;gap:9px}.product-name-cell strong{font-size:11px}.product-name-cell span{display:block;color:var(--muted);font-size:9px;margin-top:3px}.stock{font-size:10px;color:#4a9d69}.stock.low{color:#c58a2b}
    .row-actions{width:32px;border:0;background:transparent;color:#7e8799;font-size:18px}
    .product-menu{position:fixed;display:none;width:150px;background:#fff;border:1px solid var(--line);border-radius:7px;box-shadow:0 12px 30px rgba(23,32,51,.16);padding:5px;z-index:50}.product-menu.open{display:block}.product-menu button{width:100%;height:34px;border:0;background:transparent;text-align:left;border-radius:5px;font-size:11px;color:var(--ink)}.product-menu button:hover{background:#f1f4fb}.product-menu .menu-delete{color:#c85d56;border-top:1px solid var(--line);margin-top:3px;border-radius:0}
    .product-modal{position:fixed;inset:0;z-index:80;background:rgba(14,20,43,.38);display:none;align-items:center;justify-content:center;padding:20px}.product-modal.open{display:flex}.modal-panel{width:min(650px,100%);max-height:90vh;overflow:auto;background:#fff;border-radius:10px;box-shadow:0 24px 70px rgba(14,20,43,.28)}.modal-head{height:58px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--line)}.modal-head strong{color:#526ed9;font-size:15px}.modal-close{border:0;background:transparent;font-size:18px;color:#8790a2}.modal-body{padding:18px}.modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.modal-grid label{display:grid;gap:6px;color:#8790a2;font-size:10px}.modal-grid input,.modal-grid select,.modal-grid textarea{height:38px;border:1px solid var(--line);border-radius:6px;padding:0 9px;background:#f8f9fc;color:var(--ink)}.modal-grid textarea{height:70px;padding-top:9px;resize:vertical}.span-two{grid-column:1/-1}.modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:14px}.modal-actions .button{height:38px;padding:0 16px}
    @media(max-width:1050px){.stats{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}.side-column{grid-template-columns:1fr 1fr}.two-col{grid-template-columns:1fr}}
    @media(max-width:760px){.sidebar{position:sticky;width:100%;height:auto;bottom:auto;padding:8px}.brand{height:42px}.nav-list{grid-template-columns:repeat(5,1fr)}.nav-item{justify-content:center;padding:0}.nav-item span:last-child{display:none}.sidebar-footer{display:none}.main{margin-left:0}.topbar{height:58px}.page-search{width:45vw}.content{padding:16px 12px}.stats{grid-template-columns:1fr 1fr}.side-column{grid-template-columns:1fr}.add-grid,.order-row{grid-template-columns:1fr}}

    /* Dark analytics theme */
    body{background:radial-gradient(circle at top right,rgba(50,75,150,.09),transparent 28%),var(--canvas);color:var(--ink)}
    .sidebar{width:210px;background:linear-gradient(180deg,#111831 0%,#0e1428 100%);border-right:1px solid rgba(143,158,220,.08);box-shadow:12px 0 30px rgba(2,5,15,.18)}
    .brand{color:#f6f8ff;font-size:16px}
    .brand-mark{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#6878ef,#9b6de8);color:#fff}
    .nav-list{gap:3px;margin-top:16px}
    .nav-item{height:40px;border-radius:6px;color:#9da6c4;font-size:12px}
    .nav-item:hover{background:#1a2343;color:#dfe6ff}
    .nav-item.active{background:linear-gradient(90deg,rgba(93,112,220,.32),rgba(93,112,220,.12));color:#fff;box-shadow:inset 2px 0 #7c8df2}
    .nav-icon{color:#b9c3e4}
    .upgrade{background:linear-gradient(135deg,#26345d,#30305e);border:1px solid rgba(137,151,220,.12)}
    .profile{background:#171e38;border:1px solid rgba(143,158,220,.08)}
    .avatar{background:linear-gradient(135deg,#5f72d8,#8670d6)}
    .main{background:var(--canvas)}
    .topbar{height:60px;background:#11182c;border-bottom:1px solid var(--line)}
    .page-search input{height:36px;background:#0d1324;border:1px solid var(--line);color:var(--ink)}
    .page-search:before{top:8px;color:#6e7897}
    .icon-button,.back-to-store,.new-order{border:1px solid var(--line);background:#171e35;color:#dfe5f7;box-shadow:none}
    .back-to-store{color:#9eabe2}
    .new-order{background:linear-gradient(135deg,#6072df,#7468d8);border-color:transparent}
    .notification-panel,.order-detail,.product-menu,.row-menu,.modal-card{background:#11182c;border-color:var(--line);box-shadow:0 18px 50px rgba(0,0,0,.38)}
    .notification-item{border-color:var(--line);color:var(--ink)}
    .notification-dot{background:#202b4e;color:#91a1ff}
    .notification-head{border-color:var(--line)}
    .notification-head button{color:#8ea0ff}
    .admin-message{background:#17223f;border-color:#314066;color:#b8c4ef}
    .welcome-row h1{color:#f1f4ff;font-size:19px}
    .welcome-row p{color:#7f89a6}
    .content{padding:20px 22px 40px}
    .stat,.card{background:linear-gradient(145deg,#11182d,#10172a);border-color:#202c4a;border-radius:7px;box-shadow:0 10px 24px rgba(0,0,0,.08)}
    .stat{height:78px;padding:13px 15px}
    .stat-icon{background:#19264a!important;color:#7e93ef!important;border:1px solid rgba(125,145,220,.12)}
    .stat:nth-child(2) .stat-icon{background:#232346!important;color:#9e91ef!important}
    .stat:nth-child(3) .stat-icon{background:#1d2946!important;color:#68a9d4!important}
    .stat:nth-child(4) .stat-icon{background:#18354b!important;color:#5ac3cf!important}
    .stat-label{color:#8791aa}
    .stat strong{color:#f1f4ff;font-size:18px}
    .stat small{color:#57c8a4}
    .card-head h2{color:#edf2ff;font-size:12px}
    .text-link{color:#8797ef}
    .chart-area{height:190px;border-left-color:#273351;border-bottom-color:#273351;background:repeating-linear-gradient(to bottom,#1c2946 0,#1c2946 1px,transparent 1px,transparent 47px)}
    .chart-label,.bar-day{color:#737f9e}
    .bar{width:min(30px,65%);border-radius:3px 3px 0 0;background:linear-gradient(180deg,#8a76e8 0%,#36a8ca 55%,#1b769c 100%);box-shadow:0 0 16px rgba(91,117,225,.2)}
    table{color:#c8cfe1}
    th{color:#77829d;border-bottom-color:#26324d}
    td{border-bottom-color:#202b48;color:#adb6ce}
    .product-image{background:#1a223a;border:1px solid var(--line)}
    .order-table tbody tr:hover,.product-table tbody tr:hover{background:#151d34}
    .product-toolbar,.order-toolbar{border-color:#26324d}
    .product-search,.order-search,.form-grid input,.form-grid select,.form-grid textarea,.settings-form-grid input,.settings-form-grid select,.settings-form-grid textarea,.chat-conversation form input,.settings-row input,.price-form input{background:#0d1324;border-color:#26324d;color:#dce3f4}
    input::placeholder,textarea::placeholder{color:#65708c}
    .filter-link,.order-tab{color:#7f89a4!important}
    .filter-link.active,.order-tab.active{color:#8696f0!important;border-bottom-color:#7185ed!important}
    .mini-button,.button{background:#1d2a51;color:#96a6f2}
    .mini-button.danger{background:#38213a;color:#ef91a0}
    .checkout{background:linear-gradient(135deg,#6274df,#7864cf);box-shadow:0 8px 18px rgba(74,92,188,.18)}
    .donut{box-shadow:0 0 0 1px rgba(131,145,214,.08)}
    .donut:after{background:#11182c;color:#eef2ff}
    .legend,.summary-list{color:#8791aa}
    .summary-list strong,.recent-item strong{color:#dfe5f7}
    .recent-item{border-bottom-color:#26324d}
    .recent-item img{border:1px solid var(--line);background:#1a223a}
    .recent-item span{color:#7c86a0}
    .promo{background:linear-gradient(135deg,#263d79,#3d3476);border:1px solid rgba(139,151,220,.12)}
    .promo button{background:#7c8df0;color:#fff}
    .status{background:#153729;color:#70c99b}
    .status.pending{background:#40351e;color:#dcbb6b}
    .order-status{background:#29233f;color:#c7b3ef;border:1px solid #38304f}
    .order-status.pending{background:#40351e;color:#dcbb6b;border-color:#594827}
    .order-status.confirmed{background:#17372f;color:#77c79c;border-color:#1e4a3e}
    .order-status.processing{background:#1a2948;color:#91a4f1;border-color:#293b67}
    .order-status.shipped{background:#173648;color:#6ec0d7;border-color:#235168}
    .order-status.delivered{background:#17372f;color:#77c79c;border-color:#1e4a3e}
    .order-status.cancelled,.order-status.refunded{background:#402330;color:#e78a9a;border-color:#593144}
    .order-view{background:#171e35;border-color:#29334e;color:#8b9bef}
    .order-pagination{color:#77839d}
    .order-page{background:#1a223a;color:#8994ad}
    .order-page.active{background:#6574d9;color:#fff}
    .chat-conversation{border-color:#26324d}
    .chat-message{background:#161e35;color:#bcc5da}
    .chat-message.admin{background:#172d32;color:#d6eee8}
    .settings-tab{color:#8490aa}
    .settings-tab.active{color:#8797ef;border-bottom-color:#7185ed}
    .settings-tabs,.settings-section,.chat-conversation{border-color:#26324d}
    .settings-section>p,.settings-note,.chat-message small{color:#77839d}
    .settings-form-grid label{color:#aab3ca}
    .settings-actions .button{background:#1d2a51;color:#96a6f2}
    .message{background:#153729;border-color:#245b43;color:#79c99c}
    .order-detail-body{color:#c8cfe1}
    .order-detail-grid p{color:#8791aa}
    .order-detail-items,.order-detail-item{border-color:#26324d}
  </style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="brand"><span class="brand-mark">A</span><span>anderson</span></div>
    <nav class="nav-list">
      <a class="nav-item active" href="#dashboard"><span class="nav-icon">D</span><span>Dashboard</span></a>
      <a class="nav-item" href="#orders"><span class="nav-icon">O</span><span>Orders</span></a>
      <a class="nav-item" href="#products"><span class="nav-icon">P</span><span>Products</span></a>
      <a class="nav-item" href="#customers"><span class="nav-icon">C</span><span>Customers</span></a>
      <a class="nav-item" href="#analytics"><span class="nav-icon">A</span><span>Analytics</span></a>
      <a class="nav-item" href="#chat"><span class="nav-icon">M</span><span>Messages</span></a>
      <a class="nav-item" href="#settings"><span class="nav-icon">S</span><span>Settings</span></a>
    </nav>
    <div class="sidebar-footer">
      <div class="upgrade"><strong>Store plan</strong><span>Admin workspace active</span></div>
      <div class="profile"><span class="avatar">AS</span><span><strong>Admin Store</strong><br>Administrator</span></div>
    </div>
  </aside>
  <main class="main">
    <header class="topbar">
      <form class="page-search" onsubmit="return false"><input id="dashboardSearch" type="search" placeholder="Search products or customers"></form>
      <div class="top-actions">
        <a class="back-to-store" href="index.php" title="Back to Store"><span class="back-store-icon">&lt;</span><span>Back to Store</span></a>
        <div class="notification-wrap">
          <button class="icon-button notification-button" id="notificationButton" type="button" title="Notifications" aria-label="Notifications" aria-expanded="false">
            N
            <?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount > 99 ? '99+' : $notificationCount ?></span><?php endif; ?>
          </button>
          <div class="notification-panel" id="notificationPanel" role="dialog" aria-label="Notifications">
            <div class="notification-head"><strong>Notifications</strong><button type="button" id="markNotificationsRead">Mark all read</button></div>
            <?php if (count($pendingOrderNotifications) > 0): ?>
              <a class="notification-item" href="#orders"><span class="notification-dot">O</span><span class="notification-copy"><strong><?= count($pendingOrderNotifications) ?> pending order<?= count($pendingOrderNotifications) === 1 ? '' : 's' ?></strong><span>Review and update recent orders</span></span></a>
            <?php endif; ?>
            <?php if (count($chatConversations) > 0): ?>
              <a class="notification-item" href="#chat"><span class="notification-dot">M</span><span class="notification-copy"><strong><?= count($chatConversations) ?> customer conversation<?= count($chatConversations) === 1 ? '' : 's' ?></strong><span>Open the messages workspace</span></span></a>
            <?php endif; ?>
            <?php if ($notificationCount === 0): ?>
              <div class="notification-item"><span class="notification-dot">OK</span><span class="notification-copy"><strong>You are all caught up</strong><span>No new store notifications</span></span></div>
            <?php endif; ?>
          </div>
        </div>
        <a class="icon-button" href="#chat" title="Messages" style="display:grid;place-items:center;text-decoration:none">M</a>
        <a class="new-order" href="#products">New product</a>
        <form method="POST" style="margin:0"><button class="icon-button" type="submit" name="logout" value="1" title="Logout">X</button></form>
      </div>
    </header>
    <div class="content" id="dashboard">
      <div class="welcome-row">
        <div><h1>Welcome back, Admin</h1><p>Here is what is happening with your store today.</p></div>
      </div>
      <?php if ($message !== ''): ?><div class="admin-message" id="adminMessage"><?= htmlspecialchars($message) ?><button type="button" data-dismiss-message aria-label="Dismiss notification">×</button></div><?php endif; ?>
      <section class="stats">
        <article class="stat"><span class="stat-icon">$</span><div><div class="stat-label">Total sales</div><strong>$<?= number_format($totalRevenue,2) ?></strong><small>Live</small></div></article>
        <article class="stat"><span class="stat-icon">O</span><div><div class="stat-label">Orders</div><strong><?= count($orders) ?></strong><small><?= $pendingOrders ?> pending</small></div></article>
        <article class="stat"><span class="stat-icon">C</span><div><div class="stat-label">Customers</div><strong><?= $totalCustomers ?></strong><small>Unique</small></div></article>
        <article class="stat"><span class="stat-icon">P</span><div><div class="stat-label">Conversion</div><strong><?= count($orders) ? number_format(count($orders)/(count($orders)+$totalCustomers)*100,1) : '0.0' ?>%</strong><small>Tracked</small></div></article>
      </section>
      <div class="dashboard-grid">
        <div>
          <section class="card" id="analytics">
            <div class="card-head"><h2>Overview</h2><a class="text-link" href="#orders">This week</a></div>
            <div class="chart-area">
              <span class="chart-label one">$<?= number_format($chartMax,0) ?></span><span class="chart-label two">$<?= number_format($chartMax/2,0) ?></span><span class="chart-label three">$0</span>
              <div class="bars">
                <?php foreach ($dailyRevenue as $index => $value): ?>
                  <?php $height = max(2, round(($value/$chartMax)*100)); ?>
                  <div class="bar-wrap"><div class="bar" style="height:<?= $height ?>%" title="$<?= number_format($value,2) ?>"></div><span class="bar-day"><?= ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$index] ?></span></div>
                <?php endforeach; ?>
              </div>
            </div>
          </section>
          <div class="two-col">
            <section class="card product-manager" id="products">
              <div class="card-head"><h2>View Products</h2></div>
              <form method="POST" id="productTableForm">
                <div class="product-toolbar">
                  <button class="mini-button" type="button" data-open-add>Add</button>
                  <button class="mini-button danger" type="submit" name="bulk_delete_products" value="1" onclick="return confirm('Remove selected products?')">Delete</button>
                  <button class="filter-link active" type="button" data-category-filter="all">All Products</button>
                  <button class="filter-link" type="button" data-category-filter="featured">Featured</button>
                  <button class="filter-link" type="button" data-category-filter="new">New</button>
                  <button class="filter-link" type="button" data-category-filter="out">Out of Stock</button>
                  <button class="filter-link" type="button" data-category-filter="visible">Visible</button>
                  <input class="product-search" id="productManagerSearch" type="search" placeholder="Search products">
                </div>
                <div class="table-wrap">
                  <table class="product-table"><thead><tr><th><input class="checkbox" type="checkbox" id="selectAllProducts"></th><th>Image</th><th>Product</th><th>Product Code</th><th>Stock Level</th><th>Price</th><th></th></tr></thead><tbody>
                  <?php foreach ($products as $product): ?>
                    <?php $productId = (int) ($product['id'] ?? 0); ?>
                    <tr data-search="<?= htmlspecialchars(strtolower((string) ($product['name'] ?? '') . ' ' . (string) ($product['category'] ?? '') . ' ' . (string) ($product['badge'] ?? ''))) ?>" data-category="<?= htmlspecialchars(strtolower((string) ($product['category'] ?? ''))) ?>" data-badge="<?= htmlspecialchars(strtolower((string) ($product['badge'] ?? ''))) ?>">
                      <td><input class="checkbox product-check" type="checkbox" name="selected_products[]" value="<?= $productId ?>"></td>
                      <td><img class="product-image" src="<?= htmlspecialchars((string) ($product['image'] ?? '')) ?>" alt=""></td>
                      <td><div class="product-name-cell"><div><strong><?= htmlspecialchars((string) ($product['name'] ?? 'Unnamed')) ?></strong><span><?= htmlspecialchars((string) ($product['category'] ?? 'Product')) ?></span></div></div></td>
                      <td>SP<?= str_pad((string) $productId, 3, '0', STR_PAD_LEFT) ?></td>
                      <td><span class="stock"><?= $productId % 4 === 0 ? '<span class="stock low">Low stock</span>' : '93 in stock' ?></span></td>
                      <td><strong>$<?= number_format((float) ($product['price'] ?? 0),2) ?></strong></td>
                      <td><button class="row-actions" type="button" data-row-menu="<?= $productId ?>" data-product="<?= htmlspecialchars(json_encode($product, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>" aria-label="Product actions">•••</button></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody></table>
                </div>
              </form>
            </section>
            <section class="card order-manager" id="orders">
              <div class="card-head"><h2>Orders</h2></div>
              <div class="order-toolbar">
                <button class="order-tab active" type="button" data-order-filter="all">All</button>
                <button class="order-tab" type="button" data-order-filter="pending">Pending</button>
                <button class="order-tab" type="button" data-order-filter="confirmed">Confirmed</button>
                <button class="order-tab" type="button" data-order-filter="processing">Processing</button>
                <button class="order-tab" type="button" data-order-filter="shipped">Shipped</button>
                <button class="order-tab" type="button" data-order-filter="delivered">Delivered</button>
                <button class="order-tab" type="button" data-order-filter="cancelled">Cancelled</button>
                <input class="order-search" id="orderManagerSearch" type="search" placeholder="Search orders">
              </div>
              <div class="table-wrap">
                <table class="order-table"><thead><tr><th>Order ID</th><th>User</th><th>Product</th><th>Quantity</th><th>Date</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <?php if (!$orders): ?><tr><td colspan="8" style="text-align:center;color:var(--muted)">No orders have been placed yet.</td></tr><?php endif; ?>
                <?php foreach ($orders as $order): ?>
                  <?php
                    $firstItem = ($order['items'][0] ?? null);
                    $quantity = 0;
                    foreach (($order['items'] ?? []) as $item) $quantity += (int) ($item['quantity'] ?? 1);
                    $status = strtolower((string) ($order['status'] ?? 'Pending payment'));
                    $statusClass = str_contains($status, 'pending') ? 'pending' : (str_contains($status,'confirm')?'confirmed':(str_contains($status,'process')?'processing':(str_contains($status,'ship')?'shipped':(str_contains($status,'deliver')?'delivered':(str_contains($status,'cancel')?'cancelled':'pending')))));
                  ?>
                  <tr class="order-row-data" data-order-search="<?= htmlspecialchars(strtolower((string) ($order['id'] ?? '') . ' ' . (string) ($order['first_name'] ?? '') . ' ' . (string) ($order['last_name'] ?? '') . ' ' . (string) ($firstItem['product_name'] ?? ''))) ?>" data-order-status="<?= $statusClass ?>" data-order="<?= htmlspecialchars(json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>">
                    <td>#BK-<?= (int) $order['id'] ?></td>
                    <td><?= htmlspecialchars((string) (($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars((string) ($firstItem['product_name'] ?? 'Product')) ?></td>
                    <td><?= $quantity ?></td>
                    <td><?= htmlspecialchars(substr((string) ($order['created_at'] ?? ''),0,16)) ?></td>
                    <td>$<?= number_format((float) ($order['total'] ?? 0),2) ?></td>
                    <td><form class="order-status-form" method="POST"><input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>"><select class="order-status <?= $statusClass ?>" name="order_status" onchange="this.form.submit()" aria-label="Order status"><option value="Pending payment" <?= str_contains($status,'pending')?'selected':'' ?>>Pending</option><option value="Confirmed" <?= str_contains($status,'confirm')?'selected':'' ?>>Confirmed</option><option value="Processing" <?= str_contains($status,'process')?'selected':'' ?>>Processing</option><option value="Shipped" <?= str_contains($status,'ship')?'selected':'' ?>>Shipped</option><option value="Delivered" <?= str_contains($status,'deliver')?'selected':'' ?>>Delivered</option><option value="Cancelled" <?= str_contains($status,'cancel')?'selected':'' ?>>Cancelled</option><option value="Refunded" <?= str_contains($status,'refund')?'selected':'' ?>>Refunded</option></select><button type="submit" name="update_order_status" value="1" hidden></button></form></td>
                    <td><button class="order-view" type="button" data-view-order aria-label="View order">◉</button></td>
                  </tr>
                <?php endforeach; ?>
                </tbody></table>
              </div>
              <div class="order-pagination"><span id="orderShowing">Showing 10 of <?= count($orders) ?> orders</span><div class="order-pages" id="orderPages"></div></div>
            </section>
          </div>
        </div>
        <aside class="side-column">
          <section class="card">
            <div class="card-head"><h2>Sales by Channel</h2></div>
            <div class="donut"></div>
            <div class="legend"><div><span>Online store</span><strong>$<?= number_format($totalRevenue*.60,2) ?></strong></div><div><span>POS terminal</span><strong>$<?= number_format($totalRevenue*.25,2) ?></strong></div><div><span>Mobile app</span><strong>$<?= number_format($totalRevenue*.15,2) ?></strong></div></div>
          </section>
          <section class="card" id="customers">
            <div class="card-head"><h2>Checkout Summary</h2></div>
            <div class="summary-list"><div><span>Subtotal</span><strong>$<?= number_format($totalRevenue*.96,2) ?></strong></div><div><span>Tax</span><strong>$<?= number_format($totalRevenue*.04,2) ?></strong></div><div><span>Total</span><strong>$<?= number_format($totalRevenue,2) ?></strong></div></div>
            <a href="#orders"><button class="checkout">Review orders</button></a>
          </section>
          <section class="card">
            <div class="card-head"><h2>Recent Products</h2></div>
            <div class="recent-list">
              <?php foreach (array_slice($products,0,4) as $product): ?><div class="recent-item"><img src="<?= htmlspecialchars((string) ($product['image'] ?? '')) ?>" alt=""><div><strong><?= htmlspecialchars((string) ($product['name'] ?? 'Unnamed')) ?></strong><span><?= htmlspecialchars((string) ($product['category'] ?? 'Product')) ?></span></div><strong>$<?= number_format((float) ($product['price'] ?? 0),2) ?></strong></div><?php endforeach; ?>
            </div>
          </section>
          <section class="promo"><div><h2>Grow your store</h2><p>Add products and manage<br>every customer order online.</p><a href="#add-product"><button type="button">Manage store</button></a></div><div class="promo-art">AS</div></section>
        </aside>
      </div>

      <section class="card section" id="add-product">
        <div class="card-head"><h2>Add Product</h2></div>
        <form class="form-grid" method="POST" enctype="multipart/form-data">
          <div class="add-grid"><input type="text" name="product_name" placeholder="Product name" required><input type="number" name="price" min="0.01" step="0.01" placeholder="Price" required></div>
          <div class="add-grid"><select name="category"><option value="outerwear">Outerwear</option><option value="tees">Tees</option><option value="footwear">Footwear</option><option value="accessories">Accessories</option></select><input type="text" name="badge" value="New"></div>
          <div class="add-grid"><input type="number" name="rating" min="0" max="5" step="0.1" value="4.8"><input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif"></div>
          <textarea name="description" placeholder="Short product description">Premium essentials designed for streetwear everyday wear.</textarea>
          <button class="checkout" type="submit" style="margin:0">Save product</button>
        </form>
      </section>

      <section class="card section" id="chat">
        <div class="card-head"><h2>Customer Chat</h2><span class="status"><?= count($chatConversations) ?> conversations</span></div>
        <?php if (!$chatConversations): ?><p style="font-size:11px;color:var(--muted)">No customer conversations yet.</p><?php endif; ?>
        <?php foreach ($chatConversations as $conversation): ?>
          <?php $chatMessages = load_chat_messages($conversation['conversation_token']); ?>
          <article class="chat-conversation"><strong><?= htmlspecialchars($conversation['customer_name']) ?></strong>
            <?php foreach ($chatMessages as $chatMessage): ?><div class="chat-message <?= $chatMessage['sender'] === 'admin' ? 'admin' : '' ?>"><strong><?= $chatMessage['sender'] === 'admin' ? 'You' : 'Customer' ?>:</strong> <?= nl2br(htmlspecialchars($chatMessage['message'])) ?><small><?= htmlspecialchars($chatMessage['created_at']) ?></small></div><?php endforeach; ?>
            <form method="POST"><input type="hidden" name="conversation_token" value="<?= htmlspecialchars($conversation['conversation_token']) ?>"><input type="text" name="reply_message" placeholder="Write a reply" required><button class="mini-button" type="submit" name="reply_chat" value="1">Send</button></form>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="card section settings-manager" id="settings">
        <div class="card-head"><h2>General Store</h2><div><button class="mini-button" type="button" id="clearCacheButton">Clear Cache</button> <a class="new-order" href="index.php" target="_blank">Visit Store</a></div></div>
        <div class="settings-tabs">
          <button class="settings-tab active" type="button" data-settings-tab="profile">Store Profile</button>
          <button class="settings-tab" type="button" data-settings-tab="shipping">Shipping Settings</button>
          <button class="settings-tab" type="button" data-settings-tab="sms">SMS</button>
          <button class="settings-tab" type="button" data-settings-tab="email">Email</button>
          <button class="settings-tab" type="button" data-settings-tab="tracking">Tracking and Analytics</button>
          <button class="settings-tab" type="button" data-settings-tab="regional">Regional Settings</button>
          <button class="settings-tab" type="button" data-settings-tab="seo">SEO</button>
          <button class="settings-tab" type="button" data-settings-tab="account">Store Account</button>
        </div>
        <form method="POST" id="storeSettingsForm">
          <div class="settings-pane active" data-settings-pane="profile">
            <div class="settings-section"><h3>Store Name and Web Address</h3><p>Control the public identity and store details customers see.</p>
              <div class="settings-form-grid">
                <label>Store Name</label><input type="text" name="store_name" value="<?= htmlspecialchars($storeSettings['store_name'] ?: 'anderson_store') ?>">
                <label>Store Timing</label><input type="text" name="store_timing" value="<?= htmlspecialchars($storeSettings['store_timing'] ?: '10:00 AM to 09:00 PM') ?>">
                <label>Opening and Closing Time</label><div class="add-grid"><input type="time" name="opening_time" value="<?= htmlspecialchars($storeSettings['opening_time']) ?>"><input type="time" name="closing_time" value="<?= htmlspecialchars($storeSettings['closing_time']) ?>"></div>
                <label>No. of products to show</label><input type="number" name="store_tagline" min="1" max="500" value="<?= htmlspecialchars($storeSettings['store_tagline'] ?: '40') ?>">
                <label>Search text</label><input type="text" name="store_search_text" value="<?= htmlspecialchars($storeSettings['store_search_text'] ?: 'Search for categories, brands and more') ?>">
                <label>Minimum Order Value</label><input type="number" name="minimum_order_value" min="0" step="0.01" value="<?= htmlspecialchars($storeSettings['minimum_order_value'] ?: '1') ?>">
                <label>Shipping Charge</label><input type="number" name="shipping_charge" min="0" step="0.01" value="<?= htmlspecialchars($storeSettings['shipping_charge'] ?: '0') ?>">
                <label>Store Address</label><textarea name="store_address"><?= htmlspecialchars($storeSettings['store_address']) ?></textarea>
              </div>
            </div>
            <div class="settings-section"><h3>Company Name and Email</h3><p>Official business contact and payment notifications.</p>
              <div class="settings-form-grid">
                <label>Company Name</label><input type="text" name="company_name" value="<?= htmlspecialchars($storeSettings['company_name'] ?: 'Anderson Store') ?>">
                <label>Company Email</label><input type="email" name="company_email" value="<?= htmlspecialchars($storeSettings['company_email']) ?>">
                <label>Company Phone</label><input type="tel" name="company_phone" value="<?= htmlspecialchars($storeSettings['company_phone'] ?: $paymentNumber) ?>">
                <label>Customer Payment Number</label><input type="tel" name="merchant_payment_number" value="<?= htmlspecialchars($paymentNumber) ?>" pattern="(07[0-9]{8}|+2507[0-9]{8})">
              </div>
            </div>
          </div>
          <div class="settings-pane" data-settings-pane="shipping">
            <div class="settings-section"><h3>Shipping Settings</h3><p>Configure delivery methods and charges.</p><div class="settings-form-grid">
              <label>Shipping Method</label><select name="shipping_method"><option value="standard" <?= $storeSettings['shipping_method']==='standard'?'selected':'' ?>>Standard Delivery</option><option value="express" <?= $storeSettings['shipping_method']==='express'?'selected':'' ?>>Express Delivery</option><option value="pickup" <?= $storeSettings['shipping_method']==='pickup'?'selected':'' ?>>Store Pickup</option></select>
              <label>Shipping Charge</label><input type="number" name="shipping_charge" min="0" step="0.01" value="<?= htmlspecialchars($storeSettings['shipping_charge'] ?: '0') ?>">
              <label>Minimum Order Value</label><input type="number" name="minimum_order_value" min="0" step="0.01" value="<?= htmlspecialchars($storeSettings['minimum_order_value'] ?: '1') ?>">
            </div></div>
          </div>
          <div class="settings-pane" data-settings-pane="sms">
            <div class="settings-section"><h3>SMS Notifications</h3><p>Configure sender details for order and payment alerts.</p><div class="settings-form-grid">
              <label>SMS Provider</label><select name="sms_provider"><option value="">Select provider</option><option value="mtn" <?= $storeSettings['sms_provider']==='mtn'?'selected':'' ?>>MTN</option><option value="airtel" <?= $storeSettings['sms_provider']==='airtel'?'selected':'' ?>>Airtel</option></select>
              <label>Sender Name</label><input type="text" name="sms_sender" maxlength="11" value="<?= htmlspecialchars($storeSettings['sms_sender']) ?>">
              <label>API Key</label><input type="password" name="sms_api_key" autocomplete="off" value="<?= htmlspecialchars($storeSettings['sms_api_key']) ?>">
            </div></div>
          </div>
          <div class="settings-pane" data-settings-pane="email">
            <div class="settings-section"><h3>Email Notifications</h3><p>Control sender and reply addresses used by the store.</p><div class="settings-form-grid">
              <label>Sender Email</label><input type="email" name="email_sender" value="<?= htmlspecialchars($storeSettings['email_sender'] ?: 'orders@andersonstore.com') ?>">
              <label>Reply-to Email</label><input type="email" name="email_reply_to" value="<?= htmlspecialchars($storeSettings['email_reply_to']) ?>">
            </div></div>
          </div>
          <div class="settings-pane" data-settings-pane="tracking">
            <div class="settings-section"><h3>Tracking and Analytics</h3><p>Connect analytics and monitoring for the storefront.</p><div class="settings-form-grid">
              <label>Analytics Code</label><textarea name="analytics_code"><?= htmlspecialchars($storeSettings['analytics_code']) ?></textarea>
              <label>Tracking Status</label><select name="tracking_enabled"><option value="enabled" <?= $storeSettings['tracking_enabled']==='enabled'?'selected':'' ?>>Enabled</option><option value="disabled" <?= $storeSettings['tracking_enabled']==='disabled'?'selected':'' ?>>Disabled</option></select>
            </div></div>
          </div>
          <div class="settings-pane" data-settings-pane="regional">
            <div class="settings-section"><h3>Regional Settings</h3><p>Set the default region, currency, and language.</p><div class="settings-form-grid">
              <label>Default Region</label><select name="default_region"><option value="RW" <?= $storeSettings['default_region']==='RW'?'selected':'' ?>>Rwanda</option><option value="UG" <?= $storeSettings['default_region']==='UG'?'selected':'' ?>>Uganda</option><option value="KE" <?= $storeSettings['default_region']==='KE'?'selected':'' ?>>Kenya</option><option value="TZ" <?= $storeSettings['default_region']==='TZ'?'selected':'' ?>>Tanzania</option></select>
              <label>Currency</label><select name="regional_currency"><option value="RWF" <?= $storeSettings['regional_currency']==='RWF'?'selected':'' ?>>RWF</option><option value="USD" <?= $storeSettings['regional_currency']==='USD'?'selected':'' ?>>USD</option></select>
              <label>Default Language</label><select name="default_language"><option value="en" <?= $storeSettings['default_language']==='en'?'selected':'' ?>>English</option><option value="fr" <?= $storeSettings['default_language']==='fr'?'selected':'' ?>>French</option><option value="rw" <?= $storeSettings['default_language']==='rw'?'selected':'' ?>>Kinyarwanda</option></select>
            </div></div>
          </div>
          <div class="settings-pane" data-settings-pane="seo">
            <div class="settings-section"><h3>Search Engine Optimization</h3><p>Control how the store appears in search engines.</p><div class="settings-form-grid">
              <label>SEO Title</label><input type="text" name="seo_title" value="<?= htmlspecialchars($storeSettings['seo_title']) ?>">
              <label>SEO Description</label><textarea name="seo_description"><?= htmlspecialchars($storeSettings['seo_description']) ?></textarea>
              <label>Keywords</label><input type="text" name="seo_keywords" value="<?= htmlspecialchars($storeSettings['seo_keywords']) ?>">
            </div></div>
          </div>
          <div class="settings-pane" data-settings-pane="account">
            <div class="settings-section"><h3>Admin Account</h3><p>Administrator access and store security information.</p><div class="settings-form-grid">
              <label>Admin Username</label><input type="text" value="admin" readonly>
              <label>Account Role</label><input type="text" value="Administrator" readonly>
              <label class="settings-note">Password changes require the account security flow.</label><button class="button" type="button" onclick="window.location.href='verify_admin.php'">Security Settings</button>
            </div></div>
          </div>
          <div class="settings-actions"><button class="button" type="reset">Reset</button><button class="checkout" type="submit" name="save_store_settings" value="1" style="width:auto;padding:0 16px;margin:0">Save Settings</button></div>
        </form>
      </section>
    </div>
  </main>
</div>
<div class="order-modal" id="orderModal" role="dialog" aria-modal="true">
  <div class="order-detail">
    <div class="order-detail-head"><strong id="orderDetailTitle">Order Details</strong><button class="modal-close" type="button" data-close-order>×</button></div>
    <div class="order-detail-body" id="orderDetailBody"></div>
  </div>
</div>
<div class="product-menu" id="productMenu" role="menu">
  <button type="button" data-menu-action="edit">Edit</button>
  <button type="button" data-menu-action="delete" class="menu-delete">Delete</button>
  <button type="button" data-menu-action="copy">Copy</button>
  <button type="button" data-menu-action="orders">View Orders</button>
  <button type="button" data-menu-action="preview">Preview</button>
</div>
<div class="product-modal" id="productModal" role="dialog" aria-modal="true" aria-labelledby="productModalTitle">
  <div class="modal-panel">
    <div class="modal-head"><strong id="productModalTitle">General</strong><button class="modal-close" type="button" data-close-modal aria-label="Close">×</button></div>
    <div class="modal-body">
      <form method="POST" enctype="multipart/form-data" class="modal-grid" id="editProductForm">
        <input type="hidden" name="product_id" id="editProductId">
        <label>Product name*<input type="text" name="edit_name" id="editName" required></label>
        <label>Status<select name="edit_badge" id="editStatus"><option value="New">Enabled</option><option value="Draft">Draft</option><option value="Visible">Visible</option><option value="Hidden">Hidden</option></select></label>
        <label>SKU*<input type="text" id="editSku" value="" readonly></label>
        <label>Category<select name="edit_category" id="editCategory"><option value="outerwear">Outerwear</option><option value="tees">Tees</option><option value="footwear">Footwear</option><option value="accessories">Accessories</option></select></label>
        <label>Price<input type="number" name="edit_price" id="editPrice" min="0.01" step="0.01" required></label>
        <label>Weight<input type="number" id="editWeight" value="0.46" step="0.01"></label>
        <label>Rating<input type="number" name="edit_rating" id="editRating" min="0" max="5" step="0.1"></label>
        <label class="span-two">Replacement image<input type="file" name="edit_image_file" accept="image/jpeg,image/png,image/webp,image/gif"></label>
        <label class="span-two">Description<textarea name="edit_description" id="editDescription"></textarea></label>
        <div class="modal-actions span-two"><button class="button" type="button" data-close-modal>Cancel</button><button class="mini-button" type="submit" name="save_product_edit" value="1">Save product</button></div>
      </form>
    </div>
  </div>
</div>
<script>
  var notificationButton=document.getElementById('notificationButton');
  var notificationPanel=document.getElementById('notificationPanel');
  notificationButton.addEventListener('click',function(event){
    event.stopPropagation();
    var isOpen=notificationPanel.classList.toggle('open');
    notificationButton.setAttribute('aria-expanded',isOpen?'true':'false');
  });
  notificationPanel.addEventListener('click',function(event){event.stopPropagation()});
  document.addEventListener('click',function(){
    notificationPanel.classList.remove('open');
    notificationButton.setAttribute('aria-expanded','false');
  });
  document.getElementById('markNotificationsRead').addEventListener('click',function(){
    var badge=document.querySelector('.notification-badge');
    if(badge)badge.remove();
    notificationPanel.classList.remove('open');
    notificationButton.setAttribute('aria-expanded','false');
  });
  var dismissMessage=document.querySelector('[data-dismiss-message]');
  if(dismissMessage){
    dismissMessage.addEventListener('click',function(){document.getElementById('adminMessage').remove()});
  }
  document.querySelectorAll('.nav-item').forEach(function(link){
    link.addEventListener('click',function(){document.querySelectorAll('.nav-item').forEach(function(item){item.classList.remove('active')});link.classList.add('active')});
  });
  document.getElementById('dashboardSearch').addEventListener('input',function(){
    var query=this.value.toLowerCase().trim();
    document.querySelectorAll('[data-search]').forEach(function(row){row.style.display=!query||row.dataset.search.includes(query)?'':'none'});
  });
  var menu=document.getElementById('productMenu');
  var modal=document.getElementById('productModal');
  var activeProduct=null;
  var activeButton=null;

  document.querySelectorAll('[data-row-menu]').forEach(function(button){
    button.addEventListener('click',function(event){
      event.stopPropagation();
      activeButton=button;
      activeProduct=JSON.parse(button.dataset.product);
      menu.style.left=Math.min(event.clientX-125,window.innerWidth-160)+'px';
      menu.style.top=(event.clientY+4)+'px';
      menu.classList.add('open');
    });
  });
  document.addEventListener('click',function(){menu.classList.remove('open')});
  menu.addEventListener('click',function(event){event.stopPropagation()});

  function submitProductAction(actionName, productId){
    var form=document.createElement('form');
    form.method='POST';
    var input=document.createElement('input');
    input.type='hidden'; input.name='product_id'; input.value=productId; form.appendChild(input);
    var action=document.createElement('input');
    action.type='hidden'; action.name=actionName; action.value='1'; form.appendChild(action);
    document.body.appendChild(form);
    form.submit();
  }
  function openEditModal(product){
    document.getElementById('editProductId').value=product.id||0;
    document.getElementById('editName').value=product.name||'';
    document.getElementById('editSku').value='SP'+String(product.id||0).padStart(3,'0');
    document.getElementById('editPrice').value=Number(product.price||0).toFixed(2);
    document.getElementById('editRating').value=Number(product.rating||4.8).toFixed(1);
    document.getElementById('editDescription').value=product.description||'';
    var category=document.getElementById('editCategory');
    category.value=product.category||'tees';
    var status=document.getElementById('editStatus');
    var badge=product.badge||'New';
    var option=[...status.options].find(function(item){return item.value===badge});
    if(!option){option=document.createElement('option');option.value=badge;status.appendChild(option)}
    status.value=badge;
    modal.classList.add('open');
  }
  menu.querySelector('[data-menu-action="edit"]').addEventListener('click',function(){if(activeProduct)openEditModal(activeProduct);menu.classList.remove('open')});
  menu.querySelector('[data-menu-action="copy"]').addEventListener('click',function(){if(activeProduct)submitProductAction('copy_product',activeProduct.id||0)});
  menu.querySelector('[data-menu-action="delete"]').addEventListener('click',function(){if(activeProduct&&confirm('Remove this product?'))submitProductAction('delete_product',activeProduct.id||0)});
  menu.querySelector('[data-menu-action="orders"]').addEventListener('click',function(){menu.classList.remove('open');document.getElementById('orders').scrollIntoView({behavior:'smooth'})});
  menu.querySelector('[data-menu-action="preview"]').addEventListener('click',function(){if(activeProduct)window.location.href='product.php?id='+encodeURIComponent(activeProduct.id||0)});
  document.querySelectorAll('[data-close-modal]').forEach(function(button){button.addEventListener('click',function(){modal.classList.remove('open')})});
  modal.addEventListener('click',function(event){if(event.target===modal)modal.classList.remove('open')});
  document.querySelectorAll('[data-open-add]').forEach(function(button){button.addEventListener('click',function(){document.getElementById('add-product').scrollIntoView({behavior:'smooth'})})});

  var selectAll=document.getElementById('selectAllProducts');
  selectAll.addEventListener('change',function(){document.querySelectorAll('.product-check').forEach(function(check){check.checked=selectAll.checked})});

  var managerSearch=document.getElementById('productManagerSearch');
  var activeFilter='all';
  function filterProductTable(){
    var query=managerSearch.value.toLowerCase().trim();
    document.querySelectorAll('.product-table tbody tr').forEach(function(row){
      var visible=(!query||row.dataset.search.includes(query));
      if(activeFilter==='new')visible=visible&&row.dataset.badge.includes('new');
      if(activeFilter==='featured')visible=visible&&/featured|popular|best/.test(row.dataset.badge);
      if(activeFilter==='visible')visible=visible&&!/draft|hidden/.test(row.dataset.badge);
      if(activeFilter==='out')visible=visible&&Number(row.querySelector('[value]').value)%4===0;
      row.style.display=visible?'':'none';
    });
  }
  managerSearch.addEventListener('input',filterProductTable);
  document.querySelectorAll('[data-category-filter]').forEach(function(button){
    button.addEventListener('click',function(){
      document.querySelectorAll('[data-category-filter]').forEach(function(item){item.classList.remove('active')});
      button.classList.add('active');
      activeFilter=button.dataset.categoryFilter;
      filterProductTable();
    });
  });

  var orderSearch=document.getElementById('orderManagerSearch');
  var orderFilter='all';
  var orderPage=1;
  var pageSize=10;
  function visibleOrderRows(){
    var query=(orderSearch.value||'').toLowerCase().trim();
    return [...document.querySelectorAll('.order-row-data')].filter(function(row){
      var matchesSearch=!query||row.dataset.orderSearch.includes(query);
      var matchesFilter=orderFilter==='all'||row.dataset.orderStatus===orderFilter;
      return matchesSearch&&matchesFilter;
    });
  }
  function renderOrderPagination(){
    var rows=visibleOrderRows();
    var totalPages=Math.max(1,Math.ceil(rows.length/pageSize));
    if(orderPage>totalPages)orderPage=totalPages;
    document.querySelectorAll('.order-row-data').forEach(function(row){row.style.display='none'});
    rows.slice((orderPage-1)*pageSize,orderPage*pageSize).forEach(function(row){row.style.display=''});
    var start=rows.length?(orderPage-1)*pageSize+1:0;
    var end=Math.min(orderPage*pageSize,rows.length);
    document.getElementById('orderShowing').textContent='Showing '+start+'-'+end+' of '+rows.length+' orders';
    var pages=document.getElementById('orderPages');
    pages.innerHTML='';
    var previous=document.createElement('button');previous.className='order-page';previous.textContent='‹';previous.onclick=function(){if(orderPage>1){orderPage--;renderOrderPagination()}};pages.appendChild(previous);
    for(var page=1;page<=totalPages;page++){
      (function(pageNumber){var button=document.createElement('button');button.className='order-page'+(pageNumber===orderPage?' active':'');button.textContent=pageNumber;button.onclick=function(){orderPage=pageNumber;renderOrderPagination()};pages.appendChild(button)})(page);
    }
    var next=document.createElement('button');next.className='order-page';next.textContent='›';next.onclick=function(){if(orderPage<totalPages){orderPage++;renderOrderPagination()}};pages.appendChild(next);
  }
  orderSearch.addEventListener('input',function(){orderPage=1;renderOrderPagination()});
  document.querySelectorAll('[data-order-filter]').forEach(function(button){
    button.addEventListener('click',function(){
      document.querySelectorAll('[data-order-filter]').forEach(function(item){item.classList.remove('active')});
      button.classList.add('active');orderFilter=button.dataset.orderFilter;orderPage=1;renderOrderPagination();
    });
  });
  var orderModal=document.getElementById('orderModal');
  document.querySelectorAll('[data-view-order]').forEach(function(button){
    button.addEventListener('click',function(){
      var order=JSON.parse(button.closest('tr').dataset.order);
      document.getElementById('orderDetailTitle').textContent='Order #BK-'+order.id;
      var items=(order.items||[]).map(function(item){return '<div class="order-detail-item"><span>'+escapeHtml(item.product_name||'Product')+' × '+(item.quantity||1)+(item.size?' · '+escapeHtml(item.size):'')+'</span><strong>$'+Number(item.product_price||0).toFixed(2)+'</strong></div>'}).join('');
      document.getElementById('orderDetailBody').innerHTML='<div class="order-detail-grid"><div><strong>Customer</strong><p>'+escapeHtml((order.first_name||'')+' '+(order.last_name||''))+'</p><p>'+escapeHtml(order.email||'')+'</p></div><div><strong>Payment</strong><p>'+escapeHtml(order.payment_method==='mtn_momo'?'MTN MoMo':'Airtel Money')+'</p><p>'+escapeHtml(order.payment_phone||'')+'</p></div><div><strong>Delivery address</strong><p>'+escapeHtml((order.address||'')+', '+(order.city||'')+', '+(order.country||''))+'</p></div><div><strong>Order</strong><p>'+escapeHtml(order.status||'Pending payment')+'</p><p>'+escapeHtml(order.created_at||'')+'</p></div></div><div class="order-detail-items"><strong>Products</strong>'+items+'<div class="order-detail-item"><span>Total</span><strong>$'+Number(order.total||0).toFixed(2)+' '+escapeHtml(order.currency||'USD')+'</strong></div></div>';
      orderModal.classList.add('open');
    });
  });
  function escapeHtml(value){return String(value).replace(/[&<>"']/g,function(char){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]})}
  document.querySelectorAll('[data-close-order]').forEach(function(button){button.addEventListener('click',function(){orderModal.classList.remove('open')})});
  orderModal.addEventListener('click',function(event){if(event.target===orderModal)orderModal.classList.remove('open')});
  renderOrderPagination();
  document.querySelectorAll('[data-settings-tab]').forEach(function(button){
    button.addEventListener('click',function(){
      document.querySelectorAll('[data-settings-tab]').forEach(function(item){item.classList.remove('active')});
      document.querySelectorAll('[data-settings-pane]').forEach(function(pane){pane.classList.remove('active')});
      button.classList.add('active');
      var pane=document.querySelector('[data-settings-pane="'+button.dataset.settingsTab+'"]');
      if(pane)pane.classList.add('active');
    });
  });
  var clearCacheButton=document.getElementById('clearCacheButton');
  clearCacheButton.addEventListener('click',function(){
    clearCacheButton.textContent='Cache Cleared';
    clearCacheButton.disabled=true;
    if(window.caches && caches.keys)caches.keys().then(function(keys){keys.forEach(function(key){caches.delete(key)})});
    window.setTimeout(function(){clearCacheButton.textContent='Clear Cache';clearCacheButton.disabled=false},1800);
  });
</script>
</body>
</html>
