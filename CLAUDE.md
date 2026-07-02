# Woo B2B — Claude Code guide

Turns a WooCommerce store into a B2B-only shop: the storefront is gated behind login, and new
registrations stay **pending** until an admin approves them. Single-client plugin (3AG), running on
a WoodMart-theme store.

- **Text domain:** `woo-b2b` · **Version constant:** `WB2B_VERSION` — keep it in sync with the
  `Version:` header in `woo-b2b.php` and with `readme.txt`.
- **Requires:** PHP 7.4+, WP 5.8+, WC 5.0+. HPOS-compatible.

## Architecture

- **Bootstrap:** `woo-b2b.php` defines constants (`WB2B_PLUGIN_DIR`, `WB2B_PLUGIN_URL`,
  `WB2B_PLUGIN_BASENAME`, `WB2B_PRODUCT_SLUG`, `WB2B_VERSION`), a singleton `Woo_B2B` reached via
  the global `WB2B()`, and an SPL autoloader: **`WB2B_Foo_Bar` → `includes/class-foo-bar.php`**.
- **Components** (instantiated on `plugins_loaded`, after a WooCommerce check): `WB2B_Logs`,
  `WB2B_Customer`, `WB2B_Emails`, `WB2B_Access`, `WB2B_Auth`, `WB2B_Ajax`, `WB2B_License`,
  `WB2B_Updater`, `WB2B_Payments`, `WB2B_Pricing`, and `WB2B_Admin` (admin requests only). Reach them
  via `WB2B()->emails`, `WB2B()->license`, etc.
- **Customer status** lives in user meta `wb2b_status` = `pending|approved|rejected` (see
  `WB2B_Customer` constants/helpers). Addresses map to native WooCommerce billing/shipping meta.

## Layout

```
woo-b2b.php            bootstrap + autoloader + Woo_B2B singleton
includes/              one class per file (class-*.php)
includes/views/        presentational templates (admin-*, license, auth-page, form-*)
assets/css/admin.css   admin UI        (scoped under .wb2b-ui)
assets/css/b2b.css     front-end auth  (scoped under .wb2b-auth)
assets/js/admin.js     approvals + license + modal dialogs
assets/js/b2b.js       auth form behaviour
```

## Conventions

- **Prefixes:** classes `WB2B_`, functions/options `wb2b_`, CSS `wb2b-`. Admin CSS modifiers use a
  **single dash** (`.wb2b-btn-primary`, `.wb2b-input-lg`, `.wb2b-input-sm`); the front-end auth
  sheet uses BEM-ish `--` (`.wb2b-card--login`). Match the file you're editing.
- **i18n:** wrap user-facing strings with `__()` / `esc_html_e()` (text domain `woo-b2b`).
- **Security:** escape on output (`esc_html`, `esc_attr`, `esc_url`), verify nonces, and gate admin
  actions on `current_user_can('manage_woocommerce')`. Every PHP file opens with
  `if (!defined('ABSPATH')) { exit; }`.
- **Views** are plain PHP includes: the controller gathers data into locals, then
  `include WB2B_PLUGIN_DIR . 'includes/views/...';`. Document the passed vars in the view docblock.

## Admin pages & UI

`WB2B_Admin` registers a top-level **B2B Customers** menu with **Customers** (approvals),
**Settings**, and **License** subpages, plus a B2B-status column on the Users list. Admin assets
load only on our screens + `users.php` (`enqueue_assets()`), cache-busted with `filemtime()`.

The admin design system lives in `admin.css` under `.wb2b-ui` (CSS variables + shared components:
`.wb2b-header`, `.wb2b-card`, `.wb2b-btn*`, `.wb2b-input`, `.wb2b-select`, `.wb2b-switch`,
`.wb2b-table`, `.wb2b-tabs`, `.wb2b-toast`, `.wb2b-modal`). All three pages share the same
`.wb2b-header` block. In `admin.js`, prefer the provided `wb2bConfirm()` / `wb2bPrompt()` Promise
dialogs and the `notice()` toast over native `window.confirm/prompt`.

## Settings / options

Registered in `WB2B_Admin::register_settings()` under the **`wb2b_settings`** group and saved via
core `options.php` (the form calls `settings_fields('wb2b_settings')`). To add a setting:
`register_setting()` with a sanitizer callback → read it in `render_settings_page()` → pass it to
`admin-settings.php` → render a field with existing classes (`.wb2b-form-row`, `.wb2b-label`,
`.wb2b-select`/`.wb2b-input`). Read anywhere with `get_option('wb2b_*', <default>)`.

## Auth page & skins

