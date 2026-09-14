<?php

declare(strict_types=1);

namespace App\Notifying\Tests\Service;

use App\Notifying\Controller\Api\NotificationMutationController;
use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Enum\NotificationDispatchStatus;
use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Notifying\Repository\NotificationRecipientRepository;
use App\Notifying\Service\NotificationDispatchPlanService;
use App\Notifying\Service\NotificationPreferenceService;
use App\Notifying\Service\NotificationService;
use App\Notifying\Service\NotificationSubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class NotificationSubscriptionReconciliationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $databasePath = dirname(__DIR__, 2).'/var/notifying_test.sqlite';
        if (is_file($databasePath)) {
            unlink($databasePath);
        }

        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;

        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testTimedMuteRegistrationDefersPushUntilMuteExpires(): void
    {
        $notificationService = self::getContainer()->get(NotificationService::class);
        $preferenceService = self::getContainer()->get(NotificationPreferenceService::class);
        $subscriptionService = self::getContainer()->get(NotificationSubscriptionService::class);
        $dispatchPlanService = self::getContainer()->get(NotificationDispatchPlanService::class);
        $repository = self::getContainer()->get(NotificationDispatchPlanRepository::class);
        self::assertInstanceOf(NotificationService::class, $notificationService);
        self::assertInstanceOf(NotificationPreferenceService::class, $preferenceService);
        self::assertInstanceOf(NotificationSubscriptionService::class, $subscriptionService);
        self::assertInstanceOf(NotificationDispatchPlanService::class, $dispatchPlanService);
        self::assertInstanceOf(NotificationDispatchPlanRepository::class, $repository);

        $created = $notificationService->ingest($this->intentPayload('timed-mute'));
        $pushPlan = $this->channelPlan($created, 'push');
        $pushPlanId = (string) $pushPlan['id'];
        $mutedUntil = new \DateTimeImmutable('+10 minutes');
        $preferenceService->upsertPreference([
            'recipientType' => 'user',
            'recipientKey' => 'test-user-timed-mute',
            'topic' => 'task',
            'enabledChannels' => ['inbox', 'push'],
            'disabledChannels' => [],
            'muted' => true,
            'mutedUntil' => $mutedUntil->format(DATE_ATOM),
        ]);

        $subscription = $subscriptionService->registerSubscription($this->subscriptionPayload('timed-mute'));

        self::assertCount(1, $subscription['reactivatedDispatchPlans']);
        self::assertSame($pushPlanId, $subscription['reactivatedDispatchPlans'][0]['id']);
        self::assertSame('handoff_ready', $subscription['reactivatedDispatchPlans'][0]['status']);
        self::assertSame('push-policy-deferred', $subscription['reactivatedDispatchPlans'][0]['reason']);

        $plan = $repository->find($pushPlanId);
        self::assertInstanceOf(NotificationDispatchPlanEntity::class, $plan);
        self::assertSame(NotificationDispatchStatus::HandoffReady, $plan->status());
        self::assertNotNull($plan->scheduledAt());
        self::assertGreaterThanOrEqual($mutedUntil->getTimestamp(), $plan->scheduledAt()->getTimestamp());

        $claimed = $dispatchPlanService->claim('test-worker', limit: 100, leaseSeconds: 60);
        $claimedIds = array_column($claimed, 'id');
        self::assertNotContains($pushPlanId, $claimedIds);
    }

    public function testClaimExposesOnlyTokenHashForPushDelivery(): void
    {
        $notificationService = self::getContainer()->get(NotificationService::class);
        $subscriptionService = self::getContainer()->get(NotificationSubscriptionService::class);
        $dispatchPlanService = self::getContainer()->get(NotificationDispatchPlanService::class);
        self::assertInstanceOf(NotificationService::class, $notificationService);
        self::assertInstanceOf(NotificationSubscriptionService::class, $subscriptionService);
        self::assertInstanceOf(NotificationDispatchPlanService::class, $dispatchPlanService);

        $notificationService->ingest($this->intentPayload('claim-token-hash'));
        $subscription = $subscriptionService->registerSubscription($this->subscriptionPayload('claim-token-hash'));
        $claimed = $dispatchPlanService->claim('test-worker-secret-safe', limit: 100, leaseSeconds: 60);
        $pushClaim = null;
        foreach ($claimed as $claim) {
            if ('push' === ($claim['channel'] ?? null)) {
                $pushClaim = $claim;
                break;
            }
        }

        self::assertIsArray($pushClaim);
        self::assertIsArray($pushClaim['delivery'] ?? null);
        self::assertSame($subscription['tokenHash'], $pushClaim['delivery']['tokenHash'] ?? null);
        self::assertArrayNotHasKey('token', $pushClaim['delivery']);
        self::assertStringNotContainsString('token-claim-token-hash', json_encode($pushClaim, JSON_THROW_ON_ERROR));
    }

    public function testRegistrationKeepsPushSuppressedWhenPreferenceDisablesPush(): void
    {
        $notificationService = self::getContainer()->get(NotificationService::class);
        $preferenceService = self::getContainer()->get(NotificationPreferenceService::class);
        $subscriptionService = self::getContainer()->get(NotificationSubscriptionService::class);
        $repository = self::getContainer()->get(NotificationDispatchPlanRepository::class);
        self::assertInstanceOf(NotificationService::class, $notificationService);
        self::assertInstanceOf(NotificationPreferenceService::class, $preferenceService);
        self::assertInstanceOf(NotificationSubscriptionService::class, $subscriptionService);
        self::assertInstanceOf(NotificationDispatchPlanRepository::class, $repository);

        $created = $notificationService->ingest($this->intentPayload('disabled-policy'));
        $pushPlan = $this->channelPlan($created, 'push');
        $pushPlanId = (string) $pushPlan['id'];
        $preferenceService->upsertPreference([
            'recipientType' => 'user',
            'recipientKey' => 'test-user-disabled-policy',
            'topic' => 'task',
            'enabledChannels' => ['inbox', 'push'],
            'disabledChannels' => ['push'],
            'muted' => false,
        ]);

        $subscription = $subscriptionService->registerSubscription($this->subscriptionPayload('disabled-policy'));

        self::assertSame([], $subscription['reactivatedDispatchPlans']);
        $plan = $repository->find($pushPlanId);
        self::assertInstanceOf(NotificationDispatchPlanEntity::class, $plan);
        self::assertSame(NotificationDispatchStatus::Suppressed, $plan->status());
        self::assertSame('channel-disabled', $plan->reason());
        self::assertNull($plan->target());
    }

    public function testInboxOnlyDeliveryPolicyNeverCreatesPushHandoff(): void
    {
        $notificationService = self::getContainer()->get(NotificationService::class);
        self::assertInstanceOf(NotificationService::class, $notificationService);

        $payload = $this->intentPayload('inbox-only');
        $payload['metadata'] = ['deliveryPolicy' => 'inbox_only'];
        $created = $notificationService->ingest($payload);

        $inboxPlan = $this->channelPlan($created, 'inbox');
        $pushPlan = $this->channelPlan($created, 'push');
        self::assertSame('planned', $inboxPlan['status']);
        self::assertSame('inbox-entry-created', $inboxPlan['reason']);
        self::assertSame('suppressed', $pushPlan['status']);
        self::assertSame('delivery-policy-inbox-only', $pushPlan['reason']);
        self::assertNull($pushPlan['target']);
    }

    public function testCallMeCreatesOneOperationalIntentAndAcknowledgesOriginatingNeed(): void
    {
        $notificationService = self::getContainer()->get(NotificationService::class);
        $controller = self::getContainer()->get(NotificationMutationController::class);
        $recipientRepository = self::getContainer()->get(NotificationRecipientRepository::class);
        self::assertInstanceOf(NotificationService::class, $notificationService);
        self::assertInstanceOf(NotificationMutationController::class, $controller);
        self::assertInstanceOf(NotificationRecipientRepository::class, $recipientRepository);

        $created = $notificationService->ingest([
            'sourceComponent' => 'one_tasker_ai',
            'eventName' => 'need.recognized',
            'topic' => 'one_tasker.need',
            'recipientType' => 'user',
            'recipientKey' => 'test-user-callback',
            'title' => 'Kitchen faucet is leaking',
            'body' => 'Kitchen faucet is leaking',
            'priority' => 'normal',
            'payload' => ['category' => 'plumbing'],
            'metadata' => ['deliveryPolicy' => 'inbox_only'],
            'correlationId' => 'recognized-need-callback-test',
        ]);
        $entryId = (string) $created['recipientEntryId'];

        $request = Request::create(
            '/api/notification/need/callback',
            'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_NOTIFYING_RECIPIENT_KEY' => 'test-user-callback',
            ],
            content: json_encode(['recipientEntryId' => $entryId], JSON_THROW_ON_ERROR),
        );

        $first = json_decode($controller->requestNeedCallback($request)->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $second = json_decode($controller->requestNeedCallback($request)->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertTrue($first['ok']);
        self::assertTrue($second['ok']);
        self::assertSame($first['callback']['notificationId'], $second['callback']['notificationId']);
        self::assertTrue($first['callback']['created']);
        self::assertFalse($second['callback']['created']);
        self::assertSame('one_tasker.need.callback', $first['callback']['topic']);

        $entry = $recipientRepository->find($entryId);
        self::assertNotNull($entry);
        self::assertSame('acked', $entry->status()->value);
        self::assertNotNull($entry->ackedAt());
    }

    public function testMalformedSnoozeUntilReturnsBadRequest(): void
    {
        $controller = self::getContainer()->get(NotificationMutationController::class);
        self::assertInstanceOf(NotificationMutationController::class, $controller);

        $request = Request::create(
            '/api/notification/snooze',
            'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_NOTIFYING_RECIPIENT_KEY' => 'test-user-snooze',
            ],
            content: json_encode([
                'recipientEntryId' => 'missing-entry',
                'until' => 'not-a-date',
            ], JSON_THROW_ON_ERROR),
        );

        $response = $controller->snooze($request);
        $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(400, $response->getStatusCode());
        self::assertFalse($payload['ok']);
        self::assertSame('Invalid snooze until value.', $payload['error']);
    }

    public function testProviderInvalidationDisablesSubscriptionAndSuppressesPendingPush(): void
    {
        $notificationService = self::getContainer()->get(NotificationService::class);
        $subscriptionService = self::getContainer()->get(NotificationSubscriptionService::class);
        $repository = self::getContainer()->get(NotificationDispatchPlanRepository::class);
        self::assertInstanceOf(NotificationService::class, $notificationService);
        self::assertInstanceOf(NotificationSubscriptionService::class, $subscriptionService);
        self::assertInstanceOf(NotificationDispatchPlanRepository::class, $repository);

        $created = $notificationService->ingest($this->intentPayload('invalid-token'));
        $pushPlan = $this->channelPlan($created, 'push');
        $pushPlanId = (string) $pushPlan['id'];
        $subscription = $subscriptionService->registerSubscription($this->subscriptionPayload('invalid-token'));
        $tokenHash = (string) $subscription['tokenHash'];

        $result = $subscriptionService->disableInvalidSubscription(
            tokenHash: $tokenHash,
            reasonCode: 'UNREGISTERED',
            modifiedBy: 'test-provider-feedback',
        );

        self::assertTrue($result['disabled']);
        self::assertFalse($result['subscription']['enabled']);
        self::assertCount(1, $result['suppressedDispatchPlans']);
        self::assertSame($pushPlanId, $result['suppressedDispatchPlans'][0]['id']);
        self::assertSame('no-active-push-subscription', $result['suppressedDispatchPlans'][0]['reason']);

        $plan = $repository->find($pushPlanId);
        self::assertInstanceOf(NotificationDispatchPlanEntity::class, $plan);
        self::assertSame(NotificationDispatchStatus::Suppressed, $plan->status());
        self::assertSame('no-active-push-subscription', $plan->reason());
    }

    /** @return array<string, mixed> */
    private function intentPayload(string $suffix): array
    {
        return [
            'sourceComponent' => 'test',
            'eventName' => 'task.updated',
            'topic' => 'task',
            'recipientType' => 'user',
            'recipientKey' => 'test-user-'.$suffix,
            'title' => 'Test notification '.$suffix,
            'body' => 'Test body',
            'correlationId' => 'test-'.$suffix,
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionPayload(string $suffix): array
    {
        return [
            'recipientType' => 'user',
            'recipientKey' => 'test-user-'.$suffix,
            'platform' => 'android',
            'appKey' => 'test-app',
            'deviceId' => 'device-'.$suffix,
            'token' => 'token-'.$suffix,
        ];
    }

    /**
     * @param array<string, mixed> $created
     * @return array<string, mixed>
     */
    private function channelPlan(array $created, string $channel): array
    {
        foreach ($created['dispatchPlans'] as $plan) {
            if ($channel === ($plan['channel'] ?? null)) {
                return $plan;
            }
        }

        self::fail(sprintf('Dispatch plan for channel %s was not created.', $channel));
    }
}
