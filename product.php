<?php
require __DIR__ . '/config.php';

$products = load_products();
$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$productExists = false;

if ($productId !== false && $productId !== null) {
  foreach ($products as $product) {
    if ((int) ($product['id'] ?? 0) === $productId) {
      $productExists = true;
      break;
    }
  }
}

if (!$productExists) {
  http_response_code(404);
  ?>
  <!DOCTYPE html>
  <html lang="en">
    <head>
      <meta charset="UTF-8" />
      <meta name="viewport" content="width=device-width, initial-scale=1.0" />
      <title>Product not found | anderson_store</title>
      <link rel="stylesheet" href="styles.css?v=20260916" />
    </head>
    <body>
      <main class="container" style="padding-top: 80px;">
        <h1>Product not found</h1>
        <p>This product may have been removed from the store.</p>
        <a class="secondary-button" href="index.php#shop">Browse products</a>
      </main>
    </body>
  </html>
  <?php
  exit;
}

$template = file_get_contents(__DIR__ . '/product.html');
$appScript = '<script src="app.js?v=20261005"></script>';
if ($template === false || strpos($template, $appScript) === false) {
  http_response_code(500);
  echo 'The product page could not be loaded.';
  exit;
}

$productData = json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
$productBootstrap = '<script>window.__PRODUCTS__ = ' . $productData . ';</script>';
echo str_replace($appScript, $productBootstrap . "\n    " . $appScript, $template);