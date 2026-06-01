# Fufa Magento Webhook Module

`fufa/module-webhook`

## Main Functionalities

Sends Magento 2.4 store events to an external webhook endpoint with HMAC-SHA256 signed JSON payloads.

Implemented sources:

| Magento event                                            | Topic              | Event type        |
| -------------------------------------------------------- | ------------------ | ----------------- |
| `sales_order_place_after`                                | `orders/create`    | `order_created`   |
| `sales_order_save_after` (status/state/financial change) | `orders/update`    | `order_updated`   |
| `sales_order_save_after` (state = canceled)              | `orders/cancel`    | `order_cancelled` |
| `sales_order_shipment_save_after`                        | `orders/fulfilled` | `order_fulfilled` |
| `sales_order_creditmemo_save_after`                      | `refunds/create`   | `refund_created`  |
| cron every 10 minutes                                    | `carts/abandoned`  | `cart_abandoned`  |

### Order update change detection

`order_updated` / `order_cancelled` only fires when at least one of these fields changes: `status`, `state`, `total_paid`, `total_refunded`, `total_due`. Saves with no tracked field change are silently dropped.

### Abandoned cart window

The cron queries quotes updated between `threshold` minutes ago and 24 hours ago. Quotes older than 24 hours are permanently excluded, which prevents duplicate sends without requiring custom quote attributes.

## Installation

### Type 1: Zip file

- Unzip into `app/code/Fufa/Webhook`
- Enable the module: `php bin/magento module:enable Fufa_Webhook`
- Apply updates: `php bin/magento setup:upgrade`
- Flush cache: `php bin/magento cache:flush`

### Type 2: Composer

- Add the repository to your Magento root `composer.json`:
  ```json
  "repositories": [{ "type": "vcs", "url": "https://github.com/your-org/magento-fufa-webhook" }]
  ```
- Add credentials to `auth.json`:
  ```json
  { "github-oauth": { "github.com": "<personal-access-token>" } }
  ```
- Install: `composer require fufa/module-webhook`
- Enable the module: `php bin/magento module:enable Fufa_Webhook`
- Apply updates: `php bin/magento setup:upgrade`
- Flush cache: `php bin/magento cache:flush`

## Configuration

Store configuration lives at `Stores -> Configuration -> Services -> Fufa Webhook`.

| Setting                              | Description                                      |
| ------------------------------------ | ------------------------------------------------ |
| `Enabled`                            | Master on/off switch                             |
| `Webhook Endpoint URL`               | Public HTTPS endpoint (must pass URL validation) |
| `HMAC Secret`                        | Stored encrypted; used to sign all payloads      |
| `Abandoned Cart Threshold (Minutes)` | Minimum cart age before sending (e.g. 60)        |

Point **Webhook Endpoint URL** at:

```
https://<your-api-host>/api/ecommerce/magento/webhooks
```

On the Fufa side:

- `ecommerce_stores.metadata.magentoWebhookHmacSecret` must match the **HMAC Secret** configured above
- `ecommerce_stores.domain` must match the hostname of the Magento store base URL
- `ecommerce_stores.platform` must be `magento`

### Live Chat

Configure the storefront live chat widget at **Stores → Configuration → Fufa → Live Chat**.

| Setting      | Description                                                                   |
| ------------ | ----------------------------------------------------------------------------- |
| `Channel ID` | Channel ID provided by Fufa. Live chat is enabled only when a value is added. |

Live chat will only be enabled when a valid Channel ID is added in **Stores → Configuration → Fufa → Live Chat**. If the Channel ID field is left empty, the Fufa live chat script will not be added to the storefront.

## Fufa AI Integration (OAuth 1.0a)

The extension preconfigures a Magento Integration named **Fufa AI** so merchants do not have to enter Callback / Identity Link URLs manually. The Callback URL, Identity Link URL, and contact email are managed by the extension and are intentionally read-only in Admin.

API resources requested (least-privilege):

- Catalog → Inventory → Products
- Sales → Operations → Orders → Actions → View

### Activate the integration

After installing or updating the extension:

1. `composer require fufa/module-webhook` (or unzip into `app/code/Fufa/Webhook`)
2. `php bin/magento module:enable Fufa_Webhook` _(first install only)_
3. `php bin/magento setup:upgrade`
4. `php bin/magento cache:flush`
5. In Magento Admin, go to **System → Extensions → Integrations**
6. Locate **Fufa AI** in the grid and click **Activate**
7. Review the requested API resources and click **Allow**

### Reauthorize after a permissions update

If a future release of `fufa/module-webhook` adds new API resources, the integration will move into a _Reauthorize required_ state. To approve the new scope:

1. `composer update fufa/module-webhook`
2. `php bin/magento setup:upgrade`
3. `php bin/magento cache:flush`
4. In Magento Admin, go to **System → Extensions → Integrations**
5. Click **Reauthorize** on the **Fufa AI** row
6. Review the updated API resources and click **Allow**

## Payload Contract

Each request sends a signed JSON envelope:

```json
{
  "platform": "magento",
  "topic": "orders/create",
  "eventType": "order_created",
  "entityType": "order",
  "externalEventId": "order-000000123-create-1712415600",
  "externalEntityId": "000000123",
  "occurredAt": "2026-01-01T12:00:00+00:00",
  "store": { "id": "1", "code": "default", "name": "Default Store" },
  "rawPayload": { ... }
}
```

Request headers:

| Header                 | Value                       |
| ---------------------- | --------------------------- |
| `X-Fufa-Platform`      | `magento`                   |
| `X-Fufa-Topic`         | e.g. `orders/create`        |
| `X-Fufa-Event-Type`    | e.g. `order_created`        |
| `X-Fufa-Entity-Type`   | `order` / `refund` / `cart` |
| `X-Fufa-Timestamp`     | Unix timestamp (seconds)    |
| `X-Fufa-Signature`     | HMAC-SHA256 hex digest      |
| `X-Fufa-Signature-Alg` | `hmac-sha256`               |

### Dedupe keys

- `order_created`: `order-{incrementId}-create-{updatedAtUnix}` — uses increment ID since entity ID is not yet available at placement time
- `order_updated` / `order_cancelled`: `order-{entityId}-update-{state}-{status}-{updatedAtUnix}`

## Specifications

- Cron job: `fufa_webhook_abandonedcartchecker` (every 10 minutes)
- Observer: `sales_order_place_after` → `Fufa\Webhook\Observer\Sales\OrderPlaceAfter`
- Observer: `sales_order_save_after` → `Fufa\Webhook\Observer\Sales\OrderSaveAfter`
- Observer: `sales_order_shipment_save_after` → `Fufa\Webhook\Observer\Sales\OrderShipmentSaveAfter`
- Observer: `sales_order_creditmemo_save_after` → `Fufa\Webhook\Observer\Sales\OrderCreditmemoSaveAfter`
