# Dayli – Delivery Boy Onboarding & Assignment Workflow

## Overview

This document explains the complete workflow for enabling a user to become a **Delivery Boy (workman-delivery-boy)** in the Dayli mobile application and assigning delivery responsibilities based on **subscription types** (Milk, Vegetables, etc.).

The process covers:

1. OTP Login
2. Zone Selection
3. Service Selection (Workman → Delivery Boy)
4. Subscription Type Selection
5. Backend Role Assignment
6. Delivery Task Creation
7. My Work Integration

---

# 1. OTP Login

### API

```
POST /api/auth/send-otp
POST /api/auth/verify-otp
```

### Response Example

```json
{
  "token": "1189|xxxxx",
  "roles": ["customer"],
  "profile_completed": 0,
  "user_id": 11334,
  "phone": "+91xxxxxxxxxx"
}
```

### Output

User receives a **Sanctum token** used for all authenticated APIs.

---

# 2. Zone Selection

### Screen

`SelectZoneScreen`

User can select zone by:

1. Entering **pincode**
2. Using **current GPS location**

### API

```
GET /api/check-pincode/{pincode}
```

### If service available

Navigate to:

```
ServiceDashboard
```

---

# 3. Service Selection

### Screen

`ServiceDashboard`

### API

```
GET /api/service-types
```

### Response Example

```json
{
  "data": [
    { "id": 9, "name": "Delivery Boy Services" }
  ]
}
```

### User Flow

```
Workman
   ↓
Delivery Boy Services
```

This triggers loading of delivery subtypes.

---

# 4. Subscription Type Selection

### API

```
GET /api/subscription-types
```

### Response Example

```json
{
  "data": [
    { "id": 3, "name": "Milk & Dairy" },
    { "id": 4, "name": "Vegetables" }
  ]
}
```

### UI

Dropdown inside `ServiceDashboard`.

User selects:

```
Milk Delivery
Vegetable Delivery
etc
```

---

# 5. Save Delivery Assignment

### API

```
POST /api/profile/service
```

### Headers

