// TeleNexa Mini App Client Controller
const cfg = window.teleNexaWebAppConfig || {};
const i18n = cfg.i18n || {};
        // Init Telegram WebApp
        const tg = window.Telegram?.WebApp;
        if (tg) {
            tg.ready();
            tg.expand();
            if (tg.initDataUnsafe?.user) {
                const u = tg.initDataUnsafe.user;
                document.getElementById('tg_user_display').innerText = (u.first_name || '') + ' ' + (u.last_name || '');
            }
        }

        // Global State & Credentials
        const webappNonce = cfg.nonce || '';
        const initDataRaw = tg?.initData || '';
        let currentTab = 'view-shop';
        let cart = JSON.parse(localStorage.getItem('woogram_cart') || '{}');
        let wishlist = JSON.parse(localStorage.getItem('woogram_wishlist') || '[]');
        let productsCache = [];
        let appliedDiscount = 0;
        let appliedCouponCode = '';
        let selectedShippingCost = 0;
        let selectedTax = 0;
        let selectedShippingMethod = '';
        let selectedPaymentMethod = '';
        let activeVariableProduct = null;
        let selectedVariation = null;

        let cartSyncTimer = null;
        function syncCartWithBot() {
            clearTimeout(cartSyncTimer);
            cartSyncTimer = setTimeout(async () => {
                if (!initDataRaw || !tg?.initDataUnsafe?.user?.id) return;
                try {
                    await fetch(`${cfg.ajaxUrl || 'admin-ajax.php'}?action=woogram_webapp_sync_cart`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            nonce: webappNonce,
                            init_data: initDataRaw,
                            chat_id: String(tg.initDataUnsafe.user.id),
                            cart: JSON.stringify(cart)
                        })
                    });
                } catch (error) {}
            }, 250);
        }
        syncCartWithBot();

        const currency = cfg.currency || '$'; ?>;
        const numberLocale = cfg.numberLocale || 'fa-IR'; ?>;

        function formatMoney(value) {
            const amount = Number(value || 0);
            return `${new Intl.NumberFormat(numberLocale, { maximumFractionDigits: 2 }).format(amount)} ${currency}`;
        }

        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('is-active'));
                document.querySelectorAll('.tab-view').forEach(v => v.classList.remove('is-active'));
                item.classList.add('is-active');
                const targetView = item.dataset.view;
                document.getElementById(targetView).classList.add('is-active');
                currentTab = targetView;

                if (targetView === 'view-cart') renderCartView();
                if (targetView === 'view-wishlist') renderWishlistView();
                if (targetView === 'view-orders') fetchOrdersView();
            });
        });

        // Fetch products
        async function fetchProducts(catId = 0, query = '') {
            const grid = document.getElementById('products_grid');
            grid.innerHTML = '<div style="grid-column: span 2; text-align: center; padding: 20px;">⌛ ${cfg.isPersian ? 'در حال دریافت محصولات...' : 'Loading products...'}</div>';

            try {
                const res = await fetch(`${cfg.ajaxUrl || 'admin-ajax.php'}?action=woogram_webapp_products&cat_id=${catId}&s=${encodeURIComponent(query)}&lang=${cfg.lang || 'fa'}&nonce=${webappNonce}&init_data=${encodeURIComponent(initDataRaw)}`);
                const data = await res.json();
                if (data.success && data.data) {
                    productsCache = data.data;
                    renderProducts(data.data);
                } else {
                    grid.innerHTML = '<div style="grid-column: span 2; text-align: center; padding: 20px;">${cfg.isPersian ? 'محصولی یافت نشد.' : 'No products available.'}</div>';
                }
            } catch (err) {
                grid.innerHTML = '<div style="grid-column: span 2; text-align: center; color: red;">${cfg.isPersian ? 'خطا در بارگذاری محصولات' : 'Error loading products'}</div>';
            }
        }

        // Render products
        function renderProducts(products) {
            const grid = document.getElementById('products_grid');
            grid.innerHTML = '';
            if (!products.length) {
                grid.innerHTML = '<div style="grid-column: span 2; text-align: center; padding: 20px;">${cfg.isPersian ? 'محصولی یافت نشد.' : 'No products found.'}</div>';
                return;
            }

            products.forEach(p => {
                const isFav = wishlist.includes(p.id);
                const discount = p.discount_pct ? `<div class="product-discount-badge">${p.discount_pct}%</div>` : '';
                const typeBadge = p.is_variable ? `<div class="product-type-badge">${cfg.isPersian ? 'متغیر' : 'Variable'}</div>` : (p.is_virtual ? `<div class="product-type-badge">${cfg.isPersian ? 'دانلودی' : 'Digital'}</div>` : '');
                const regPrice = p.regular_price ? `<span class="product-regular-price">${formatMoney(p.regular_price)}</span>` : '';

                const card = document.createElement('div');
                card.className = 'product-card';
                card.innerHTML = `
                    <div class="product-img-wrap">
                        <img src="${p.image}" class="product-img" alt="${p.name}" loading="lazy">
                        ${discount}
                        ${typeBadge}
                        <button class="product-wishlist-btn" onclick="toggleFav(${p.id})">${isFav ? '❤️' : '🤍'}</button>
                    </div>
                    <div class="product-info">
                        <div class="product-title">${p.name}</div>
                        <div class="product-price-row">
                            ${regPrice}
                            <span class="product-price">${p.is_variable ? '${cfg.isPersian ? 'از ' : 'From '}' : ''}${formatMoney(p.price)}</span>
                        </div>
                        <div id="btn_wrap_${p.id}">
                            ${renderProductAction(p)}
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function renderProductAction(p) {
            if (p.is_variable) {
                return `<button class="btn-add-cart" onclick="openVariationModal(${p.id})">⚙️ ${cfg.isPersian ? 'انتخاب ویژگی‌ها' : 'Select Options'}</button>`;
            }
            const qty = cart[p.id] || 0;
            if (qty > 0) {
                return `
                    <div class="cart-qty-counter">
                        <button class="qty-btn" onclick="updateQty(${p.id}, -1)">-</button>
                        <span class="qty-val">${qty}</span>
                        <button class="qty-btn" onclick="updateQty(${p.id}, 1)">+</button>
                    </div>
                `;
            }
            return `<button class="btn-add-cart" onclick="updateQty(${p.id}, 1)">🛒 ${cfg.isPersian ? 'افزودن به سبد' : 'Add to Cart'}</button>`;
        }

        // Variation Modal Handlers
        function openVariationModal(pId) {
            const prod = productsCache.find(p => p.id == pId);
            if (!prod || !prod.variations) return;
            activeVariableProduct = prod;

            document.getElementById('var_modal_title').innerText = prod.name;
            document.getElementById('var_modal_img').src = prod.image;
            document.getElementById('var_modal_price').innerText = formatMoney(prod.price);

            const container = document.getElementById('var_options_container');
            container.innerHTML = '';

            prod.variations.forEach((v, idx) => {
                const opt = document.createElement('div');
                opt.className = `option-card ${idx === 0 ? 'is-selected' : ''}`;
                const attrText = Object.entries(v.attributes).map(([k, val]) => val).join(' - ') || `Option #${idx+1}`;
                opt.innerHTML = `
                    <div>
                        <strong>${attrText}</strong>
                        <div style="font-size: 11px; color: var(--hint-color);">${v.is_in_stock ? '${cfg.isPersian ? 'موجود در انبار' : 'In Stock'}' : '${cfg.isPersian ? 'ناموجود' : 'Out of Stock'}'}</div>
                    </div>
                    <strong>${formatMoney(v.price)}</strong>
                `;
                opt.onclick = () => {
                    document.querySelectorAll('#var_options_container .option-card').forEach(el => el.classList.remove('is-selected'));
                    opt.classList.add('is-selected');
                    selectedVariation = v;
                    document.getElementById('var_modal_img').src = v.image;
                    document.getElementById('var_modal_price').innerText = formatMoney(v.price);
                };
                container.appendChild(opt);
            });

            selectedVariation = prod.variations[0];
            document.getElementById('var_modal').classList.add('is-active');
        }

        function closeVariationModal() {
            document.getElementById('var_modal').classList.remove('is-active');
            activeVariableProduct = null;
            selectedVariation = null;
        }

        document.getElementById('btn_add_variation')?.addEventListener('click', () => {
            if (!activeVariableProduct || !selectedVariation) return;
            const key = `${activeVariableProduct.id}V${selectedVariation.variation_id}`;
            cart[key] = (cart[key] || 0) + 1;
            localStorage.setItem('woogram_cart', JSON.stringify(cart));
            syncCartWithBot();
            updateCartBadge();
            closeVariationModal();
            if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            alert("${cfg.isPersian ? 'محصول با موفقیت به سبد افزوده شد!' : 'Added to cart successfully!'}");
        });

        function updateQty(key, delta) {
            const current = cart[key] || 0;
            const updated = current + delta;
            if (updated <= 0) {
                delete cart[key];
            } else {
                cart[key] = updated;
            }
            localStorage.setItem('woogram_cart', JSON.stringify(cart));
            syncCartWithBot();
            updateCartBadge();

            const wrap = document.getElementById(`btn_wrap_${key}`);
            if (wrap) {
                const prod = productsCache.find(p => p.id == key);
                if (prod) wrap.innerHTML = renderProductAction(prod);
            }
            if (currentTab === 'view-cart') renderCartView();
        }

        function updateCartBadge() {
            const totalItems = Object.values(cart).reduce((a, b) => a + b, 0);
            const badge = document.getElementById('nav_cart_badge');
            if (totalItems > 0) {
                badge.innerText = totalItems;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function toggleFav(pId) {
            if (wishlist.includes(pId)) {
                wishlist = wishlist.filter(id => id !== pId);
            } else {
                wishlist.push(pId);
            }
            localStorage.setItem('woogram_wishlist', JSON.stringify(wishlist));
            renderProducts(productsCache);
            if (currentTab === 'view-wishlist') renderWishlistView();
        }

        // Render Cart View
        async function renderCartView() {
            const list = document.getElementById('cart_items_list');
            const summaryBox = document.getElementById('cart_summary_box');
            const entries = Object.entries(cart);

            if (!entries.length) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">🛒</div>
                        <div class="empty-title">${cfg.isPersian ? 'سبد خرید شما خالی است' : 'Your cart is empty'}</div>
                        <div class="empty-desc">${cfg.isPersian ? 'محصولات مورد نظرتان را به سبد خرید اضافه کنید.' : 'Add products from the store to checkout.'}</div>
                    </div>
                `;
                summaryBox.style.display = 'none';
                return;
            }

            let subtotal = 0;
            list.innerHTML = '';
            entries.forEach(([key, qty]) => {
                let title = `${cfg.isPersian ? 'محصول شماره ' : 'Product #'}${key}`;
                let price = 0;
                let img = '';

                if (key.includes('V')) {
                    const [pId, vId] = key.split('V');
                    const parent = productsCache.find(p => p.id == pId);
                    const v = parent?.variations?.find(vr => vr.variation_id == vId);
                    title = parent ? `${parent.name} (${Object.values(v?.attributes || {}).join(', ')})` : `Variation #${vId}`;
                    price = v ? v.price : (parent ? parent.price : 0);
                    img = v?.image || parent?.image || '';
                } else {
                    const prod = productsCache.find(p => p.id == key);
                    title = prod ? prod.name : `${cfg.isPersian ? 'محصول شماره ' : 'Product #'}${key}`;
                    price = prod ? prod.price : 0;
                    img = prod ? prod.image : '';
                }

                subtotal += (price * qty);

                const item = document.createElement('div');
                item.className = 'cart-item';
                item.innerHTML = `
                    <img src="${img}" class="cart-item-img">
                    <div class="cart-item-info">
                        <div class="cart-item-title">${title}</div>
                        <div class="cart-item-price">${formatMoney(price)}</div>
                    </div>
                    <div style="width: 90px;">
                        <div class="cart-qty-counter">
                            <button class="qty-btn" onclick="updateQty('${key}', -1)">-</button>
                            <span class="qty-val">${qty}</span>
                            <button class="qty-btn" onclick="updateQty('${key}', 1)">+</button>
                        </div>
                    </div>
                `;
                list.appendChild(item);
            });

            document.getElementById('cart_subtotal').innerText = formatMoney(subtotal);
            calculateFinalTotal(subtotal);
            summaryBox.style.display = 'block';

            // Fetch dynamic shipping and payment gateways
            fetchShippingAndTax(subtotal);
        }

        function calculateFinalTotal(subtotal) {
            const finalVal = Math.max(0, subtotal - appliedDiscount + selectedShippingCost + selectedTax);
            document.getElementById('cart_total').innerText = formatMoney(finalVal);
        }

        // Fetch Shipping & Payment Methods
        async function fetchShippingAndTax(subtotal) {
            const city = document.getElementById('chk_city')?.value || '';
            const state = document.getElementById('chk_state')?.value || '';

            try {
                const res = await fetch(`${cfg.ajaxUrl || 'admin-ajax.php'}?action=woogram_webapp_shipping_tax&chat_id=${encodeURIComponent(tg?.initDataUnsafe?.user?.id || '')}&nonce=${webappNonce}&init_data=${encodeURIComponent(initDataRaw)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        cart: JSON.stringify(cart),
                        city: city,
                        state: state
                    })
                });
                const data = await res.json();
                if (data.success && data.data) {
                    selectedTax = Number(data.data.tax || 0);
                    document.getElementById('cart_tax').innerText = formatMoney(selectedTax);
                    renderShippingMethods(data.data.shipping_methods, data.data.needs_shipping);
                    renderPaymentGateways(data.data.payment_gateways);
                }
            } catch (err) {}
        }

        function renderShippingMethods(methods, needsShipping) {
            const sec = document.getElementById('shipping_section');
            const list = document.getElementById('shipping_methods_list');
            if (!needsShipping || !methods || !methods.length) {
                sec.style.display = 'none';
                selectedShippingCost = 0;
                selectedShippingMethod = '';
                document.getElementById('cart_shipping').innerText = needsShipping ? '${cfg.isPersian ? 'وارد کردن آدرس' : 'Enter address'}' : '${cfg.isPersian ? 'تحویل دانلودی (رایگان)' : 'Digital Delivery (Free)'}';
                return;
            }

            sec.style.display = 'block';
            list.innerHTML = '';
            methods.forEach((m, idx) => {
                const card = document.createElement('div');
                card.className = `option-card ${idx === 0 ? 'is-selected' : ''}`;
                card.innerHTML = `
                    <span>🚚 ${m.title}</span>
                    <strong>${m.formatted_cost}</strong>
                `;
                card.onclick = () => {
                    document.querySelectorAll('#shipping_methods_list .option-card').forEach(el => el.classList.remove('is-selected'));
                    card.classList.add('is-selected');
                    selectedShippingCost = m.cost;
                    selectedShippingMethod = m.id;
                    document.getElementById('cart_shipping').innerText = m.formatted_cost;
                    const subtotal = Number(document.getElementById('cart_subtotal').innerText.replace(/[^0-9.]/g, '')) || 0;
                    calculateFinalTotal(subtotal);
                };
                list.appendChild(card);
            });

            // Default first
            selectedShippingCost = methods[0].cost;
            selectedShippingMethod = methods[0].id;
            document.getElementById('cart_shipping').innerText = methods[0].formatted_cost;
        }

        function renderPaymentGateways(gateways) {
            const sec = document.getElementById('payment_section');
            const list = document.getElementById('payment_gateways_list');
            if (!gateways || !gateways.length) {
                sec.style.display = 'none';
                return;
            }

            sec.style.display = 'block';
            list.innerHTML = '';
            gateways.forEach((g, idx) => {
                const card = document.createElement('div');
                card.className = `option-card ${idx === 0 ? 'is-selected' : ''}`;
                card.innerHTML = `
                    <span>💳 ${g.title}</span>
                `;
                card.onclick = () => {
                    document.querySelectorAll('#payment_gateways_list .option-card').forEach(el => el.classList.remove('is-selected'));
                    card.classList.add('is-selected');
                    selectedPaymentMethod = g.id;
                };
                list.appendChild(card);
            });
            selectedPaymentMethod = gateways[0].id;
        }

        // Coupon Application
        document.getElementById('btn_apply_coupon')?.addEventListener('click', async () => {
            const code = document.getElementById('chk_coupon').value.trim();
            const feedback = document.getElementById('coupon_feedback');
            if (!code) return;

            const subtotal = Number(document.getElementById('cart_subtotal').innerText.replace(/[^0-9.]/g, '')) || 0;
            try {
                const res = await fetch(`${cfg.ajaxUrl || 'admin-ajax.php'}?action=woogram_webapp_validate_coupon&chat_id=${encodeURIComponent(tg?.initDataUnsafe?.user?.id || '')}&nonce=${webappNonce}&init_data=${encodeURIComponent(initDataRaw)}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ coupon_code: code, subtotal: subtotal })
                });
                const data = await res.json();
                if (data.success && data.data) {
                    appliedDiscount = data.data.discount;
                    appliedCouponCode = data.data.coupon_code;
                    feedback.style.color = '#15803d';
                    feedback.innerText = data.data.message;
                    document.getElementById('row_discount').style.display = 'flex';
                    document.getElementById('cart_discount').innerText = `-${data.data.formatted_discount}`;
                    calculateFinalTotal(subtotal);
                } else {
                    feedback.style.color = '#b91c1c';
                    feedback.innerText = data.data?.message || 'Invalid coupon';
                }
            } catch (err) {
                feedback.style.color = '#b91c1c';
                        feedback.innerText = '${cfg.isPersian ? 'خطا در بررسی کد تخفیف' : 'Error validating coupon'}';
            }
        });

        // Render Wishlist
        function renderWishlistView() {
            const list = document.getElementById('wishlist_items_list');
            if (!wishlist.length) {
                list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-icon">❤️</div>
                        <div class="empty-title">${cfg.isPersian ? 'لیست علاقه‌مندی شما خالی است' : 'Your wishlist is empty'}</div>
                        <div class="empty-desc">${cfg.isPersian ? 'با کلیک روی آیکون قلب، محصولات را برای بعد ذخیره کنید.' : 'Tap the heart icon on any product to save it here.'}</div>
                    </div>
                `;
                return;
            }

            list.innerHTML = '';
            wishlist.forEach(id => {
                const prod = productsCache.find(p => p.id == id);
                if (!prod) return;
                const item = document.createElement('div');
                item.className = 'cart-item';
                item.innerHTML = `
                    <img src="${prod.image}" class="cart-item-img">
                    <div class="cart-item-info">
                        <div class="cart-item-title">${prod.name}</div>
                        <div class="cart-item-price">${formatMoney(prod.price)}</div>
                    </div>
                    <button class="btn-add-cart" style="width: auto; padding: 0 12px;" onclick="updateQty(${prod.id}, 1)">🛒</button>
                    <button style="border: none; background: transparent; font-size: 16px; cursor: pointer;" onclick="toggleFav(${prod.id})">❌</button>
                `;
                list.appendChild(item);
            });
        }

        // Fetch Orders View
        async function fetchOrdersView() {
            const list = document.getElementById('orders_list');
            list.innerHTML = '<div style="text-align: center; padding: 20px;">⌛ ${cfg.isPersian ? 'در حال بارگذاری سفارش‌ها...' : 'Loading orders...'}</div>';

            const chatId = tg?.initDataUnsafe?.user?.id || '';
            try {
                const res = await fetch(`${cfg.ajaxUrl || 'admin-ajax.php'}?action=woogram_webapp_orders&chat_id=${chatId}&nonce=${webappNonce}&init_data=${encodeURIComponent(initDataRaw)}`);
                const data = await res.json();
                if (data.success && data.data && data.data.length) {
                    list.innerHTML = '';
                    data.data.forEach(o => {
                        const card = document.createElement('div');
                        card.className = 'cart-summary';
                        card.style.marginTop = '10px';

                        let itemsHtml = '<div style="margin: 8px 0; font-size: 12px; color: var(--hint-color);">';
                        (o.items || []).forEach(it => {
                            itemsHtml += `<div>▫ ${it.name} × ${it.quantity} (${it.total})</div>`;
                        });
                        itemsHtml += '</div>';

                        let downloadsHtml = '';
                        if (o.downloads && o.downloads.length) {
                            downloadsHtml = '<div style="margin-top: 8px; border-top: 1px dashed var(--card-border); padding-top: 6px;">';
                            o.downloads.forEach(d => {
                                downloadsHtml += `<a href="${d.url}" target="_blank" style="display: block; font-size: 12px; color: var(--button-color); text-decoration: none; margin-bottom: 4px;">📥 ${d.name}</a>`;
                            });
                            downloadsHtml += '</div>';
                        }

                        card.innerHTML = `
                            <div class="summary-row">
                                <strong>#${o.number}</strong>
                                <span>${o.status_label || o.status}</span>
                            </div>
                            <div class="summary-row">
                                <span>${cfg.isPersian ? 'تاریخ:' : 'Date:'} ${o.date}</span>
                                <strong>${o.formatted_total}</strong>
                            </div>
                            ${itemsHtml}
                            ${downloadsHtml}
                            ${o.pay_url ? `<a href="${o.pay_url}" class="btn-checkout" style="display: block; text-align: center; text-decoration: none; line-height: 44px; margin-top: 8px;">💳 ${cfg.isPersian ? 'پرداخت سفارش' : 'Pay Direct'}</a>` : ''}
                        `;
                        list.appendChild(card);
                    });
                } else {
                    list.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <div class="empty-title">${cfg.isPersian ? 'هنوز سفارشی ثبت نشده است' : 'No orders yet'}</div>
                        </div>
                    `;
                }
            } catch (e) {
                list.innerHTML = '<div>${cfg.isPersian ? 'خطا در دریافت سفارش‌ها.' : 'Error fetching orders.'}</div>';
            }
        }

        // Checkout submit
        document.getElementById('btn_proceed_checkout')?.addEventListener('click', async () => {
            const name = document.getElementById('chk_name').value.trim();
            const phone = document.getElementById('chk_phone').value.trim();
            const email = document.getElementById('chk_email').value.trim();
            const state = document.getElementById('chk_state').value.trim();
            const city = document.getElementById('chk_city').value.trim();
            const address = document.getElementById('chk_address').value.trim();
            const zip = document.getElementById('chk_zip').value.trim();
            const note = document.getElementById('chk_note').value.trim();

            if (!name || !phone || !address) {
                alert("${cfg.isPersian ? 'لطفاً نام، شماره تماس و آدرس را وارد کنید.' : 'Please fill in all required recipient fields.'}");
                return;
            }

            const btn = document.getElementById('btn_proceed_checkout');
            btn.disabled = true;
            btn.innerText = "${cfg.isPersian ? 'در حال پردازش سفارش...' : 'Processing Order...'}";

            try {
                const res = await fetch(cfg.ajaxUrl || 'admin-ajax.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'woogram_webapp_checkout',
                        nonce: webappNonce,
                        init_data: initDataRaw,
                        cart: JSON.stringify(cart),
                        name: name,
                        phone: phone,
                        email: email,
                        state: state,
                        city: city,
                        address: address,
                        zip: zip,
                        note: note,
                        coupon_code: appliedCouponCode,
                        shipping_method: selectedShippingMethod,
                        shipping_cost: selectedShippingCost,
                        payment_method: selectedPaymentMethod,
                        chat_id: tg?.initDataUnsafe?.user?.id || '',
                        username: tg?.initDataUnsafe?.user?.username || '',
                    })
                });

                const data = await res.json();
                if (data.success && data.data?.redirect_url) {
                    localStorage.removeItem('woogram_cart');
                    cart = {};
                    updateCartBadge();
                    if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
                    window.location.href = data.data.redirect_url;
                } else {
                    alert(data.data?.message || '${cfg.isPersian ? 'خطا در ایجاد سفارش.' : 'Error creating order.'}');
                    btn.disabled = false;
                    btn.innerText = "💳 ${cfg.isPersian ? 'تکمیل سفارش و پرداخت' : 'Proceed to Payment'}";
                }
            } catch (err) {
                alert('${cfg.isPersian ? 'خطا در اتصال. لطفاً دوباره تلاش کنید.' : 'Connection error. Please try again.'}');
                btn.disabled = false;
                btn.innerText = "💳 ${cfg.isPersian ? 'تکمیل سفارش و پرداخت' : 'Proceed to Payment'}";
            }
        });

        // Search listener
        let searchTimer = null;
        document.getElementById('search_input')?.addEventListener('input', (e) => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                fetchProducts(0, e.target.value.trim());
            }, 300);
        });

        // Initial Load
        updateCartBadge();
        fetchProducts();
    </script>
