# Dayli Architecture & Functionality Documentation

## 1. Purpose of This Document

This document captures the technical architecture and functionality of the Dayli platform in a breadth-first, top-down manner.  
It is intended as a handover and reference document so that new technical team members can understand what has already been built and reuse existing components such as the notification mechanism, event-driven jobs, reconciliation flow, role-based access, and subscription/order processing.

---

## 2. High-Level Product View

Dayli is an e-commerce and delivery platform that supports:

- On-demand ordering
- Subscription-based recurring delivery
- Multiple product types and sub-types
- Role-based operational workflows
- Vendor supply capture
- Delivery actuals capture
- Change request handling
- Zone-level approvals and reconciliation
- Notifications and background jobs through an event-driven server-side mechanism

The platform serves both end users and internal operators through:

- Mobile application access
- Web admin access at `admin.dayli.in`
- Laravel backend API
- Database-backed transactional and event systems

---

## 3. Top-Level Architecture

```mermaid
flowchart TD
    A[Mobile Apps] --> B[API Layer / Laravel Backend]
    W[Admin Web<br/>admin.dayli.in] --> B

    A1[Customer App] --> A
    A2[Delivery Agent App] --> A
    A3[Vendor App] --> A

    B --> C[Transactional Modules]
    B --> D[Master Data Modules]
    B --> E[Operational Modules]

    C --> C1[Orders]
    C --> C2[Subscriptions]
    C --> C3[Order Items]
    C --> C4[Invoices]

    D --> D1[Users]
    D --> D2[Roles & Permissions]
    D --> D3[Zones]
    D --> D4[Product Types / Sub-types]

    E --> E1[Vendor Supply]
    E --> E2[Delivery Actuals]
    E --> E3[Change Requests]
    E --> E4[Approvals]
    E --> E5[Reconciliation]

    C --> F[Event / Outbox System]
    E --> F
    D --> F

    F --> G[Queue Workers / Job Processors]
    G --> H[Notifications]
    G --> I[Reports]
    G --> J[Reconciliation Jobs]
    G --> K[Async Processing]

    H --> H1[Push]
    H --> H2[SMS]
    H --> H3[WhatsApp]
    H --> H4[Email]
```

---

## 4. Actor Responsibility Map

Dayli is a multi-actor system. Each actor has a specific operational responsibility.

```mermaid
flowchart LR
    Customer[Customer]
    Vendor[Vendor]
    Delivery[Delivery Agent / Workman]
    ZM[Zone Manager]
    ZD[Zone Director]
    Admin[Admin]

    Customer --> C1[Place Orders]
    Customer --> C2[Create Subscriptions]
    Customer --> C3[Pause / Resume / Cancel Subscription]

    Vendor --> V1[Register for Product-Type Supply]
    Vendor --> V2[Supply Goods]
    Vendor --> V3[Submit Supply Data]

    Delivery --> D1[Register for Delivery Work]
    Delivery --> D2[Deliver Goods]
    Delivery --> D3[Enter Delivery Actuals]
    Delivery --> D4[Raise Change Requests for Customers]

    ZM --> Z1[Approve Vendor Registration]
    ZM --> Z2[Approve Delivery Agent Registration]
    ZM --> Z3[Approve Change Requests]
    ZM --> Z4[Send Invoice Notifications]
    ZM --> Z5[Send No-Delivery Notifications]
    ZM --> Z6[Perform Reconciliation]
    ZM --> Z7[Raise Change Requests on Customer Behalf]

    ZD --> ZD1[Functionality Not Yet Assigned]

    Admin --> A1[Full System Control]
    Admin --> A2[Configuration]
    Admin --> A3[User / Role / Zone Management]
    Admin --> A4[Operational Oversight]
```

---

## 5. Core Business Flow

This is the simplified end-to-end flow from customer demand to delivery, reconciliation, invoice, and notification.

```mermaid
flowchart TD
    A[Customer creates order or subscription] --> B[Subscription / Draft Order state created]
    B --> C[Daily Order Generation Job]
    C --> D[Orders created for delivery date]
    D --> E[Vendor supplies quantity]
    E --> F[Delivery agent delivers goods]
    F --> G[Delivery actuals captured]
    G --> H[Zone-level reconciliation]
    H --> I[Invoice / Report preparation]
    I --> J[Notifications sent]
```

---

## 6. Transactional vs Event-Driven Architecture

Dayli has two major backend layers:

1. **Transactional Layer**  
   Handles immediate business actions such as order creation, subscription updates, delivery actual entry, vendor supply entry, approvals, and change requests.

2. **Event-Driven Layer**  
   Handles asynchronous processing such as notifications, reconciliation jobs, report generation, retries, and background job execution.

```mermaid
flowchart TD
    A[User / System Action] --> B[Transactional Write]
    B --> C[(Database Tables)]
    B --> D[Create Event in outbox_events]

    D --> E[Scheduler / Queue Dispatcher]
    E --> F[Queue Worker]
    F --> G[Event Handler]

    G --> H{Handler Type}
    H --> H1[Notification Handler]
    H --> H2[Reconciliation Handler]
    H --> H3[Report Handler]
    H --> H4[Other Job Handler]

    H1 --> I[Push / SMS / WhatsApp / Email]
    H2 --> J[Supply vs Delivery Diff]
    H3 --> K[Report / Invoice Preparation]
    H4 --> L[Other Async Processing]
```

