<div align="center">

# ⚡ TeleNexa

### WooCommerce Storefront, Sales Bot & Mini App for Telegram

Turn your WooCommerce store into a native-feeling Telegram shopping experience — open source and free.

[![Version](https://img.shields.io/badge/version-1.0.0-2ea44f?style=for-the-badge)](#)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-blue?style=for-the-badge)](#license)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-TeleNexa-21759B?style=for-the-badge&logo=wordpress&logoColor=white)](https://wordpress.org/plugins/telenexa-woocommerce-telegram-bot/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-Ready-96588A?style=for-the-badge&logo=woocommerce&logoColor=white)](https://woocommerce.com/)
[![Telegram](https://img.shields.io/badge/Telegram-Bot%20%2B%20Mini%20App-26A5E4?style=for-the-badge&logo=telegram&logoColor=white)](https://core.telegram.org/bots/webapps)
[![MilMit](https://img.shields.io/badge/by-MilMit-111827?style=for-the-badge)](https://milmit.net/)

**[Features](#-features) · [Installation](#-installation) · [Configuration](#%EF%B8%8F-configuration) · [WordPress.org](https://wordpress.org/plugins/telenexa-woocommerce-telegram-bot/) · [Official Page](https://milmit.net/product/telenexa-woocommerce-telegram-bot/) · [فارسی](#-فارسی)**

</div>

---

## ✨ What is TeleNexa?

**TeleNexa** connects **WordPress + WooCommerce** with **Telegram**, giving your customers a convenient way to discover products, manage a cart and interact with your store without leaving Telegram.

It combines a **Telegram Bot** with a **Telegram Mini App** so the conversational layer and the visual storefront work together.

> **Free & open source.** TeleNexa does not require a purchase code or commercial license to use.

## 🚀 Features

| Feature | What it does |
| --- | --- |
| 🤖 **Telegram Sales Bot** | Connect your WooCommerce store to a Telegram bot |
| 📱 **Telegram Mini App** | Provide a modern visual storefront inside Telegram |
| 🛍️ **Product Discovery** | Browse and search WooCommerce products |
| 🛒 **Shopping Cart** | Add, remove and manage cart items |
| 🎛️ **Variable Products** | Support product variations and selectable options |
| 🏷️ **Coupons** | Work with WooCommerce coupon and discount flows |
| 📦 **Orders** | Create and work with WooCommerce orders |
| 💳 **Payments** | Continue through the WooCommerce payment flow |
| 🔎 **Order Tracking** | Help customers follow their orders |
| ❤️ **Wishlist** | Save products for later |
| 🔔 **Telegram Notifications** | Deliver useful store and order notifications |
| 📣 **Broadcast** | Send store announcements through Telegram |
| 🌍 **WPML** | Designed for multilingual WordPress stores |

## 🧩 How it works

```text
Customer
   │
   ▼
Telegram
   ├── 🤖 TeleNexa Bot
   └── 📱 TeleNexa Mini App
              │
              ▼
        WordPress / WooCommerce
              │
      ┌───────┼────────┐
      ▼       ▼        ▼
   Products  Cart    Orders
                       │
                       ▼
                 Payment Flow
```

TeleNexa keeps **WooCommerce as the commerce engine** while Telegram becomes an additional customer-facing storefront.

## 📦 Installation

1. Download the latest TeleNexa release from GitHub.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**.
3. Upload the TeleNexa ZIP file and activate it.
4. Make sure **WooCommerce** is installed and active.
5. Open the TeleNexa settings in WordPress.
6. Connect your Telegram bot and configure the Mini App.
7. Test the storefront, cart and checkout before sharing the bot publicly.

## ⚙️ Configuration

### 1. Create a Telegram bot

Open **@BotFather** in Telegram, create a new bot and copy the generated bot token.

> 🔐 Never publish your bot token in GitHub issues, screenshots, commits or public configuration files.

### 2. Connect TeleNexa

Enter the required Telegram configuration in the TeleNexa settings inside WordPress and save your changes.

### 3. Configure the Mini App

Configure the Telegram Mini App / Web App entry point for your bot and use your store's secure **HTTPS** endpoint.

### 4. Test before launch

Verify product browsing, variable products, cart operations, coupons, order creation, payment redirection and Telegram notifications with a test account.

## 🌍 Multilingual stores

TeleNexa is designed to work with multilingual WordPress setups and includes **WPML support**, making it suitable for stores serving customers in more than one language.

## 🔐 Security

TeleNexa should be deployed using normal WordPress and WooCommerce security practices:

- Keep WordPress, WooCommerce and TeleNexa updated.
- Use HTTPS for the website and Mini App.
- Treat Telegram bot tokens as secrets.
- Never commit production credentials to a public repository.
- Use trusted payment gateways and keep checkout inside the WooCommerce flow.
- Back up the site before major upgrades.

If you discover a security issue, please avoid posting sensitive exploit details publicly.

## 🗺️ Project direction

TeleNexa aims to make Telegram a practical commerce channel for WooCommerce while keeping store ownership and product/order management inside WordPress.

Ideas and improvements are welcome through GitHub Issues and Pull Requests.

## 🤝 Contributing

Contributions are welcome.

1. Fork the repository.
2. Create a feature branch.
3. Make focused, documented changes.
4. Test against WordPress + WooCommerce.
5. Open a Pull Request describing the problem and your solution.

Please keep changes backward-compatible where practical.

## 🐛 Bugs & feature requests

Found a bug or have an idea? Open a GitHub Issue with:

- WordPress and WooCommerce versions
- TeleNexa version
- PHP version
- Clear reproduction steps
- Expected vs. actual behavior

**Do not include bot tokens, passwords, API secrets or customer data.**

## 🇮🇷 فارسی

**TeleNexa** یک افزونه رایگان و متن‌باز برای اتصال **ووکامرس به تلگرام** است. این پروژه امکان ایجاد **ربات فروش تلگرام** و **Telegram Mini App** را فراهم می‌کند تا کاربران بتوانند محصولات فروشگاه را مشاهده و جستجو کنند، سبد خرید داشته باشند، محصولات متغیر و کد تخفیف را استفاده کنند و فرایند سفارش و پرداخت ووکامرس را دنبال کنند.

TeleNexa برای فروشگاه‌های چندزبانه نیز طراحی شده و از **WPML** پشتیبانی می‌کند.

> TeleNexa رایگان است و برای استفاده از افزونه نیازی به Purchase Code یا لایسنس تجاری ندارد.

## ❤️ Built by MilMit

TeleNexa is developed and maintained by **MilMit**.

**Official TeleNexa Page:** [milmit.net/product/telenexa-woocommerce-telegram-bot/](https://milmit.net/product/telenexa-woocommerce-telegram-bot/)  
**WordPress.org:** [wordpress.org/plugins/telenexa-woocommerce-telegram-bot/](https://wordpress.org/plugins/telenexa-woocommerce-telegram-bot/)  
**GitHub:** [github.com/MilMit](https://github.com/MilMit)

If TeleNexa helps your project, consider giving the repository a ⭐ — it helps more WooCommerce developers discover it.

## 📄 License

TeleNexa is distributed under the **GNU General Public License v2.0 or later (GPL-2.0+)**.

WordPress and WooCommerce are trademarks of their respective owners. Telegram is a trademark of Telegram Messenger Inc. TeleNexa is an independent project and is not affiliated with or endorsed by Telegram.

---

<div align="center">

**TeleNexa · WooCommerce × Telegram**

Made with ❤️ by [MilMit](https://milmit.net/)

</div>
