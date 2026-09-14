<?php

declare(strict_types=1);

namespace App\Notifying\Tests\Repository;

use App\Notifying\Entity\NotificationDispatchPlanEntity;
use App\Notifying\Entity\NotificationEntity;
use App\Notifying\Entity\NotificationRecipientEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\NotificationDispatchStatus;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationDispatchPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NotificationDispatchPlanRepositoryTest extends KernelTestCase
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

    public function testDoctrineSchemaUsesCanonicalObjectingColumns(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schema = $schemaTool->getSchemaFromMetadata($this->entityManager->getMetadataFactory()->getAllMetadata());

        foreach ([
            'notifying_notification',
            'notifying_notification_recipient',
            'notifying_notification_dispatch_plan',
            'notifying_notification_preference',
            'notifying_notification_subscription',
        ] as $tableName) {
            $table = $schema->getTable($tableName);
            foreach (['uuid', 'slug', 'created_at', 'modified_at', 'created_by', 'modified_by'] as $column) {
                self::assertTrue($table->hasColumn($column), sprintf('%s must expose canonical Objecting column %s.', $tableName, $column));
            }
            foreach (['object_uuid', 'object_slug', 'object_created_at', 'object_modified_at', 'object_created_by', 'object_modified_by'] as $legacyColumn) {
                self::assertFalse($table->hasColumn($legacyColumn), sprintf('%s must not expose legacy column %s.', $tableName, $legacyColumn));
            }
        }

        $notification = $schema->getTable('notifying_notification');
        foreach (['first_title', 'middle_title', 'last_title'] as $column) {
            self::assertTrue($notification->hasColumn($column));
        }
        self::assertFalse($notification->hasColumn('title'), 'Notification title must be an Objecting first_title alias, not duplicate storage.');
        self::assertFalse($notification->hasColumn('object_first_title'));
        self::assertFalse($notification->hasColumn('object_middle_title'));
        self::assertFalse($notification->hasColumn('object_last_title'));
    }

    public function testOnlyDueHandoffReadyPlanIsSelected(): void
    {
        $now = new \DateTimeImmutable('2026-08-13T19:15:00+00:00');
        $duePlan = $this->plan('00000000-0000-4000-8000-000000000101', $now->modify('-1 minute'));
        $futurePlan = $this->plan('00000000-0000-4000-8000-000000000102', $now->modify('+10 minutes'));
        $this->persistPlan($duePlan);
        $this->persistPlan($futurePlan);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(NotificationDispatchPlanRepository::class);
        self::assertInstanceOf(NotificationDispatchPlanRepository::class, $repository);
        $selected = $repository->claimHandoffReady(
            claimedBy: 'test-worker',
            claimLeaseHash: hash('sha256', 'test-claim'),
            claimedAt: $now,
            claimExpiresAt: $now->modify('+60 seconds'),
            limit: 10,
        );

        self::assertCount(1, $selected);
        self::assertSame($duePlan->id(), $selected[0]->id());
        self::assertSame(NotificationDispatchStatus::Claimed, $selected[0]->status());

        $this->entityManager->clear();
        $future = $repository->find($futurePlan->id());
        self::assertInstanceOf(NotificationDispatchPlanEntity::class, $future);
        self::assertSame(NotificationDispatchStatus::HandoffReady, $future->status());
    }

    public function testExpiredClaimIsRecoveredAndSelectedAgain(): void
    {
        $now = new \DateTimeImmutable('2026-08-13T19:15:00+00:00');
        $plan = $this->plan('00000000-0000-4000-8000-000000000103', $now->modify('-10 minutes'));
        $firstClaimAt = $now->modify('-2 minutes');
        $plan->claim(
            claimedBy: 'worker-old',
            claimLeaseHash: hash('sha256', 'old-claim'),
            expiresAt: $firstClaimAt->modify('+60 seconds'),
            at: $firstClaimAt,
        );
        $this->persistPlan($plan);
        $this->entityManager->flush();

        $repository = self::getContainer()->get(NotificationDispatchPlanRepository::class);
        self::assertInstanceOf(NotificationDispatchPlanRepository::class, $repository);
        $selected = $repository->claimHandoffReady(
            claimedBy: 'worker-new',
            claimLeaseHash: hash('sha256', 'new-claim'),
            claimedAt: $now,
            claimExpiresAt: $now->modify('+60 seconds'),
            limit: 10,
        );

        self::assertCount(1, $selected);
        self::assertSame($plan->id(), $selected[0]->id());
        self::assertSame(NotificationDispatchStatus::Claimed, $selected[0]->status());
        self::assertSame('worker-new', $selected[0]->claimedBy());
        self::assertEquals($now, $selected[0]->claimedAt());
    }

    private function persistPlan(NotificationDispatchPlanEntity $plan): void
    {
        $this->entityManager->persist($plan->notification());
        $this->entityManager->persist($plan->recipientEntry());
        $this->entityManager->persist($plan);
    }

    private function plan(string $planId, \DateTimeImmutable $scheduledAt): NotificationDispatchPlanEntity
    {
        $suffix = substr($planId, -3);
        $notification = new NotificationEntity(
            id: '00000000-0000-4000-8000-000000001'.$suffix,
            sourceComponent: 'test',
            eventName: 'task.updated.'.$suffix,
            topic: 'task',
            title: 'Test notification '.$suffix,
            body: 'Test body',
        );
        $recipient = new NotificationRecipientEntity(
            id: '00000000-0000-4000-8000-000000002'.$suffix,
            notification: $notification,
            recipientType: RecipientType::User,
            recipientKey: 'test-user-'.$suffix,
        );

        return new NotificationDispatchPlanEntity(
            id: $planId,
            notification: $notification,
            recipientEntry: $recipient,
            channel: NotificationChannel::Push,
            status: NotificationDispatchStatus::HandoffReady,
            reason: 'push-policy-deferred',
            target: 'subscription:test-'.$suffix,
            scheduledAt: $scheduledAt,
        );
    }
}
