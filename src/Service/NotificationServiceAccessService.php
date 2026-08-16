<?php

declare(strict_types=1);

namespace App\Notifying\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class NotificationServiceAccessService
{
    public const string SCOPE_INTENT_WRITE = 'intent:write';
    public const string SCOPE_DISPATCH_CONSUME = 'dispatch:consume';

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function requireService(Request $request, string $requiredScope, ?string $requestedServiceKey = null): string
    {
        [$serviceKey, $scopes] = $this->resolveServiceContext($request);
        $requestedServiceKey = trim((string) $requestedServiceKey);

        if ('' !== $requestedServiceKey && $requestedServiceKey !== $serviceKey) {
            throw new AccessDeniedHttpException('Notification service identity mismatch.');
        }
        if (!in_array($requiredScope, $scopes, true)) {
            throw new AccessDeniedHttpException('Notification service scope is not authorized.');
        }

        return $serviceKey;
    }

    /** @return array{0: string, 1: list<string>} */
    private function resolveServiceContext(Request $request): array
    {
        $hostServiceKey = trim((string) $request->attributes->get('notifying_service_key', ''));
        if ('' !== $hostServiceKey) {
            return [$this->validateServiceKey($hostServiceKey), $this->normalizeScopes(
                $request->attributes->get('notifying_service_scopes', $request->attributes->get('notifying_service_scope', [])),
            )];
        }

        if (in_array($this->environment, ['dev', 'test'], true)) {
            $developmentServiceKey = trim((string) $request->headers->get('X-Notifying-Service-Key', ''));
            if ('' !== $developmentServiceKey) {
                return [$this->validateServiceKey($developmentServiceKey), $this->normalizeScopes(
                    (string) $request->headers->get('X-Notifying-Service-Scopes', ''),
                )];
            }
        }

        throw new AccessDeniedHttpException('Authorized notification service is required.');
    }

    private function validateServiceKey(string $serviceKey): string
    {
        if (strlen($serviceKey) > 190) {
            throw new AccessDeniedHttpException('Notification service identity is invalid.');
        }

        return $serviceKey;
    }

    /** @return list<string> */
    private function normalizeScopes(mixed $value): array
    {
        $values = is_array($value) ? $value : (preg_split('/[\s,]+/', trim((string) $value)) ?: []);
        $scopes = [];
        foreach ($values as $scope) {
            $scope = trim((string) $scope);
            if ('' !== $scope) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes));
    }
}
