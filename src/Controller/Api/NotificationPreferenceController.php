<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationPreferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationPreferenceController extends AbstractController
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService,
    ) {
    }

    #[Route('/api/notification/pref', name: 'notifying_api_notification_pref', methods: ['POST'])]
    public function upsert(Request $request): JsonResponse
    {
        $preference = $this->preferenceService->upsertPreference($request->toArray());

        return $this->json([
            'ok' => true,
            'preference' => $preference,
        ]);
    }
}
