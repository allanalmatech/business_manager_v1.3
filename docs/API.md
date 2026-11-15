# Mobile API (v1)

Base: `/api/v1/index.php?r=<route>`. JSON in, JSON out. Every response has `ok`.
Send the token as `Authorization: Bearer <token>` or `X-Auth-Token: <token>`.

| Route | Method | Purpose |
|---|---|---|
| `auth/login` | POST | `{username, password, device_name}` → `token` (shown once) plus the bootstrap data |
| `auth/logout` | POST | Revokes this device's token |
| `bootstrap` | GET | User, permissions, business, money, tax, receipt settings, `sync_head` |
| `sync/pull?since=N&limit=500` | GET | Changes after version N |
| `sync/push` | POST | `{mutations: [{uuid, kind, payload, created_at}]}` |
| `products/image` | POST | multipart `product_id`, `image` (≤ 3 MB; re-encoded to ≤ 1280 px JPEG) |
| `sales/recent?limit=50` | GET | Recent sales (own, or all with `reports.sales.view`) |
| `reports/today` | GET | Today's totals |

## Pull

```json
{"ok": true, "full": false, "since": 15, "next": 19, "has_more": false,
 "changes": {"product": {"upserts": [{"id": 1, "retail_price": 4300}], "deletes": []},
             "customer": {"upserts": [], "deletes": ["2"]}}}
```

- Save the page, then store `next` as the new `since`. If the connection drops, ask again with the old `since`; pages are safe to repeat.
- Repeat while `has_more` is true.
- `full: true` means "replace your local copy with this": sent on hosts without database triggers, or when a device is further behind than the retained history.
- Entities: `location`, `category`, `brand`, `customer`, `product`, `stock` (stock keys are `productId:locationId`).

## Push

Each mutation has a UUID made on the device. The server applies it once; sending it again returns the first result with `duplicate: true`. Results come back in order with `status`:

- `applied` — done; the result carries server ids (`sale_id`, `doc_no`, `id`).
- `rejected` — will never succeed; show `error` to the user. `conflict: true` includes the server's copy.
- `retry` — server problem; keep it queued.

| Kind | Payload | Notes |
|---|---|---|
| `sale.create` | The till payload: `items`, `payments`, `selling_location_id`, `customer_id` or `customer_ref`, `doc_type`, `pricing_mode`, `client_ref`, `sold_at` | The sale already happened, so it is never refused for stock or price. Oddities go to `needs_review` and appear under Admin → Devices. |
| `customer.create` | `name`, `phone`, `email`, `address` | Same name + phone merges into the existing customer. A later sale points at it with `customer_ref` = this mutation's uuid. |
| `product.update` | `id`, `fields{name, barcode, retail_price, wholesale_price}`, `base{...}` | `base` is what the device last saw. If the server value differs from both `base` and the new value, the change is rejected as a conflict. |
| `stock.adjust` / `stock.receive` | `product_id`, `location_id`, `delta`, `reason` | Always a change (+3, −2), never "set to", so two devices adjusting the same item both count. |

## Consistency rules

- The server is the source of truth. Devices show their own unsent work on top of the last pulled state.
- Sales are append-only. Stock moves by deltas inside row-locked transactions.
- Document numbers are assigned by the server; the device prints its own provisional number (`client_ref`) and shows the official one after sync.
