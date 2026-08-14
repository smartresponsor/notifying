<?php

declare(strict_types=1);

namespace App\Notifying\Tests\Entity;

use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\RecipientType;
use PHPUnit\Framework\TestCase;

final class NotificationPreferenceEntityTest extends TestCase
{
    public function testIndefiniteMuteHasPriorityOverChannelPolicy(): void
    {
        $preference = $this->preference();
        $preference->setEnabledChannels([NotificationChannel::Inbox]);
        $preference->setDisabledChannels([NotificationChannel::Push]);
        $preference->mute();

        self::assertSame('recipient-muted', $preference->pushSuppressionReason());
    }

    public function testDisabledPushIsSuppressedWhenRecipientIsNotMuted(): void
    {
        $preference = $this->preference();
        $preference->setEnabledChannels([NotificationChannel::Inbox, NotificationChannel::Push]);
        $preference->setDisabledChannels([NotificationChannel::Push]);

        self::assertSame('channel-disabled', $preference->pushSuppressionReason());
    }

    public function testOvernightQuietHoursReturnNextMorningBoundary(): void
    {
        $preference = $this->preference();
        $preference->setQuietHours('22:00', '07:00', 'America/Chicago');
        $now = new \DateTimeImmutable('2026-08-13T23:30:00-05:00');

        $end = $preference->quietHoursEndAfter($now);

        self::assertNotNull($end);
        self::assertSame('2026-08-14T07:00:00-05:00', $end->format('c'));
    }

    public function testOutsideQuietHoursDoesNotDefer(): void
    {
        $preference = $this->preference();
        $preference->setQuietHours('22:00', '07:00', 'UTC');
        $now = new \DateTimeImmutable('2026-08-13T12:00:00+00:00');

        self::assertNull($preference->quietHoursEndAfter($now));
        self::assertNull($preference->pushDeferredUntil($now));
    }

    public function testTimedMuteComposesWithQuietHours(): void
    {
        $preference = $this->preference();
        $preference->setQuietHours('22:00', '07:00', 'UTC');
        $preference->mute(new \DateTimeImmutable('2026-08-13T22:30:00+00:00'));
        $now = new \DateTimeImmutable('2026-08-13T21:00:00+00:00');

        $deferredUntil = $preference->pushDeferredUntil($now);

        self::assertNull($preference->pushSuppressionReason());
        self::assertNotNull($deferredUntil);
        self::assertSame('2026-08-14T07:00:00+00:00', $deferredUntil->format('c'));
    }

    public function testTimedMuteWithoutQuietHoursDefersUntilMuteExpiry(): void
    {
        $preference = $this->preference();
        $mutedUntil = new \DateTimeImmutable('2026-08-13T22:30:00+00:00');
        $preference->mute($mutedUntil);
        $now = new \DateTimeImmutable('2026-08-13T21:00:00+00:00');

        self::assertEquals($mutedUntil, $preference->pushDeferredUntil($now));
    }

    private function preference(): NotificationPreferenceEntity
    {
        return new NotificationPreferenceEntity(
            id: '00000000-0000-4000-8000-000000000001',
            recipientType: RecipientType::User,
            recipientKey: 'test-user',
            topic: 'task',
        );
    }
}