---

## 7. Subscription and Order Generation Flow

Customers can either place immediate orders or subscribe to recurring delivery. Subscription state changes eventually affect daily order generation.

```mermaid
flowchart TD
    A[Customer Subscription Request] --> B[Change Request / Subscription State]
    B --> C[Draft Order]
    C --> D[Draft Order Items]
    D --> E[Frequency Rules]

    E --> E1[Daily]
    E --> E2[Alternate Days]
    E --> E3[Weekdays]
    E --> E4[Weekends]
    E --> E5[Custom]
    E --> E6[Paused / Cancelled]

    E --> F[Daily Order Generation Command]
    F --> G[Orders]
    G --> H[Order Items]
```

---

## 8. Vendor Supply, Delivery Actuals, and Reconciliation Flow

Vendor-side data represents supply. Delivery-side data represents actual delivered quantity. Reconciliation compares both sides.

```mermaid
flowchart TD
    A[Vendor supplies goods] --> B[Vendor Supply Entry]
    B --> C[Supplier-side Order / Order Items]

    D[Delivery Agent delivers goods] --> E[Delivery Actual Entry]
    E --> F[Consumer-side Order / Order Items]

    C --> G[Daily Zone Reconciliation]
    F --> G

    G --> H[Compare Supply vs Delivered]
    H --> I{Difference?}

    I -->|No Difference| J[Mark Balanced]
    I -->|Extra Supply| K[Leftover / Excess]
    I -->|Short Supply| L[Missing / Shortage]

    J --> M[Store Reconciliation Result]
    K --> M
    L --> M
```

---

## 9. Approval and Onboarding Flow

Vendors and delivery agents do not immediately become active. They go through a controlled onboarding and approval flow.

```mermaid
stateDiagram-v2
    [*] --> Pending
    Pending --> UnderReview: Documents Submitted
    UnderReview --> Approved: Zone Manager Approves
    UnderReview --> Rejected: Zone Manager Rejects
    Approved --> Active: Service Enabled
    Active --> Suspended: Suspended by Admin / Manager
    Suspended --> Active: Re-enabled
    Rejected --> Pending: Resubmission
```

---

## 10. Notification Mechanism Overview

Notifications are not treated as direct inline actions only. They are generally handled through the event/job mechanism so that retries and failures can be tracked.

```mermaid
flowchart TD
    A[Business Trigger] --> B[Create Notification Event]
    B --> C[outbox_events]
    C --> D[Queue Dispatcher]
    D --> E[Notification Handler]

    E --> F{Channel}
    F --> F1[Push Notification]
    F --> F2[SMS]
    F --> F3[WhatsApp]
    F --> F4[Email]

    F1 --> G[Delivery Status / Result]
    F2 --> G
    F3 --> G
    F4 --> G

    G --> H[Update Event Status]
```

---

## 11. Access Channels

```mermaid
flowchart LR
    A[Customer] --> M[Mobile App]
    B[Vendor] --> M
    C[Delivery Agent] --> M

    D[Zone Manager] --> W[Admin Web]
    E[Admin] --> W
    F[Future Zone Director] --> W

    M --> API[Laravel API]
    W --> API
    API --> DB[(Database)]
    API --> OUTBOX[(Outbox / Jobs)]
```

---

## 12. Documentation Drill-Down Plan

The remaining documentation should be expanded in the following order:

1. Actor and role model
2. Product type and sub-type model
3. Customer ordering flow
4. Subscription lifecycle flow
5. Daily order generation
6. Vendor onboarding and supply flow
7. Delivery agent onboarding and delivery flow
8. Delivery actuals
9. Change request mechanism
10. Zone manager workflows
11. Reconciliation logic
12. Invoice and report flow
13. Notification system
14. Event / outbox architecture
15. Queue worker and scheduler setup
16. Admin web functionality
17. Mobile app functionality
18. Database table mapping
19. API module mapping
20. Reusable components for future development

---

## 13. Current Known Scope Notes

- Dayli currently supports both on-demand and subscription-based delivery.
- The platform is structured around 16 product types with multiple sub-types.
- Customers, vendors, delivery agents, zone managers, zone directors, and admins exist as system roles.
- Zone director is currently present in the role hierarchy but does not yet have assigned functionality.
- Zone manager is central to approvals, reconciliation, notifications, and operational corrections.
- Admin has full control across the system.
- The backend contains a significant event-driven mechanism for notifications and job processing.
- Both mobile app and web admin interfaces use the backend API.

---

## 14. Next Section To Expand

Recommended next drill-down:

**Actor and Role Model**

This should document:

- Role hierarchy
- Role slugs
- User service approvals
- Vendor registration flow
- Delivery agent registration flow
- Customer permissions
- Zone manager permissions
- Admin permissions
