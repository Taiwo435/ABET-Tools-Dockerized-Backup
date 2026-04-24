# Database

we currently use mySQL for the database container. It will hold important records, such as:

- login information
- session information
- audit/login logs
- etc..

## Schema

Ultimately, even if these docs aren't finished, the database schema will be in [init.sql](../../docker/mysql/init.sql).

Sometimes, you may need to change `init.sql` to change the schema. If you end up committing these changes, please make a **migration**. This is code that will execute on the server in order to update the schema to your version.

## Migration Creation

```bash
# CWD is src/abet_private
composer doctrine migratoins:generate 
```

Now, a migration in `database/migrations` is created labeled VersionDATETIME.php

Please add the corresponding ALTER statements to update the schema of the database.

```php
final class Version20260322173456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Gives users the PERMISSIONS column';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(Services::doesColumnExist("users", "permissions"), 'Skipping this migration.');
        $this->addSql('ALTER TABLE users ADD permissions INT NOT NULL;');
    }
}
```

More docs on how to create migrations on the [official doctrine migrations docs.](https://www.doctrine-project.org/projects/doctrine-migrations/en/3.9/reference/migration-classes.html#migration-classes)

