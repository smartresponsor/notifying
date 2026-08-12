<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\RecipientType;
use App\Notifying\Repository\NotificationPreferenceRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationPreferenceService
{
    public function __construct(
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly NotificationDispatchPlanService $dispatchPlanService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upsertPreference(array $payload, ?string $modifiedBy = null): array
    {
        $recipientType = RecipientType::tryFrom((string) ($payload['recipientType'] ?? 'user')) ?? RecipientType::User;
        $recipientKey = (string) ($payload['recipientKey'] ?? '');
        $topic = (string) ($payload['topic'] ?? 'default');

        if ('' === trim($recipientKey)) {
            throw new \InvalidArgumentException('recipientKey is required.');
        }

        $preference = $this->preferenceRepository->findForTopic($recipientType, $recipientKey, $topic);
        $created = false;
        if (!$preference instanceof NotificationPreferenceEntity) {
            $preference = new NotificationPreferenceEntity(self::newUuid(), $recipientType, $recipientKey, $topic, $modifiedBy);
            $this->entityManager->persist($preference);
            $created = true;
        }

        if (isset($payload['enabledChannels']) && is_array($payload['enabledChannels'])) {
            $preference->setEnabledChannels(self::channelsFromStrings($payload['enabledChannels']), $modifiedBy);
        }
        if (isset($payload['disabledChannels']) && is_array($payload['disabledChannels'])) {
            $preference->setDisabledChannels(self::channelsFromStrings($payload['disabledChannels']), $modifiedBy);
        }
        if (array_key_exists('muted', $payload)) {
            ((bool) $payload['muted']) ? $preference->mute(modifiedBy: $modifiedBy) : $preference->unmute($modifiedBy);
        }
        if (array_key_exists('digestEnabled', $payload)) {
            $preference->setDigest((bool) $payload['digestEnabled'], isset($payload['digestFrequency']) ? (string) $payload['digestFrequency'] : null, $modifiedBy);
        }
        if (isset($payload['policy']) && is_array($payload['policy'])) {
            $preference->setPolicy($payload['policy'], $modifiedBy);
        }
        if (array_key_exists('quietHoursStart', $payload) || array_key_exists('quietHoursEnd', $payload) || array_key_exists('timezone', $payload)) {
            $preference->setQuietHours(
                isset($payload['quietHoursStart']) ? (string) $payload['quietHoursStart'] : null,
                isset($payload['quietHoursEnd']) ? (string) $payload['quietHoursEnd'] : null,
                isset($payload['timezone']) ? (string) $payload['timezone'] : null,
                $modifiedBy,
            );
        }

        $this->entityManager->flush();

        $reactivatedDispatchPlans = [];
        $suppressedDispatchPlans = [];
        $pushSuppressionReason = self::pushSuppressionReason($preference);
        if (null === $pushSuppressionReason) {
            $reactivatedDispatchPlans = $this->dispatchPlanService->reactivatePushForPreference(
                recipientType: $preference->recipientType(),
                recipientKey: $preference->recipientKey(),
                topic: $preference->topic(),
                modifiedBy: $modifiedBy,
            );
        } else {
            $suppressedDispatchPlans = $this->dispatchPlanService->suppressPushForPreference(
                recipientType: $preference->recipientType(),
                recipientKey: $preference->recipientKey(),
                topic: $preference->topic(),
                reason: $pushSuppressionReason,
                modifiedBy: $modifiedBy,
            );
        }

        return [
            'id' => $preference->getObjectUuid(),
            'recipientType' => $preference->recipientType()->value,
            'recipientKey' => $preference->recipientKey(),
            'topic' => $preference->topic(),
            'enabledChannels' => $preference->enabledChannels(),
            'disabledChannels' => $preference->disabledChannels(),
            'muted' => $preference->muted(),
            'mutedUntil' => $preference->mutedUntil()?->format(DATE_ATOM),
            'digestEnabled' => $preference->digestEnabled(),
            'created' => $created,
            'reactivatedDispatchPlans' => $reactivatedDispatchPlans,
            'suppressedDispatchPlans' => $suppressedDispatchPlans,
        ];
    }

    private static function pushSuppressionReason(NotificationPreferenceEntity $preference): ?string
    {
        $now = new \DateTimeImmutable();
        if ($preference->muted() && (null === $preference->mutedUntil() || $preference->mutedUntil() > $now)) {
            return 'recipient-muted';
        }

        $enabledChannels = $preference->enabledChannels();
        if ([] !== $enabledChannels && !in_array(NotificationChannel::Push->value, $enabledChannels, true)) {
            return 'channel-not-enabled';
        }

        if (in_array(NotificationChannel::Push->value, $preference->disabledChannels(), true)) {
            return 'channel-disabled';
        }

        return null;
    }

    /**
     * @param list<mixed> $values
     * @return list<NotificationChannel>
     */
    private static function channelsFromStrings(array $values): array
    {
        $channels = [];
        foreach ($values as $value) {
            $channel = NotificationChannel::tryFrom((string) $value);
            if ($channel instanceof NotificationChannel) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    private static function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