The `[woo_b2b_auth]` shortcode (`WB2B_Auth::render_auth_page()`) renders login + registration into
`.wb2b-auth`, styled by `b2b.css`. The **`wb2b_auth_ui_style`** option selects the skin via a
wrapper class:
- `theme` (default) → `wb2b-skin--theme`, which remaps the auth CSS variables to the active theme's
  tokens (WoodMart: `--wd-primary-color`, `--btn-accented-*`, `--wd-form-*`), each with the
  plugin's own value as a `var()` fallback — so it degrades to the default look on non-WoodMart
  themes. Add new themes as more `wb2b-skin--*` blocks + a whitelist value in `sanitize_ui_style()`.
- `default` → the plugin's self-contained palette (no remap).

## Access modes & pricing

The storefront gate is driven by the **`wb2b_access_mode`** option, read through the single helper
`WB2B_Access::get_mode()` (never read the option directly). Two values (the store is always gated in
one of them; to disable entirely, deactivate the plugin):
- `redirect` (default) → lockdown: `WB2B_Access::maybe_redirect()` sends guests to the auth surface,
  and locked pages get `noindex`.
- `prices` → public catalog, but `WB2B_Pricing` hides prices and disables purchasing for anyone
  without access (`!WB2B_Customer::has_access()` — in practice guests, since unapproved users can't
  log in), showing a "Log in to see prices" link. No redirect, catalog stays indexable.

`get_mode()` defaults to `redirect`, so legacy installs (only the old `wb2b_enabled` set) stay gated.
`WB2B_Pricing` only registers its WooCommerce filters (`woocommerce_get_price_html`,
`woocommerce_is_purchasable`, `woocommerce_loop_add_to_cart_link`, …) while the mode is `prices`. To
add a mode, extend the whitelist in both `WB2B_Admin::sanitize_access_mode()` and `get_mode()`.

## Auth surface (single system on My Account)

There is **one** auth surface: the WooCommerce **My Account** page. `WB2B_Auth` overrides the
`myaccount/form-login.php` template (via the `wc_get_template` filter, which beats the theme's own
override) so logged-out visitors see the plugin's unified login + custom B2B registration UI
(`render_auth_page()`). Native WooCommerce/WoodMart **registration** is force-disabled on the front
end (`option_woocommerce_enable_myaccount_registration` → `no`), so registration only ever happens
through the custom form. **Login** stays native and is gated by `block_unapproved_login()` on the
`authenticate` filter — unapproved (pending/rejected) users cannot log in on any path. Lockdown
redirects and "Log in to see prices" links target `WB2B_Access::get_auth_url()` (My Account, falling
back to the standalone `[woo_b2b_auth]` page, which is kept as a backward-compat alias). `b2b.css`/
`b2b.js` load on both the account page (logged-out) and the shortcode page.

## Payments — Pay by Invoice

`WB2B_Payments` registers the `WB2B_Gateway_Invoice` gateway (id **`wb2b_invoice`**, extends
`WC_Payment_Gateway`, modelled on WooCommerce BACS) and gates it to approved customers via
`woocommerce_available_payment_gateways` + `WB2B_Customer::has_access()`. The gateway is an offline
"on account" method: at checkout the order is set to its configured status (default `on-hold`,
which auto-reduces stock via WC's status-transition hooks — don't reduce stock manually). Settings
live under **WooCommerce → Settings → Payments → Pay by Invoice** (WC-native option
`woocommerce_wb2b_invoice_settings`), *not* the plugin's own settings page. Net terms (`terms_days`,
default 30) yield a due date stored as order meta `_wb2b_invoice_due_date` / `_wb2b_invoice_terms_days`
(HPOS CRUD), shown on the order-received page and in customer emails.

## License & updates

`WB2B_License` (3AG License API), the License admin page, and `WB2B_Updater` (GitHub Releases for
`3agApp/woo-b2b`). Licensing is **non-enforcing**: an invalid/absent key only shows an admin nag and
blocks updates — it never disables the catalog gate. `.github/workflows/release.yml` publishes a
release on every `woo-b2b.php` version bump.

## Local dev / testing

This is a **Local by Flywheel** site (`myshop`, `http://myshop.local`). There is no global `wp`;
use Local's bundled PHP + the wp-cli phar. The run-id path segment (`wLToD2csV`) and PHP version can
change if the site is recreated — re-derive them if the paths break.

```bash
PHP="/Users/sourov/Library/Application Support/Local/lightning-services/php-8.2.27+1/bin/darwin-arm64/bin/php"
INI="/Users/sourov/Library/Application Support/Local/run/wLToD2csV/conf/php/php.ini"
SITE="/Users/sourov/Local Sites/myshop/app/public"
"$PHP" -c "$INI" /tmp/wp-cli.phar option get wb2b_auth_ui_style --path="$SITE"
```

- `wp db query` won't work (no mysql binary) — use `wp eval` with `$wpdb` for SQL.
- Lint with `"$PHP" -l <file>` before trusting a page.
- PHP notices land in `wp-content/debug.log` (with `WP_DEBUG` + `WP_DEBUG_LOG` on).
- HTTP smoke tests: `curl http://myshop.local/...`.
