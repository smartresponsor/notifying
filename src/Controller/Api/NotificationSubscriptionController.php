<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly NotificationSubscriptionService $subscriptionService,
    ) {
    }

    #[Route('/api/notification/subscription', name: 'notifying_api_notification_subscription', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        if (array_key_exists('enabled', $payload) && false === filter_var($payload['enabled'], FILTER_VALIDATE_BOOLEAN)) {
            return $this->json([
                'ok' => true,
                'result' => $this->subscriptionService->disableSubscription($payload),
            ]);
        }

        try {
            $subscription = $this->subscriptionService->registerSubscription($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }

        return $this->json([
            'ok' => true,
            'subscription' => $subscription,
        ]);
    }
}
