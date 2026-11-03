<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ADMIN_USERNAME = 'admin';
const ADMIN_PASSWORD = 'amcamc12';
const DB_HOST = '127.0.0.1';
const DB_NAME = 'anderson_store';
const DB_USER = 'root';
const DB_PASS = '';

function require_admin_login(): void
{
    if (
        !isset($_SESSION['admin_logged_in'])
        || $_SESSION['admin_logged_in'] !== true
        || !isset($_SESSION['admin_user'])
        || $_SESSION['admin_user'] !== ADMIN_USERNAME
    ) {
        $_SESSION['admin_logged_in'] = false;
        unset($_SESSION['admin_user']);
        header('Location: login.php');
        exit;
    }
}

function get_db_connection(): ?PDO
{
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}

function ensure_database_schema(): void
{
    static $schemaChecked = false;
    if ($schemaChecked) {
        return;
    }

    $schemaMarker = __DIR__ . '/data/.schema-ready';

    try {
        $adminPdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]
        );

        $adminPdo->exec('CREATE DATABASE IF NOT EXISTS ' . DB_NAME);
    } catch (PDOException $e) {
        return;
    }

    $db = get_db_connection();
    if (!$db) {
        return;
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            category VARCHAR(50) NOT NULL,
            badge VARCHAR(100) DEFAULT "New",
            rating DECIMAL(3,1) DEFAULT 4.8,
            image TEXT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS customer_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS store_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value VARCHAR(255) NOT NULL DEFAULT ""
        )'
    );
    $db->exec(
        'INSERT INTO store_settings (setting_key, setting_value)
         VALUES ("merchant_payment_number", "+250798773881")
         ON DUPLICATE KEY UPDATE setting_key = setting_key'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            postal_code VARCHAR(30) NOT NULL,
            country VARCHAR(100) NOT NULL,
            notes TEXT,
            payment_method VARCHAR(30) NOT NULL DEFAULT "mobile_money",
            payment_phone VARCHAR(30) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT "USD",
            total DECIMAL(12,2) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT "New",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )'
    );

    try {
        $db->exec('ALTER TABLE orders ADD COLUMN payment_method VARCHAR(30) NOT NULL DEFAULT "mobile_money"');
    } catch (PDOException $exception) {
    }
    try {
        $db->exec('ALTER TABLE orders ADD COLUMN payment_phone VARCHAR(30) NOT NULL DEFAULT ""');
    } catch (PDOException $exception) {
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_price DECIMAL(12,2) NOT NULL,
            quantity INT NOT NULL,
            size VARCHAR(30),
            image TEXT,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS chat_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_token VARCHAR(64) NOT NULL UNIQUE,
            customer_name VARCHAR(150) NOT NULL DEFAULT "Guest",
            customer_email VARCHAR(255) NOT NULL DEFAULT "",
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )'
    );

    $db->exec(
        'CREATE TABLE IF NOT EXISTS chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
        )'
    );

    $adminHash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
    $db->prepare(
        'INSERT INTO admin_users (username, password_hash)
         VALUES (:username, :password_hash)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
    )->execute([
        ':username' => ADMIN_USERNAME,
        ':password_hash' => $adminHash,
    ]);

    $count = (int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count === 0) {
        $db->exec(
            'INSERT INTO products (name, price, category, badge, rating, image, description)
             VALUES
               ("Cinder Bomber", 228.00, "outerwear", "New", 4.9, "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80", "Premium essentials designed with clean lines, everyday comfort, and a sharp streetwear attitude."),
               ("Signal Tee", 72.00, "tees", "Best seller", 4.8, "https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80", "A heavyweight tee built for daily wear, with a clean drape and premium-touch finish."),
               ("North Loop Cap", 46.00, "accessories", "Limited", 4.7, "https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=80", "A structured cap with muted tones and an easy everyday fit for everyday movement."),
               ("Drift Hoodie", 118.00, "outerwear", "Popular", 4.9, "https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=700&q=80", "A softly brushed layer built for movement, warmth, and an elevated everyday silhouette."),
               ("Crest Runner", 146.00, "footwear", "New", 4.9, "https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80", "A lightweight everyday runner built with breathable mesh and a cushioned sole for all-day movement."),
               ("Northwalk Sneaker", 164.00, "footwear", "Best seller", 4.8, "https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=900&q=80", "A refined sneaker with grip, comfort, and a clean finish that suits daily city wear.")'
        );
    }

    @touch($schemaMarker);
    $schemaChecked = true;
}

