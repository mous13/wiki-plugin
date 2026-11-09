<?php

declare(strict_types=1);

namespace CitadelWikiPluginMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109143750 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add wiki, page, revision and link tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE wiki (title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(255) NOT NULL, created_at DATETIME DEFAULT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_22CDDC06989D9B62 (slug), INDEX IDX_22CDDC068B8E8428 (created_at), INDEX IDX_22CDDC0643625D9F (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_link (to_slug VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, from_page_id INT NOT NULL, INDEX idx_wiki_link_to_slug (to_slug), UNIQUE INDEX uniq_wiki_link (from_page_id, to_slug), INDEX IDX_B68E59588A18804B (from_page_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_page (title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, html LONGTEXT NOT NULL, toc JSON NOT NULL, is_protected TINYINT DEFAULT 0 NOT NULL, slug VARCHAR(255) NOT NULL, id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, current_revision_id INT DEFAULT NULL, wiki_id INT NOT NULL, FULLTEXT INDEX ft_wiki_page_search (title, content), UNIQUE INDEX uniq_wiki_page_slug (wiki_id, slug), INDEX IDX_94287689727ACA70 (parent_id), INDEX IDX_94287689A32ED756 (current_revision_id), INDEX IDX_94287689AA948DBE (wiki_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE wiki_revision (created_at DATETIME NOT NULL, content LONGTEXT NOT NULL, summary VARCHAR(255) DEFAULT NULL, minor TINYINT NOT NULL, size INT NOT NULL, size_delta INT NOT NULL, id INT AUTO_INCREMENT NOT NULL, page_id INT NOT NULL, author_id INT DEFAULT NULL, INDEX idx_wiki_revision_created (created_at), INDEX IDX_86A51003C4663E4 (page_id), INDEX IDX_86A51003F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE wiki_link ADD CONSTRAINT FK_B68E59588A18804B FOREIGN KEY (from_page_id) REFERENCES wiki_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_page ADD CONSTRAINT FK_94287689727ACA70 FOREIGN KEY (parent_id) REFERENCES wiki_page (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_page ADD CONSTRAINT FK_94287689A32ED756 FOREIGN KEY (current_revision_id) REFERENCES wiki_revision (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE wiki_page ADD CONSTRAINT FK_94287689AA948DBE FOREIGN KEY (wiki_id) REFERENCES wiki (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_revision ADD CONSTRAINT FK_86A51003C4663E4 FOREIGN KEY (page_id) REFERENCES wiki_page (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wiki_revision ADD CONSTRAINT FK_86A51003F675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE wiki_link DROP FOREIGN KEY FK_B68E59588A18804B');
        $this->addSql('ALTER TABLE wiki_page DROP FOREIGN KEY FK_94287689727ACA70');
        $this->addSql('ALTER TABLE wiki_page DROP FOREIGN KEY FK_94287689A32ED756');
        $this->addSql('ALTER TABLE wiki_page DROP FOREIGN KEY FK_94287689AA948DBE');
        $this->addSql('ALTER TABLE wiki_revision DROP FOREIGN KEY FK_86A51003C4663E4');
        $this->addSql('ALTER TABLE wiki_revision DROP FOREIGN KEY FK_86A51003F675F31B');
        $this->addSql('DROP TABLE wiki');
        $this->addSql('DROP TABLE wiki_link');
        $this->addSql('DROP TABLE wiki_page');
        $this->addSql('DROP TABLE wiki_revision');
    }
}
