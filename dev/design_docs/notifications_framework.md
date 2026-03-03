# Push Notifications — Total Funda (End-to-End)

## 1) What “Push” actually is
Push notification = your backend asks a **Push Provider** to deliver a message to a **device app**.

Providers:
- **Android:** Firebase Cloud Messaging (**FCM**)
- **iOS:** Apple Push Notification service (**APNs**) (often still via FCM as a bridge)

Your backend never talks directly to the phone. It talks to FCM/APNs, and they deliver.

---

## 2) Core pieces (Architecture)
### Client App (Flutter)
- Requests notification permission (esp iOS).
- Gets a **device token** (FCM token).
- Sends token to your backend and keeps it updated.
- Receives notifications:
  - Foreground (show in-app UI)
  - Background (system tray)
  - Terminated (tap opens app)

### Backend (Laravel / any)
- Stores device tokens per user/device.
- Decides *when* to notify and *what* to send.
- Sends message to FCM (HTTP v1 API).
- Uses **queue** for reliability.

### Push Provider (FCM/APNs)
- Routes message to the device, best-effort delivery.
- Handles offline devices, throttling, platform rules.

---

## 3) Token fundamentals (most important)
### What is a token?
A token identifies **one app install on one device**.

### Token lifecycle rules
- Token can change anytime (app reinstall, data clear, OS updates).
- Must handle:
  - **create/update token**
  - **remove invalid token** when FCM says it’s not valid

### Recommended DB model
Table: `device_tokens`
- `id`
- `user_id` (nullable for pre-login if needed)
- `token` (unique)
- `platform` (`android|ios|web`)
- `device_id` (optional: your own device fingerprint)
- `app_version` (optional)
- `last_seen_at`
- `created_at`, `updated_at`

---

## 4) Notification types
### A) Transactional (1:1)
Examples:
- Order confirmed
- Delivery boy assigned
- Payment received
- OTP/login alert (usually better via SMS/email, but possible)

**Send to one user’s tokens**

### B) Broadcast (1:many)
Examples:
- “New offers”
- “Holiday schedule”

Ways:
- **Topics** (subscribe devices to `offers`, `zone_1`, `vendor_milk`)
- **Segments** (your backend selects tokens by rules)

### C) Silent / Data-only
Used to:
- Refresh data in background
- Trigger app sync (platform restrictions apply)

---

## 5) Payload: Notification vs Data
### Notification payload
- Shows in system tray automatically (mostly)
- Limited control
- Works well for simple alerts

### Data payload
- Delivered to app code
- You decide UI / navigation
- Best for deep links and app-specific routing

Best practice: send BOTH when you want reliable UI + custom behavior:
- `notification`: title/body
- `data`: type, entity_id, deeplink, etc.

---

## 6) Deep linking (tap behavior)
Always include routing fields in `data`, e.g.
- `type: "order"`
- `order_id: "4995"`
- `screen: "order_details"`
- `deeplink: "dayli://orders/4995"`

Flutter side:
- On notification tap → parse `data` → navigate to correct screen.

---

## 7) Reliability & queues (production grade)
Never send push directly inside request/response for important events.

### Recommended pattern
1. Business event occurs (Order created, Vendor supplied, etc.)
2. Write a `notification_jobs` row OR publish domain event
3. Queue worker sends push
4. Store delivery attempt result
5. Retry on transient failures

Laravel:
- Queue job `SendPushNotificationJob`
- Retries with backoff (e.g., 5, 30, 120 sec)
- Idempotency key to avoid duplicates

---

## 8) Handling invalid tokens
FCM can respond with errors like:
- token not registered
- invalid argument

On these:
- Mark token as invalid / delete it.

This keeps your send list clean and reduces cost/time.

---

## 9) Throttling + batching
If user has multiple devices:
- Send to all tokens (phone + tablet) OR only most recent (your choice).

If sending to many tokens:
- Prefer topics OR chunk tokens (batching).

---

## 10) Security rules
- Never expose server keys in app.
- App only sends token to backend (authenticated if possible).
- Backend sends to FCM using server credentials.
- Validate user ownership before storing token.

---

## 11) Observability (must-have)
Log:
- notification_id
- user_id
- token_id count
- provider response
- failure reason
- retry count

Track metrics:
- sent
- failed
- invalid tokens removed
- open rate (from app analytics)

---

## 12) Minimal end-to-end flow (Dayli-like)
### Client (Flutter)
- On app start/login:
  - get FCM token
  - POST `/api/device-token` with `{token, platform}`
- On token refresh:
  - POST again

### Backend (Laravel)
- `/api/device-token` saves token to DB
- When event happens:
  - dispatch `SendPushNotificationJob(user_id, payload)`
- Job fetches tokens, sends to FCM, removes invalid tokens

---

## 13) Common mistakes
- Not updating token refresh
- Not deleting invalid tokens
- Sending push synchronously (causes slow APIs)
- Not using deep link data (tap opens wrong screen)
- No dedupe / idempotency (double notifications)
- Using push for OTP as primary channel (push delivery isn’t guaranteed)

---

## 14) Recommended payload standard (copy-paste)
### Data (always)
- `type` (order|invoice|delivery|promo|system)
- `entity_id`
- `deeplink`
- `ts` (unix)
- `notification_id` (uuid)

### Notification (if you want tray UI)
- `title`
- `body`

---

## 15) FAQ quick answers
- **Does push guarantee delivery?** No. Best-effort.
- **Do we need APNs separately?** If you use FCM for iOS, FCM still relies on APNs behind the scenes.
- **Can we target by zone?** Yes: topics like `zone_1`, or your own segmentation query.
- **Can one user have many tokens?** Yes. Store all; optionally keep only last N active.

---

## 16) Practical next steps checklist
- [ ] Flutter: integrate FCM, permission, token refresh
- [ ] API: `/device-token` endpoint
- [ ] DB: `device_tokens` table
- [ ] Queue: `SendPushNotificationJob`
- [ ] FCM HTTP v1 credentials on server
- [ ] Deep link routing in Flutter
- [ ] Logging + invalid token cleanup
- [ ] Topics/segments (optional)

--- 
End.