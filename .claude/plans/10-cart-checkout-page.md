# 10: Cart and checkout pages (no shipping, three payment methods, Telegram order alerts)

Status: Done, except a paid OnePay sandbox payment (needs the contract's sandbox account) and the optional customer email; see log
Depends on: 09 (storefront package, customer sign-in, header mini cart). VND is the only currency (2026-11-05).

## Goal

Let a customer put products in a cart, review it, and place an order from the storefront:

- **Add to cart** from the product cards on the home page and search results.
- **Cart page** (`/cart`): lines with image, name, unit price, quantity stepper, remove, subtotal, total.
- **Checkout page** (`/checkout`): contact details, order note, the same lines with **editable quantities**,
  a choice of **payment method**, and a "Đặt hàng" (place order) button.
- Placing the order **saves it to the `orders` table** (with items, payment method, contact details) and
  **sends a Telegram message** to the shop.
- **No shipping**: the company has no delivery service, so there is no shipping address, carrier, shipping option
  or shipping fee. Customers collect at the office (address from Settings → General).
- **Payment methods** (only these three):
  1. **Pay at the office** (`office`): pay cash or card when collecting.
  2. **Online pay** (`bank_transfer`): bank transfer with a VietQR code and the order number as the transfer note
     (confirmed by the user 2026-11-08).
  3. **OnePay** (`onepay`): redirect to the OnePay **domestic** gateway (ATM/Napas cards only, decided 2026-11-08),
     back to the shop on return, confirmed by OnePay's IPN call.

Out of scope: customer profile and order history (later plan), shipping, coupons UI (the cart supports coupons,
the UI can come later), invoices/VAT e-invoices, emails to the customer beyond Laravel's default (see Phase 3).

## What already exists (reuse, do not rebuild)

| Need | Existing piece |
|------|----------------|
| Cart, lines, totals, stock check | `packages/cart`: `CartManager::add/update/remove/clear/calculate`, `guardStock`, `CartPipelineRunner` (lines → discounts → tax → total) |
| Session cart | `CartSessionManager::current/create/use/forget/associate` (`shopper.cart.session.auto_create` is false, keep it) |
| Cart → order | `Shopper\Cart\Actions\CreateOrderFromCartAction`: locks the cart, creates `Order` + `OrderItem`s, reserves stock, writes tax lines, sets `completed_at`, fires `CartCompleted` |
| Order model | `Order` (`number` from `generate_number()`, `price_amount`, `payment_method_id`, `notes`, `metadata`, `status` New, `payment_status` Pending, `shipping_status` Unfulfilled); `OrderCreated` event |
| Payment methods | `PaymentMethod` (`title`, `slug`, `driver`, `logo`, `instructions`, `is_enabled`, `metadata`) and the admin screen for it |
| Payment drivers | `packages/payment`: `PaymentDriver` contract, `ManualDriver`, `Payment::extend()`, `PaymentProcessingService` (records `payment_transactions`, syncs `payment_status`) |
| Driver package template | `packages/stripe` (`StripeServiceProvider` calls `Payment::extend('stripe', …)`) |
| Storefront shell | `packages/storefront`: layout, header `MiniCart` (reads the session cart without creating one), `ProductPrice`, `ProductImage`, `StorefrontUrls`, path `cart` = `/cart` in `config/storefront.php` |
| Customers | storefront sign-in / sign-up (plan 09), customer role |
| Office address, store phone | `StorefrontSettings::address()`, `hotline()` |

Missing today: **no add-to-cart anywhere**, no cart or checkout page, no product page, no OnePay driver,
no Telegram integration, `CreateOrderFromCartAction` does not set `payment_method_id`, `notes` or contact details.

## Architecture

1. **Cart and checkout live in `packages/storefront`** (pages, Livewire components, actions). The order is
   still created by `CreateOrderFromCartAction`; extend it with an optional `OrderDetails` data object
   (payment method, note, contact) instead of copying it.
2. **OnePay is its own package `packages/onepay`** (`Shopper\OnePay`), modelled on `packages/stripe` and
   registered with `Payment::extend('onepay', …)` (follow the `shopper-package` skill). Office and bank transfer
   use the existing `ManualDriver` (`PaymentMethod.driver = 'manual'`, told apart by `slug`).
3. **Telegram** is a small notifier in `packages/storefront` (`Shopper\Storefront\Notifications\Telegram`):
   - Its settings come from a **JSON file that is not in git**, because the bot and chat for production are
     different from localhost.
   - Path: `config('shopper.storefront.telegram.config_path')` = `env('SHOPPER_TELEGRAM_CONFIG', base_path('telegram.json'))`.
     The workbench sets `SHOPPER_TELEGRAM_CONFIG=/var/www/html/workbench/telegram.json`.
   - `workbench/telegram.example.json` is committed. `.gitignore` gets `telegram.json` (any folder).
   - `docker-compose.yml` mounts `./telegram.json` read-only into the container like it does `.env.production`.
   - Missing file, `"enabled": false` or bad JSON: no message, a warning in the log, **the order is still placed**.
4. **Prices are always recomputed on the server** from the database (`CartManager::calculate`). The browser only
   sends line ids and quantities.
5. **Guest checkout allowed** (see Open decisions): the cart belongs to the session; when a guest signs in,
   `CartSessionManager::associate()` attaches the cart to the customer (hook it into the plan 09 login and
   register controllers).
6. Storefront text in `vi` (default) and `en`; admin labels `en`, `vi`. JSON API for headless sites mirrors the pages.

### Telegram config file

```json
{
    "enabled": true,
    "bot_token": "123456:ABC-DEF…",
    "chat_ids": ["-1001234567890"],
    "message_thread_id": null,
    "admin_order_url": "https://shop.example.vn/cpanel/orders/{id}"
}
```

- Sent with `Http::post("https://api.telegram.org/bot{token}/sendMessage", [chat_id, text, parse_mode: HTML,
  message_thread_id?, link_preview_options: {is_disabled: true}])`, timeout 5 s.
- Sent by a queued job `SendTelegramOrderMessage` (3 tries, backoff 10/60 s), dispatched **after the DB commit**
  (`afterCommit()`); with `QUEUE_CONNECTION=sync` (dev) it runs inline but its failure is caught and logged.
- Message (all user input escaped with `e()`, text capped to Telegram's 4096 characters):

```
🛒 Đơn hàng mới #ORD-000123
Khách: Nguyễn Văn A · 0901 234 567 · a@example.com
Thanh toán: OnePay (chờ thanh toán)
— Áo thun x2 · 398.000 ₫
— Mũ lưỡi trai x1 · 150.000 ₫
Tổng: 548.000 ₫
Ghi chú: Lấy hàng chiều thứ 7
Xem đơn: https://…/orders/123
```

- A second, short message when OnePay confirms payment ("✅ Đã thanh toán #ORD-000123 qua OnePay · 548.000 ₫")
  or reports a failure.
- The bot token never appears in logs, exceptions or the admin.

### OnePay

- Gateway: redirect (`vpc_*` query parameters, `vpc_SecureHash` = uppercase HMAC-SHA256 of the sorted `vpc_`/`user_`
  parameters with the hex-decoded secure secret). `vpc_Amount` = VND amount × 100. `vpc_MerchTxnRef` = a unique
  reference per attempt (`{order number}-{attempt}`), `vpc_OrderInfo` = order number, `vpc_ReturnURL` = storefront
  return route, `vpc_TicketNo` = customer IP, `vpc_Locale` = `vn`/`en`.
- Two endpoints in OnePay's API: domestic (ATM/Napas) and international (Visa/Master/JCB). Credentials per endpoint
  (`merchant_id`, `access_code`, `hash_secret`), sandbox URLs when `PAYMENT_SANDBOX=true`. Credentials come from
  env (`ONEPAY_*`) in `shopper.payment.drivers.onepay`, like Stripe.
- **Return URL** (customer's browser): verify the hash, show success/failure, **never** mark the order paid from
  it alone unless the IPN has not arrived and a `QueryDR` call confirms it.
- **IPN URL** (server to server, `POST/GET /api/onepay/ipn`): verify the hash, check amount and reference match the
  order, make it idempotent (ignore a second "paid" for the same reference), record the transaction through
  `PaymentProcessingService`, set `payment_status = Paid`, answer `responsecode=1&desc=confirm-success` as OnePay
  expects. Exempt from CSRF.
- `vpc_TxnResponseCode = 0` is success; map the other codes to translated messages.
- A "Pay again" button on the order page while the order is unpaid and not cancelled.

## Data model

No new tables. Changes:

| Where | Change |
|-------|--------|
| `orders.metadata` (JSON, exists) | `contact` → `{name, phone, email}`, `pickup: true`, `source: storefront`, `ip`. Contact is also copied to a `billing` `OrderAddress` (name, phone, no street) so the admin order screen shows it without changes. |
| `orders.notes` (exists) | customer's note, max 1000 characters, plain text |
| `orders.payment_method_id` (exists) | set at checkout |
| `orders.shipping_status` | stays `Unfulfilled`; admin marks it delivered when the customer collects |
| `payment_methods` rows | seeded by a migration/seeder: `office` (manual), `bank_transfer` (manual, `metadata.bank`: bank BIN, account number, account name), `onepay` (driver `onepay`). Other existing methods are disabled, not deleted. |
| `cart_lines.metadata` | none needed |

Bank account details for VietQR live in `PaymentMethod.metadata` for `bank_transfer`, edited in the existing
payment method admin form (add the fields there through a render hook or the storefront addon if the form allows).
The QR image comes from `https://img.vietqr.io/image/{bin}-{account}-compact2.png?amount=&addInfo=&accountName=`
(no API key needed), with the plain account details printed next to it in case the image fails.

## Pages and routes (storefront, when `SHOPPER_STOREFRONT_ROUTES=true`)

| Route | What |
|-------|------|
| `GET /cart` (`storefront.cart`) | Livewire `CartPage`: lines, quantity stepper (debounced, min 1, max stock), remove, totals, "Thanh toán" button, empty state with a link home |
| `GET /checkout` (`storefront.checkout`) | Livewire `CheckoutPage`; redirects to `/cart` when the cart is empty |
| `GET /orders/{order:number}/thank-you` (`storefront.order.thanks`) | confirmation; allowed for the owner (signed in) or for the guest whose session placed it (order id kept in the session), else 404 |
| `GET /onepay/return` (`storefront.onepay.return`) | OnePay browser return → redirects to the thank-you page with a flash status |
| `POST|GET /api/onepay/ipn` | IPN, in `packages/onepay` routes, no session, CSRF-exempt, rate limited |
| `POST /api/storefront/cart/lines`, `PATCH|DELETE /api/storefront/cart/lines/{id}`, `GET /api/storefront/cart`, `POST /api/storefront/checkout` | JSON API for headless sites, same actions, cart token in a header instead of the session (see Phase 5) |

Checkout form fields: full name (required), phone (required, Vietnamese format `^(0|\+84)\d{9,10}$`), email
(optional for guests, prefilled for customers), note (optional), payment method (radio, required, only enabled
ones of the three), "I agree to the terms" checkbox linking the policy pages. Under the lines: subtotal, tax
(only if tax rules apply), total. A box shows the pickup office address and working hours from the storefront
settings. Quantities are editable here too; each change re-runs `calculate()` and re-checks stock.

Placing the order (`PlaceOrderAction`, in `packages/storefront`):

1. Rate limit 5/min per session + IP. Validate the form.
2. Re-check every line: product still published, price recalculated, stock available (show which line failed
   and stop, keep the form).
3. `CreateOrderFromCartAction::execute($cart, new OrderDetails(paymentMethod, note, contact))` inside its
   transaction (it already locks the cart, so a double click gets `CartCompletedException`; then redirect to the
   order that cart produced instead of an error).
4. `CartSessionManager::forget()`; remember the order id in the session for the thank-you page.
5. Dispatch the Telegram job after commit.
6. By payment method:
   - `office`: thank-you page with the office address, hours and "bring your order number".
   - `bank_transfer`: thank-you page with VietQR, bank details, amount and transfer note = order number;
     admin confirms the payment by hand in the order screen (existing "mark as paid").
   - `onepay`: `PaymentProcessingService::initiate()` → redirect (Livewire `redirect()` to an external URL)
     to OnePay. If OnePay cannot be reached, the order stays, payment pending, with "Pay again".

## Phases

### Phase 1: Telegram notifier and config file

- [x] `config/storefront.php`: `telegram.config_path`; `workbench/.env(.example)`, `.env.production.example`:
      `SHOPPER_TELEGRAM_CONFIG`; `workbench/telegram.example.json`; `.gitignore` `telegram.json`;
      `docker-compose.yml` read-only mount; CLAUDE.md section.
- [x] `TelegramConfig` (reads and validates the file once per request; `isEnabled()`, `botToken()`, `chatIds()`).
- [x] `TelegramClient` (`sendMessage(string $chatId, string $html)`, timeout, throws on non-2xx without the token
      in the message).
- [x] `OrderTelegramMessage` (builds the text above from an `Order`, escapes, truncates) and
      `SendTelegramOrderMessage` job (`new`, `paid`, `payment_failed` variants).
- [x] Console command `shopper:storefront:telegram-test` to send a test message (checks the file on a server).
- [x] Tests: missing/invalid/disabled file → no request and a log warning; `Http::fake` asserts URL, chat id,
      escaped HTML (name `<b>x</b>` is escaped), truncation; token absent from exception messages; the job never
      throws out of a sync queue.

### Phase 2: Add to cart and cart page

- [x] `AddToCartAction` (creates the session cart on first add, `CartManager::add`, merges same product into one line,
      caps at stock) and `UpdateCartLineAction` / `RemoveCartLineAction` (check the line belongs to the session cart).
- [x] "Thêm vào giỏ" button on `product-card` (Livewire `AddToCartButton`, lazy, shows a toast and updates the
      `MiniCart` through a `cart-updated` event). Products with variants open a small variant picker (dialog with
      Alpine trap, keyboard accessible); out-of-stock products show a disabled "Hết hàng".
- [x] `CartPage` Livewire page at `/cart` (layout from plan 09, `noindex` SEO).
- [x] `MiniCart`: dropdown with the last lines, total and links to cart and checkout.
- [x] Attach the guest cart to the customer on login/register (`associate()`); if the customer already had an open
      cart, merge lines.
- [x] Tests: add simple product, add variant, add beyond stock is capped with a message, update/remove someone
      else's line → 404, unpublished product cannot be added, merge on login, mini cart count updates,
      prices shown come from the database.

### Phase 3: Checkout page and placing the order

- [x] `OrderDetails` data object and the optional argument on `CreateOrderFromCartAction` (sets
      `payment_method_id`, `notes`, `metadata`, billing `OrderAddress`); keep existing callers and tests working.
- [x] `CheckoutPage` Livewire page at `/checkout` with the form, editable quantities, payment method choice
      (only enabled methods among `office`, `bank_transfer`, `onepay`), pickup box, terms checkbox.
- [x] `PlaceOrderAction` (steps above), rate limiting, double-submit handling.
- [x] Thank-you page per payment method (office instructions, VietQR + bank details, OnePay status), access rule.
- [x] Payment methods migration/seeder for the three methods; disable the others; `HomepageSeeder`-style demo data.
- [ ] Telegram job dispatched after commit; customer gets Laravel's default mail only if `MAIL_*` is set (optional,
      behind a config flag, off by default).
- [x] Tests: happy path per method (order row, items, `payment_method_id`, `notes`, metadata contact,
      `shipping_status` Unfulfilled, no shipping option, cart completed and forgotten, stock reserved,
      Telegram job pushed); validation (phone format, missing name, disabled method, non-allowed method id);
      price tampering impossible (quantities only); stock ran out between cart and submit; double submit makes
      one order; thank-you page 404 for another session; empty cart redirects to `/cart`.

### Phase 4: OnePay driver package

- [x] `packages/onepay` skeleton (`shopper-package` skill): config under `shopper.payment.drivers.onepay`,
      `OnePayDriver` implementing `PaymentDriver` (`initiatePayment` builds the signed URL, `retrievePayment` uses
      `QueryDR`, `handleWebhook` verifies the IPN, `supportsRefunds` false for now), `OnePaySigner`
      (hash building and checking, constant-time compare), response-code translations.
- [x] Routes: storefront return route, `/api/onepay/ipn` (CSRF-exempt, throttled, no session).
- [x] Domestic endpoint only (decided 2026-11-08); the international endpoint is not built.
- [x] Telegram "paid" / "failed" message from the IPN handler.
- [x] "Pay again" on the thank-you page for unpaid OnePay orders (new `vpc_MerchTxnRef` each attempt).
- [x] Tests: signature matches OnePay's documented sample, tampered hash rejected, amount mismatch rejected,
      IPN idempotent, IPN for unknown reference, return URL alone does not mark paid, `QueryDR` fallback
      (`Http::fake`), response codes mapped, credentials missing → method hidden at checkout.
- [ ] Manual test against the OnePay sandbox (test cards from OnePay's docs), recorded in the progress log.

### Phase 5: JSON API, admin touches, checks

- [x] JSON API endpoints listed above. Headless carts use a `X-Cart-Token` header (a random token stored in
      `carts.metadata.token`), never the numeric id. Same actions as the pages.
- [x] Admin: bank account fields for `bank_transfer`; order screen shows the contact block and "Pickup at office";
      orders list filter by payment method works with the three methods (check, likely no change).
- [ ] Browser checks desktop + mobile: add to cart, change quantities on cart and checkout, each payment method,
      OnePay sandbox round trip, no console errors, keyboard-only checkout.
- [x] Lighthouse on `/cart` and `/checkout` (accessibility, best practices ≥ 95); both pages `noindex`.
- [x] Full Pest suite, Pint, PHPStan. `code-reviewer` agent on the diff if the user asks for agents.

## Security checklist

- Server-side prices and stock only; line ids checked against the session cart.
- CSRF on all storefront forms (Livewire), IPN exempt but signature-checked; rate limits on add, update, place order, IPN.
- Order pages only for the owner or the session that placed them; order numbers are not secrets.
- All Telegram text escaped; bot token only in the JSON file, never logged or rendered.
- OnePay secrets only in env; hash compare with `hash_equals`.
- `telegram.json` and real `.env*` files stay out of git.

## Open decisions (ask before the phase that needs them)

| Decision | Options | Recommendation | Needed by |
|----------|---------|----------------|-----------|
| What "online pay" means next to OnePay | bank transfer + VietQR / e-wallet / OnePay international | **Decided 2026-11-08 by the user:** bank transfer with VietQR, admin confirms by hand | 3 |
| Guest checkout | allowed (name + phone) / sign-in required | **Decided 2026-11-08 by the user:** guests check out with just name and phone | 2 |
| Address at checkout | none (pickup only) / optional VAT address | **Decided 2026-11-08 by the user:** pickup only, no address | 3 |
| OnePay endpoints | domestic / international / both | **Decided 2026-11-08 by the user:** domestic cards only | 4 |
| Stock while unpaid | reserve at order time (current behaviour) / reserve only when paid | Reserve at order time; admin cancels unpaid orders (auto-cancel after N hours can come later) | 3 |

## Progress log

- 2026-11-07: Plan written. Nothing implemented.
- 2026-11-08: Decisions from the user: online pay = bank transfer with VietQR, guests check out with name + phone,
  pickup only, OnePay domestic cards only.
- 2026-11-08 Phase 1: Telegram config file (`SHOPPER_TELEGRAM_CONFIG`, `workbench/telegram.example.json`, `telegram.json`
  in `.gitignore` and `.dockerignore`, read-only mount in `docker-compose.yml`), `TelegramConfig` (scoped),
  `TelegramClient`, `OrderTelegramMessage` (storefront locale, escaped, ≤ 4096 chars), `SendTelegramOrderMessage`
  job (after commit, 3 tries, logs instead of throwing), `shopper:storefront:telegram-test`. 15 tests.
  Deviation: `orders` had no `metadata` column (only the docblock said so): core migration
  `2026_11_08_000001_add_metadata_column_to_orders_table` + `array` cast.
- 2026-11-08 Phase 2: `StorefrontCart`, `AddToCartAction` (variant picker via 409 + variants list, capped at stock),
  `UpdateCartLineAction`, `RefreshCartAction` (drops unpublished/deleted/sold-out lines, follows DB prices, caps
  quantities), `MergeCartOnLoginAction` (login + register), `CartPage` (`/cart`), add-to-cart form on product cards
  (works without JS, Alpine `fetch` with toast and `<dialog>` variant picker instead of a Livewire component per
  card), mini cart links. Fixes found on the way: Livewire updates lost the storefront language (now persistent
  middleware), SEO head accepts `noindex` in the context for private pages. 21 tests.
- 2026-11-08 Phase 3: `OrderDetails` on `CreateOrderFromCartAction` (payment method, note, metadata). Contact is kept
  in `orders.metadata.contact`, not a billing `OrderAddress` (that table requires street and postal code).
  `CheckoutPage`, `PlaceOrderAction` (refresh → stop if the cart changed, double submit → same order),
  confirmation page per method with VietQR (`img.vietqr.io`), `OrderAccess` (owner or placing session).
  Payment methods migration creates `office`, `bank-transfer`, `onepay`; deviation: other methods are not disabled,
  the checkout only offers the slugs in `shopper.storefront.checkout.payment_methods`. Payment logo resolver no longer
  crashes for a driver that is not installed. Skipped: customer confirmation email (not asked for). 20 tests.
- 2026-11-08 Phase 4: `packages/onepay` (driver, signer, response codes, `ConfirmOnePayPaymentAction` idempotent with
  amount check, `StartOnePayPaymentAction`, IPN + return controllers, events → Telegram paid/failed, "Pay again").
  The browser return only marks paid after QueryDR confirms; otherwise the IPN does it. Sandbox findings: the old
  `onecomm-pay/vpc.op` + `ONEPAY` test merchant now answers "giao dịch không hợp lệ"; the working setup is
  `paygate/vpcpay.op` + `TESTONEPAY`, `vpc_CardList=DOMESTIC` (straight to ATM bank choice) and QueryDR v2 at
  `msp/api/v1/vpc/invoices/queries`. Redirect, signature of OnePay's real return and cancel → back to the order page
  checked in the browser. Not done: a paid sandbox payment (the public test merchant lists no banks). 24 tests
  (signature checked against a vector computed with Python).
- 2026-11-08 Phase 5: cart/checkout JSON API with `X-Cart-Token` (`UseCartToken`, `StorefrontCart` scoped and
  token-aware), admin "Checkout" tab (bank BIN/name/account/holder, pickup note), storefront contact panel on the
  admin order page (render hook). Fixed: unprefixed `throttle:N,1` middlewares shared one counter per IP (plan 09
  auth routes too), now each has its own prefix; English validation messages at checkout (no `vi/validation.php`);
  mobile overflow (fieldset min-width, line controls wrap); header accessible names and contrast when signed in.
  Browser: add to cart, quantities on cart and checkout, office/bank transfer orders, OnePay redirect + cancel,
  admin panel, no console errors, no horizontal scroll at 390 px. Not checked: keyboard-only checkout, orders list
  filter by payment method. Lighthouse mobile on `/checkout`: accessibility 100, best practices 100, SEO 83 (no meta
  description on a noindex page). Test orders, carts, reservations and the demo bank account removed from the dev DB.
  Full suite 1533 passed; Pint clean; PHPStan only the existing `RevisionKind` error. Agent reviews not run.
