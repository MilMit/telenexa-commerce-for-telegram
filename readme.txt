=== TeleNexa Commerce for Telegram ===
Contributors: milmitnet
Tags: telegram, woocommerce, telegram bot, ecommerce, shopping cart
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A Telegram storefront, Mini App and order-management integration for WooCommerce.

== Description ==

TeleNexa connects a WooCommerce store to Telegram and gives customers a shopping experience through a Telegram bot and an optional Telegram Mini App.

TeleNexa is free to use and does not require a purchase code or license activation.

= Features =

* Product catalog, categories, search and pagination
* Product images, stock status, SKU and sale badges
* Variable products and variation selection
* Virtual and downloadable product support
* Persistent cart with quantity controls and coupons
* WooCommerce order creation and payment links
* Customer-only order tracking with HPOS-compatible queries
* Wishlist and product notifications
* Persian and English language selection
* WPML language synchronization
* Configurable Telegram command and main menus
* Optional Telegram Mini App storefront
* Action Scheduler support for background broadcasts and notifications

= External services =

TeleNexa communicates with Telegram when the store owner configures a Telegram bot. Bot tokens, message content, Telegram chat identifiers, profile information required by the bot, product/order interaction data, and files intentionally sent through the bot may be transmitted to Telegram as necessary to provide the configured bot features.

Telegram Bot API: https://core.telegram.org/bots/api
Telegram Terms of Service: https://telegram.org/tos
Telegram Privacy Policy: https://telegram.org/privacy

TeleNexa also includes an optional MilMit relay webhook mode for servers that cannot receive Telegram webhooks directly. This mode is disabled by default. When the store owner explicitly enables the relay mode, Telegram webhook traffic is routed through the MilMit relay endpoint and then forwarded to the WordPress site. Do not enable this mode if you do not want webhook traffic to pass through the relay service.

MilMit service: https://milmit.net/
MilMit Privacy Policy: https://milmit.net/privacy/

The optional Telegram Mini App loads Telegram's official Web App JavaScript bridge from `https://telegram.org/js/telegram-web-app.js` when the Mini App is opened. This bridge is required for Telegram Mini App functionality and is not loaded on ordinary WordPress pages.

== Requirements ==

* WordPress 5.8 or newer
* WooCommerce 7.0 or newer
* PHP 7.4 or newer
* PHP extensions: cURL, mbstring and PDO are recommended
* HTTPS is recommended for direct Telegram webhooks and required for production Mini App use

== Installation ==

1. Upload the `telenexa-commerce-for-telegram` directory to `/wp-content/plugins/`, or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate WooCommerce and TeleNexa.
3. Open TeleNexa in the WordPress admin.
4. Create/configure a Telegram bot with BotFather and enter the bot token and username.
5. Configure the webhook, language, storefront and optional Mini App features.
6. Use the direct webhook mode when possible. Enable the optional MilMit relay only when your hosting environment requires it.

== Frequently Asked Questions ==

= Does TeleNexa require a paid license? =

No. Version 1.0.0 does not require a purchase code or license activation.

= Does it send data to external services? =

Yes, when you configure and use Telegram features, the plugin communicates with Telegram as described in the External services section. The MilMit webhook relay is optional and disabled by default.

= Does the plugin support WooCommerce HPOS? =

The order queries used by TeleNexa are designed to work with WooCommerce HPOS-compatible APIs.

= Can I type emoji without the old emoji picker? =

Yes. TeleNexa accepts standard Unicode emoji directly in message fields. The bundled third-party picker was removed from the distribution to avoid unnecessary remote executable-code fallbacks.

== Privacy ==

TeleNexa stores Telegram chat identifiers and basic Telegram profile information required to deliver bot messages and associate Telegram activity with customers. Depending on enabled features, it may also store cart/session data, order references, referral attribution, notification preferences and payment transaction references.

When Telegram functionality is configured, information required to perform the requested bot operation is transmitted to Telegram. If the store owner explicitly enables the optional MilMit relay webhook mode, incoming webhook traffic passes through the MilMit relay service before reaching the WordPress site.

TeleNexa adds suggested privacy-policy text to WordPress under Settings > Privacy. Store owners remain responsible for adapting their privacy notice, lawful basis, retention periods and deletion/export procedures to their jurisdiction and configuration.

== Third-party code ==

Third-party PHP dependencies distributed in `vendor/` retain their respective license and copyright notices. The legacy `WooGram` PHP namespace and `woogram_*` internal identifiers are retained for backward compatibility with existing settings, database records and integrations; the public product name is TeleNexa.

== Changelog ==

= 1.0.0 =
* Initial TeleNexa release.
* Added WooCommerce Telegram storefront, order management and optional Mini App functionality.
* Added privacy-policy disclosure for Telegram and optional MilMit relay processing.
* Removed the legacy emoji picker runtime to avoid unnecessary external executable-code fallbacks; native Unicode emoji remain supported.
* Removed the remotely hosted Telegram-style admin background asset.
* Kept legacy internal identifiers for safe backward compatibility.
