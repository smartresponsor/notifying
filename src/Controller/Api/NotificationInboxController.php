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

final class NotificationInboxController extends AbstractController
{
    public function __construct(
        private readonly NotificationInboxService $inboxService,
        private readonly NotificationRecipientAccessService $recipientAccess,
        private readonly NotificationService $notificationService,
    ) {
    }

    #[Route('/api/notification/inbox', name: 'notifying_api_notification_inbox', methods: ['GET'])]
    public function inbox(Request $request): JsonResponse
    {
        $recipientKey = $this->recipientAccess->requireRecipientKey(
            $request,
            (string) $request->query->get('recipientKey', ''),
        );
        $limit = (int) $request->query->get('limit', 50);
        $offset = (int) $request->query->get('offset', 0);

        return $this->json([
            'ok' => true,
            'items' => $this->inboxService->listInbox($recipientKey, $limit, $offset),
            'limit' => max(1, min(100, $limit)),
            'offset' => max(0, $offset),
        ]);
    }

    #[Route('/api/notification/need/recognized', name: 'notifying_api_recognized_need', methods: ['POST'])]
    public function recognizedNeed(Request $request): JsonResponse
    {
        $body = $request->toArray();
        $summary = trim((string) ($body['summary'] ?? ''));
        if ('' === $summary) {
            return $this->json(['ok' => false, 'error' => 'summary is required.'], 400);
        }

        $recipientKey = $this->recipientAccess->requireRecipientKey($request);
        $category = trim((string) ($body['category'] ?? 'unknown'));
        $source = trim((string) ($body['source'] ?? 'voice'));
        $locale = trim((string) ($body['locale'] ?? ''));
        $classifierVersion = trim((string) ($body['classifierVersion'] ?? ''));
        $confidence = isset($body['confidence']) && is_numeric($body['confidence'])
            ? max(0.0, min(1.0, (float) $body['confidence']))
            : null;

        $result = $this->notificationService->ingest([
            'sourceComponent' => 'one_tasker_ai',
            'eventName' => 'need.recognized',
            'topic' => 'one_tasker.need',
            'recipientType' => 'user',
            'recipientKey' => $recipientKey,
            'title' => $summary,
            'body' => $summary,
            'priority' => 'normal',
            'payload' => [
                'type' => 'recognized_need',
                'category' => '' !== $category ? $category : 'unknown',
                'confidence' => $confidence,
                'source' => '' !== $source ? $source : 'voice',
                'locale' => '' !== $locale ? $locale : null,
                'classifierVersion' => '' !== $classifierVersion ? $classifierVersion : null,
            ],
            'metadata' => ['deliveryPolicy' => 'inbox_only'],
            'correlationId' => isset($body['correlationId']) ? (string) $body['correlationId'] : null,
            'actionUrl' => 'onetasker://needs',
        ]);

        return $this->json(['ok' => true, 'notification' => $result], 201);
    }

    #[Route('/api/notification/unread/count', name: 'notifying_api_notification_unread_count', methods: ['GET'])]
    public function unreadCount(Request $request): JsonResponse
    {
        $recipientKey = $this->recipientAccess->requireRecipientKey(
            $request,
            (string) $request->query->get('recipientKey', ''),
        );

        return $this->json([
            'ok' => true,
            'unreadCount' => $this->inboxService->countUnread($recipientKey),
        ]);
    }
}
