<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationDispatchPlanService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationDispatchPlanController extends AbstractController
{
    public function __construct(
        private readonly NotificationDispatchPlanService $dispatchPlanService,
    ) {
    }

    #[Route('/api/notification/dispatch-plan', name: 'notifying_api_notification_dispatch_plan', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $recipientEntryId = (string) $request->query->get('recipientEntryId', '');
        $limit = (int) $request->query->get('limit', 100);

        return $this->json([
            'ok' => true,
            'items' => $this->dispatchPlanService->list('' === $recipientEntryId ? null : $recipientEntryId, $limit),
            'limit' => max(1, min(500, $limit)),
        ]);
    }

    #[Route('/api/notification/dispatch-plan/handoff', name: 'notifying_api_notification_dispatch_plan_handoff', methods: ['POST'])]
    public function handoff(Request $request): JsonResponse
    {
        $payload = $request->toArray();

        try {
            $items = $this->dispatchPlanService->markHandedOff(NotificationDispatchPlanService::idsFromPayload($payload));
        } catch (\DomainException $exception) {
            return $this->transitionConflict($exception);
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    #[Route('/api/notification/dispatch-plan/fail', name: 'notifying_api_notification_dispatch_plan_fail', methods: ['POST'])]
    public function fail(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $reason = (string) ($payload['reason'] ?? 'handoff-failed');

        try {
            $items = $this->dispatchPlanService->markFailed(NotificationDispatchPlanService::idsFromPayload($payload), $reason);
        } catch (\DomainException $exception) {
            return $this->transitionConflict($exception);
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    #[Route('/api/notification/dispatch-plan/cancel', name: 'notifying_api_notification_dispatch_plan_cancel', methods: ['POST'])]
    public function cancel(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $reason = (string) ($payload['reason'] ?? 'cancelled');

        try {
            $items = $this->dispatchPlanService->cancel(NotificationDispatchPlanService::idsFromPayload($payload), $reason);
        } catch (\DomainException $exception) {
            return $this->transitionConflict($exception);
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    private function transitionConflict(\DomainException $exception): JsonResponse
    {
        return $this->json([
            'ok' => false,
            'error' => $exception->getMessage(),
        ], 409);
    }
}