function product_file_path(): string
{
    return __DIR__ . '/data/products.json';
}

function orders_file_path(): string
{
    return __DIR__ . '/data/orders.json';
}

function load_local_orders(): array
{
    $file = orders_file_path();
    if (!is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    $orders = json_decode((string) $raw, true);
    return is_array($orders) ? $orders : [];
}

function save_local_orders(array $orders): void
{
    $file = orders_file_path();
    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return;
    }

    file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

function get_store_setting(string $key, string $fallback = ''): string
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$db) {
        return $fallback;
    }

    $stmt = $db->prepare('SELECT setting_value FROM store_settings WHERE setting_key = :setting_key');
    $stmt->execute([':setting_key' => $key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $fallback : (string) $value;
}

function save_store_setting(string $key, string $value): bool
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$db) {
        return false;
    }

    $stmt = $db->prepare(
        'INSERT INTO store_settings (setting_key, setting_value)
         VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    return $stmt->execute([':setting_key' => $key, ':setting_value' => $value]);
}

function save_uploaded_product_image(?array $file, string $fallback): array
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['path' => $fallback, 'error' => ''];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['path' => $fallback, 'error' => 'The image could not be uploaded. Please try again.'];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['path' => $fallback, 'error' => 'Please choose an image smaller than 5 MB.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedTypes = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_GIF => 'gif',
    ];
    $imageType = $imageInfo['2'] ?? null;

    if (!$imageInfo || !isset($allowedTypes[$imageType])) {
        return ['path' => $fallback, 'error' => 'Please choose a JPG, PNG, WEBP, or GIF image.'];
    }

    $uploadDirectory = __DIR__ . '/uploads/product-images';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        return ['path' => $fallback, 'error' => 'The image folder could not be created.'];
    }

    try {
        $fileName = bin2hex(random_bytes(12)) . '.' . $allowedTypes[$imageType];
    } catch (Throwable $exception) {
        return ['path' => $fallback, 'error' => 'The image could not be prepared. Please try again.'];
    }

    $destination = $uploadDirectory . '/' . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['path' => $fallback, 'error' => 'The image could not be saved. Please try again.'];
    }

    return ['path' => 'uploads/product-images/' . $fileName, 'error' => ''];
}

function load_products(): array
{
    ensure_database_schema();

    $db = get_db_connection();
    if ($db) {
        $stmt = $db->query('SELECT * FROM products ORDER BY id DESC');
        $rows = $stmt->fetchAll();
        if (is_array($rows) && $rows) {
            return $rows;
        }
    }

    $file = product_file_path();
    if (!file_exists($file)) {
        return [];
    }

    $raw = file_get_contents($file);
    $products = json_decode($raw, true);

    return is_array($products) ? $products : [];
}

function create_order(array $customer, array $items, string $currency, float $total): ?int
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$items) {
        return null;
    }

    if (!$db) {
        $localOrders = load_local_orders();
        $orderId = 1;
        foreach ($localOrders as $order) {
            $orderId = max($orderId, (int) ($order['id'] ?? 0) + 1);
        }

        $order = [
            'id' => $orderId,
            'first_name' => (string) ($customer['first_name'] ?? ''),
            'last_name' => (string) ($customer['last_name'] ?? ''),
            'email' => (string) ($customer['email'] ?? ''),
            'address' => (string) ($customer['address'] ?? ''),
            'city' => (string) ($customer['city'] ?? ''),
            'postal_code' => (string) ($customer['postal_code'] ?? ''),
            'country' => (string) ($customer['country'] ?? ''),
            'notes' => (string) ($customer['notes'] ?? ''),
            'payment_method' => (string) ($customer['payment_method'] ?? 'mtn_momo'),
            'payment_phone' => (string) ($customer['payment_phone'] ?? ''),
            'currency' => $currency,
            'total' => (float) $total,
            'status' => 'Pending payment',
            'created_at' => date('Y-m-d H:i:s'),
            'items' => array_map(static function (array $line): array {
                return [
                    'product_name' => (string) ($line['name'] ?? 'Product'),
                    'product_price' => (float) ($line['price'] ?? 0),
                    'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                    'size' => $line['size'] ?? null,
                    'image' => $line['image'] ?? null,
                ];
            }, $items),
        ];

        $localOrders[] = $order;
        save_local_orders($localOrders);
        return $orderId;
    }

    try {
        $db->beginTransaction();
        $order = $db->prepare(
            'INSERT INTO orders
             (first_name, last_name, email, address, city, postal_code, country, notes, payment_method, payment_phone, currency, total, status)
             VALUES (:first_name, :last_name, :email, :address, :city, :postal_code, :country, :notes, :payment_method, :payment_phone, :currency, :total, "Pending payment")'
        );
        $order->execute([
            ':first_name' => $customer['first_name'],
            ':last_name' => $customer['last_name'],
            ':email' => $customer['email'],
            ':address' => $customer['address'],
            ':city' => $customer['city'],
            ':postal_code' => $customer['postal_code'],
            ':country' => $customer['country'],
            ':notes' => $customer['notes'] ?? '',
            ':payment_method' => $customer['payment_method'],
            ':payment_phone' => $customer['payment_phone'],
            ':currency' => $currency,
            ':total' => $total,
        ]);

        $orderId = (int) $db->lastInsertId();
        $item = $db->prepare(
            'INSERT INTO order_items (order_id, product_name, product_price, quantity, size, image)
             VALUES (:order_id, :product_name, :product_price, :quantity, :size, :image)'
        );
        foreach ($items as $line) {
            $item->execute([
                ':order_id' => $orderId,
                ':product_name' => (string) ($line['name'] ?? 'Product'),
                ':product_price' => (float) ($line['price'] ?? 0),
                ':quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                ':size' => $line['size'] ?? null,
                ':image' => $line['image'] ?? null,
            ]);
        }
        $db->commit();
        return $orderId;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return null;
    }
}

