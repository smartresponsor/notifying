<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

final class NotificationRecipientAccessService
{
    public function __construct(
        private readonly Security $security,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function requireRecipientKey(Request $request, ?string $requestedRecipientKey = null): string
    {
        $recipientKey = $this->resolveRecipientKey($request);
        $requestedRecipientKey = trim((string) $requestedRecipientKey);

        if ('' !== $requestedRecipientKey && $requestedRecipientKey !== $recipientKey) {
            throw new AccessDeniedHttpException('Notification recipient ownership mismatch.');
        }

        return $recipientKey;
    }

    private function resolveRecipientKey(Request $request): string
    {
        $hostRecipientKey = trim((string) $request->attributes->get('notifying_recipient_key', ''));
        if ('' !== $hostRecipientKey) {
            return $hostRecipientKey;
        }

        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            $identifier = trim($user->getUserIdentifier());
            if ('' !== $identifier) {
                return $identifier;
            }
        }

        if (in_array($this->environment, ['dev', 'test'], true)) {
            $developmentRecipientKey = trim((string) $request->headers->get('X-Notifying-Recipient-Key', ''));
            if ('' !== $developmentRecipientKey) {
                return $developmentRecipientKey;
            }
        }

        throw new AccessDeniedHttpException('Authenticated notification recipient is required.');
    }
}
