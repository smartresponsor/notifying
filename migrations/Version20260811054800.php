<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260811054800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create Notifying notification, inbox recipient, preference, and subscription product schema.';
    }

    public function up(Schema $schema): void
    {
        $notification = $schema->createTable('notifying_notification');
        $notification->addColumn('id', 'guid');
        $notification->addColumn('source_component', 'string', ['length' => 80]);
        $notification->addColumn('event_name', 'string', ['length' => 120]);
        $notification->addColumn('topic', 'string', ['length' => 120]);
        $notification->addColumn('title', 'string', ['length' => 200]);
        $notification->addColumn('body', 'text');
        $notification->addColumn('priority', 'string', ['length' => 255]);
        $notification->addColumn('status', 'string', ['length' => 255]);
        $notification->addColumn('correlation_id', 'string', ['length' => 190, 'notnull' => false]);
        $notification->addColumn('action_url', 'string', ['length' => 500, 'notnull' => false]);
        $notification->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $notification->addColumn('payload', 'json');
        $notification->addColumn('metadata', 'json');
        $this->addIdentityColumns($notification);
        $this->addAuditColumns($notification);
        $this->addTitleColumns($notification);
        $notification->setPrimaryKey(['id']);
        $notification->addUniqueIndex(['object_uuid'], 'UNIQ_9E3FBB5F4C6A6CB5');
        $notification->addUniqueIndex(['object_slug'], 'UNIQ_9E3FBB5F588A771');
        $notification->addIndex(['source_component', 'event_name'], 'idx_notifying_notification_source_event');
        $notification->addIndex(['topic'], 'idx_notifying_notification_topic');
        $notification->addIndex(['status'], 'idx_notifying_notification_status');
        $notification->addIndex(['correlation_id'], 'idx_notifying_notification_correlation');

        $recipient = $schema->createTable('notifying_notification_recipient');
        $recipient->addColumn('id', 'guid');
        $recipient->addColumn('notification_id', 'guid');
        $recipient->addColumn('recipient_type', 'string', ['length' => 255]);
        $recipient->addColumn('recipient_key', 'string', ['length' => 160]);
        $recipient->addColumn('status', 'string', ['length' => 255]);
        $recipient->addColumn('read_at', 'datetime_immutable', ['notnull' => false]);
        $recipient->addColumn('acked_at', 'datetime_immutable', ['notnull' => false]);
        $recipient->addColumn('snoozed_until', 'datetime_immutable', ['notnull' => false]);
        $recipient->addColumn('muted_until', 'datetime_immutable', ['notnull' => false]);
        $recipient->addColumn('archived_at', 'datetime_immutable', ['notnull' => false]);
        $this->addIdentityColumns($recipient);
        $this->addAuditColumns($recipient);
        $recipient->setPrimaryKey(['id']);
        $recipient->addUniqueIndex(['object_uuid'], 'UNIQ_44AC95614C6A6CB5');
        $recipient->addUniqueIndex(['object_slug'], 'UNIQ_44AC9561588A771');
        $recipient->addIndex(['recipient_key', 'status', 'object_created_at'], 'idx_notifying_recipient_inbox');
        $recipient->addIndex(['notification_id'], 'idx_notifying_recipient_notification');
        $recipient->addIndex(['snoozed_until'], 'idx_notifying_recipient_snoozed');
        $recipient->addForeignKeyConstraint('notifying_notification', ['notification_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifying_recipient_notification');

        $dispatchPlan = $schema->createTable('notifying_notification_dispatch_plan');
        $dispatchPlan->addColumn('id', 'guid');
        $dispatchPlan->addColumn('notification_id', 'guid');
        $dispatchPlan->addColumn('recipient_entry_id', 'guid');
        $dispatchPlan->addColumn('recipient_type', 'string', ['length' => 255]);
        $dispatchPlan->addColumn('recipient_key', 'string', ['length' => 160]);
        $dispatchPlan->addColumn('channel', 'string', ['length' => 255]);
        $dispatchPlan->addColumn('status', 'string', ['length' => 255]);
        $dispatchPlan->addColumn('reason', 'string', ['length' => 255, 'notnull' => false]);
        $dispatchPlan->addColumn('target', 'string', ['length' => 500, 'notnull' => false]);
        $dispatchPlan->addColumn('scheduled_at', 'datetime_immutable', ['notnull' => false]);
        $dispatchPlan->addColumn('handed_off_at', 'datetime_immutable', ['notnull' => false]);
        $dispatchPlan->addColumn('failed_at', 'datetime_immutable', ['notnull' => false]);
        $dispatchPlan->addColumn('cancelled_at', 'datetime_immutable', ['notnull' => false]);
        $dispatchPlan->addColumn('payload', 'json');
        $dispatchPlan->addColumn('metadata', 'json');
        $this->addIdentityColumns($dispatchPlan);
        $this->addAuditColumns($dispatchPlan);
        $this->addTitleColumns($dispatchPlan);
        $dispatchPlan->setPrimaryKey(['id']);
        $dispatchPlan->addUniqueIndex(['object_uuid'], 'UNIQ_ED2327F94C6A6CB5');
        $dispatchPlan->addUniqueIndex(['object_slug'], 'UNIQ_ED2327F9588A771');
        $dispatchPlan->addUniqueIndex(['recipient_entry_id', 'channel'], 'uniq_notifying_dispatch_recipient_channel');
        $dispatchPlan->addIndex(['recipient_key', 'status', 'object_created_at'], 'idx_notifying_dispatch_recipient_status');
        $dispatchPlan->addIndex(['notification_id'], 'idx_notifying_dispatch_notification');
        $dispatchPlan->addIndex(['recipient_entry_id'], 'idx_notifying_dispatch_recipient_entry');
        $dispatchPlan->addIndex(['channel', 'status'], 'idx_notifying_dispatch_channel_status');
        $dispatchPlan->addIndex(['scheduled_at'], 'idx_notifying_dispatch_scheduled');
        $dispatchPlan->addForeignKeyConstraint('notifying_notification', ['notification_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifying_dispatch_notification');
        $dispatchPlan->addForeignKeyConstraint('notifying_notification_recipient', ['recipient_entry_id'], ['id'], ['onDelete' => 'CASCADE'], 'fk_notifying_dispatch_recipient_entry');

        $preference = $schema->createTable('notifying_notification_preference');
        $preference->addColumn('id', 'guid');
        $preference->addColumn('recipient_type', 'string', ['length' => 255]);
        $preference->addColumn('recipient_key', 'string', ['length' => 160]);
        $preference->addColumn('topic', 'string', ['length' => 120]);
        $preference->addColumn('enabled_channels', 'json');
        $preference->addColumn('disabled_channels', 'json');
        $preference->addColumn('muted', 'boolean');
        $preference->addColumn('muted_until', 'datetime_immutable', ['notnull' => false]);
        $preference->addColumn('quiet_hours_start', 'string', ['length' => 5, 'notnull' => false]);
        $preference->addColumn('quiet_hours_end', 'string', ['length' => 5, 'notnull' => false]);
        $preference->addColumn('timezone', 'string', ['length' => 80, 'notnull' => false]);
        $preference->addColumn('digest_enabled', 'boolean');
        $preference->addColumn('digest_frequency', 'string', ['length' => 40, 'notnull' => false]);
        $preference->addColumn('policy', 'json');
        $this->addIdentityColumns($preference);
        $this->addAuditColumns($preference);
        $this->addTitleColumns($preference);
        $preference->setPrimaryKey(['id']);
        $preference->addUniqueIndex(['object_uuid'], 'UNIQ_68F0B0C74C6A6CB5');
        $preference->addUniqueIndex(['object_slug'], 'UNIQ_68F0B0C7588A771');
        $preference->addUniqueIndex(['recipient_type', 'recipient_key', 'topic'], 'uniq_notifying_pref_recipient_topic');
        $preference->addIndex(['recipient_key'], 'idx_notifying_pref_recipient');

        $subscription = $schema->createTable('notifying_notification_subscription');
        $subscription->addColumn('id', 'guid');
        $subscription->addColumn('recipient_type', 'string', ['length' => 255]);
        $subscription->addColumn('recipient_key', 'string', ['length' => 160]);
        $subscription->addColumn('platform', 'string', ['length' => 80]);
        $subscription->addColumn('app_key', 'string', ['length' => 120]);
        $subscription->addColumn('device_id', 'string', ['length' => 190]);
        $subscription->addColumn('token', 'text');
        $subscription->addColumn('token_hash', 'string', ['length' => 64]);
        $subscription->addColumn('enabled', 'boolean');
        $subscription->addColumn('last_seen_at', 'datetime_immutable', ['notnull' => false]);
        $subscription->addColumn('disabled_at', 'datetime_immutable', ['notnull' => false]);
        $subscription->addColumn('expires_at', 'datetime_immutable', ['notnull' => false]);
        $subscription->addColumn('metadata', 'json');
        $this->addIdentityColumns($subscription);
        $this->addAuditColumns($subscription);
        $this->addTitleColumns($subscription);
        $subscription->setPrimaryKey(['id']);
        $subscription->addUniqueIndex(['object_uuid'], 'UNIQ_B6EB1E544C6A6CB5');
        $subscription->addUniqueIndex(['object_slug'], 'UNIQ_B6EB1E54588A771');
        $subscription->addUniqueIndex(['token_hash'], 'uniq_notifying_subscription_token_hash');
        $subscription->addIndex(['recipient_key', 'enabled'], 'idx_notifying_subscription_recipient');
        $subscription->addIndex(['app_key', 'platform', 'device_id'], 'idx_notifying_subscription_device');
    }

    public function down(Schema $schema): void
    {
        foreach (['notifying_notification_subscription', 'notifying_notification_preference', 'notifying_notification_dispatch_plan', 'notifying_notification_recipient', 'notifying_notification'] as $tableName) {
            if ($schema->hasTable($tableName)) {
                $schema->dropTable($tableName);
            }
        }
    }

    private function addIdentityColumns(\Doctrine\DBAL\Schema\Table $table): void
    {
        $table->addColumn('object_uuid', 'binary', ['length' => 16, 'fixed' => true]);
        $table->addColumn('object_slug', 'string', ['length' => 190]);
    }

    private function addAuditColumns(\Doctrine\DBAL\Schema\Table $table): void
    {
        $table->addColumn('object_created_at', 'datetime_immutable');
        $table->addColumn('object_modified_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('object_created_by', 'string', ['length' => 190, 'notnull' => false]);
        $table->addColumn('object_modified_by', 'string', ['length' => 190, 'notnull' => false]);
    }

    private function addTitleColumns(\Doctrine\DBAL\Schema\Table $table): void
    {
        $table->addColumn('object_first_title', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('object_middle_title', 'text', ['notnull' => false]);
        $table->addColumn('object_last_title', 'text', ['notnull' => false]);
    }
}
