<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationRecipientAccessService;
use App\Notifying\Service\NotificationSubscriptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationSubscriptionController extends AbstractController
{
    public function __construct(
        private readonly NotificationSubscriptionService $subscriptionService,
        private readonly NotificationRecipientAccessService $recipientAccess,
    ) {
    }

    #[Route('/api/notification/subscription', name: 'notifying_api_notification_subscription', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $recipientKey = $this->recipientAccess->requireRecipientKey(
            $request,
            (string) ($payload['recipientKey'] ?? ''),
        );
        $payload['recipientKey'] = $recipientKey;

        $enabled = true;
        if (array_key_exists('enabled', $payload)) {
            $rawEnabled = $payload['enabled'];
            if (is_bool($rawEnabled)) {
                $enabled = $rawEnabled;
            } elseif (0 === $rawEnabled || 1 === $rawEnabled || '0' === $rawEnabled || '1' === $rawEnabled) {
                $enabled = (bool) (int) $rawEnabled;
            } elseif (is_string($rawEnabled) && in_array(strtolower($rawEnabled), ['true', 'false'], true)) {
                $enabled = 'true' === strtolower($rawEnabled);
            } else {
                return $this->json([
                    'ok' => false,
                    'error' => 'enabled must be a boolean value.',
                ], 400);
            }
        }

        try {
            if (!$enabled) {
                return $this->json([
                    'ok' => true,
                    'result' => $this->subscriptionService->disableSubscription($payload, expectedRecipientKey: $recipientKey),
                ]);
            }

            $subscription = $this->subscriptionService->registerSubscription($payload);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        } catch (\DomainException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 403);
        }

        return $this->json([
            'ok' => true,
            'subscription' => $subscription,
        ]);
    }
}
