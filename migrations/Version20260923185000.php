<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923185000 extends AbstractMigration
{
    private const array IDENTITY_INDEX_NAMES = [
        'notifying_notification' => [
            'uuid' => 'uniq_notifying_notification_uuid',
            'slug' => 'uniq_notifying_notification_slug',
        ],
        'notifying_notification_recipient' => [
            'uuid' => 'uniq_notifying_notification_recipient_uuid',
            'slug' => 'uniq_notifying_notification_recipient_slug',
        ],
        'notifying_notification_dispatch_plan' => [
            'uuid' => 'uniq_notifying_notification_dispatch_plan_uuid',
            'slug' => 'uniq_notifying_notification_dispatch_plan_slug',
        ],
        'notifying_notification_preference' => [
            'uuid' => 'uniq_notifying_notification_preference_uuid',
            'slug' => 'uniq_notifying_notification_preference_slug',
        ],
        'notifying_notification_subscription' => [
            'uuid' => 'uniq_notifying_notification_subscription_uuid',
            'slug' => 'uniq_notifying_notification_subscription_slug',
        ],
    ];

    public function getDescription(): string
    {
        return 'Rename Objecting identity unique indexes to deterministic semantic names.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::IDENTITY_INDEX_NAMES as $tableName => $identityIndexes) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }

            $table = $schema->getTable($tableName);
            foreach ($identityIndexes as $column => $canonicalName) {
                $this->renameUniqueColumnIndex($table, $column, $canonicalName);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Legacy hash-derived identity index names are intentionally not restored.');
    }

    private function renameUniqueColumnIndex(Table $table, string $column, string $canonicalName): void
    {
        if ($table->hasIndex($canonicalName)) {
            return;
        }

        foreach ($table->getIndexes() as $index) {
            if ($index->isPrimary() || !$index->isUnique() || [$column] !== $index->getColumns()) {
                continue;
            }

            $table->renameIndex($index->getName(), $canonicalName);

            return;
        }

        $this->abortIf(
            true,
            sprintf('Cannot normalize %s.%s: no unique identity index was found.', $table->getName(), $column),
        );
    }
}
