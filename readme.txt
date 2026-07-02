=== Woo B2B ===
Contributors: 3ag
Tags: woocommerce, b2b, wholesale, login, registration, approval, catalog visibility
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn a WooCommerce store into a B2B-only shop: hide the catalog from guests and require admin approval before customers can browse or order.

== Description ==

Woo B2B locks your entire storefront behind login and a manual approval step, so only
approved business customers can see products, prices, or place orders.

* **Catalog lock** — every front-end page redirects guests and unapproved users to a single
  account page. Administrators and shop managers are never gated. A small allowlist keeps
  pages like your privacy policy and terms public.
* **Login + registration page** — a self-contained, theme-neutral account page with a login
  form and a full B2B registration form (salutation, company, VAT ID, billing and optional
  shipping address, and optional document uploads).
* **Manual approval** — new registrations are created as WooCommerce customers in a *pending*
  state and cannot log in until an administrator approves them. Approve or reject from the
  dedicated **B2B Customers** screen or directly from the WordPress Users list.
* **Email notifications** — the admin is notified of new registrations; customers are emailed
  when their registration is received, approved, or rejected.
* **WooCommerce-native data** — address fields are stored on the customer so checkout and the
  My Account area are pre-filled after approval.
* **License + automatic updates** — activate your 3AG license under **B2B Customers → License**
  to receive automatic updates delivered from GitHub Releases.

This plugin handles access control and approval only. It does not change pricing or tax logic.

== Installation ==

1. Upload the `woo-b2b` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress. WooCommerce must be active.
3. On activation an **Account** page is created automatically containing the `[woo_b2b_auth]`
   shortcode. You can move or restyle this page; set it under **B2B Customers → Settings** if
   you use a different page.
4. Review options under **B2B Customers → Settings** (allowed countries, document requirements,
   always-public pages, notification email, and the master enable switch).

Existing user accounts are grandfathered in as *approved* on activation so they are not locked
out.

== Frequently Asked Questions ==

= Can I temporarily make the shop public again? =
Yes. Turn off **Enable B2B lock** under **B2B Customers → Settings**.

= Where do uploaded documents go? =
They are stored as private attachments and linked from the customer row on the approvals screen.

= Does it support multiple languages? =
The interface ships in English and German (`de_DE`). All strings are translation-ready (text domain `woo-b2b`); a `woo-b2b.pot` template is included for adding further languages.

== Changelog ==

= 1.4.1 =
* New **Access mode** setting replacing the on/off B2B lock: choose **Lockdown** (redirect guests to the account page) or **Show catalog, hide prices** (guests can browse products but prices are hidden and purchasing is disabled until they log in).
* In "Show catalog, hide prices" mode, guests see a "Log in to see prices" link in place of prices and Add to Cart; approved customers and admins see prices as normal.
* **Single sign-in surface:** login and the custom B2B registration now live on the WooCommerce My Account page. Native WooCommerce/theme registration is disabled so new customers always register through the approval form; login stays gated (unapproved accounts cannot sign in).
* Existing sites are migrated automatically to Lockdown mode.
* Added a **German** translation (`de_DE`) and a `woo-b2b.pot` template for further languages.

= 1.2.0 =
* New **Pay by Invoice** payment method for approved B2B customers: orders are placed on account (on-hold) and settled later against an invoice.
* Configurable net payment terms (default 30 days); the due date is stored on the order and shown on the order-received page and in customer emails.
* Configure it under **WooCommerce → Settings → Payments → Pay by Invoice** (title, description, instructions, order status, payment terms). The method is only offered to approved B2B customers.

= 1.1.0 =
* Redesigned admin UI with a modern, consistent design system and AJAX settings save (no page reload).
* New B2B Customers dashboard: status stat cards plus customer search, country filter, sort, and bulk approve/reject.
* Replaced browser confirm/prompt popups with styled, accessible in-page dialogs.
* Auth page: selectable UI style (inherit the active theme's styles, or use the plugin's own), a refreshed two-column layout with a hero/benefits panel, and full WoodMart theme inheritance — including dark-mode support.

= 1.0.1 =
* Version bump for the current release.

= 1.0.0 =
* Initial release: catalog lock, login/registration page, manual approval workflow,
  email notifications, admin Users-list integration, 3AG license activation, and
  automatic updates from GitHub Releases.
