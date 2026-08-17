<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationInboxService;
use App\Notifying\Service\NotificationRecipientAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationInboxController extends AbstractController
{
    public function __construct(
        private readonly NotificationInboxService $inboxService,
        private readonly NotificationRecipientAccessService $recipientAccess,
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
