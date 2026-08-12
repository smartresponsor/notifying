<?php

declare(strict_types=1);

return [
    'scope' => ['notifying'],
    'entity' => ['notification', 'notification-recipient', 'notification-dispatch-plan', 'notification-preference', 'notification-subscription'],
    'view_token' => ['card', 'table', 'list', 'detail'],
    'reserved' => ['api', 'admin'],
];
