<?php

declare(strict_types=1);

namespace App\Notifying\MessageHandler;

use App\Notifying\Message\NotificationIntentMessage;
use App\Notifying\Service\NotificationService;

final class NotificationIntentMessageHandler
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function __invoke(NotificationIntentMessage $message): void
    {
        $this->notificationService->createIntent($message->payload);
    }
}
