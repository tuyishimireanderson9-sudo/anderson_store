const fallbackProducts = [
  {
    id: 1,
    name: 'Cinder Bomber',
    price: 228,
    category: 'outerwear',
    badge: 'New',
    rating: 4.9,
    description:
      'Premium essentials designed with clean lines, everyday comfort, and a sharp streetwear attitude.',
    image:
      'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 2,
    name: 'Signal Tee',
    price: 72,
    category: 'tees',
    badge: 'Best seller',
    rating: 4.8,
    description:
      'A heavyweight tee built for daily wear, with a clean drape and premium-touch finish.',
    image:
      'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 3,
    name: 'North Loop Cap',
    price: 46,
    category: 'accessories',
    badge: 'Limited',
    rating: 4.7,
    description:
      'A structured cap with muted tones and an easy everyday fit for everyday movement.',
    image:
      'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 4,
    name: 'Drift Hoodie',
    price: 118,
    category: 'outerwear',
    badge: 'Popular',
    rating: 4.9,
    description:
      'A softly brushed layer built for movement, warmth, and an elevated everyday silhouette.',
    image:
      'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=700&q=80',
  },
  {
    id: 5,
    name: 'Field Overshirt',
    price: 162,
    category: 'outerwear',
    badge: 'Editor pick',
    rating: 4.8,
    description:
      'A utility layer cut with a tailored finish and durable fabric made for long wear.',
    image:
      'https://images.unsplash.com/photo-1483985988355-763728e1935b?auto=format&fit=crop&w=700&q=80',
  },
  {
    id: 6,
    name: 'Urban Knit',
    price: 94,
    category: 'tees',
    badge: 'Soft feel',
    rating: 4.9,
    description:
      'A refined knit piece designed for layering without losing shape or comfort.',
    image:
      'https://images.unsplash.com/photo-1496747611176-843222e1e57c?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 7,
    name: 'Stone Tote',
    price: 58,
    category: 'accessories',
    badge: 'Travel',
    rating: 4.6,
    description:
      'An understated everyday carry with generous storage and a polished neutral finish.',
    image:
      'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 8,
    name: 'Basalt Crew',
    price: 86,
    category: 'tees',
    badge: 'New drop',
    rating: 4.8,
    description:
      'An all-day essential delivering an easy fit and premium texture in a modern palette.',
    image:
      'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 9,
    name: 'Crest Runner',
    price: 146,
    category: 'footwear',
    badge: 'New',
    rating: 4.9,
    description:
      'A lightweight everyday runner built with breathable mesh and a cushioned sole for all-day movement.',
    image:
      'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=80',
  },
  {
    id: 10,
    name: 'Northwalk Sneaker',
    price: 164,
    category: 'footwear',
    badge: 'Best seller',
    rating: 4.8,
    description:
      'A refined sneaker with grip, comfort, and a clean finish that suits daily city wear.',
    image:
      'https://images.unsplash.com/photo-1543508282-6319a3e2621f?auto=format&fit=crop&w=900&q=80',
  },
];

const products = window.__PRODUCTS__ && Array.isArray(window.__PRODUCTS__) ? window.__PRODUCTS__ : fallbackProducts;

