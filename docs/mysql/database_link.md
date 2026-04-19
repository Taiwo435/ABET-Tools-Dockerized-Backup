# Connecting to the Database

This is a tutorial on how to link your code to the database.
This is important to both the backend team (linking the frontend forms to the database)
and any other team that links to the databse (reportgen, canvas scripts).

## Core Concept

Developing with docker allows us to develop in an easier way.
If you're able to interface with the *mysql* container,
your code **will** connect to the server's mysql
container if you set it up correctly. 

## Database Connections

When you launch the docker containers, a *mysql* container is created
to simulate the server's mysql instance. When you develop
locally, connect to this like it's the actual server.
Make sure you use the environment variables that I have
supplied to every container. Important ones are here:

![Database images](./static/db_environ.png)

> [!IMPORTANT]  
> Make sure you use the supplied environment variables.
> Hard-coding the mysql connection will lead to failure
> on the server!
> The server has its own .env file with sensitive
> information, but also accurate information that
> points to mysql on the server.

## Examples

### Python

assuming you have mysql-connector-python installed:

```python
import os
import mysql.connector

try:
    # Establish the connection
    # user, password, host, db
    # Don't 
    cnx = mysql.connector.connect(
        host=os.environ["MYSQL_HOSTNAME"],
        database=os.environ["MYSQL_DATABASE"],
        user=os.environ["MYSQL_USER"],
        password=os.environ["MYSQL_PASS"],
    )
    
    if cnx.is_connected():
        print("Connection established successfully!")
        
        # You can now create a cursor object to execute SQL queries
        cursor = cnx.cursor()
        # Example query: cursor.execute("SHOW TABLES") 
        # results = cursor.fetchall()

except mysql.connector.Error as err:
    # log the error with your logger
    print(f"Error connecting to MySQL: {err}")

finally:
    # Always close the connection and cursor when done
    if 'cursor' in locals() and cursor is not None:
        cursor.close()
    if 'cnx' in locals() and cnx is not None and cnx.is_connected():
        cnx.close()
        print("Connection closed.")
```

### PHP

```php
# use our PDO manager
require_once getenv('ABET_PRIVATE_DIR') . '/lib/db.php';

# somewhere later on...

try {
    db()->prepare(
        'INSERT INTO login_events (user_id, email_attempted, result, reason, ip_address, user_agent)
        VALUES (:user_id, :email_attempted, :result, :reason, :ip, :ua)'
    )->execute([
        ':user_id' => $userId,
        ':email_attempted' => $emailAttempted,
        ':result' => $result, // success | failed_password | failed_mfa | locked
        ':reason' => $reason,
        ':ip' => client_ip(),
        ':ua' => user_agent(),
    ]);
} catch (Throwable $e) {
    # log the error 
}
```
