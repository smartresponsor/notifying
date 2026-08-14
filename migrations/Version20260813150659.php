<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\StringType;
use Doctrine\Migrations\AbstractMigration;

final class Version20260813150659 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable claim lease hash for notification dispatch handoff safety.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('notifying_notification_dispatch_plan');
        if ($table->hasColumn('claim_lease_hash')) {
            $column = $table->getColumn('claim_lease_hash');
            $this->abortIf(
                !$column->getType() instanceof StringType || 64 !== $column->getLength() || $column->getNotnull(),
                'Existing claim_lease_hash column is incompatible; expected nullable VARCHAR(64).',
            );

            return;
        }

        $table->addColumn('claim_lease_hash', 'string', [
            'length' => 64,
            'notnull' => false,
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Removing claim lease identity would weaken active dispatch lease safety.');
    }
}