function useLocalImageFallback(image) {
  const localFallback = 'uploads/product-images/default-product.svg';
  if (!image || (navigator.onLine === false && /^https?:\/\//i.test(image))) {
    return localFallback;
  }
  return image;
}

const productGrid = document.getElementById('productGrid');
const filterButtons = document.querySelectorAll('.filter');
const cartCount = document.getElementById('cartCount');
const cartItems = document.getElementById('cartItems');
const cartTotal = document.getElementById('cartTotal');
const cartPanel = document.getElementById('cartPanel');
const cartToggle = document.getElementById('cartToggle');
const closeCart = document.getElementById('closeCart');
const menuToggle = document.getElementById('menuToggle');
const menuPanel = document.getElementById('menuPanel');
const searchToggle = document.getElementById('searchToggle');
const searchPanel = document.getElementById('searchPanel');
const searchForm = document.getElementById('searchForm');
const productSearch = document.getElementById('productSearch');
const emptyState = document.getElementById('emptyState');
const supportDialog = document.getElementById('supportDialog');
const supportAnswer = document.getElementById('supportAnswer');
const closeSupport = document.getElementById('closeSupport');
const currencyToggle = document.getElementById('currencyToggle');
const currencyPanel = document.getElementById('currencyPanel');
const currencyOptions = document.querySelectorAll('[data-currency]');
const currencyCurrent = document.getElementById('currencyCurrent');
const currencyChange = document.getElementById('currencyChange');

const pageName = document.body.dataset.page;

let activeFilter = 'all';
let searchQuery = '';
let cart = [];
const currencyRates = { USD: 1, EUR: 0.92, GBP: 0.79, CAD: 1.36, AUD: 1.52, SGD: 1.35, CHF: 0.88, JPY: 150, ZAR: 18.5, RWF: 1450 };
const storedCurrency = localStorage.getItem('anderson_currency');
let selectedCurrency = storedCurrency && currencyRates[storedCurrency] ? storedCurrency : 'USD';

function loadCart() {
  try {
    const saved = JSON.parse(localStorage.getItem('anderson_cart') || '[]');
    return Array.isArray(saved) ? saved : [];
  } catch {
    return [];
  }
}

function saveCart() {
  localStorage.setItem('anderson_cart', JSON.stringify(cart));
  window.dispatchEvent(new CustomEvent('anderson:cartchange'));
}

function formatCurrency(value) {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: selectedCurrency,
    maximumFractionDigits: selectedCurrency === 'JPY' ? 0 : 2,
  }).format(value * currencyRates[selectedCurrency]);
}

function updateCurrencyUI() {
  if (currencyToggle) currencyToggle.title = `Language and currency: ${selectedCurrency}`;
  if (currencyCurrent) {
    const currentOption = document.querySelector(`[data-currency="${selectedCurrency}"]`);
    const currentFlag = currentOption?.querySelector('img')?.src || '';
    currencyCurrent.innerHTML = currentFlag
      ? `<img src="${currentFlag}" alt="" /><strong>${selectedCurrency}</strong><span class="currency-chevron">⌃</span>`
      : `<span>◎</span><strong>${selectedCurrency}</strong><span class="currency-chevron">⌃</span>`;
  }
  currencyOptions.forEach((option) => option.classList.toggle('selected', option.dataset.currency === selectedCurrency));
  if (productGrid) renderProducts();
  if (cartItems) renderCart();
  const productPrice = document.getElementById('productPrice');
  if (productPrice && pageName === 'product') {
    const productId = Number(new URLSearchParams(window.location.search).get('id'));
    const product = products.find((item) => item.id === productId) || products[0];
    if (product) productPrice.textContent = formatCurrency(product.price);
  }
  window.dispatchEvent(new CustomEvent('anderson:currencychange'));
}

if (currencyToggle && currencyPanel) {
  currencyToggle.addEventListener('click', () => {
    const open = !currencyPanel.classList.contains('open');
    currencyPanel.hidden = !open;
    currencyPanel.classList.toggle('open', open);
    currencyPanel.setAttribute('aria-hidden', String(!open));
    currencyToggle.setAttribute('aria-expanded', String(open));
  });
}

