<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create employees and tasks tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE employees (
            employee_id INT NOT NULL,
            skill_level INT NOT NULL,
            hourly_rate INT NOT NULL,
            PRIMARY KEY(employee_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE tasks (
            task_id INT NOT NULL,
            skill_level INT NOT NULL,
            estimation INT NOT NULL,
            PRIMARY KEY(task_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE employees');
        $this->addSql('DROP TABLE tasks');
    }
}
