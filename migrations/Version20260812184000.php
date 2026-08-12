<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812184000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce one Notifying push subscription per recipient app platform device identity.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('notifying_notification_subscription');
        if ($table->hasIndex('idx_notifying_subscription_device')) {
            $table->dropIndex('idx_notifying_subscription_device');
        }
        if (!$table->hasIndex('uniq_notifying_subscription_device')) {
            $table->addUniqueIndex(
                ['recipient_type', 'recipient_key', 'app_key', 'platform', 'device_id'],
                'uniq_notifying_subscription_device',
            );
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('notifying_notification_subscription');
        if ($table->hasIndex('uniq_notifying_subscription_device')) {
            $table->dropIndex('uniq_notifying_subscription_device');
        }
        if (!$table->hasIndex('idx_notifying_subscription_device')) {
            $table->addIndex(['app_key', 'platform', 'device_id'], 'idx_notifying_subscription_device');
        }
    }
}