document.addEventListener('click', (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;

  if (currencyPanel && currencyToggle && !currencyPanel.contains(target) && !currencyToggle.contains(target)) {
    currencyPanel.hidden = true;
    currencyPanel.classList.remove('open');
    currencyPanel.setAttribute('aria-hidden', 'true');
    currencyToggle.setAttribute('aria-expanded', 'false');
  }

  if (searchPanel && searchToggle && !searchPanel.contains(target) && !searchToggle.contains(target)) {
    searchPanel.hidden = true;
    searchPanel.classList.remove('open');
    searchPanel.setAttribute('aria-hidden', 'true');
    searchToggle.setAttribute('aria-expanded', 'false');
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') return;
  [currencyPanel, searchPanel, menuPanel].forEach((panel) => {
    if (panel) panel.hidden = true;
    panel?.classList.remove('open');
    panel?.setAttribute('aria-hidden', 'true');
  });
  currencyToggle?.setAttribute('aria-expanded', 'false');
  searchToggle?.setAttribute('aria-expanded', 'false');
  menuToggle?.setAttribute('aria-expanded', 'false');
});

currencyOptions.forEach((option) => {
  option.addEventListener('click', () => {
    selectedCurrency = option.dataset.currency;
    localStorage.setItem('anderson_currency', selectedCurrency);
    updateCurrencyUI();
    currencyPanel?.classList.remove('open');
    currencyPanel?.setAttribute('hidden', '');
    currencyPanel?.setAttribute('aria-hidden', 'true');
    currencyToggle?.setAttribute('aria-expanded', 'false');
  });
});

currencyChange?.addEventListener('click', () => {
  currencyPanel?.classList.remove('open');
  currencyPanel?.setAttribute('aria-hidden', 'true');
  if (currencyPanel) currencyPanel.hidden = true;
  currencyToggle?.setAttribute('aria-expanded', 'false');
});

updateCurrencyUI();
window.formatStoreCurrency = formatCurrency;
window.getStoreCurrency = () => selectedCurrency;
window.clearStoreCart = () => {
  cart = [];
  saveCart();
  renderCart();
};

function getFilteredProductsByScope(scope) {
  if (scope === 'all') return [...products];
  if (scope === 'apparel') {
    return products.filter((product) => ['outerwear', 'tees'].includes(product.category));
  }
  if (scope === 'new-arrivals') {
    return products.filter((product) => String(product.badge || '').toLowerCase().includes('new'));
  }
  if (scope === 'sale') {
    return products.filter((product) => {
      const badge = String(product.badge || '').toLowerCase();
      return badge.includes('sale') || product.sale === true || Number(product.price) < 60;
    });
  }
  return products.filter((product) => product.category === scope);
}

function renderProducts() {
  const filterMatches = getFilteredProductsByScope(activeFilter);
  const normalizedQuery = searchQuery.trim().toLowerCase();
  const filteredProducts = normalizedQuery
    ? filterMatches.filter((product) =>
        [product.name, product.category, product.badge, product.description]
          .join(' ')
          .toLowerCase()
          .includes(normalizedQuery)
      )
    : filterMatches;

  if (emptyState) {
    emptyState.hidden = filteredProducts.length > 0;
    emptyState.textContent = searchQuery
      ? `No products match “${searchQuery}” yet. Try another word or browse all pieces.`
      : 'No products are available in this category yet.';
  }

  productGrid.innerHTML = filteredProducts
    .map(
      (product) => `
        <article class="product-card">
          <div class="product-media">
            <a href="product.php?id=${product.id}">
              <img src="${useLocalImageFallback(product.image)}" alt="${product.name}" onerror="this.onerror=null;this.src='uploads/product-images/default-product.svg';" />
            </a>
            <span class="badge">${product.badge}</span>
          </div>
          <div class="product-info">
            <div class="product-topline">
              <h3>${product.name}</h3>
              <span class="rating">★ ${product.rating}</span>
            </div>
            <div class="product-meta">
              <span class="price">${formatCurrency(product.price)}</span>
              <button class="add-button" data-id="${product.id}">Add</button>
            </div>
          </div>
        </article>
      `
    )
    .join('');

  document.querySelectorAll('.add-button').forEach((button) => {
    button.addEventListener('click', () => addToCart(Number(button.dataset.id)));
  });
}

function setPanelState(panel, toggle, open) {
  if (!panel || !toggle) return;
  panel.hidden = !open;
  panel.classList.toggle('open', open);
  panel.setAttribute('aria-hidden', String(!open));
  toggle.setAttribute('aria-expanded', String(open));
}

if (menuToggle) {
  menuToggle.addEventListener('click', () => {
    const isOpen = !menuPanel.classList.contains('open');
    setPanelState(menuPanel, menuToggle, isOpen);
    setPanelState(searchPanel, searchToggle, false);
  });
}

if (searchToggle) {
  searchToggle.addEventListener('click', () => {
    const isOpen = !searchPanel.classList.contains('open');
    setPanelState(searchPanel, searchToggle, isOpen);
    setPanelState(menuPanel, menuToggle, false);
    if (isOpen && productSearch) productSearch.focus();
  });
}

if (searchForm) {
  searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    searchQuery = productSearch.value;
    renderProducts();
    document.getElementById('shop')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

if (productSearch) {
  productSearch.addEventListener('input', () => {
    searchQuery = productSearch.value;
    renderProducts();
  });
}

document.querySelectorAll('[data-menu-filter]').forEach((link) => {
  link.addEventListener('click', () => {
    activeFilter = link.dataset.menuFilter;
    filterButtons.forEach((button) => button.classList.toggle('active', button.dataset.filter === activeFilter));
    document.querySelectorAll('[data-menu-filter]').forEach((menuLink) => {
      menuLink.classList.toggle('active', menuLink.dataset.menuFilter === activeFilter);
    });
    setPanelState(menuPanel, menuToggle, false);
    renderProducts();
  });
});

const supportContent = {
  size: '<h4>Choosing your size</h4><p>Most pieces fit true to size. For a relaxed layer, choose one size up. Clothing sizes run from XS to XXXL.</p><a href="#shop">Browse the collection</a>',
  delivery: '<h4>Delivery guidance</h4><p>Orders over 150k qualify for free shipping. We aim to dispatch orders within 48 hours, with East African express delivery available.</p><a href="https://wa.me/250798773881?text=Hello%20Anderson%20Store%2C%20I%20have%20a%20delivery%20question." target="_blank" rel="noopener">Message us on WhatsApp</a> or <a href="mailto:tuyishimireanderson9@gmail.com">send an email</a>.',
  returns: '<h4>Returns made simple</h4><p>Unused items can be returned within 30 days. Keep the original tags attached and contact support before sending anything back.</p><a href="https://wa.me/250798773881?text=Hello%20Anderson%20Store%2C%20I%20need%20help%20with%20a%20return." target="_blank" rel="noopener">Start a WhatsApp request</a> or <a href="mailto:tuyishimireanderson9@gmail.com">send an email</a>.',
};

function openSupport(topic = 'size') {
  if (!supportDialog || !supportAnswer) return;
  supportAnswer.innerHTML = supportContent[topic] || supportContent.size;
  supportDialog.hidden = false;
  closeSupport?.focus();
}

document.querySelectorAll('[data-support-open]').forEach((link) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    openSupport();
  });
});

