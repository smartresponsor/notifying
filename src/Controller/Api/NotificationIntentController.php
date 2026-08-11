<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationIntentController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    #[Route('/api/notification/intent', name: 'notifying_api_notification_intent', methods: ['POST'])]
    public function ingest(Request $request): JsonResponse
    {
        try {
            $result = $this->notificationService->ingest($request->toArray());
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }

        return $this->json([
            'ok' => true,
            'notification' => $result,
        ], 201);
    }
}
