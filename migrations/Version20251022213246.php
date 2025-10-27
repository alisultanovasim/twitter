<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251022213246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hashtag (id INT AUTO_INCREMENT NOT NULL, tag VARCHAR(100) NOT NULL, usage_count INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_5AB52A61389B783 (tag), INDEX idx_usage_count (usage_count), INDEX idx_tag (tag), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tweet_hashtag (tweet_id INT NOT NULL, hashtag_id INT NOT NULL, INDEX IDX_D5364B091041E39B (tweet_id), INDEX IDX_D5364B09FB34EF56 (hashtag_id), PRIMARY KEY(tweet_id, hashtag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tweet_hashtag ADD CONSTRAINT FK_D5364B091041E39B FOREIGN KEY (tweet_id) REFERENCES tweet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tweet_hashtag ADD CONSTRAINT FK_D5364B09FB34EF56 FOREIGN KEY (hashtag_id) REFERENCES hashtag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tweet_hashtag DROP FOREIGN KEY FK_D5364B091041E39B');
        $this->addSql('ALTER TABLE tweet_hashtag DROP FOREIGN KEY FK_D5364B09FB34EF56');
        $this->addSql('DROP TABLE hashtag');
        $this->addSql('DROP TABLE tweet_hashtag');
    }
}
