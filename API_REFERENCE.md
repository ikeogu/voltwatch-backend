# VoltWatch API Reference
## Base URL: `https://api.voltwatchapp.com/api/v1`

---

## Auth Headers (all protected routes)
```
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

---

## 1. Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register` | ❌ | Register new user |
| POST | `/auth/login` | ❌ | Login (email or phone) |
| POST | `/auth/logout` | ✅ | Revoke token |
| GET | `/auth/me` | ✅ | Get current user + subscription |
| PATCH | `/auth/me` | ✅ | Update profile / FCM token |
| POST | `/auth/change-password` | ✅ | Change password |

### POST /auth/register
```json
{ "name": "Chidi Kalu", "email": "chidi@example.com", "phone": "08123456789", "password": "secret123", "password_confirmation": "secret123" }
```
**Response:** `{ token, user: { id, name, email, phone, role, plan, subscription, has_meter } }`

### POST /auth/login
```json
{ "login": "08123456789", "password": "secret123" }
```
`login` field accepts email OR phone number.

---

## 2. Reference Data (public)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/tariff-bands` | All NERC tariff bands (A-E) with rates |
| GET | `/appliance-types` | Appliance catalogue grouped by category |

---

## 3. Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/dashboard` | Full home screen data (single call) |

**Response shape:**
```json
{
  "has_meter": true,
  "meter": { "id", "nickname", "type", "disco" },
  "units": { "current", "daily_avg", "days_remaining", "depletion_at", "is_critically_low" },
  "costs": { "tariff_band", "rate_per_kwh", "daily_cost_ngn", "monthly_estimate_ngn" },
  "last_recharge": { "units_added", "amount_paid", "recharged_at" },
  "top_consumers": [ { "name", "icon", "daily_kwh", "daily_cost_ngn", "usage_percentage" } ],
  "unread_alert_count": 2,
  "alerts_preview": [ { "id", "title", "severity", "time" } ],
  "weekly_usage": [ { "date", "kwh", "cost_ngn" } ],
  "live_data": null,
  "insights": [ { "type", "icon", "message" } ]
}
```

---

## 4. Meters

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/meters` | List all user's meters |
| POST | `/meters` | Add a meter |
| PATCH | `/meters/{id}` | Update meter (units, nickname, tariff) |
| DELETE | `/meters/{id}` | Deactivate meter |

### POST /meters
```json
{
  "meter_number": "0101234567890",
  "nickname": "Home Meter",
  "type": "prepaid",
  "tariff_band_id": 1,
  "disco": "ikeja",
  "current_units": 42.7,
  "tenant_count": 1
}
```

---

## 5. Recharges

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/meters/{meter}/recharges` | List recharges + summary |
| POST | `/meters/{meter}/recharges` | Log a recharge |
| DELETE | `/recharges/{id}` | Delete recharge (reverses balance) |

### POST /meters/{meter}/recharges
```json
{
  "units_added": 50,
  "amount_paid": 4600,
  "token_number": "1234-5678-9012-3456-7890",
  "recharged_at": "2026-02-23",
  "notes": "Bought at agent"
}
```

---

## 6. Appliances

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/meters/{meter}/appliances` | List appliances with cost breakdown |
| POST | `/meters/{meter}/appliances` | Add appliance |
| PATCH | `/appliances/{id}` | Update appliance |
| DELETE | `/appliances/{id}` | Remove appliance |

### POST /meters/{meter}/appliances
```json
{
  "appliance_type_id": 1,
  "nickname": "Bedroom AC",
  "quantity": 1,
  "wattage": 1100,
  "daily_hours": 8,
  "is_always_on": false
}
```

---

## 7. Analytics

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/meters/{meter}/analytics?period=week` | Usage charts + insights |

**period options:** `week`, `month`, `3months`, `year`

---

## 8. Alerts

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/meters/{meter}/alerts` | Alert rules for meter |
| POST | `/meters/{meter}/alerts` | Create alert rule |
| PATCH | `/alerts/{id}` | Toggle/update alert |
| DELETE | `/alerts/{id}` | Delete alert rule |
| GET | `/notifications` | Alert history (paginated) |
| POST | `/notifications/{id}/read` | Mark one as read |
| POST | `/notifications/read-all` | Mark all as read |

### POST /meters/{meter}/alerts
```json
{
  "type": "low_units",
  "threshold_value": 20,
  "severity": "warning",
  "notify_push": true,
  "cooldown_minutes": 120
}
```
**Alert types:** `low_units` | `high_daily_usage` | `estimated_depletion` | `voltage_drop` | `power_surge`

---

## 9. Estates (Shared Meter / Landlord)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/estates` | Create estate (landlord) |
| POST | `/estates/join` | Join estate with invite code (tenant) |
| GET | `/estates/{id}/dashboard` | Landlord view — all tenants + usage |

### POST /estates/join
```json
{ "invite_code": "VOLT8ABC", "flat_label": "Flat A", "meter_id": 3 }
```

---

## 10. IoT Devices (Phase 2 — Pro plan)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/iot/devices` | List paired devices |
| POST | `/iot/devices/pair` | Pair new hardware device |
| GET | `/iot/devices/{id}/live` | Latest live readings (polling fallback) |
| POST | `/iot/ingest` | Internal — MQTT bridge posts readings here |

### POST /iot/devices/pair
```json
{ "device_id": "VW-2024-ABC123", "meter_id": 1, "nickname": "Main Meter Monitor", "connection_type": "wifi" }
```

---

## 11. Subscriptions

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/subscription` | Current plan + all plan options |
| POST | `/subscription/checkout` | Get Paystack checkout URL |
| POST | `/subscription/cancel` | Cancel subscription |
| POST | `/subscriptions/paystack/webhook` | Paystack webhook (public, signed) |

### POST /subscription/checkout
```json
{ "plan": "pro" }
```
**Response:** `{ "checkout_url": "https://checkout.paystack.com/xxxx", "reference": "ref_xxx" }`

---

## Plan Limits

| Plan | Price | Meters | IoT | Shared Meter | AI Insights |
|------|-------|--------|-----|--------------|-------------|
| Free | ₦0 | 1 | ❌ | ❌ | ❌ |
| Basic | ₦500/mo | 3 | ❌ | ✅ | ❌ |
| Pro | ₦1,500/mo | 5 | 2 devices | ✅ | ✅ |
| Estate | ₦5,000/mo | 20 | 5 devices | ✅ | ✅ |

---

## WebSocket Channels (Laravel Reverb)

```
Private channel: voltwatch.user.{userId}
Events:
  - IoTReadingReceived    → live dashboard update
  - UnitsCriticallyLow   → push-like in-app alert
  - AlertFired           → notification badge increment
```

---

## Error Responses

```json
{ "message": "The provided credentials are incorrect.", "errors": { "login": ["..."] } }
```

All errors follow Laravel's standard format. HTTP codes: `200`, `201`, `400`, `401`, `403`, `404`, `422`, `429`, `500`.