function load_orders(): array
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$db) {
        return load_local_orders();
    }

    $orders = $db->query('SELECT * FROM orders ORDER BY created_at DESC, id DESC')->fetchAll();
    if (!$orders) {
        $localOrders = load_local_orders();
        return $localOrders ?: [];
    }

    foreach ($orders as &$order) {
        $items = $db->prepare('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id');
        $items->execute([':order_id' => $order['id']]);
        $order['items'] = $items->fetchAll();
    }
    unset($order);
    return $orders;
}

function update_order_status(int $orderId, string $status): bool
{
    $allowed = ['Pending payment', 'Processing', 'Confirmed', 'Shipped', 'Delivered', 'Cancelled', 'Refunded'];
    if (!in_array($status, $allowed, true)) return false;

    $db = get_db_connection();
    if ($db) {
        $stmt = $db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        return $stmt->execute([':status' => $status, ':id' => $orderId]);
    }

    $file = __DIR__ . '/data/orders.json';
    if (!is_file($file)) return false;
    $orders = json_decode((string) file_get_contents($file), true);
    if (!is_array($orders)) return false;
    foreach ($orders as &$order) {
        if ((int) ($order['id'] ?? 0) === $orderId) {
            $order['status'] = $status;
            return file_put_contents($file, json_encode($orders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) !== false;
        }
    }
    return false;
}

function send_chat_message(string $token, string $sender, string $message, string $name = 'Guest', string $email = ''): bool
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$db || !in_array($sender, ['customer', 'admin'], true) || trim($message) === '') {
        return false;
    }

    $conversation = $db->prepare('SELECT id FROM chat_conversations WHERE conversation_token = :token');
    $conversation->execute([':token' => $token]);
    $conversationId = $conversation->fetchColumn();
    if (!$conversationId) {
        $create = $db->prepare(
            'INSERT INTO chat_conversations (conversation_token, customer_name, customer_email)
             VALUES (:token, :name, :email)'
        );
        $create->execute([
            ':token' => $token,
            ':name' => trim($name) !== '' ? trim($name) : 'Guest',
            ':email' => trim($email),
        ]);
        $conversationId = $db->lastInsertId();
    } elseif ($sender === 'customer' && (trim($name) !== '' || trim($email) !== '')) {
        $update = $db->prepare(
            'UPDATE chat_conversations SET customer_name = :name, customer_email = :email WHERE id = :id'
        );
        $update->execute([
            ':name' => trim($name) !== '' ? trim($name) : 'Guest',
            ':email' => trim($email),
            ':id' => $conversationId,
        ]);
    }

    $insert = $db->prepare(
        'INSERT INTO chat_messages (conversation_id, sender, message)
         VALUES (:conversation_id, :sender, :message)'
    );
    return $insert->execute([
        ':conversation_id' => $conversationId,
        ':sender' => $sender,
        ':message' => trim($message),
    ]);
}

