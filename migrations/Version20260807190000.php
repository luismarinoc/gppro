<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DoctrineMigrations;

use App\Doctrine\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260807190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quotation catalog items, quotations, and immutable quotation line snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE gppro_quotation_catalog_items (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, unit VARCHAR(50) NOT NULL, default_price NUMERIC(18, 4) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("CREATE TABLE gppro_quotations (id INT AUTO_INCREMENT NOT NULL, customer_id INT NOT NULL, project_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, modified_at DATETIME DEFAULT NULL, status VARCHAR(20) DEFAULT 'draft' NOT NULL, valid_until DATE DEFAULT NULL, tax NUMERIC(18, 4) DEFAULT NULL, discount NUMERIC(18, 4) DEFAULT NULL, surcharge NUMERIC(18, 4) DEFAULT NULL, INDEX IDX_GPPRO_QUOTATIONS_CUSTOMER (customer_id), INDEX IDX_GPPRO_QUOTATIONS_PROJECT (project_id), INDEX IDX_GPPRO_QUOTATIONS_CREATED_BY (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE gppro_quotation_lines (id INT AUTO_INCREMENT NOT NULL, quotation_id INT NOT NULL, catalog_item_id INT DEFAULT NULL, description LONGTEXT NOT NULL, unit VARCHAR(50) NOT NULL, unit_price NUMERIC(18, 4) NOT NULL, quantity NUMERIC(18, 4) NOT NULL, INDEX IDX_GPPRO_QUOTATION_LINES_QUOTATION (quotation_id), INDEX IDX_GPPRO_QUOTATION_LINES_CATALOG (catalog_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE gppro_quotations ADD CONSTRAINT FK_GPPRO_QUOTATIONS_CUSTOMER FOREIGN KEY (customer_id) REFERENCES gppro_customers (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE gppro_quotations ADD CONSTRAINT FK_GPPRO_QUOTATIONS_PROJECT FOREIGN KEY (project_id) REFERENCES gppro_projects (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE gppro_quotations ADD CONSTRAINT FK_GPPRO_QUOTATIONS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES gppro_users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE gppro_quotation_lines ADD CONSTRAINT FK_GPPRO_QUOTATION_LINES_QUOTATION FOREIGN KEY (quotation_id) REFERENCES gppro_quotations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE gppro_quotation_lines ADD CONSTRAINT FK_GPPRO_QUOTATION_LINES_CATALOG FOREIGN KEY (catalog_item_id) REFERENCES gppro_quotation_catalog_items (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_quotation_lines DROP FOREIGN KEY FK_GPPRO_QUOTATION_LINES_QUOTATION');
        $this->addSql('ALTER TABLE gppro_quotation_lines DROP FOREIGN KEY FK_GPPRO_QUOTATION_LINES_CATALOG');
        $this->addSql('ALTER TABLE gppro_quotations DROP FOREIGN KEY FK_GPPRO_QUOTATIONS_CUSTOMER');
        $this->addSql('ALTER TABLE gppro_quotations DROP FOREIGN KEY FK_GPPRO_QUOTATIONS_PROJECT');
        $this->addSql('ALTER TABLE gppro_quotations DROP FOREIGN KEY FK_GPPRO_QUOTATIONS_CREATED_BY');
        $this->addSql('DROP TABLE gppro_quotation_lines');
        $this->addSql('DROP TABLE gppro_quotations');
        $this->addSql('DROP TABLE gppro_quotation_catalog_items');
    }
}
