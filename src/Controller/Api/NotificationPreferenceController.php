<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationPreferenceService;
use App\Notifying\Service\NotificationRecipientAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationPreferenceController extends AbstractController
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
        private readonly NotificationRecipientAccessService $recipientAccess,
    ) {
    }

    #[Route('/api/notification/pref', name: 'notifying_api_notification_pref', methods: ['POST'])]
    public function upsert(Request $request): JsonResponse
    {
        try {
            $payload = $request->toArray();
            $payload['recipientKey'] = $this->recipientAccess->requireRecipientKey(
                $request,
                (string) ($payload['recipientKey'] ?? ''),
            );
            $preference = $this->preferenceService->upsertPreference($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }

        return $this->json([
            'ok' => true,
            'preference' => $preference,
        ]);
    }
}
