<?php

declare(strict_types=1);

namespace App\Notifying\Migrations\Baseline;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BinaryType;
use Doctrine\DBAL\Types\GuidType;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815234500NotificationAdoption extends AbstractMigration
{
    private const TABLES = [
        'notifying_notification',
        'notifying_notification_recipient',
        'notifying_notification_dispatch_plan',
        'notifying_notification_preference',
        'notifying_notification_subscription',
    ];

    public function getDescription(): string
    {
        return 'Adopt or create the final Notifying notification-center schema for the shared PostgreSQL host.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform,
            'Notifying host adoption is PostgreSQL-only.',
        );

        $existing = array_values(array_filter(self::TABLES, static fn (string $table): bool => $schema->hasTable($table)));
        if ([] === $existing) {
            $this->createSchema($schema);

            return;
        }

        $this->abortIf(
            count($existing) !== count(self::TABLES),
            sprintf('Partial Notifying schema detected (%s); aborting instead of guessing production drift.', implode(', ', $existing)),
        );

        $this->adoptExistingSchema($schema);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing Notifying notification-center tables would destroy durable production notification state.');
    }

    private function createSchema(Schema $schema): void
    {
        $notification = $schema->createTable('notifying_notification');
        $notification->addColumn('id', Types::GUID);
        $notification->addColumn('source_component', Types::STRING, ['length' => 80]);
        $notification->addColumn('event_name', Types::STRING, ['length' => 120]);
        $notification->addColumn('topic', Types::STRING, ['length' => 120]);
        $notification->addColumn('title', Types::STRING, ['length' => 200]);
        $notification->addColumn('body', Types::TEXT);
        $notification->addColumn('priority', Types::STRING, ['length' => 255]);
        $notification->addColumn('status', Types::STRING, ['length' => 255]);
        $notification->addColumn('correlation_id', Types::STRING, ['length' => 190, 'notnull' => false]);
        $notification->addColumn('action_url', Types::STRING, ['length' => 500, 'notnull' => false]);
        $notification->addColumn('expires_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $notification->addColumn('payload', Types::JSON);
        $notification->addColumn('metadata', Types::JSON);
        $this->addIdentityColumns($notification);
        $this->addAuditColumns($notification);
        $this->addTitleColumns($notification);
        $notification->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $notification->addUniqueIndex(['object_uuid'], 'UNIQ_9E3FBB5F4C6A6CB5');
        $notification->addUniqueIndex(['object_slug'], 'UNIQ_9E3FBB5F588A771');
        $notification->addIndex(['source_component', 'event_name'], 'idx_notifying_notification_source_event');
        $notification->addIndex(['topic'], 'idx_notifying_notification_topic');
        $notification->addIndex(['status'], 'idx_notifying_notification_status');
        $notification->addIndex(['correlation_id'], 'idx_notifying_notification_correlation');

        $recipient = $schema->createTable('notifying_notification_recipient');
        $recipient->addColumn('id', Types::GUID);
        $recipient->addColumn('notification_id', Types::GUID);
        $recipient->addColumn('recipient_type', Types::STRING, ['length' => 255]);
        $recipient->addColumn('recipient_key', Types::STRING, ['length' => 160]);
        $recipient->addColumn('status', Types::STRING, ['length' => 255]);
        $recipient->addColumn('read_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $recipient->addColumn('acked_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $recipient->addColumn('snoozed_until', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $recipient->addColumn('muted_until', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $recipient->addColumn('archived_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $this->addIdentityColumns($recipient);
        $this->addAuditColumns($recipient);
        $recipient->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $recipient->addUniqueIndex(['object_uuid'], 'UNIQ_44AC95614C6A6CB5');
        $recipient->addUniqueIndex(['object_slug'], 'UNIQ_44AC9561588A771');
        $recipient->addIndex(['recipient_key', 'status', 'object_created_at'], 'idx_notifying_recipient_inbox');
        $recipient->addIndex(['notification_id'], 'idx_notifying_recipient_notification');
        $recipient->addIndex(['snoozed_until'], 'idx_notifying_recipient_snoozed');
        $recipient->addForeignKeyConstraint('notifying_notification', ['notification_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifying_recipient_notification');

        $dispatch = $schema->createTable('notifying_notification_dispatch_plan');
        $dispatch->addColumn('id', Types::GUID);
        $dispatch->addColumn('notification_id', Types::GUID);
        $dispatch->addColumn('recipient_entry_id', Types::GUID);
        $dispatch->addColumn('recipient_type', Types::STRING, ['length' => 255]);
        $dispatch->addColumn('recipient_key', Types::STRING, ['length' => 160]);
        $dispatch->addColumn('channel', Types::STRING, ['length' => 255]);
        $dispatch->addColumn('status', Types::STRING, ['length' => 255]);
        $dispatch->addColumn('reason', Types::STRING, ['length' => 255, 'notnull' => false]);
        $dispatch->addColumn('target', Types::STRING, ['length' => 500, 'notnull' => false]);
        $dispatch->addColumn('scheduled_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $dispatch->addColumn('claimed_by', Types::STRING, ['length' => 190, 'notnull' => false]);
        $dispatch->addColumn('claimed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $dispatch->addColumn('claim_expires_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $dispatch->addColumn('claim_lease_hash', Types::STRING, ['length' => 64, 'notnull' => false]);
        $dispatch->addColumn('handed_off_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $dispatch->addColumn('failed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $dispatch->addColumn('cancelled_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $dispatch->addColumn('payload', Types::JSON);
        $dispatch->addColumn('metadata', Types::JSON);
        $this->addIdentityColumns($dispatch);
        $this->addAuditColumns($dispatch);
        $this->addTitleColumns($dispatch);
        $dispatch->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $dispatch->addUniqueIndex(['object_uuid'], 'UNIQ_ED2327F94C6A6CB5');
        $dispatch->addUniqueIndex(['object_slug'], 'UNIQ_ED2327F9588A771');
        $dispatch->addUniqueIndex(['recipient_entry_id', 'channel'], 'uniq_notifying_dispatch_recipient_channel');
        $dispatch->addIndex(['recipient_key', 'status', 'object_created_at'], 'idx_notifying_dispatch_recipient_status');
        $dispatch->addIndex(['notification_id'], 'idx_notifying_dispatch_notification');
        $dispatch->addIndex(['recipient_entry_id'], 'idx_notifying_dispatch_recipient_entry');
        $dispatch->addIndex(['channel', 'status'], 'idx_notifying_dispatch_channel_status');
        $dispatch->addIndex(['status', 'claim_expires_at'], 'idx_notifying_dispatch_claim');
        $dispatch->addIndex(['scheduled_at'], 'idx_notifying_dispatch_scheduled');
        $dispatch->addForeignKeyConstraint('notifying_notification', ['notification_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifying_dispatch_notification');
        $dispatch->addForeignKeyConstraint('notifying_notification_recipient', ['recipient_entry_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifying_dispatch_recipient_entry');

        $preference = $schema->createTable('notifying_notification_preference');
        $preference->addColumn('id', Types::GUID);
        $preference->addColumn('recipient_type', Types::STRING, ['length' => 255]);
        $preference->addColumn('recipient_key', Types::STRING, ['length' => 160]);
        $preference->addColumn('topic', Types::STRING, ['length' => 120]);
        $preference->addColumn('enabled_channels', Types::JSON);
        $preference->addColumn('disabled_channels', Types::JSON);
        $preference->addColumn('muted', Types::BOOLEAN);
        $preference->addColumn('muted_until', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $preference->addColumn('quiet_hours_start', Types::STRING, ['length' => 5, 'notnull' => false]);
        $preference->addColumn('quiet_hours_end', Types::STRING, ['length' => 5, 'notnull' => false]);
        $preference->addColumn('timezone', Types::STRING, ['length' => 80, 'notnull' => false]);
        $preference->addColumn('digest_enabled', Types::BOOLEAN);
        $preference->addColumn('digest_frequency', Types::STRING, ['length' => 40, 'notnull' => false]);
        $preference->addColumn('policy', Types::JSON);
        $this->addIdentityColumns($preference);
        $this->addAuditColumns($preference);
        $this->addTitleColumns($preference);
        $preference->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $preference->addUniqueIndex(['object_uuid'], 'UNIQ_68F0B0C74C6A6CB5');
        $preference->addUniqueIndex(['object_slug'], 'UNIQ_68F0B0C7588A771');
        $preference->addUniqueIndex(['recipient_type', 'recipient_key', 'topic'], 'uniq_notifying_pref_recipient_topic');
        $preference->addIndex(['recipient_key'], 'idx_notifying_pref_recipient');

        $subscription = $schema->createTable('notifying_notification_subscription');
        $subscription->addColumn('id', Types::GUID);
        $subscription->addColumn('recipient_type', Types::STRING, ['length' => 255]);
        $subscription->addColumn('recipient_key', Types::STRING, ['length' => 160]);
        $subscription->addColumn('platform', Types::STRING, ['length' => 80]);
        $subscription->addColumn('app_key', Types::STRING, ['length' => 120]);
        $subscription->addColumn('device_id', Types::STRING, ['length' => 190]);
        $subscription->addColumn('token', Types::TEXT);
        $subscription->addColumn('token_hash', Types::STRING, ['length' => 64]);
        $subscription->addColumn('enabled', Types::BOOLEAN);
        $subscription->addColumn('last_seen_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('disabled_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('expires_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $subscription->addColumn('metadata', Types::JSON);
        $this->addIdentityColumns($subscription);
        $this->addAuditColumns($subscription);
        $this->addTitleColumns($subscription);
        $subscription->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create());
        $subscription->addUniqueIndex(['object_uuid'], 'UNIQ_B6EB1E544C6A6CB5');
        $subscription->addUniqueIndex(['object_slug'], 'UNIQ_B6EB1E54588A771');
        $subscription->addUniqueIndex(['token_hash'], 'uniq_notifying_subscription_token_hash');
        $subscription->addUniqueIndex(['recipient_type', 'recipient_key', 'app_key', 'platform', 'device_id'], 'uniq_notifying_subscription_device');
        $subscription->addIndex(['recipient_key', 'enabled'], 'idx_notifying_subscription_recipient');
    }

    private function adoptExistingSchema(Schema $schema): void
    {
        foreach (self::TABLES as $tableName) {
            $this->assertIdentityAndIdShape($schema->getTable($tableName));
        }

        $dispatch = $schema->getTable('notifying_notification_dispatch_plan');
        foreach ([
            'notification_id', 'recipient_entry_id', 'recipient_type', 'recipient_key', 'channel', 'status',
            'reason', 'target', 'scheduled_at', 'handed_off_at', 'failed_at', 'cancelled_at', 'payload', 'metadata',
        ] as $column) {
            $this->abortIf(!$dispatch->hasColumn($column), sprintf('Existing Notifying dispatch table is missing required column %s.', $column));
        }
        $this->ensureNullableColumn($dispatch, 'claimed_by', Types::STRING, 190);
        $this->ensureNullableColumn($dispatch, 'claimed_at', Types::DATETIME_IMMUTABLE);
        $this->ensureNullableColumn($dispatch, 'claim_expires_at', Types::DATETIME_IMMUTABLE);
        $this->ensureNullableColumn($dispatch, 'claim_lease_hash', Types::STRING, 64);
        $this->ensureIndex($dispatch, ['status', 'claim_expires_at'], 'idx_notifying_dispatch_claim');

        $subscription = $schema->getTable('notifying_notification_subscription');
        foreach (['recipient_type', 'recipient_key', 'platform', 'app_key', 'device_id', 'token', 'token_hash', 'enabled', 'metadata'] as $column) {
            $this->abortIf(!$subscription->hasColumn($column), sprintf('Existing Notifying subscription table is missing required column %s.', $column));
        }
        if (!$subscription->hasIndex('uniq_notifying_subscription_device')) {
            $duplicateCount = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM (SELECT 1 FROM notifying_notification_subscription GROUP BY recipient_type, recipient_key, app_key, platform, device_id HAVING COUNT(*) > 1) duplicate_devices',
            );
            $this->abortIf($duplicateCount > 0, 'Cannot adopt Notifying subscription device uniqueness: duplicate device identities exist.');
            if ($subscription->hasIndex('idx_notifying_subscription_device')) {
                $subscription->dropIndex('idx_notifying_subscription_device');
            }
            $subscription->addUniqueIndex(['recipient_type', 'recipient_key', 'app_key', 'platform', 'device_id'], 'uniq_notifying_subscription_device');
        }

        $this->ensureIndex($subscription, ['recipient_key', 'enabled'], 'idx_notifying_subscription_recipient');
        $this->ensureForeignKey($schema->getTable('notifying_notification_recipient'), 'notifying_notification', ['notification_id'], ['id'], 'fk_notifying_recipient_notification');
        $this->ensureForeignKey($dispatch, 'notifying_notification', ['notification_id'], ['id'], 'fk_notifying_dispatch_notification');
        $this->ensureForeignKey($dispatch, 'notifying_notification_recipient', ['recipient_entry_id'], ['id'], 'fk_notifying_dispatch_recipient_entry');
    }

    private function assertIdentityAndIdShape(Table $table): void
    {
        foreach (['id', 'object_uuid', 'object_slug', 'object_created_at', 'object_modified_at', 'object_created_by', 'object_modified_by'] as $column) {
            $this->abortIf(!$table->hasColumn($column), sprintf('Existing table %s is missing required Objecting column %s.', $table->getName(), $column));
        }
        $this->abortIf(!$table->getColumn('id')->getType() instanceof GuidType, sprintf('Existing table %s id must be GUID/UUID.', $table->getName()));
        $this->abortIf(!$table->getColumn('object_uuid')->getType() instanceof BinaryType || 16 !== $table->getColumn('object_uuid')->getLength(), sprintf('Existing table %s object_uuid must be fixed binary(16).', $table->getName()));
        $this->abortIf(!$table->getColumn('object_slug')->getType() instanceof StringType || 190 !== $table->getColumn('object_slug')->getLength(), sprintf('Existing table %s object_slug must be varchar(190).', $table->getName()));
    }

    private function ensureNullableColumn(Table $table, string $name, string $type, ?int $length = null): void
    {
        if ($table->hasColumn($name)) {
            $this->abortIf($table->getColumn($name)->getNotnull(), sprintf('Existing column %s.%s must be nullable.', $table->getName(), $name));

            return;
        }
        $options = ['notnull' => false];
        if (null !== $length) {
            $options['length'] = $length;
        }
        $table->addColumn($name, $type, $options);
    }

    /** @param list<string> $columns */
    private function ensureIndex(Table $table, array $columns, string $name): void
    {
        if (!$table->hasIndex($name)) {
            $table->addIndex($columns, $name);
        }
    }

    /** @param list<string> $localColumns @param list<string> $foreignColumns */
    private function ensureForeignKey(Table $table, string $foreignTable, array $localColumns, array $foreignColumns, string $name): void
    {
        if (!$table->hasForeignKey($name)) {
            $table->addForeignKeyConstraint($foreignTable, $localColumns, $foreignColumns, ['onDelete' => 'CASCADE'], $name);
        }
    }

    private function addIdentityColumns(Table $table): void
    {
        $table->addColumn('object_uuid', Types::BINARY, ['length' => 16, 'fixed' => true]);
        $table->addColumn('object_slug', Types::STRING, ['length' => 190]);
    }

    private function addAuditColumns(Table $table): void
    {
        $table->addColumn('object_created_at', Types::DATETIME_IMMUTABLE);
        $table->addColumn('object_modified_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $table->addColumn('object_created_by', Types::STRING, ['length' => 190, 'notnull' => false]);
        $table->addColumn('object_modified_by', Types::STRING, ['length' => 190, 'notnull' => false]);
    }

    private function addTitleColumns(Table $table): void
    {
        $table->addColumn('object_first_title', Types::STRING, ['length' => 255, 'notnull' => false]);
        $table->addColumn('object_middle_title', Types::TEXT, ['notnull' => false]);
        $table->addColumn('object_last_title', Types::TEXT, ['notnull' => false]);
    }
}