<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319051758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and user_profiles tables (example migration)';
    }

       public function up(Schema $schema): void
    {

        // heredoc syntax
        // I know it's UGLY but it's what we have to work with
        $sql =
<<<SQL
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty') NOT NULL DEFAULT 'faculty',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
SQL;
        $this->addSql($sql);    

        $this->addSql(
<<<SQL
        CREATE TABLE IF NOT EXISTS user_profiles (
            profile_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            display_name VARCHAR(255),
            department VARCHAR(255),
            phone VARCHAR(50),
            office_location VARCHAR(255),
            bio VARCHAR(512),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
SQL);

    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_profiles');
        $this->addSql('DROP TABLE users');
    }
}
