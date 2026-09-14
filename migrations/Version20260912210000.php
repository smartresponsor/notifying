<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\Migrations\AbstractMigration;

final class Version20260912210000 extends AbstractMigration
{
    private const array TABLES = [
        'notifying_notification',
        'notifying_notification_recipient',
        'notifying_notification_dispatch_plan',
        'notifying_notification_preference',
        'notifying_notification_subscription',
    ];

    private const array BASE_RENAMES = [
        'object_uuid' => 'uuid',
        'object_slug' => 'slug',
        'object_created_at' => 'created_at',
        'object_modified_at' => 'modified_at',
        'object_created_by' => 'created_by',
        'object_modified_by' => 'modified_by',
    ];

    private const array TITLE_RENAMES = [
        'object_first_title' => 'first_title',
        'object_middle_title' => 'middle_title',
        'object_last_title' => 'last_title',
    ];

    public function getDescription(): string
    {
        return 'Converge Notifying storage on canonical Objecting column names and remove duplicate notification title storage.';
    }

    public function up(Schema $schema): void
    {
        $notificationTitleTarget = null;
        if ($schema->hasTable('notifying_notification')) {
            $notificationBeforeConvergence = $schema->getTable('notifying_notification');
            if ($notificationBeforeConvergence->hasColumn('title')) {
                $notificationTitleTarget = $notificationBeforeConvergence->hasColumn('object_first_title')
                    ? 'object_first_title'
                    : 'first_title';
            }
        }

        foreach (self::TABLES as $tableName) {
            if (!$schema->hasTable($tableName)) {
                continue;
            }

            $table = $schema->getTable($tableName);
            $this->renameColumns($table, self::BASE_RENAMES);
            if ('notifying_notification_recipient' !== $tableName) {
                $this->renameColumns($table, self::TITLE_RENAMES);
            }

            $this->normalizeIdentityIndexNames($table);
        }

        if (!$schema->hasTable('notifying_notification')) {
            return;
        }

        $notification = $schema->getTable('notifying_notification');
        if ($notification->hasColumn('title') && null !== $notificationTitleTarget) {
            $this->addSql(sprintf(
                'UPDATE notifying_notification SET %1$s = title WHERE (%1$s IS NULL OR %1$s = \'\') AND title IS NOT NULL',
                $notificationTitleTarget,
            ));
            $notification->dropColumn('title');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restoring duplicate legacy Objecting/title storage would reintroduce the schema ambiguity removed by this convergence migration.');
    }

    /** @param array<string, string> $renames */
    private function renameColumns(Table $table, array $renames): void
    {
        foreach ($renames as $from => $to) {
            if (!$table->hasColumn($from)) {
                continue;
            }

            $this->abortIf(
                $table->hasColumn($to),
                sprintf('Cannot converge %s: both legacy column %s and canonical Objecting column %s exist.', $table->getName(), $from, $to),
            );
            $table->renameColumn($from, $to);
        }
    }

    private function normalizeIdentityIndexNames(Table $table): void
    {
        foreach ($table->getIndexes() as $index) {
            if (!$index->isUnique() || $index->isPrimary()) {
                continue;
            }

            $columns = $index->getColumns();
            if (['uuid'] !== $columns && ['slug'] !== $columns) {
                continue;
            }

            $table->renameIndex($index->getName());
        }
    }
}