document.querySelectorAll('[data-support-topic]').forEach((button) => {
  button.addEventListener('click', () => openSupport(button.dataset.supportTopic));
});

closeSupport?.addEventListener('click', () => { supportDialog.hidden = true; });
supportDialog?.addEventListener('click', (event) => {
  if (event.target === supportDialog) supportDialog.hidden = true;
});

function addToCart(id, size = null) {
  const product = products.find((item) => item.id === id);
  if (!product) return;

  const existing = cart.find((item) => item.id === id && item.size === size);
  if (existing) {
    existing.quantity += 1;
  } else {
    cart.push({ ...product, size, quantity: 1 });
  }

  saveCart();
  renderCart();
  if (cartPanel) cartPanel.classList.add('open');
}

function renderCart() {
  if (!cart.length) {
    cartItems.innerHTML = '<p style="color:#5f5d57; margin:0;">Your cart is empty.</p>';
    cartCount.textContent = '0';
    cartTotal.textContent = '$0.00';
    return;
  }

  cartItems.innerHTML = cart
    .map(
      (item, index) => `
        <div class="cart-item">
          <img src="${useLocalImageFallback(item.image)}" alt="${item.name}" onerror="this.onerror=null;this.src='uploads/product-images/default-product.svg';" />
          <div>
            <h4>${item.name}</h4>
            ${item.size ? `<p>Size ${item.size}</p>` : ''}
            <p>Qty ${item.quantity}</p>
            <p>${formatCurrency(item.price)} each</p>
            <strong>${formatCurrency(item.price * item.quantity)}</strong>
            <button class="cart-item-remove" type="button" data-remove-cart-item data-cart-index="${index}" aria-label="Remove item from cart">Remove</button>
          </div>
        </div>
      `
    )
    .join('');

  cartCount.textContent = String(cart.reduce((sum, item) => sum + item.quantity, 0));
  cartTotal.textContent = formatCurrency(
    cart.reduce((sum, item) => sum + item.price * item.quantity, 0)
  );
}

if (cartItems) {
  cartItems.addEventListener('click', (event) => {
    if (!(event.target instanceof Element)) return;
    const removeButton = event.target.closest('[data-remove-cart-item]');
    if (!removeButton) return;

    const index = Number(removeButton.dataset.cartIndex);
    if (!Number.isInteger(index) || index < 0 || index >= cart.length) return;
    cart.splice(index, 1);
    saveCart();
    renderCart();
  });
}

filterButtons.forEach((button) => {
  button.addEventListener('click', () => {
    activeFilter = button.dataset.filter;
    filterButtons.forEach((btn) => btn.classList.toggle('active', btn === button));
    renderProducts();
  });
});

