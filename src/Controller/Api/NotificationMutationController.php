<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationInboxService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationMutationController extends AbstractController
{
    public function __construct(
        private readonly NotificationInboxService $inboxService,
    ) {
    }

    #[Route('/api/notification/mark-read', name: 'notifying_api_notification_mark_read', methods: ['POST'])]
    public function markRead(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $notificationIds = array_values(array_filter(array_map('strval', $payload['notificationIds'] ?? [])));

        $updated = $this->inboxService->markRead($notificationIds);

        return $this->json([
            'ok' => true,
            'updated' => $updated,
        ]);
    }

    #[Route('/api/notification/ack', name: 'notifying_api_notification_ack', methods: ['POST'])]
    public function ack(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $notificationId = (string) ($payload['notificationId'] ?? '');

        return $this->json([
            'ok' => true,
            'acked' => $this->inboxService->ack($notificationId),
        ]);
    }
}
