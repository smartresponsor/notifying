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
        $subscription = $this->subscriptionService->registerSubscription($request->toArray());

        return $this->json([
            'ok' => true,
            'subscription' => $subscription,
        ]);
    }
}