function load_chat_messages(string $token): array
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$db) {
        return [];
    }
    $stmt = $db->prepare(
        'SELECT m.sender, m.message, m.created_at
         FROM chat_messages m
         INNER JOIN chat_conversations c ON c.id = m.conversation_id
         WHERE c.conversation_token = :token ORDER BY m.id'
    );
    $stmt->execute([':token' => $token]);
    return $stmt->fetchAll();
}

function load_chat_conversations(): array
{
    ensure_database_schema();
    $db = get_db_connection();
    if (!$db) {
        return [];
    }
    return $db->query(
        'SELECT c.*, (SELECT COUNT(*) FROM chat_messages m WHERE m.conversation_id = c.id) AS message_count
         FROM chat_conversations c ORDER BY c.updated_at DESC, c.id DESC'
    )->fetchAll();
}

function insert_product(array $product): bool
{
    $db = get_db_connection();
    if ($db) {
        $stmt = $db->prepare(
            'INSERT INTO products (name, price, category, badge, rating, image, description)
             VALUES (:name, :price, :category, :badge, :rating, :image, :description)'
        );

        return $stmt->execute([
            ':name' => $product['name'],
            ':price' => $product['price'],
            ':category' => $product['category'],
            ':badge' => $product['badge'],
            ':rating' => $product['rating'],
            ':image' => $product['image'],
            ':description' => $product['description'],
        ]);
    }

    $file = product_file_path();
    $products = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($products)) {
        $products = [];
    }

    $products[] = $product;
    $encoded = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $encoded . PHP_EOL) !== false;
}

function update_product(int $productId, array $product): bool
{
    $db = get_db_connection();
    if ($db) {
        $findProduct = $db->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $findProduct->execute([':id' => $productId]);
        if ($findProduct->fetch()) {
            $updateProduct = $db->prepare(
                'UPDATE products SET name=:name, price=:price, category=:category, badge=:badge,
                 rating=:rating, image=:image, description=:description WHERE id=:id'
            );
            return $updateProduct->execute([
                ':name' => $product['name'],
                ':price' => $product['price'],
                ':category' => $product['category'],
                ':badge' => $product['badge'],
                ':rating' => $product['rating'],
                ':image' => $product['image'],
                ':description' => $product['description'] ?? '',
                ':id' => $productId,
            ]);
        }
    }

    $file = product_file_path();
    if (!is_file($file)) return false;
    $products = json_decode((string) file_get_contents($file), true);
    if (!is_array($products)) return false;
    foreach ($products as &$existing) {
        if (is_array($existing) && (int) ($existing['id'] ?? 0) === $productId) {
            $existing = array_merge($existing, $product, ['id' => $productId]);
            $encoded = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return $encoded !== false && file_put_contents($file, $encoded . PHP_EOL) !== false;
        }
    }
    return false;
}

function update_product_price(int $productId, float $price): bool
{
    $db = get_db_connection();
    if ($db) {
        $findProduct = $db->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $findProduct->execute([':id' => $productId]);
        if ($findProduct->fetch()) {
            $updateProduct = $db->prepare('UPDATE products SET price = :price WHERE id = :id');
            return $updateProduct->execute([
                ':price' => $price,
                ':id' => $productId,
            ]);
        }
    }

    $file = product_file_path();
    if (!is_file($file)) {
        return false;
    }

    $products = json_decode((string) file_get_contents($file), true);
    if (!is_array($products)) {
        return false;
    }

    $found = false;
    foreach ($products as &$product) {
        if (is_array($product) && (int) ($product['id'] ?? 0) === $productId) {
            $product['price'] = number_format($price, 2, '.', '');
            $found = true;
            break;
        }
    }
    unset($product);

    if (!$found) {
        return false;
    }

    $encoded = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return $encoded !== false && file_put_contents($file, $encoded . PHP_EOL) !== false;
}

function delete_product(int $productId): bool
{
    $db = get_db_connection();
    if (!$db) {
        return false;
    }

    $stmt = $db->prepare('DELETE FROM products WHERE id = :id');
    $stmt->execute([':id' => $productId]);
    return $stmt->rowCount() > 0;
}

