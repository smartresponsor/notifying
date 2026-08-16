<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Service\NotificationDispatchPlanService;
use App\Notifying\Service\NotificationServiceAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationDispatchPlanController extends AbstractController
{
    public function __construct(
        private readonly NotificationDispatchPlanService $dispatchPlanService,
        private readonly NotificationServiceAccessService $serviceAccess,
    ) {
    }

    #[Route('/api/notification/dispatch-plan', name: 'notifying_api_notification_dispatch_plan', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $this->serviceAccess->requireService($request, NotificationServiceAccessService::SCOPE_DISPATCH_CONSUME);
        $recipientEntryId = (string) $request->query->get('recipientEntryId', '');
        $limit = (int) $request->query->get('limit', 100);

        return $this->json([
            'ok' => true,
            'items' => $this->dispatchPlanService->list('' === $recipientEntryId ? null : $recipientEntryId, $limit),
            'limit' => max(1, min(500, $limit)),
        ]);
    }

    #[Route('/api/notification/dispatch-plan/claim', name: 'notifying_api_notification_dispatch_plan_claim', methods: ['POST'])]
    public function claim(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $claimedBy = $this->serviceAccess->requireService(
            $request,
            NotificationServiceAccessService::SCOPE_DISPATCH_CONSUME,
            isset($payload['claimedBy']) ? (string) $payload['claimedBy'] : null,
        );

        try {
            $items = $this->dispatchPlanService->claim(
                claimedBy: $claimedBy,
                limit: (int) ($payload['limit'] ?? 100),
                leaseSeconds: (int) ($payload['leaseSeconds'] ?? 60),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }

        return $this->json([
            'ok' => true,
            'items' => $items,
        ]);
    }

    #[Route('/api/notification/dispatch-plan/handoff', name: 'notifying_api_notification_dispatch_plan_handoff', methods: ['POST'])]
    public function handoff(Request $request): JsonResponse
    {
        $payload = $request->toArray();
        $claimedBy = $this->serviceAccess->requireService(
            $request,
            NotificationServiceAccessService::SCOPE_DISPATCH_CONSUME,
            isset($payload['claimedBy']) ? (string) $payload['claimedBy'] : null,
        );

        try {
            $items = $this->dispatchPlanService->markHandedOff(
                NotificationDispatchPlanService::idsFromPayload($payload),
                $claimedBy,
                (string) ($payload['claimLeaseId'] ?? ''),
            );
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'ok' => false,
                'error' => $exception->getMessage(),
            ], 400);
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
        $claimedBy = $this->serviceAccess->requireService(
            $request,
            NotificationServiceAccessService::SCOPE_DISPATCH_CONSUME,
            isset($payload['claimedBy']) ? (string) $payload['claimedBy'] : null,
        );

        try {
            $items = $this->dispatchPlanService->markFailed(
                NotificationDispatchPlanService::idsFromPayload($payload),
                $reason,
                $claimedBy,
                isset($payload['claimLeaseId']) ? (string) $payload['claimLeaseId'] : null,
            );
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
        $serviceKey = $this->serviceAccess->requireService($request, NotificationServiceAccessService::SCOPE_DISPATCH_CONSUME);

        try {
            $items = $this->dispatchPlanService->cancel(NotificationDispatchPlanService::idsFromPayload($payload), $reason, $serviceKey);
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
