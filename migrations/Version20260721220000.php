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

/**
 * @version 2.x
 */
final class Version20260721220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename Doctrine-generated indexes still hashed against the old kimai2_* table names to match the current gppro_* table names (MySQL RENAME TABLE does not rename indexes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_teams RENAME INDEX uniq_3beddc7f5e237e06 TO UNIQ_2A31B19C5E237E06');
        $this->addSql('ALTER TABLE gppro_milestones RENAME INDEX idx_2517d07d166d1f9c TO IDX_5465ADE8166D1F9C');
        $this->addSql('ALTER TABLE gppro_projects_meta RENAME INDEX idx_50536ef2166d1f9c TO IDX_8F110333166D1F9C');
        $this->addSql('ALTER TABLE gppro_projects_meta RENAME INDEX uniq_50536ef2166d1f9c5e237e06 TO UNIQ_8F110333166D1F9C5E237E06');
        $this->addSql('ALTER TABLE gppro_timesheet_meta RENAME INDEX idx_cb606cbaabdd46be TO IDX_27DCDCF1ABDD46BE');
        $this->addSql('ALTER TABLE gppro_timesheet_meta RENAME INDEX uniq_cb606cbaabdd46be5e237e06 TO UNIQ_27DCDCF1ABDD46BE5E237E06');
        $this->addSql('ALTER TABLE gppro_user_preferences RENAME INDEX idx_8d08f631a76ed395 TO IDX_55E43FCFA76ED395');
        $this->addSql('ALTER TABLE gppro_user_preferences RENAME INDEX uniq_8d08f631a76ed3955e237e06 TO UNIQ_55E43FCFA76ED3955E237E06');
        $this->addSql('ALTER TABLE gppro_customers_comments RENAME INDEX idx_a5b142d9b03a8386 TO IDX_CD362C12B03A8386');
        $this->addSql('ALTER TABLE gppro_customers_comments RENAME INDEX idx_a5b142d99395c3f3 TO IDX_CD362C129395C3F3');
        $this->addSql('ALTER TABLE gppro_working_times RENAME INDEX idx_f95e4933a76ed395 TO IDX_261C24F2A76ED395');
        $this->addSql('ALTER TABLE gppro_working_times RENAME INDEX idx_f95e49334ea3cb3d TO IDX_261C24F24EA3CB3D');
        $this->addSql('ALTER TABLE gppro_working_times RENAME INDEX uniq_f95e4933a76ed395aa9e377a TO UNIQ_261C24F2A76ED395AA9E377A');
        $this->addSql('ALTER TABLE gppro_tags RENAME INDEX idx_27caf54c7ab0e859 TO IDX_FAAC4A977AB0E859');
        $this->addSql('ALTER TABLE gppro_tags RENAME INDEX uniq_27caf54c5e237e06 TO UNIQ_FAAC4A975E237E06');
        $this->addSql('ALTER TABLE gppro_invoice_templates_meta RENAME INDEX idx_a165b0555da0fb8 TO IDX_75CBD89E5DA0FB8');
        $this->addSql('ALTER TABLE gppro_invoice_templates_meta RENAME INDEX uniq_a165b0555da0fb85e237e06 TO UNIQ_75CBD89E5DA0FB85E237E06');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX uniq_b9ac5bcec05fb297 TO UNIQ_A870362DC05FB297');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX idx_b9ac5bce19e9ac5f TO IDX_A870362D19E9AC5F');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX uniq_b9ac5bcef85e0677 TO UNIQ_A870362DF85E0677');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX uniq_b9ac5bcee7927c74 TO UNIQ_A870362DE7927C74');
        $this->addSql('ALTER TABLE gppro_access_token RENAME INDEX idx_6fb0db1ea76ed395 TO IDX_4D32D9B2A76ED395');
        $this->addSql('ALTER TABLE gppro_access_token RENAME INDEX uniq_6fb0db1e5f37a13b TO UNIQ_4D32D9B25F37A13B');
        $this->addSql('ALTER TABLE gppro_configuration RENAME INDEX uniq_1c5d63d85e237e06 TO UNIQ_C31F0E195E237E06');
        $this->addSql('ALTER TABLE gppro_activities_rates RENAME INDEX idx_4a7f11be81c06096 TO IDX_9293D84081C06096');
        $this->addSql('ALTER TABLE gppro_activities_rates RENAME INDEX idx_4a7f11bea76ed395 TO IDX_9293D840A76ED395');
        $this->addSql('ALTER TABLE gppro_activities_rates RENAME INDEX uniq_4a7f11bea76ed39581c06096 TO UNIQ_9293D840A76ED39581C06096');
        $this->addSql('ALTER TABLE gppro_customers_meta RENAME INDEX idx_a48a760f9395c3f3 TO IDX_4836C6449395C3F3');
        $this->addSql('ALTER TABLE gppro_customers_meta RENAME INDEX uniq_a48a760f9395c3f35e237e06 TO UNIQ_4836C6449395C3F35E237E06');
        $this->addSql('ALTER TABLE gppro_projects_comments RENAME INDEX idx_29a23638b03a8386 TO IDX_737F05EAB03A8386');
        $this->addSql('ALTER TABLE gppro_projects_comments RENAME INDEX idx_29a23638166d1f9c TO IDX_737F05EA166D1F9C');
        $this->addSql('ALTER TABLE gppro_customers RENAME INDEX idx_5a97604412946d8b TO IDX_996F7C0012946D8B');
        $this->addSql('ALTER TABLE gppro_customers RENAME INDEX idx_5a9760447ab0e859 TO IDX_996F7C007AB0E859');
        $this->addSql('ALTER TABLE gppro_customers_teams RENAME INDEX idx_50bd83889395c3f3 TO IDX_B15FA7209395C3F3');
        $this->addSql('ALTER TABLE gppro_customers_teams RENAME INDEX idx_50bd8388296cd8ae TO IDX_B15FA720296CD8AE');
        $this->addSql('ALTER TABLE gppro_projects RENAME INDEX idx_407f12069395c3f3 TO IDX_B4EDF7FB9395C3F3');
        $this->addSql('ALTER TABLE gppro_projects RENAME INDEX idx_407f12069395c3f37ab0e8595e237e06 TO IDX_B4EDF7FB9395C3F37AB0E8595E237E06');
        $this->addSql('ALTER TABLE gppro_projects RENAME INDEX idx_407f12069395c3f37ab0e859bf396750 TO IDX_B4EDF7FB9395C3F37AB0E859BF396750');
        $this->addSql('ALTER TABLE gppro_projects_teams RENAME INDEX idx_9345d431166d1f9c TO IDX_7FF9647A166D1F9C');
        $this->addSql('ALTER TABLE gppro_projects_teams RENAME INDEX idx_9345d431296cd8ae TO IDX_7FF9647A296CD8AE');
        $this->addSql('ALTER TABLE gppro_export_templates RENAME INDEX uniq_2f0ca26f2b36786b TO UNIQ_F7E06B912B36786B');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX idx_76c38e379395c3f3 TO IDX_82516BCA9395C3F3');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX idx_76c38e37a76ed395 TO IDX_82516BCAA76ED395');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX uniq_76c38e372da68207 TO UNIQ_82516BCA2DA68207');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX uniq_76c38e372323b33d TO UNIQ_82516BCA2323B33D');
        $this->addSql('ALTER TABLE gppro_bookmarks RENAME INDEX idx_4016ef25a76ed395 TO IDX_83EEF361A76ED395');
        $this->addSql('ALTER TABLE gppro_bookmarks RENAME INDEX uniq_4016ef25a76ed3955e237e06 TO UNIQ_83EEF361A76ED3955E237E06');
        $this->addSql('ALTER TABLE gppro_invoices_meta RENAME INDEX idx_7edc37d92989f1fd TO IDX_A19E5A182989F1FD');
        $this->addSql('ALTER TABLE gppro_invoices_meta RENAME INDEX uniq_7edc37d92989f1fd5e237e06 TO UNIQ_A19E5A182989F1FD5E237E06');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX idx_8811fe1c166d1f9c TO IDX_F9638389166D1F9C');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX idx_8811fe1c4b3e2eda TO IDX_F96383894B3E2EDA');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX idx_8811fe1c7ab0e859166d1f9c TO IDX_F96383897AB0E859166D1F9C');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX idx_8811fe1c7ab0e859166d1f9c5e237e06 TO IDX_F96383897AB0E859166D1F9C5E237E06');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX idx_8811fe1c7ab0e8595e237e06 TO IDX_F96383897AB0E8595E237E06');
        $this->addSql('ALTER TABLE gppro_activities_teams RENAME INDEX idx_986998da81c06096 TO IDX_4085512481C06096');
        $this->addSql('ALTER TABLE gppro_activities_teams RENAME INDEX idx_986998da296cd8ae TO IDX_40855124296CD8AE');
        $this->addSql('ALTER TABLE gppro_activities_meta RENAME INDEX idx_a7c0a43d81c06096 TO IDX_4622809581C06096');
        $this->addSql('ALTER TABLE gppro_activities_meta RENAME INDEX uniq_a7c0a43d81c060965e237e06 TO UNIQ_4622809581C060965E237E06');
        $this->addSql('ALTER TABLE gppro_roles_permissions RENAME INDEX idx_d263a3b8d60322ac TO IDX_88BE906AD60322AC');
        $this->addSql('ALTER TABLE gppro_customers_rates RENAME INDEX idx_82ab0aec9395c3f3 TO IDX_63492E449395C3F3');
        $this->addSql('ALTER TABLE gppro_customers_rates RENAME INDEX idx_82ab0aeca76ed395 TO IDX_63492E44A76ED395');
        $this->addSql('ALTER TABLE gppro_customers_rates RENAME INDEX uniq_82ab0aeca76ed3959395c3f3 TO UNIQ_63492E44A76ED3959395C3F3');
        $this->addSql('ALTER TABLE gppro_projects_rates RENAME INDEX idx_41535d55166d1f9c TO IDX_ADEFED1E166D1F9C');
        $this->addSql('ALTER TABLE gppro_projects_rates RENAME INDEX idx_41535d55a76ed395 TO IDX_ADEFED1EA76ED395');
        $this->addSql('ALTER TABLE gppro_projects_rates RENAME INDEX uniq_41535d55a76ed395166d1f9c TO UNIQ_ADEFED1EA76ED395166D1F9C');
        $this->addSql('ALTER TABLE gppro_timesheet RENAME INDEX idx_4f60c6b1166d1f9c TO IDX_8C98DAF5166D1F9C');
        $this->addSql('ALTER TABLE gppro_timesheet_tags RENAME INDEX idx_e3284efeabdd46be TO IDX_9F925CE2ABDD46BE');
        $this->addSql('ALTER TABLE gppro_timesheet_tags RENAME INDEX idx_e3284efebad26311 TO IDX_9F925CE2BAD26311');
        $this->addSql('ALTER TABLE gppro_invoice_templates RENAME INDEX idx_1626cfe99395c3f3 TO IDX_4CFBFC3B9395C3F3');
        $this->addSql('ALTER TABLE gppro_invoice_templates RENAME INDEX uniq_1626cfe95e237e06 TO UNIQ_4CFBFC3B5E237E06');
        $this->addSql('ALTER TABLE gppro_users_teams RENAME INDEX idx_b5e92cf8a76ed395 TO IDX_35FD394EA76ED395');
        $this->addSql('ALTER TABLE gppro_users_teams RENAME INDEX idx_b5e92cf8296cd8ae TO IDX_35FD394E296CD8AE');
        $this->addSql('ALTER TABLE gppro_users_teams RENAME INDEX uniq_b5e92cf8a76ed395296cd8ae TO UNIQ_35FD394EA76ED395296CD8AE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE gppro_teams RENAME INDEX UNIQ_2A31B19C5E237E06 TO uniq_3beddc7f5e237e06');
        $this->addSql('ALTER TABLE gppro_milestones RENAME INDEX IDX_5465ADE8166D1F9C TO idx_2517d07d166d1f9c');
        $this->addSql('ALTER TABLE gppro_projects_meta RENAME INDEX IDX_8F110333166D1F9C TO idx_50536ef2166d1f9c');
        $this->addSql('ALTER TABLE gppro_projects_meta RENAME INDEX UNIQ_8F110333166D1F9C5E237E06 TO uniq_50536ef2166d1f9c5e237e06');
        $this->addSql('ALTER TABLE gppro_timesheet_meta RENAME INDEX IDX_27DCDCF1ABDD46BE TO idx_cb606cbaabdd46be');
        $this->addSql('ALTER TABLE gppro_timesheet_meta RENAME INDEX UNIQ_27DCDCF1ABDD46BE5E237E06 TO uniq_cb606cbaabdd46be5e237e06');
        $this->addSql('ALTER TABLE gppro_user_preferences RENAME INDEX IDX_55E43FCFA76ED395 TO idx_8d08f631a76ed395');
        $this->addSql('ALTER TABLE gppro_user_preferences RENAME INDEX UNIQ_55E43FCFA76ED3955E237E06 TO uniq_8d08f631a76ed3955e237e06');
        $this->addSql('ALTER TABLE gppro_customers_comments RENAME INDEX IDX_CD362C12B03A8386 TO idx_a5b142d9b03a8386');
        $this->addSql('ALTER TABLE gppro_customers_comments RENAME INDEX IDX_CD362C129395C3F3 TO idx_a5b142d99395c3f3');
        $this->addSql('ALTER TABLE gppro_working_times RENAME INDEX IDX_261C24F2A76ED395 TO idx_f95e4933a76ed395');
        $this->addSql('ALTER TABLE gppro_working_times RENAME INDEX IDX_261C24F24EA3CB3D TO idx_f95e49334ea3cb3d');
        $this->addSql('ALTER TABLE gppro_working_times RENAME INDEX UNIQ_261C24F2A76ED395AA9E377A TO uniq_f95e4933a76ed395aa9e377a');
        $this->addSql('ALTER TABLE gppro_tags RENAME INDEX IDX_FAAC4A977AB0E859 TO idx_27caf54c7ab0e859');
        $this->addSql('ALTER TABLE gppro_tags RENAME INDEX UNIQ_FAAC4A975E237E06 TO uniq_27caf54c5e237e06');
        $this->addSql('ALTER TABLE gppro_invoice_templates_meta RENAME INDEX IDX_75CBD89E5DA0FB8 TO idx_a165b0555da0fb8');
        $this->addSql('ALTER TABLE gppro_invoice_templates_meta RENAME INDEX UNIQ_75CBD89E5DA0FB85E237E06 TO uniq_a165b0555da0fb85e237e06');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX UNIQ_A870362DC05FB297 TO uniq_b9ac5bcec05fb297');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX IDX_A870362D19E9AC5F TO idx_b9ac5bce19e9ac5f');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX UNIQ_A870362DF85E0677 TO uniq_b9ac5bcef85e0677');
        $this->addSql('ALTER TABLE gppro_users RENAME INDEX UNIQ_A870362DE7927C74 TO uniq_b9ac5bcee7927c74');
        $this->addSql('ALTER TABLE gppro_access_token RENAME INDEX IDX_4D32D9B2A76ED395 TO idx_6fb0db1ea76ed395');
        $this->addSql('ALTER TABLE gppro_access_token RENAME INDEX UNIQ_4D32D9B25F37A13B TO uniq_6fb0db1e5f37a13b');
        $this->addSql('ALTER TABLE gppro_configuration RENAME INDEX UNIQ_C31F0E195E237E06 TO uniq_1c5d63d85e237e06');
        $this->addSql('ALTER TABLE gppro_activities_rates RENAME INDEX IDX_9293D84081C06096 TO idx_4a7f11be81c06096');
        $this->addSql('ALTER TABLE gppro_activities_rates RENAME INDEX IDX_9293D840A76ED395 TO idx_4a7f11bea76ed395');
        $this->addSql('ALTER TABLE gppro_activities_rates RENAME INDEX UNIQ_9293D840A76ED39581C06096 TO uniq_4a7f11bea76ed39581c06096');
        $this->addSql('ALTER TABLE gppro_customers_meta RENAME INDEX IDX_4836C6449395C3F3 TO idx_a48a760f9395c3f3');
        $this->addSql('ALTER TABLE gppro_customers_meta RENAME INDEX UNIQ_4836C6449395C3F35E237E06 TO uniq_a48a760f9395c3f35e237e06');
        $this->addSql('ALTER TABLE gppro_projects_comments RENAME INDEX IDX_737F05EAB03A8386 TO idx_29a23638b03a8386');
        $this->addSql('ALTER TABLE gppro_projects_comments RENAME INDEX IDX_737F05EA166D1F9C TO idx_29a23638166d1f9c');
        $this->addSql('ALTER TABLE gppro_customers RENAME INDEX IDX_996F7C0012946D8B TO idx_5a97604412946d8b');
        $this->addSql('ALTER TABLE gppro_customers RENAME INDEX IDX_996F7C007AB0E859 TO idx_5a9760447ab0e859');
        $this->addSql('ALTER TABLE gppro_customers_teams RENAME INDEX IDX_B15FA7209395C3F3 TO idx_50bd83889395c3f3');
        $this->addSql('ALTER TABLE gppro_customers_teams RENAME INDEX IDX_B15FA720296CD8AE TO idx_50bd8388296cd8ae');
        $this->addSql('ALTER TABLE gppro_projects RENAME INDEX IDX_B4EDF7FB9395C3F3 TO idx_407f12069395c3f3');
        $this->addSql('ALTER TABLE gppro_projects RENAME INDEX IDX_B4EDF7FB9395C3F37AB0E8595E237E06 TO idx_407f12069395c3f37ab0e8595e237e06');
        $this->addSql('ALTER TABLE gppro_projects RENAME INDEX IDX_B4EDF7FB9395C3F37AB0E859BF396750 TO idx_407f12069395c3f37ab0e859bf396750');
        $this->addSql('ALTER TABLE gppro_projects_teams RENAME INDEX IDX_7FF9647A166D1F9C TO idx_9345d431166d1f9c');
        $this->addSql('ALTER TABLE gppro_projects_teams RENAME INDEX IDX_7FF9647A296CD8AE TO idx_9345d431296cd8ae');
        $this->addSql('ALTER TABLE gppro_export_templates RENAME INDEX UNIQ_F7E06B912B36786B TO uniq_2f0ca26f2b36786b');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX IDX_82516BCA9395C3F3 TO idx_76c38e379395c3f3');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX IDX_82516BCAA76ED395 TO idx_76c38e37a76ed395');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX UNIQ_82516BCA2DA68207 TO uniq_76c38e372da68207');
        $this->addSql('ALTER TABLE gppro_invoices RENAME INDEX UNIQ_82516BCA2323B33D TO uniq_76c38e372323b33d');
        $this->addSql('ALTER TABLE gppro_bookmarks RENAME INDEX IDX_83EEF361A76ED395 TO idx_4016ef25a76ed395');
        $this->addSql('ALTER TABLE gppro_bookmarks RENAME INDEX UNIQ_83EEF361A76ED3955E237E06 TO uniq_4016ef25a76ed3955e237e06');
        $this->addSql('ALTER TABLE gppro_invoices_meta RENAME INDEX IDX_A19E5A182989F1FD TO idx_7edc37d92989f1fd');
        $this->addSql('ALTER TABLE gppro_invoices_meta RENAME INDEX UNIQ_A19E5A182989F1FD5E237E06 TO uniq_7edc37d92989f1fd5e237e06');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX IDX_F9638389166D1F9C TO idx_8811fe1c166d1f9c');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX IDX_F96383894B3E2EDA TO idx_8811fe1c4b3e2eda');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX IDX_F96383897AB0E859166D1F9C TO idx_8811fe1c7ab0e859166d1f9c');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX IDX_F96383897AB0E859166D1F9C5E237E06 TO idx_8811fe1c7ab0e859166d1f9c5e237e06');
        $this->addSql('ALTER TABLE gppro_activities RENAME INDEX IDX_F96383897AB0E8595E237E06 TO idx_8811fe1c7ab0e8595e237e06');
        $this->addSql('ALTER TABLE gppro_activities_teams RENAME INDEX IDX_4085512481C06096 TO idx_986998da81c06096');
        $this->addSql('ALTER TABLE gppro_activities_teams RENAME INDEX IDX_40855124296CD8AE TO idx_986998da296cd8ae');
        $this->addSql('ALTER TABLE gppro_activities_meta RENAME INDEX IDX_4622809581C06096 TO idx_a7c0a43d81c06096');
        $this->addSql('ALTER TABLE gppro_activities_meta RENAME INDEX UNIQ_4622809581C060965E237E06 TO uniq_a7c0a43d81c060965e237e06');
        $this->addSql('ALTER TABLE gppro_roles_permissions RENAME INDEX IDX_88BE906AD60322AC TO idx_d263a3b8d60322ac');
        $this->addSql('ALTER TABLE gppro_customers_rates RENAME INDEX IDX_63492E449395C3F3 TO idx_82ab0aec9395c3f3');
        $this->addSql('ALTER TABLE gppro_customers_rates RENAME INDEX IDX_63492E44A76ED395 TO idx_82ab0aeca76ed395');
        $this->addSql('ALTER TABLE gppro_customers_rates RENAME INDEX UNIQ_63492E44A76ED3959395C3F3 TO uniq_82ab0aeca76ed3959395c3f3');
        $this->addSql('ALTER TABLE gppro_projects_rates RENAME INDEX IDX_ADEFED1E166D1F9C TO idx_41535d55166d1f9c');
        $this->addSql('ALTER TABLE gppro_projects_rates RENAME INDEX IDX_ADEFED1EA76ED395 TO idx_41535d55a76ed395');
        $this->addSql('ALTER TABLE gppro_projects_rates RENAME INDEX UNIQ_ADEFED1EA76ED395166D1F9C TO uniq_41535d55a76ed395166d1f9c');
        $this->addSql('ALTER TABLE gppro_timesheet RENAME INDEX IDX_8C98DAF5166D1F9C TO idx_4f60c6b1166d1f9c');
        $this->addSql('ALTER TABLE gppro_timesheet_tags RENAME INDEX IDX_9F925CE2ABDD46BE TO idx_e3284efeabdd46be');
        $this->addSql('ALTER TABLE gppro_timesheet_tags RENAME INDEX IDX_9F925CE2BAD26311 TO idx_e3284efebad26311');
        $this->addSql('ALTER TABLE gppro_invoice_templates RENAME INDEX IDX_4CFBFC3B9395C3F3 TO idx_1626cfe99395c3f3');
        $this->addSql('ALTER TABLE gppro_invoice_templates RENAME INDEX UNIQ_4CFBFC3B5E237E06 TO uniq_1626cfe95e237e06');
        $this->addSql('ALTER TABLE gppro_users_teams RENAME INDEX IDX_35FD394EA76ED395 TO idx_b5e92cf8a76ed395');
        $this->addSql('ALTER TABLE gppro_users_teams RENAME INDEX IDX_35FD394E296CD8AE TO idx_b5e92cf8296cd8ae');
        $this->addSql('ALTER TABLE gppro_users_teams RENAME INDEX UNIQ_35FD394EA76ED395296CD8AE TO uniq_b5e92cf8a76ed395296cd8ae');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
