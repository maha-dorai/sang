<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124230116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE don DROP FOREIGN KEY FK_F8F081D991EF7EAA');
        $this->addSql('ALTER TABLE don ADD CONSTRAINT FK_F8F081D991EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE don DROP FOREIGN KEY FK_F8F081D991EF7EAA');
        $this->addSql('ALTER TABLE don ADD CONSTRAINT FK_F8F081D991EF7EAA FOREIGN KEY (rendez_vous_id) REFERENCES rendez_vous (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}
