<?php

declare(strict_types=1);

namespace App\Notifying\Controller\Api;

use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Notifying\Repository\NotificationRecipientRepository;
use App\Notifying\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class NotificationDispatchPlanController extends AbstractController
{
    public function __construct(
        private readonly NotificationDispatchPlanRepository $dispatchPlanRepository,
        private readonly NotificationRecipientRepository $recipientRepository,
    ) {
    }

    #[Route('/api/notification/dispatch-plan', name: 'notifying_api_notification_dispatch_plan', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $recipientEntryId = (string) $request->query->get('recipientEntryId', '');
        if ('' !== $recipientEntryId) {
            $recipients = $this->recipientRepository->findByIds([$recipientEntryId]);
            $plans = [] === $recipients ? [] : $this->dispatchPlanRepository->listForRecipientEntry($recipients[0]);

            return $this->json([
                'ok' => true,
                'items' => NotificationService::dispatchPlanSummary($plans),
            ]);
        }

        $limit = (int) $request->query->get('limit', 100);

        return $this->json([
            'ok' => true,
            'items' => NotificationService::dispatchPlanSummary($this->dispatchPlanRepository->listPending($limit)),
            'limit' => max(1, min(500, $limit)),
        ]);
    }
}
