=== Woo B2B ===
Contributors: 3ag
Tags: woocommerce, b2b, wholesale, login, registration, approval, catalog visibility
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
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
The interface is English for now. All strings are translation-ready (text domain `woo-b2b`).

== Changelog ==

= 1.0.0 =
* Initial release: catalog lock, login/registration page, manual approval workflow,
  email notifications, admin Users-list integration, 3AG license activation, and
  automatic updates from GitHub Releases.
