<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use App\Notifying\Entity\NotificationPreferenceEntity;
use App\Notifying\Enum\NotificationChannel;
use App\Notifying\Enum\NotificationRecipientType;
use App\Notifying\Repository\NotificationPreferenceRepository;

final class NotificationPreferenceService
{
    public function __construct(
        private readonly NotificationPreferenceRepository $preferenceRepository,
        private readonly NotificationDispatchPlanService $dispatchPlanService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function upsertPreference(array $payload, ?string $modifiedBy = null): array
    {
        [$preference, $created] = $this->resolvePreference($payload, $modifiedBy);
        $this->applyPreferencePayload($preference, $payload, $modifiedBy);
        $this->preferenceRepository->flush();
        $dispatchPlans = $this->reconcilePushDispatchPlans($preference, $modifiedBy);

        return array_merge([
            'id' => $preference->getObjectUuid(),
            'recipientType' => $preference->recipientType()->value,
            'recipientKey' => $preference->recipientKey(),
            'topic' => $preference->topic(),
            'enabledChannels' => $preference->enabledChannels(),
            'disabledChannels' => $preference->disabledChannels(),
            'muted' => $preference->muted(),
            'mutedUntil' => $preference->mutedUntil()?->format(DATE_ATOM),
            'quietHoursStart' => $preference->quietHoursStart(),
            'quietHoursEnd' => $preference->quietHoursEnd(),
            'timezone' => $preference->timezone(),
            'digestEnabled' => $preference->digestEnabled(),
            'created' => $created,
        ], $dispatchPlans);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: NotificationPreferenceEntity, 1: bool}
     */
    private function resolvePreference(array $payload, ?string $modifiedBy): array
    {
        $recipientType = NotificationRecipientType::tryFrom((string) ($payload['recipientType'] ?? 'user'));
        if (!$recipientType instanceof NotificationRecipientType) {
            throw new \InvalidArgumentException('recipientType is invalid.');
        }

        $recipientKey = trim((string) ($payload['recipientKey'] ?? ''));
        $topic = trim((string) ($payload['topic'] ?? 'default'));
        if ('' === $recipientKey) {
            throw new \InvalidArgumentException('recipientKey is required.');
        }
        if ('' === $topic) {
            throw new \InvalidArgumentException('topic is required.');
        }

        $preference = $this->preferenceRepository->findForTopic($recipientType, $recipientKey, $topic);
        if ($preference instanceof NotificationPreferenceEntity) {
            return [$preference, false];
        }

        $preference = new NotificationPreferenceEntity(self::newUuid(), $recipientType, $recipientKey, $topic, $modifiedBy);
        $this->preferenceRepository->persist($preference);

        return [$preference, true];
    }

    /** @param array<string, mixed> $payload */
    private function applyPreferencePayload(NotificationPreferenceEntity $preference, array $payload, ?string $modifiedBy): void
    {
        if (isset($payload['enabledChannels']) && is_array($payload['enabledChannels'])) {
            $preference->setEnabledChannels(self::channelsFromStrings($payload['enabledChannels']), $modifiedBy);
        }
        if (isset($payload['disabledChannels']) && is_array($payload['disabledChannels'])) {
            $preference->setDisabledChannels(self::channelsFromStrings($payload['disabledChannels']), $modifiedBy);
        }

        self::applyMutePayload($preference, $payload, $modifiedBy);

        if (array_key_exists('digestEnabled', $payload)) {
            $preference->setDigest(
                self::strictBoolean($payload['digestEnabled'], 'digestEnabled'),
                isset($payload['digestFrequency']) ? (string) $payload['digestFrequency'] : null,
                $modifiedBy,
            );
        }
        if (isset($payload['policy']) && is_array($payload['policy'])) {
            $preference->setPolicy($payload['policy'], $modifiedBy);
        }
        if (array_key_exists('quietHoursStart', $payload) || array_key_exists('quietHoursEnd', $payload) || array_key_exists('timezone', $payload)) {
            [$quietHoursStart, $quietHoursEnd, $timezone] = self::quietHoursFromPayload($payload);
            $preference->setQuietHours($quietHoursStart, $quietHoursEnd, $timezone, $modifiedBy);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function applyMutePayload(NotificationPreferenceEntity $preference, array $payload, ?string $modifiedBy): void
    {
        if (!array_key_exists('muted', $payload)) {
            if (array_key_exists('mutedUntil', $payload)) {
                throw new \InvalidArgumentException('mutedUntil requires muted to be provided.');
            }

            return;
        }

        if (self::strictBoolean($payload['muted'], 'muted')) {
            $mutedUntil = null;
            if (array_key_exists('mutedUntil', $payload) && null !== $payload['mutedUntil'] && '' !== $payload['mutedUntil']) {
                $mutedUntil = self::dateTimeFromPayload($payload['mutedUntil'], 'mutedUntil');
            }
            $preference->mute($mutedUntil, $modifiedBy);

            return;
        }

        if (array_key_exists('mutedUntil', $payload) && null !== $payload['mutedUntil'] && '' !== $payload['mutedUntil']) {
            throw new \InvalidArgumentException('mutedUntil requires muted=true.');
        }
        $preference->unmute($modifiedBy);
    }

    /**
     * @return array{
     *     reactivatedDispatchPlans: array<mixed>,
     *     suppressedDispatchPlans: array<mixed>,
     *     rescheduledDispatchPlans: array<mixed>
     * }
     */
    private function reconcilePushDispatchPlans(NotificationPreferenceEntity $preference, ?string $modifiedBy): array
    {
        $pushSuppressionReason = self::pushSuppressionReason($preference);
        if (null !== $pushSuppressionReason) {
            return [
                'reactivatedDispatchPlans' => [],
                'suppressedDispatchPlans' => $this->dispatchPlanService->suppressPushForPreference(
                    recipientType: $preference->recipientType(),
                    recipientKey: $preference->recipientKey(),
                    topic: $preference->topic(),
                    reason: $pushSuppressionReason,
                    modifiedBy: $modifiedBy,
                ),
                'rescheduledDispatchPlans' => [],
            ];
        }

        return [
            'reactivatedDispatchPlans' => $this->dispatchPlanService->reactivatePushForPreference(
                recipientType: $preference->recipientType(),
                recipientKey: $preference->recipientKey(),
                topic: $preference->topic(),
                modifiedBy: $modifiedBy,
            ),
            'suppressedDispatchPlans' => [],
            'rescheduledDispatchPlans' => $this->dispatchPlanService->reschedulePushForPreference(
                recipientType: $preference->recipientType(),
                recipientKey: $preference->recipientKey(),
                topic: $preference->topic(),
                scheduledAt: $preference->pushDeferredUntil(new \DateTimeImmutable()),
                modifiedBy: $modifiedBy,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: ?string, 1: ?string, 2: ?string}
     */
    private static function quietHoursFromPayload(array $payload): array
    {
        foreach (['quietHoursStart', 'quietHoursEnd', 'timezone'] as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new \InvalidArgumentException('quietHoursStart, quietHoursEnd, and timezone must be provided together.');
            }
        }

        $start = trim((string) ($payload['quietHoursStart'] ?? ''));
        $end = trim((string) ($payload['quietHoursEnd'] ?? ''));
        $timezone = trim((string) ($payload['timezone'] ?? ''));
        if ('' === $start && '' === $end && '' === $timezone) {
            return [null, null, null];
        }
        if ('' === $start || '' === $end || '' === $timezone) {
            throw new \InvalidArgumentException('quietHoursStart, quietHoursEnd, and timezone must either all be set or all be cleared.');
        }

        foreach (['quietHoursStart' => $start, 'quietHoursEnd' => $end] as $field => $value) {
            if (1 !== preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/', $value)) {
                throw new \InvalidArgumentException(sprintf('%s must use HH:MM 24-hour format.', $field));
            }
        }
        if ($start === $end) {
            throw new \InvalidArgumentException('quietHoursStart and quietHoursEnd must differ.');
        }
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw new \InvalidArgumentException('timezone must be a valid IANA timezone.');
        }

        return [$start, $end, $timezone];
    }

    private static function pushSuppressionReason(NotificationPreferenceEntity $preference): ?string
    {
        return $preference->pushSuppressionReason();
    }

    private static function strictBoolean(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (0 === $value || 1 === $value || '0' === $value || '1' === $value) {
            return (bool) (int) $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ('true' === $normalized) {
                return true;
            }
            if ('false' === $normalized) {
                return false;
            }
        }

        throw new \InvalidArgumentException(sprintf('%s must be a boolean value.', $field));
    }

    private static function dateTimeFromPayload(mixed $value, string $field): \DateTimeImmutable
    {
        if (!is_string($value) || '' === trim($value)) {
            throw new \InvalidArgumentException(sprintf('%s must be an ISO-8601 date-time string.', $field));
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new \InvalidArgumentException(sprintf('%s must be an ISO-8601 date-time string.', $field));
        }
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
            if (!$channel instanceof NotificationChannel) {
                throw new \InvalidArgumentException(sprintf('Unknown notification channel: %s.', (string) $value));
            }
            $channels[] = $channel;
        }

        return array_values(array_unique($channels, SORT_REGULAR));
    }

    private static function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
