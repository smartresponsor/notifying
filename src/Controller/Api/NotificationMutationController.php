<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationInboxService;
use App\Notifying\Service\NotificationRecipientAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationMutationController extends AbstractController
{
    public function __construct(
        private readonly NotificationInboxService $inboxService,
        private readonly NotificationRecipientAccessService $recipientAccess,
    ) {
    }

    #[Route('/api/notification/mark/read', name: 'notifying_api_notification_mark_read', methods: ['POST'])]
    public function markRead(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $entryIds = array_values(array_filter(array_map('strval', $payload['recipientEntryIds'] ?? $payload['notificationIds'] ?? [])));

        $recipientKey = $this->recipientAccess->requireRecipientKey($request);
        $updated = $this->inboxService->markRead($entryIds, $recipientKey);

        return $this->json([
            'ok' => true,
            'updated' => $updated,
        ]);
    }

    #[Route('/api/notification/ack', name: 'notifying_api_notification_ack', methods: ['POST'])]
    public function ack(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $entryId = (string) ($payload['recipientEntryId'] ?? $payload['notificationId'] ?? '');

        $recipientKey = $this->recipientAccess->requireRecipientKey($request);

        return $this->json([
            'ok' => true,
            'acked' => $this->inboxService->ack($entryId, $recipientKey),
        ]);
    }

    #[Route('/api/notification/archive', name: 'notifying_api_notification_archive', methods: ['POST'])]
    public function archive(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $entryId = (string) ($payload['recipientEntryId'] ?? '');

        $recipientKey = $this->recipientAccess->requireRecipientKey($request);

        return $this->json([
            'ok' => true,
            'archived' => $this->inboxService->archive($entryId, $recipientKey),
        ]);
    }

    #[Route('/api/notification/snooze', name: 'notifying_api_notification_snooze', methods: ['POST'])]
    public function snooze(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $entryId = (string) ($payload['recipientEntryId'] ?? '');
        $until = new \DateTimeImmutable((string) ($payload['until'] ?? '+1 hour'));

        $recipientKey = $this->recipientAccess->requireRecipientKey($request);

        return $this->json([
            'ok' => true,
            'snoozed' => $this->inboxService->snooze($entryId, $recipientKey, $until),
        ]);
    }
}
