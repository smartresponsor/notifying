<?php

declare(strict_types=1);

namespace App\Notifying\Tests\Service;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Enum\NotificationDispatchStatus;
use App\Notifying\Repository\NotificationDispatchPlanRepository;
use App\Notifying\Service\NotificationDispatchPlanService;
use App\Notifying\Service\NotificationPreferenceService;
use App\Notifying\Service\NotificationService;
use App\Notifying\Service\NotificationSubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NotificationSubscriptionReconciliationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
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
