<?php
require __DIR__ . '/config.php';
$products = load_products();
$customer = current_customer();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Anderson Store</title>
    <meta name="description" content="Premium essentials and streetwear-inspired apparel for everyday wear." />
    <link rel="stylesheet" href="styles.css?v=20261005" />
  </head>
  <body>
    <div class="site-shell">
      <header class="topbar">
        <div class="container topbar-inner">
          <p>Free shipping on orders over 150k</p>
          <div class="topbar-links">
            <a href="#story">Journal</a>
            <a href="#support" data-chat-open>Chat</a>
            <?php if ($customer): ?>
              <a href="customer_account.php">Hi, <?= htmlspecialchars($customer['first_name']) ?></a>
            <?php else: ?>
              <a href="customer_account.php?redirect=checkout.php">Sign in to checkout</a>
            <?php endif; ?>
            <a href="login.php">Admin</a>
          </div>
        </div>
      </header>

      <nav class="navbar container">
        <div class="brand-wrap">
          <button class="nav-menu" id="menuToggle" aria-label="Open menu" aria-expanded="false">☰</button>
          <a href="#" class="brand">anderson_store</a>
        </div>

        <div class="nav-links">
          <a href="#shop" data-menu-filter="new-arrivals">New Arrivals</a>
          <a href="#shop" data-menu-filter="apparel">Apparel</a>
          <a href="#shop" data-menu-filter="accessories">Accessories</a>
          <a href="#shop" data-menu-filter="footwear">Footwear</a>
          <a href="#shop" data-menu-filter="sale">Sale</a>
        </div>

        <div class="nav-actions">
          <button class="icon-button header-icon" id="searchToggle" aria-label="Search products" aria-expanded="false" title="Search"><span class="icon-search" aria-hidden="true"></span></button>
          <button class="icon-button header-icon" id="currencyToggle" aria-label="Choose language and currency" aria-expanded="false" title="Language and currency"><span class="icon-globe" aria-hidden="true"></span></button>
          <a class="icon-button header-icon" href="customer_account.php" aria-label="Customer account" title="<?= $customer ? 'Customer account' : 'Sign in' ?>">♙</a>
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

      <div class="menu-panel" id="menuPanel" aria-hidden="true" hidden>
        <div class="menu-panel-inner container">
          <div>
            <p class="eyebrow">Explore anderson_store</p>
            <h2>Find your everyday uniform.</h2>
          </div>
          <nav class="menu-panel-links" aria-label="Store menu">
            <a href="#shop" data-menu-filter="all">Shop all <span>01</span></a>
            <a href="#shop" data-menu-filter="new-arrivals">New arrivals <span>02</span></a>
            <a href="#shop" data-menu-filter="apparel">Apparel <span>03</span></a>
            <a href="#shop" data-menu-filter="accessories">Accessories <span>04</span></a>
            <a href="#shop" data-menu-filter="footwear">Footwear <span>05</span></a>
            <a href="#shop" data-menu-filter="sale">Sale <span>06</span></a>
            <a href="#support" data-chat-open>Customer chat <span>07</span></a>
          </nav>
        </div>
      </div>

      <section class="search-panel" id="searchPanel" aria-hidden="true" hidden>
        <div class="container">
          <form class="search-form" id="searchForm" role="search">
            <label for="productSearch">Search the collection</label>
            <div class="search-input-wrap">
              <input id="productSearch" name="q" type="search" placeholder="Try &quot;bomber&quot;, &quot;tee&quot;, or &quot;cap&quot;" autocomplete="off" />
              <button type="submit" class="primary-button">Search</button>
            </div>
            <p class="search-hint" id="searchHint">Search by product name, category, badge, or description.</p>
          </form>
        </div>
      </section>

      <main>
        <section class="hero container">
          <div class="hero-copy">
            <p class="eyebrow">New season / fall 2026</p>
            <h1>Built for the street,<br />refined for daily life.</h1>
            <p class="lede">Essential layers, elevated staples, and clean silhouettes designed to go from city mornings to late-night plans.</p>
            <div class="hero-actions">
              <a href="#shop" class="primary-button">Shop collection</a>
              <a href="#story" class="secondary-button">Read story</a>
            </div>
            <ul class="hero-meta">
              <li><strong>4.9</strong><span>Average rating</span></li>
              <li><strong>18k+</strong><span>Customers</span></li>
              <li><strong>48h</strong><span>Shipping</span></li>
            </ul>
          </div>

          <div class="hero-visual">
            <div class="visual-card visual-large">
              <img src="uploads/product-images/under.jpg" alt="Anderson Store product" />
            </div>
            <div class="visual-card visual-small">
              <img src="uploads/product-images/under.jpg" alt="Anderson Store product detail" />
            </div>
          </div>
        </section>

        <section class="feature-bar">
          <div class="container feature-grid">
            <div><span>01</span><p>Premium cotton blends</p></div>
            <div><span>02</span><p>Designed in small batches</p></div>
            <div><span>03</span><p>East African express shipping</p></div>
          </div>
        </section>

        <section class="showcase container">
          <div class="showcase-inner">
            <p class="eyebrow">Curated edit</p>
            <h2>Layered essentials for everyday motion.</h2>
            <div class="showcase-meta">
              <span>New silhouettes</span>
              <span>Natural textures</span>
              <span>Built to last</span>
            </div>
          </div>
        </section>

        <section id="shop" class="catalog container">
          <div class="section-heading">
            <div>
              <p class="eyebrow">Featured essentials</p>
              <h2>Most loved this week</h2>
            </div>
            <div class="filter-row" id="filterRow">
              <button class="filter active" data-filter="all">All</button>
              <button class="filter" data-filter="outerwear">Outerwear</button>
              <button class="filter" data-filter="tees">Tees</button>
              <button class="filter" data-filter="accessories">Accessories</button>
            </div>
          </div>

          <div class="product-grid" id="productGrid"></div>
          <p class="empty-state" id="emptyState" hidden>No products match that search yet. Try another word or browse all pieces.</p>
        </section>

        <section class="editorial container" id="story">
          <div class="editorial-copy">
            <p class="eyebrow">Our approach</p>
            <h2>Minimal design, maximum utility.</h2>
            <p>Every piece in the collection is built around balance: durable fabrics, versatile silhouettes, and everyday comfort that works across seasons.</p>
            <a href="#" class="primary-button">Explore the journal</a>
          </div>
          <div class="editorial-tiles">
            <div class="tile tile-large"><img src="uploads/product-images/040c8cbc52748f7baadc3ae8.jpg" alt="Layered streetwear outfit with shoes" /></div>
            <div class="tile tile-small"><img src="uploads/product-images/0dbab670c69bf8a9a1893e1b.jpg" alt="Streetwear outfit with white sneakers" /></div>
          </div>
        </section>

        <section class="banner container">
          <div class="banner-content">
            <p class="eyebrow">Limited release</p>
            <h3>New capsule drop: Coast / Utility</h3>
            <p>Weather-ready layers in muted neutrals and sharp silhouettes.</p>
          </div>
          <a href="#shop" class="primary-button alt">Shop the capsule</a>
        </section>
      </main>

      <section class="support-bar container" id="support">
        <div>
          <p class="eyebrow">Need a hand?</p>
          <h2>Good questions make better choices.</h2>
          <p>Get quick guidance on sizing, delivery, returns, or choosing the right layer. Call <a href="tel:0798773881">0798773881</a>, message us on WhatsApp, or email <a href="mailto:tuyishimireanderson9@gmail.com">tuyishimireanderson9@gmail.com</a>.</p>
        </div>
        <div class="support-actions">
          <button class="secondary-button" type="button" data-support-topic="size">Sizing help</button>
          <button class="secondary-button" type="button" data-support-topic="delivery">Delivery</button>
          <button class="secondary-button" type="button" data-support-topic="returns">Returns</button>
          <a class="secondary-button" href="tel:0798773881">Call 0798773881</a>
          <a class="primary-button" href="https://wa.me/250798773881?text=Hello%20Anderson%20Store%2C%20I%20need%20support." target="_blank" rel="noopener">WhatsApp support</a>
          <a class="secondary-button" href="mailto:tuyishimireanderson9@gmail.com">Email support</a>
        </div>
      </section>

      <section class="social-community" aria-label="Anderson Store social media">
        <div class="container social-community-inner">
          <a class="social-community-card" href="https://www.instagram.com/dangerous_sw/" target="_blank" rel="noopener noreferrer">
            <span class="social-community-icon instagram-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" role="img">
                <rect x="2.5" y="2.5" width="19" height="19" rx="5.5"></rect>
                <circle cx="12" cy="12" r="4.25"></circle>
                <circle class="social-svg-dot" cx="17.4" cy="6.7" r="1.15"></circle>
              </svg>
            </span>
            <span class="social-community-copy">
              <strong>Instagram</strong>
              <small>@dangerous_sw</small>
            </span>
          </a>
          <a class="social-community-card" href="https://www.facebook.com/search/top?q=ni%20kevin%20swift" target="_blank" rel="noopener noreferrer">
            <span class="social-community-icon facebook-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" role="img"><path d="M13.7 22v-8h2.7l.5-3h-3.2V9c0-.9.3-1.5 1.6-1.5h1.7V4.9c-.3-.1-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3V11H7.6v3h2.7v8h3.4z"></path></svg>
            </span>
            <span class="social-community-copy">
              <strong>Facebook</strong>
              <small>Ni Kevin Swift</small>
            </span>
          </a>
          <a class="social-community-follow" href="https://www.instagram.com/dangerous_sw/" target="_blank" rel="noopener noreferrer">
            Follow the community <span aria-hidden="true">→</span>
          </a>
        </div>
      </section>

      <footer class="footer">
        <div class="container footer-grid">
          <div>
            <a href="#" class="brand">anderson_store</a>
            <p>Curated essentials for everyday movement.</p>
          </div>
          <div>
            <h4>Shop</h4>
            <ul>
              <li><a href="#">New arrivals</a></li>
              <li><a href="#">Outerwear</a></li>
              <li><a href="#">Accessories</a></li>
            </ul>
          </div>
          <div>
            <h4>About</h4>
            <ul>
              <li><a href="#">Journal</a></li>
              <li><a href="#">Our story</a></li>
              <li><a href="#">Contact</a></li>
            </ul>
          </div>
          <div>
            <h4>Newsletter</h4>
            <form class="newsletter">
              <input type="email" placeholder="Email address" />
              <button type="submit">Join</button>
            </form>
          </div>
        </div>
      </footer>
    </div>

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

    <div class="support-dialog" id="supportDialog" role="dialog" aria-modal="true" aria-labelledby="supportDialogTitle" hidden>
      <div class="support-dialog-card">
        <div class="cart-header">
          <h3 id="supportDialogTitle">Customer help</h3>
          <button type="button" id="closeSupport" aria-label="Close help">✕</button>
        </div>
        <div class="support-answer" id="supportAnswer"></div>
      </div>
    </div>

    <div class="support-dialog" id="customerChat" role="dialog" aria-modal="true" aria-labelledby="customerChatTitle" hidden>
      <div class="chat-dialog-card">
        <div class="cart-header">
          <div>
            <p class="eyebrow">Customer support</p>
            <h3 id="customerChatTitle">Chat with the store</h3>
          </div>
          <button id="closeCustomerChat" aria-label="Close chat">✕</button>
        </div>
        <div class="chat-messages" id="chatMessages"><p class="search-hint">Send us a message and we will reply from the admin dashboard.</p></div>
        <form class="chat-form" id="chatForm">
          <input type="text" id="chatName" placeholder="Your name (optional)" maxlength="150" />
          <input type="email" id="chatEmail" placeholder="Email (optional)" maxlength="255" />
          <input type="text" id="chatMessage" placeholder="Write a message" maxlength="2000" required />
          <button type="submit">Send message</button>
        </form>
      </div>
    </div>

    <script>
      window.__PRODUCTS__ = <?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    </script>
    <script src="app.js?v=20261005"></script>
    <script>
      const customerChat = document.getElementById('customerChat');
      const chatMessages = document.getElementById('chatMessages');
      const chatForm = document.getElementById('chatForm');
      const chatToken = localStorage.getItem('anderson_chat_token') || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
      localStorage.setItem('anderson_chat_token', chatToken);

      function renderChat(messages) {
        chatMessages.innerHTML = messages.length
          ? messages.map(item => `<div class="chat-bubble ${item.sender}">${item.message.replace(/[&<>]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[character])}</div>`).join('')
          : '<p class="search-hint">Send us a message and we will reply from the admin dashboard.</p>';
        chatMessages.scrollTop = chatMessages.scrollHeight;
      }

      async function loadChat() {
        const response = await fetch(`chat.php?token=${encodeURIComponent(chatToken)}`);
        const result = await response.json();
        renderChat(result.messages || []);
      }

      document.querySelectorAll('[data-chat-open]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        customerChat.hidden = false;
        loadChat();
      }));
      document.getElementById('closeCustomerChat')?.addEventListener('click', () => { customerChat.hidden = true; });
      customerChat?.addEventListener('click', event => { if (event.target === customerChat) customerChat.hidden = true; });
      chatForm?.addEventListener('submit', async event => {
        event.preventDefault();
        const message = document.getElementById('chatMessage');
        const response = await fetch('chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ token: chatToken, message: message.value, name: document.getElementById('chatName').value, email: document.getElementById('chatEmail').value }),
        });
        const result = await response.json();
        if (response.ok) { message.value = ''; renderChat(result.messages || []); }
      });
      setInterval(() => { if (!customerChat.hidden) loadChat(); }, 5000);
    </script>
  </body>
</html>