```
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body

```json
{
  "service_handle": "workman-delivery-boy",
  "subscription_type_id": 3,
  "zone_id": 1,
  "full_name": "User Name",
  "address": "Temporary Address",
  "latitude": 15.828,
  "longitude": 78.036
}
```

---

# 6. Backend Processing

Controller:

```
ProfileController::saveServiceProfile()
```

### Role Assignment

```
$user->syncRoles(['workman-delivery-boy']);
```

Only **one role** is assigned:

```
workman-delivery-boy
```

---

# 7. Delivery Task Creation

Table used:

```
delivery_tasks
```

### Columns

| Column               | Purpose                 |
| -------------------- | ----------------------- |
| delivery_task        | Task name               |
| delivery_exec_id     | User ID                 |
| zone_id              | Delivery zone           |
| subscription_type_id | Delivery category       |
| status               | today/pending/completed |
| start_date           | task start              |

---

### Insert Logic

```php
DB::table('delivery_tasks')->updateOrInsert(
[
    'delivery_exec_id' => $user->id,
    'subscription_type_id' => $data['subscription_type_id']
],
[
    'delivery_task' => 'Delivery Assignment',
    'zone_id' => $data['zone_id'] ?? 1,
    'status' => 'today',
    'start_date' => now()->toDateString(),
    'updated_at' => now(),
    'created_at' => now(),
]);
```

### Result

Only **one row per delivery type** per user.

Example:

| id  | delivery_exec_id | subscription_type_id |
| --- | ---------------- | -------------------- |
| 14  | 11334            | Milk                 |
| 15  | 11334            | Vegetables           |

No duplicates are allowed.

---

# 8. My Work Integration

### API Group

```
/api/my-work/*
```

Routes include:

```
GET /my-work/summary
GET /my-work/orders
GET /my-work/subscription-types
POST /my-work/{id}/start
POST /my-work/{id}/complete
```

These APIs load work based on:

```
delivery_tasks.subscription_type_id
delivery_tasks.delivery_exec_id
```

---

# 9. Dashboard Navigation

Main dashboard:

```
MainDashboardShell
```

Tabs:

| Tab     | Purpose        |
| ------- | -------------- |
| Home    | Dashboard      |
| Orders  | User orders    |
| Subs    | Subscriptions  |
| My Work | Delivery tasks |
| Profile | User profile   |

For delivery users:

```
My Work tab → TabMyWork
```

---

# 10. Final Flow

```
OTP Login
   ↓
Select Zone
   ↓
Service Dashboard
   ↓
Select Workman
   ↓
Select Delivery Boy
   ↓
Load Subscription Types
   ↓
User selects Milk / Veg
   ↓
POST /profile/service
   ↓
Assign role: workman-delivery-boy
   ↓
Insert/Update delivery_tasks
   ↓
My Work shows assigned deliveries
```

---

# 11. Database Tables Used

| Table              | Purpose              |
| ------------------ | -------------------- |
| users              | User account         |
| model_has_roles    | Spatie roles         |
| services           | service categories   |
| subscription_types | delivery categories  |
| delivery_tasks     | delivery assignments |

---

# 12. Future Improvements

Recommended enhancements:

1. Add DB constraint

```
UNIQUE(delivery_exec_id, subscription_type_id)
```

2. Prevent duplicate assignment in UI.

3. Allow multiple zones per delivery boy.

4. Add scheduling support.

---

# End of Document



Dayli App – OTP Login & Service Role Flow
Overview

This document describes the full authentication and onboarding flow for the Dayli application, including:

OTP login/signup

Role assignment

Vendor onboarding

Workman onboarding

Delivery assignment creation

Dashboard routing

The system ensures:

Every new user starts as a customer

Additional roles are assigned only after user chooses a service type

Vendor and Workman flows are handled separately

1. User Entry Flow

User enters the system through two main entry points.

Customer Login
LoginScreen → OTP Screen
Vendor / Workman Login
ServiceLogin → OTP Screen

The OTP screen receives:

mobileNumber
userType (Customer | Vendor | Workman)
latitude
longitude
2. Send OTP API

Endpoint

POST /api/auth/send-otp

Request body

{
  "phone": "6364168111",
  "role": "customer | vendor"
}

Purpose

Generate OTP

Send SMS / WhatsApp OTP

Return OTP during development

Example response

{
  "message": "OTP sent",
  "otp": "123456"
}
3. Verify OTP API

Endpoint

POST /api/auth/verify-otp

Request

{
  "phone": "6364168111",
  "otp": "123456",
  "role": "vendor"
}

Process

Check OTP validity

Create user if not exists

Always assign base role

customer

Generate Sanctum token

Example response

{
  "token": "1|sanctumtoken",
  "role": "customer",
  "profile_completed": 0
}

Important rule:

OTP stage NEVER assigns vendor or workman roles.
4. Flutter OTP Navigation Logic

After successful OTP verification:

Customer Flow
OTP Screen
→ MainDashboardShell
Vendor / Workman Flow
OTP Screen
→ ServiceDashboard

Important rule:

Navigation is based on

widget.userType

NOT on backend role.

5. Service Dashboard

Screen

service_dashboard.dart

User selects service type:

Vendor
Workman
6. Vendor Flow

Vendor selects:

Subscription Type
→ Sub Types
→ Products

Vendor role assignment happens when saving profile.

API called

POST /api/profile/service

Request

{
  "full_name": "Vendor Name",
  "address": "Address",
  "service_handle": "vendor"
}

Backend action

assignRole('vendor')

Result

customer
vendor
7. Workman Flow

Workman selects:

Service Type
→ Delivery Boy

Then chooses

Delivery Subscription Type

Example

Milk Delivery
Vegetable Delivery
8. Delivery Assignment API

Endpoint

POST /api/profile/service

Request

{
  "full_name": "Worker Name",
  "address": "Address",
  "latitude": 15.80,
  "longitude": 78.04,
  "service_handle": "workman-delivery-boy",
  "subscription_type_id": 1,
  "zone_id": 1
}
9. Backend Role Assignment

File

ProfileController.php

Logic

Vendor
assignRole('vendor')
Delivery Boy
assignRole('workman-delivery-boy')

Parent workman role is intentionally not assigned.

10. Delivery Task Creation

When delivery boy is registered, the system creates a task.

Table

delivery_tasks

Inserted using

updateOrInsert

Conditions

delivery_exec_id
subscription_type_id

Example record

delivery_task        Delivery Assignment
delivery_exec_id     11334
subscription_type_id 1
zone_id              1
status               today
start_date           2026-03-10

Purpose

Used later for:

My Work
Delivery Tracking
Daily Ops
11. Address Storage

Address is stored in polymorphic table

addresses

Structure

addressable_type
addressable_id
line1
lat
lng
is_default

Example

addressable_type  App\Models\User
addressable_id    11334
line1             Hyderabad
lat               15.80
lng               78.04
12. Device Token Registration

After OTP verification

FirebaseMessaging.getToken()

API

POST /api/device-tokens

Payload

{
  "token": "FCM_TOKEN",
  "platform": "android",
  "device_id": "V2401",
  "app_version": "1.0.0"
}

Purpose

Push notifications
13. App Mode Storage

Stored in

SharedPreferences

Keys

auth_token
user_role
user_roles
app_mode

Possible app modes

customer
vendor
delivery
14. Dashboard Routing

Final routing rules

User Type	Screen
Customer	MainDashboard
Vendor	ServiceDashboard → Subscriptions
Workman Delivery	ServiceDashboard → My Work
15. Final Role Structure

User may have multiple roles.

Examples

Customer only
customer
Vendor
customer
vendor
Delivery Boy
customer
workman-delivery-boy
16. Database Tables Used

Main tables

users
addresses
delivery_tasks
model_has_roles
device_tokens
17. Final Flow Diagram
User Login
     │
     ▼
OTP Sent
     │
     ▼
OTP Verified
     │
     ▼
Role = customer
     │
     ▼
UserType check (Flutter)
     │
 ┌───────┴────────┐
 │                │
 ▼                ▼
Customer       Vendor/Workman
 │                │
 ▼                ▼
Dashboard     ServiceDashboard
                   │
                   ▼
           Choose Vendor / Workman
                   │
         ┌─────────┴─────────┐
         ▼                   ▼
       Vendor           Delivery Boy
         │                   │
         ▼                   ▼
   Assign vendor role   Assign workman-delivery-boy
         │                   │
         ▼                   ▼
     Vendor Dashboard   Delivery Task Created