if (cartToggle) {
  cartToggle.addEventListener('click', () => cartPanel.classList.add('open'));
}

if (closeCart) {
  closeCart.addEventListener('click', () => cartPanel.classList.remove('open'));
}

const newsletter = document.querySelector('.newsletter');
if (newsletter) {
  newsletter.addEventListener('submit', (event) => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button');
    button.textContent = 'Joined';
    button.disabled = true;
  });
}

if (pageName === 'product') {
  const params = new URLSearchParams(window.location.search);
  const productId = Number(params.get('id'));
  const product = products.find((item) => item.id === productId) || products[0];

  const imageEl = document.getElementById('productImage');
  const badgeEl = document.getElementById('productBadge');
  const nameEl = document.getElementById('productName');
  const priceEl = document.getElementById('productPrice');
  const ratingEl = document.getElementById('productRating');
  const descEl = document.getElementById('productDescription');
  const addButton = document.getElementById('addToCart');
  const sizeMessage = document.getElementById('sizeMessage');
  const sizeSelector = document.getElementById('productSizeSelector');
  const sizeButtons = document.querySelectorAll('.size-option');
  const relatedProducts = document.getElementById('relatedProducts');
  const relatedDescription = document.getElementById('relatedDescription');
  let selectedSize = '';

  imageEl.src = useLocalImageFallback(product.image);
  imageEl.onerror = () => {
    imageEl.onerror = null;
    imageEl.src = 'uploads/product-images/default-product.svg';
  };
  imageEl.alt = product.name;
  badgeEl.textContent = product.badge;
  nameEl.textContent = product.name;
  priceEl.textContent = formatCurrency(product.price);
  ratingEl.textContent = `★ ${product.rating}`;
  descEl.textContent = product.description;

  const requiresSize = product.category !== 'accessories';
  if (sizeSelector) sizeSelector.hidden = !requiresSize;

  if (relatedProducts) {
    const sameCategory = products.filter((item) => item.id !== product.id && item.category === product.category);
    const otherProducts = products.filter((item) => item.id !== product.id && item.category !== product.category);
    const related = [...sameCategory, ...otherProducts].slice(0, 4);
    relatedDescription.textContent = sameCategory.length
      ? `More ${product.category} selected for you.`
      : 'More pieces selected for you.';
    relatedProducts.innerHTML = related.map((item) => `
      <article class="product-card">
        <div class="product-media">
          <a href="product.php?id=${item.id}">
            <img src="${useLocalImageFallback(item.image)}" alt="${item.name}" onerror="this.onerror=null;this.src='uploads/product-images/default-product.svg';" />
          </a>
        </div>
        <div class="product-info">
          <span class="badge">${item.badge || 'Selected'}</span>
          <h3><a href="product.php?id=${item.id}">${item.name}</a></h3>
          <strong>${formatCurrency(item.price)}</strong>
        </div>
      </article>
    `).join('');
  }

  sizeButtons.forEach((button) => {
    button.addEventListener('click', () => {
      selectedSize = button.dataset.size;
      sizeButtons.forEach((sizeButton) => {
        const isSelected = sizeButton === button;
        sizeButton.classList.toggle('selected', isSelected);
        sizeButton.setAttribute('aria-pressed', String(isSelected));
      });
      if (sizeMessage) sizeMessage.textContent = `Size ${selectedSize} selected.`;
    });
  });

  addButton.addEventListener('click', () => {
    if (requiresSize && !selectedSize) {
      if (sizeMessage) sizeMessage.textContent = 'Please choose a size before adding to cart.';
      sizeButtons[0]?.focus();
      return;
    }
    addToCart(product.id, requiresSize ? selectedSize : null);
  });
}

function attachCheckoutLinks() {
  document.querySelectorAll('[data-checkout-link], .cart-panel .primary-button.full').forEach((button) => {
    if (button.dataset.checkoutBound === 'true') return;
    button.dataset.checkoutBound = 'true';
    button.addEventListener('click', (event) => {
      if (button.tagName === 'A') {
        return;
      }
      event.preventDefault();
      window.location.href = 'checkout.php';
    });
  });
}

if (productGrid) {
  renderProducts();
}

attachCheckoutLinks();
cart = loadCart();
renderCart();