function save_products(array $products): bool
{
    $db = get_db_connection();
    if ($db) {
        $db->exec('DELETE FROM products');
        $stmt = $db->prepare(
            'INSERT INTO products (name, price, category, badge, rating, image, description)
             VALUES (:name, :price, :category, :badge, :rating, :image, :description)'
        );

        foreach ($products as $product) {
            $stmt->execute([
                ':name' => $product['name'],
                ':price' => $product['price'],
                ':category' => $product['category'],
                ':badge' => $product['badge'],
                ':rating' => $product['rating'],
                ':image' => $product['image'],
                ':description' => $product['description'] ?? '',
            ]);
        }

        return true;
    }

    $file = product_file_path();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $encoded = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($file, $encoded . PHP_EOL) !== false;
}

function verify_admin_login(string $username, string $password): bool
{
    if ($username === ADMIN_USERNAME && $password === ADMIN_PASSWORD) {
        return true;
    }

    $db = get_db_connection();
    if (!$db) {
        return false;
    }

    $stmt = $db->prepare('SELECT password_hash FROM admin_users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    return password_verify($password, $row['password_hash']);
}

function login_admin(): void
{
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = ADMIN_USERNAME;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function customer_logged_in(): bool
{
    return isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true;
}

function current_customer(): array
{
    return customer_logged_in()
        ? [
            'id' => $_SESSION['customer_id'] ?? null,
            'first_name' => $_SESSION['customer_first_name'] ?? '',
            'last_name' => $_SESSION['customer_last_name'] ?? '',
            'email' => $_SESSION['customer_email'] ?? '',
        ]
        : [];
}

function register_customer(string $firstName, string $lastName, string $email, string $password): array
{
    $email = strtolower(trim($email));
    if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        return ['success' => false, 'error' => 'Enter your name, a valid email, and a password of at least 6 characters.'];
    }

    ensure_database_schema();
    $db = get_db_connection();
    if ($db) {
        $check = $db->prepare('SELECT id FROM customer_accounts WHERE email = :email LIMIT 1');
        $check->execute([':email' => $email]);
        if ($check->fetch()) {
            return ['success' => false, 'error' => 'An account with this email already exists.'];
        }
        $stmt = $db->prepare('INSERT INTO customer_accounts (first_name, last_name, email, password_hash) VALUES (:first_name, :last_name, :email, :password_hash)');
        if (!$stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName,
            ':email' => $email,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ])) {
            return ['success' => false, 'error' => 'Your account could not be created.'];
        }
        $id = (int) $db->lastInsertId();
    } else {
        $file = __DIR__ . '/data/customers.json';
        $customers = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        if (!is_array($customers)) {
            $customers = [];
        }
        foreach ($customers as $customer) {
            if (strtolower((string) ($customer['email'] ?? '')) === $email) {
                return ['success' => false, 'error' => 'An account with this email already exists.'];
            }
        }
        $id = count($customers) + 1;
        $customers[] = [
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ];
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        if (file_put_contents($file, json_encode($customers, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL) === false) {
            return ['success' => false, 'error' => 'Your account could not be created.'];
        }
    }

    login_customer($id, $firstName, $lastName, $email);
    return ['success' => true];
}

function verify_customer_login(string $email, string $password): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        return ['success' => false, 'error' => 'Enter a valid email and password.'];
    }

    ensure_database_schema();
    $db = get_db_connection();
    if ($db) {
        $stmt = $db->prepare('SELECT id, first_name, last_name, email, password_hash FROM customer_accounts WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $customer = $stmt->fetch();
    } else {
        $file = __DIR__ . '/data/customers.json';
        $customer = null;
        $customers = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        foreach (is_array($customers) ? $customers : [] as $row) {
            if (strtolower((string) ($row['email'] ?? '')) === $email) {
                $customer = $row;
                break;
            }
        }
    }

    if (!$customer || !password_verify($password, (string) ($customer['password_hash'] ?? ''))) {
        return ['success' => false, 'error' => 'Invalid email or password.'];
    }

    login_customer((int) $customer['id'], (string) $customer['first_name'], (string) $customer['last_name'], (string) $customer['email']);
    return ['success' => true];
}

function login_customer(int $id, string $firstName, string $lastName, string $email): void
{
    session_regenerate_id(true);
    $_SESSION['customer_logged_in'] = true;
    $_SESSION['customer_id'] = $id;
    $_SESSION['customer_first_name'] = $firstName;
    $_SESSION['customer_last_name'] = $lastName;
    $_SESSION['customer_email'] = $email;
}

function logout_customer(): void
{
    unset(
        $_SESSION['customer_logged_in'],
        $_SESSION['customer_id'],
        $_SESSION['customer_first_name'],
        $_SESSION['customer_last_name'],
        $_SESSION['customer_email']
    );
}
