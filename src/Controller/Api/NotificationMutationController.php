<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationInboxService;
use App\Notifying\Service\NotificationRecipientAccessService;
use App\Notifying\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationMutationController extends AbstractController
{
    public function __construct(
        private readonly NotificationInboxService $inboxService,
        private readonly NotificationRecipientAccessService $recipientAccess,
        private readonly NotificationService $notificationService,
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

    #[Route('/api/notification/need/callback', name: 'notifying_api_need_callback', methods: ['POST'])]
    public function requestNeedCallback(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $entryId = trim((string) ($payload['recipientEntryId'] ?? ''));
        $recipientKey = $this->recipientAccess->requireRecipientKey($request);
        $entry = $this->inboxService->findOwnedEntry($entryId, $recipientKey);

        if (null === $entry) {
            return $this->json(['ok' => false, 'error' => 'Recognized need was not found.'], 404);
        }

        $need = $entry->notification();
        if ('need.recognized' !== $need->eventName() || 'one_tasker.need' !== $need->topic()) {
            return $this->json(['ok' => false, 'error' => 'Callback can only be requested for a recognized need.'], 409);
        }

        $result = $this->notificationService->ingest([
            'sourceComponent' => 'one_tasker_widget',
            'eventName' => 'need.callback_requested',
            'topic' => 'one_tasker.need.callback',
            'recipientType' => 'system',
            'recipientKey' => 'one_tasker.operations',
            'title' => 'Callback requested: '.$need->title(),
            'body' => $need->body(),
            'priority' => 'high',
            'payload' => [
                'recognizedNeedNotificationId' => $need->id(),
                'recognizedNeedRecipientEntryId' => $entry->id(),
                'requesterRecipientKey' => $recipientKey,
                'category' => $need->payload()['category'] ?? 'unknown',
            ],
            'metadata' => ['deliveryPolicy' => 'inbox_only'],
            'correlationId' => 'need-callback:'.$need->id(),
            'actionUrl' => 'onetasker://operations/needs/'.$need->id(),
        ]);

        $this->inboxService->ack($entry->id(), $recipientKey);

        return $this->json(['ok' => true, 'callback' => $result], 201);
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
        $untilValue = $payload['until'] ?? '+1 hour';
        if (!is_string($untilValue) || '' === trim($untilValue)) {
            return $this->json(['ok' => false, 'error' => 'Invalid snooze until value.'], 400);
        }

        try {
            $until = new \DateTimeImmutable($untilValue);
        } catch (\Exception) {
            return $this->json(['ok' => false, 'error' => 'Invalid snooze until value.'], 400);
        }

        $recipientKey = $this->recipientAccess->requireRecipientKey($request);

        return $this->json([
            'ok' => true,
            'snoozed' => $this->inboxService->snooze($entryId, $recipientKey, $until),
        ]);
    }
}
