<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260301120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on filterable columns (photos: taken_at, location, camera; users: username)';
    }

    public function up(Schema $schema): void
    {
        // taken_at benefits most from a B-tree index (used in range queries)
        $this->addSql('CREATE INDEX idx_photos_taken_at ON photos (taken_at)');

        // B-tree indexes on location and camera help with equality lookups and prefix LIKE.
        // For substring LIKE (%value%), enable pg_trgm + GIN index instead:
        //   CREATE EXTENSION IF NOT EXISTS pg_trgm;
        //   CREATE INDEX idx_photos_location_trgm ON photos USING GIN (location gin_trgm_ops);
        $this->addSql('CREATE INDEX idx_photos_location ON photos (location)');
        $this->addSql('CREATE INDEX idx_photos_camera ON photos (camera)');

        // username is already unique but an explicit index speeds up JOIN filtering
        $this->addSql('CREATE INDEX idx_users_username ON users (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_photos_taken_at');
        $this->addSql('DROP INDEX IF EXISTS idx_photos_location');
        $this->addSql('DROP INDEX IF EXISTS idx_photos_camera');
        $this->addSql('DROP INDEX IF EXISTS idx_users_username');
    }
}
