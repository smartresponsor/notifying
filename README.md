# Notifying

Notifying is the platform notification center component.

It owns notification meaning, inbox state, recipient preferences, device subscriptions, and dispatch planning. It does **not** own low-level provider delivery.

## Runtime target

- Symfony 8
- PHP 8.4
- PostgreSQL
- Root namespace: `App\\Notifying\\`
- Symfony-oriented structure under `src/`
- No Port/Adapter pattern
- No `src/Domain/`

## Mandatory platform stack

Notifying is a platform application component and therefore connects the shared SmartResponsor stack:

- EasyAdmin for internal back-office screens and entity CRUD entry points.
- Objecting for reusable system fields such as identity, audit, and title field packs.
- Interfacing for shared interface templates and interface shell responsibility.
- Viewing for the central view/rendering boundary.
- Cruding for generic CRUD route grammar and entity/resource resolution.

This stack is consumed as Symfony packages. Notifying still owns its own Entity classes, Doctrine migrations, repositories, services, API controllers, mobile contracts, notification policy, inbox behavior, and Dispatch Plan boundary toward Delivering.

The Objecting consumer declaration lives in `resources/consumer/notifying-object-field-packs.yaml` and records the initial field-pack adoption surface. The mandatory stack summary lives in `resources/consumer/notifying-platform-stack.yaml`. The local Cruding runtime placeholder lives in `config/kernel/runtime_scope.lock.php`.

The host application should mount this repository through a Composer path repository and require `smartresponsor/notifying` when Notifying is promoted into the host runtime.

## Responsibility boundary

### Notifying owns

- Notification inbox
- Unread/read state
- Acknowledgement state
- Snooze and mute policy
- Recipient model
- Notification preferences
- Notification subscriptions and device tokens
- Notification policy
- Digest planning
- Event-to-notification mapping
- Dispatch plan toward Delivering
- Mobile API contract for notifications

### Delivering owns

- Provider sends
- Delivery attempts
- Retries
- Webhook/provider status processing
- SMS/email/push/WhatsApp physical transport
- Symfony Notifier integration

## Relationship to business components

Business components such as Messaging, Ordering, Billing, Tasking, and Vendoring emit notification intents/events.

Notifying converts those intents into notification records, recipient inbox entries, channel decisions, preference-aware plans, and dispatch plans addressed to Delivering.

Delivering physically sends prepared payloads through provider transports and reports delivery-state outcomes in its own storage.

Mobiling consumes Notifying APIs for inbox, unread-count, mark-read, ack, preferences, and push subscription registration.

Recipient-facing APIs are ownership-scoped. The Host may provide a verified recipient through the `notifying_recipient_key` request attribute; otherwise Notifying uses the authenticated Symfony `UserInterface::getUserIdentifier()`. Standalone `dev`/`test` runtime additionally accepts `X-Notifying-Recipient-Key` for smoke/testing only. Caller-supplied `recipientKey` is treated only as an assertion and must match the verified identity; production does not trust it as authentication. Inbox mutations also verify that each recipient entry belongs to that identity, and subscription disable verifies token/device ownership before changing state.

Preference writes use strict recipient type, channel, and boolean validation. Timed mute is supported with `muted=true` plus an ISO-8601 `mutedUntil`; unmuting clears `mutedUntil`. Quiet-hours fields are already entity-backed but are not yet applied as dispatch delay until deferred scheduling is implemented.

## Initial API contract

| Method | Path | Responsibility |
| --- | --- | --- |
| `GET` | `/api/notification/inbox` | List inbox notifications for a recipient |
| `GET` | `/api/notification/unread-count` | Return unread count for a recipient |
| `POST` | `/api/notification/mark-read` | Mark one or more notifications as read |
| `POST` | `/api/notification/ack` | Acknowledge a notification |
| `POST` | `/api/notification/subscription` | Register, update, or disable a device push subscription |
| `POST` | `/api/notification/pref` | Upsert recipient notification preferences |
| `GET` | `/api/notification/dispatch-plan` | Inspect planned or suppressed notification dispatch handoffs |
| `POST` | `/api/notification/dispatch-plan/claim` | Atomically lease handoff-ready plans to one Delivering worker |
| `POST` | `/api/notification/dispatch-plan/handoff` | Mark claimed dispatch plans as handed off to Delivering |
| `POST` | `/api/notification/dispatch-plan/fail` | Mark dispatch plans as failed without retrying provider sends |
| `POST` | `/api/notification/dispatch-plan/cancel` | Cancel dispatch plans before physical delivery |

## Dispatch planning model

Notifying persists `NotificationDispatchPlanEntity` records for each notification recipient and channel decision. The dispatch plan is the durable boundary that Delivering can later consume.

The current repository creates inbox and push dispatch plans during intent ingestion. It does not call Symfony Notifier and does not send through providers.

When Mobiling registers a push subscription, Notifying reactivates previously suppressed push plans for that recipient if they were suppressed only because no active subscription existed. Reactivated plans move back to `handoff_ready` and become visible through the dispatch-plan API.

Subscription registration is device-first for a recipient/app/platform/device identity. Push token rotation updates that existing row instead of creating duplicate active subscriptions. Outstanding `handoff_ready` push plans that still target the previous token are retargeted to the new token before delivery handoff. The same token hash cannot be silently claimed by a different subscription identity.

Preference updates are symmetric for push plans that have not yet been claimed by Delivering. If a recipient becomes muted, push is removed from enabled channels, or push is explicitly disabled, matching `handoff_ready` plans immediately move back to `suppressed`, their delivery target is cleared, and the exact policy reason is persisted (`recipient-muted`, `channel-not-enabled`, or `channel-disabled`). When the recipient later allows push again, those topic-matching policy-suppressed plans are reactivated to `handoff_ready` using the current active subscription. Claimed or already handed-off plans are not rewritten by this preference transition.

Delivering must claim work before handoff. Claim acquisition uses an atomic conditional database update from `handoff_ready` to `claimed`, so concurrent workers cannot successfully claim the same row. The entity-backed lease records `claimedBy`, `claimedAt`, and `claimExpiresAt`; expired claims are recovered back to `handoff_ready` before the next claim scan, and only the worker identified by `claimedBy` may complete handoff while its lease is valid.

Dispatch lifecycle transitions are guarded in the entity. `handoff_ready` may be revoked to `suppressed` by recipient policy before claim; otherwise it becomes `claimed`, then `claimed` may become `handed_off`. `handoff_ready`, `claimed`, or `handed_off` may become `failed`; `planned`, `suppressed`, or `handoff_ready` may become `cancelled`. Repeating an already completed terminal transition is idempotent, while invalid transitions return HTTP `409 Conflict`.

## Repository layout

```text
config/packages/
config/routes/
migrations/
src/Controller/Api/
src/Entity/
src/Enum/
src/Message/
src/MessageHandler/
