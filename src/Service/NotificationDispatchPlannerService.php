<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Enum\NotificationChannel;
use App\Notifying\ValueObject\NotificationDeliveryPlan;
use App\Notifying\ValueObject\NotificationIntent;

final class NotificationDispatchPlannerService
{
    public function plan(string $notificationId, NotificationIntent $intent): NotificationDeliveryPlan
    {
        return new NotificationDeliveryPlan(
            notificationId: $notificationId,
            recipientKey: $intent->recipientKey,
            channels: [NotificationChannel::Inbox, NotificationChannel::Push],
            title: $intent->title,
            body: $intent->body,
            payload: $intent->payload,
            policy: [
                'sourceComponent' => $intent->sourceComponent,
                'priority' => $intent->priority->value,
            ],
        );
    }
}
