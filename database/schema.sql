CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  category VARCHAR(50) NOT NULL,
  badge VARCHAR(100) DEFAULT 'New',
  rating DECIMAL(3,1) DEFAULT 4.8,
  image TEXT,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customer_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS store_settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL DEFAULT ''
);

INSERT INTO store_settings (setting_key, setting_value)
VALUES ('merchant_payment_number', '+250798773881')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL,
  address VARCHAR(255) NOT NULL,
  city VARCHAR(100) NOT NULL,
  postal_code VARCHAR(30) NOT NULL,
  country VARCHAR(100) NOT NULL,
  notes TEXT,
  payment_method VARCHAR(30) NOT NULL DEFAULT 'mobile_money',
  payment_phone VARCHAR(30) NOT NULL,
  currency VARCHAR(3) NOT NULL DEFAULT 'USD',
  total DECIMAL(12,2) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'New',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  product_price DECIMAL(12,2) NOT NULL,
  quantity INT NOT NULL,
  size VARCHAR(30),
  image TEXT,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS chat_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_token VARCHAR(64) NOT NULL UNIQUE,
  customer_name VARCHAR(150) NOT NULL DEFAULT 'Guest',
  customer_email VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  sender VARCHAR(20) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
);

INSERT INTO admin_users (username, password_hash)
VALUES ('admin', '$2y$10$8uR3b8H2u1q4sTXe8otn7eG1tS9rH5V1Y6uG3w4M6LD5x8T3w0bNu')
ON DUPLICATE KEY UPDATE username = username;

INSERT INTO products (name, price, category, badge, rating, image, description)
VALUES
  ('Cinder Bomber', 228.00, 'outerwear', 'New', 4.9, 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80', 'Premium essentials designed with clean lines, everyday comfort, and a sharp streetwear attitude.'),
  ('Signal Tee', 72.00, 'tees', 'Best seller', 4.8, 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80', 'A heavyweight tee built for daily wear, with a clean drape and premium-touch finish.'),
  ('North Loop Cap', 46.00, 'accessories', 'Limited', 4.7, 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=80', 'A structured cap with muted tones and an easy everyday fit for everyday movement.'),
  ('Drift Hoodie', 118.00, 'outerwear', 'Popular', 4.9, 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=700&q=80', 'A softly brushed layer built for movement, warmth, and an elevated everyday silhouette.')
ON DUPLICATE KEY UPDATE name = name;
