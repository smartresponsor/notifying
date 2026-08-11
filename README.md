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

## Initial API contract

| Method | Path | Responsibility |
| --- | --- | --- |
| `GET` | `/api/notification/inbox` | List inbox notifications for a recipient |
| `GET` | `/api/notification/unread-count` | Return unread count for a recipient |
| `POST` | `/api/notification/mark-read` | Mark one or more notifications as read |
| `POST` | `/api/notification/ack` | Acknowledge a notification |
| `POST` | `/api/notification/subscription` | Register/update a device push subscription |
| `POST` | `/api/notification/pref` | Upsert recipient notification preferences |

## Dispatch planning model

Notifying prepares a `NotificationDeliveryPlan` value object. The plan is the boundary object that a future Delivering integration can consume.

The current repository only defines the plan shape and planning service. It does not call Symfony Notifier and does not send through providers.

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
