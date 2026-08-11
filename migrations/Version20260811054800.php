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
        $this->addSql('CREATE TABLE notifying_notification (id UUID NOT NULL, source_component VARCHAR(80) NOT NULL, event_name VARCHAR(120) NOT NULL, topic VARCHAR(120) NOT NULL, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, priority VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, correlation_id VARCHAR(190) DEFAULT NULL, action_url VARCHAR(500) DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, payload JSON NOT NULL, metadata JSON NOT NULL, object_uuid BYTEA NOT NULL, object_slug VARCHAR(190) NOT NULL, object_created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, object_created_by VARCHAR(190) DEFAULT NULL, object_modified_by VARCHAR(190) DEFAULT NULL, object_first_title VARCHAR(255) DEFAULT NULL, object_middle_title TEXT DEFAULT NULL, object_last_title TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_notification_object_uuid ON notifying_notification (object_uuid)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_notification_object_slug ON notifying_notification (object_slug)');
        $this->addSql('CREATE INDEX idx_notifying_notification_source_event ON notifying_notification (source_component, event_name)');
        $this->addSql('CREATE INDEX idx_notifying_notification_topic ON notifying_notification (topic)');
        $this->addSql('CREATE INDEX idx_notifying_notification_status ON notifying_notification (status)');
        $this->addSql('CREATE INDEX idx_notifying_notification_correlation ON notifying_notification (correlation_id)');

        $this->addSql('CREATE TABLE notifying_notification_recipient (id UUID NOT NULL, notification_id UUID NOT NULL, recipient_type VARCHAR(255) NOT NULL, recipient_key VARCHAR(160) NOT NULL, status VARCHAR(255) NOT NULL, read_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, acked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, snoozed_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, muted_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, object_uuid BYTEA NOT NULL, object_slug VARCHAR(190) NOT NULL, object_created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, object_created_by VARCHAR(190) DEFAULT NULL, object_modified_by VARCHAR(190) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_recipient_object_uuid ON notifying_notification_recipient (object_uuid)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_recipient_object_slug ON notifying_notification_recipient (object_slug)');
        $this->addSql('CREATE INDEX idx_notifying_recipient_inbox ON notifying_notification_recipient (recipient_key, status, object_created_at)');
        $this->addSql('CREATE INDEX idx_notifying_recipient_notification ON notifying_notification_recipient (notification_id)');
        $this->addSql('CREATE INDEX idx_notifying_recipient_snoozed ON notifying_notification_recipient (snoozed_until)');
        $this->addSql('ALTER TABLE notifying_notification_recipient ADD CONSTRAINT fk_notifying_recipient_notification FOREIGN KEY (notification_id) REFERENCES notifying_notification (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('CREATE TABLE notifying_notification_preference (id UUID NOT NULL, recipient_type VARCHAR(255) NOT NULL, recipient_key VARCHAR(160) NOT NULL, topic VARCHAR(120) NOT NULL, enabled_channels JSON NOT NULL, disabled_channels JSON NOT NULL, muted BOOLEAN NOT NULL, muted_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, quiet_hours_start VARCHAR(5) DEFAULT NULL, quiet_hours_end VARCHAR(5) DEFAULT NULL, timezone VARCHAR(80) DEFAULT NULL, digest_enabled BOOLEAN NOT NULL, digest_frequency VARCHAR(40) DEFAULT NULL, policy JSON NOT NULL, object_uuid BYTEA NOT NULL, object_slug VARCHAR(190) NOT NULL, object_created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, object_created_by VARCHAR(190) DEFAULT NULL, object_modified_by VARCHAR(190) DEFAULT NULL, object_first_title VARCHAR(255) DEFAULT NULL, object_middle_title TEXT DEFAULT NULL, object_last_title TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_pref_object_uuid ON notifying_notification_preference (object_uuid)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_pref_object_slug ON notifying_notification_preference (object_slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_pref_recipient_topic ON notifying_notification_preference (recipient_type, recipient_key, topic)');
        $this->addSql('CREATE INDEX idx_notifying_pref_recipient ON notifying_notification_preference (recipient_key)');

        $this->addSql('CREATE TABLE notifying_notification_subscription (id UUID NOT NULL, recipient_type VARCHAR(255) NOT NULL, recipient_key VARCHAR(160) NOT NULL, platform VARCHAR(80) NOT NULL, app_key VARCHAR(120) NOT NULL, device_id VARCHAR(190) NOT NULL, token TEXT NOT NULL, token_hash VARCHAR(64) NOT NULL, enabled BOOLEAN NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, disabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, metadata JSON NOT NULL, object_uuid BYTEA NOT NULL, object_slug VARCHAR(190) NOT NULL, object_created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, object_modified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, object_created_by VARCHAR(190) DEFAULT NULL, object_modified_by VARCHAR(190) DEFAULT NULL, object_first_title VARCHAR(255) DEFAULT NULL, object_middle_title TEXT DEFAULT NULL, object_last_title TEXT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_subscription_object_uuid ON notifying_notification_subscription (object_uuid)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_subscription_object_slug ON notifying_notification_subscription (object_slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_notifying_subscription_token_hash ON notifying_notification_subscription (token_hash)');
        $this->addSql('CREATE INDEX idx_notifying_subscription_recipient ON notifying_notification_subscription (recipient_key, enabled)');
        $this->addSql('CREATE INDEX idx_notifying_subscription_device ON notifying_notification_subscription (app_key, platform, device_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifying_notification_subscription');
        $this->addSql('DROP TABLE notifying_notification_preference');
        $this->addSql('DROP TABLE notifying_notification_recipient');
        $this->addSql('DROP TABLE notifying_notification');
    }
}
