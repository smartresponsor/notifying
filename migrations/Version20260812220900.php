<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260812220900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Materialize Notifying dispatch claim lease fields declared by NotificationDispatchPlanEntity.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('notifying_notification_dispatch_plan');
        $table->addColumn('claimed_by', 'string', ['length' => 190, 'notnull' => false]);
        $table->addColumn('claimed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('claim_expires_at', 'datetime_immutable', ['notnull' => false]);
        $table->addIndex(['status', 'claim_expires_at'], 'idx_notifying_dispatch_claim');
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('notifying_notification_dispatch_plan');
        if ($table->hasIndex('idx_notifying_dispatch_claim')) {
            $table->dropIndex('idx_notifying_dispatch_claim');
        }
        foreach (['claim_expires_at', 'claimed_at', 'claimed_by'] as $column) {
            $table->dropColumn($column);
        }
    }
}
