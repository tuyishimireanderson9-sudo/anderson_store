<?php
require __DIR__ . '/config.php';
require_admin_login();

$file = __DIR__ . '/data/products.json';
$products = load_products();

$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($_POST['action'] ?? '') === 'delete') {
    $productId = (string) ($_POST['product_id'] ?? '');
    $productToDelete = null;

    foreach ($products as $product) {
      if ((string) ($product['id'] ?? '') === $productId) {
        $productToDelete = $product;
        break;
      }
    }

    if ($productToDelete) {
      $deletedFromDatabase = delete_product((int) $productId);
      $products = array_values(array_filter(
        $products,
        static fn (array $product): bool => (string) ($product['id'] ?? '') !== $productId
      ));
      file_put_contents($file, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

      $imagePath = (string) ($productToDelete['image'] ?? '');
      $imageFile = __DIR__ . '/' . ltrim($imagePath, '/\\');
      if (
        str_starts_with($imagePath, 'uploads/product-images/')
        && basename($imagePath) !== 'default-product.svg'
        && is_file($imageFile)
      ) {
        unlink($imageFile);
      }

      $_SESSION['flash_message'] = 'Product removed successfully.';
    } else {
      $_SESSION['flash_message'] = 'That product could not be found.';
    }

    header('Location: admin.php');
    exit;
  }

  $name = trim($_POST['name'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? 'tees');
    $badge = trim($_POST['badge'] ?? 'New');
    $rating = (float) ($_POST['rating'] ?? 4.8);
    $fallbackImage = 'uploads/product-images/default-product.svg';
    $imageUpload = save_uploaded_product_image($_FILES['image_file'] ?? null, $fallbackImage);

    if ($imageUpload['error'] !== '') {
      $_SESSION['flash_message'] = $imageUpload['error'];
      header('Location: admin.php');
      exit;
    }

    $image = $imageUpload['path'];

    if ($name !== '') {
      if (!is_dir(__DIR__ . '/uploads/product-images')) {
        mkdir(__DIR__ . '/uploads/product-images', 0755, true);
      }

      if (!file_exists(__DIR__ . '/uploads/product-images/default-product.svg')) {
        file_put_contents(
          __DIR__ . '/uploads/product-images/default-product.svg',
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 1100"><rect width="900" height="1100" fill="#e8e0d6"/><path d="M250 860c30-210 80-350 200-350s170 140 200 350" fill="#1d1d1b"/><circle cx="450" cy="310" r="105" fill="#c8b8a8"/><text x="450" y="1010" text-anchor="middle" fill="#1d1d1b" font-family="sans-serif" font-size="32" letter-spacing="6">ANDERSON STORE</text></svg>'
        );
      }

        $products[] = [
            'id' => time(),
            'name' => $name,
            'price' => $price,
            'category' => $category,
            'badge' => $badge,
            'rating' => $rating,
            'image' => $image,
        ];

        file_put_contents($file, json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $_SESSION['flash_message'] = 'Product added successfully.';
        header('Location: admin.php');
        exit;
    }

    $_SESSION['flash_message'] = 'Please add a product name.';
    header('Location: admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin | Anderson Store</title>
  <style>
    :root { --bg:#f4efe9; --panel:#fff; --text:#171717; --muted:#5b5854; --line:#e5dfd8; --accent:#111; }
    *{box-sizing:border-box} body{margin:0;font-family:'Inter',sans-serif;background:var(--bg);color:var(--text)}
    .wrap{max-width:1100px;margin:40px auto;padding:0 20px} .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:26px}
    .brand{font-size:1.2rem;font-weight:800;letter-spacing:.18em} .nav{display:flex;gap:12px;flex-wrap:wrap}
    .nav a,.button{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:999px;text-decoration:none;border:1px solid var(--line);background:#fff;color:var(--text);font-weight:600}
    .button.primary{background:var(--accent);color:#fff;border-color:var(--accent)}
    .button.danger{background:#fff4f2;color:#a53325;border-color:#e7b8b0}
    .grid{display:grid;grid-template-columns:1.1fr 0.9fr;gap:24px}
    .card{background:var(--panel);border:1px solid var(--line);border-radius:24px;padding:22px;box-shadow:0 12px 30px rgba(10,10,10,.04)}
    h1,h2,h3{margin:0 0 14px} p{color:var(--muted)}
    form {display:grid;gap:14px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    input, select {width:100%;padding:12px 14px;border-radius:12px;border:1px solid var(--line);font:inherit;background:#faf8f5}
    .upload-field {display:grid;gap:8px;color:var(--muted);font-size:.9rem;font-weight:600}
    .upload-field input {padding:10px;color:var(--text);font-weight:400}
    .upload-field small {font-weight:400}
    .message{padding:12px 14px;border-radius:12px;background:#e9f7ee;color:#1c5d3b;border:1px solid #bfe2cb;margin-bottom:16px}
    table{width:100%;border-collapse:collapse;margin-top:18px} th,td{padding:12px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:middle}
    img{width:60px;height:60px;border-radius:12px;object-fit:cover}
    @media (max-width: 760px){.grid,.row{grid-template-columns:1fr} .top{display:grid;gap:12px}} 
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div class="brand">anderson_store</div>
      <div class="nav">
        <a href="index.php">Store Front</a>
        <a href="admin.php">Admin</a>
      </div>
    </div>

    <div class="grid">
      <section class="card">
        <h1>Add new item</h1>
        <?php if (!empty($message)): ?>
          <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
          <input type="text" name="name" placeholder="Product name" required />
          <div class="row">
            <input type="number" step="0.01" name="price" placeholder="Price" required />
            <select name="category">
              <option value="outerwear">Outerwear</option>
              <option value="tees">Tees</option>
              <option value="footwear">Footwear</option>
              <option value="accessories">Accessories</option>
            </select>
          </div>
          <div class="row">
            <input type="text" name="badge" value="New" />
            <input type="number" step="0.1" name="rating" value="4.8" />
          </div>
          <label class="upload-field">
            Product image
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif" />
            <small>Choose a JPG, PNG, WEBP, or GIF image from your device. Maximum 5 MB.</small>
          </label>
          <button class="button primary" type="submit">Save item</button>
        </form>
      </section>

      <aside class="card">
        <h2>Current stock</h2>
        <p>These items appear on your storefront automatically.</p>
        <table>
          <thead>
            <tr>
              <th>Item</th>
              <th>Price</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <tr>
                <td>
                  <div style="display:flex;align-items:center;gap:10px;">
                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" />
                    <span><?= htmlspecialchars($product['name']) ?></span>
                  </div>
                </td>
                <td>$<?= number_format((float) $product['price'], 2) ?></td>
                <td>
                  <form method="POST" onsubmit="return confirm('Remove this product?');">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars((string) ($product['id'] ?? '')) ?>" />
                    <button class="button danger" type="submit">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </aside>
    </div>
  </div>
</body>
</html>
