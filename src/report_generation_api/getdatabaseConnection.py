"""This module provides a function to establish a connection to a MySQL database using the PyMySQL library."""

import pymysql.cursors

from config import (
    MYSQL_HOSTNAME,
    MYSQL_USER,
    MYSQL_PASSWORD,
    MYSQL_DATABASE,
    MYSQL_PORT
)

def get_database_connection():
    """Establishes a connection to the MySQL database using configuration values."""

    print("Attempting to connect to the database...")

    connection = pymysql.connect(
        host=MYSQL_HOSTNAME,
        user=MYSQL_USER,
        password=MYSQL_PASSWORD,
        database=MYSQL_DATABASE,
        port=MYSQL_PORT,
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=10
    )

    return connection


def main():
    """Main function to test the database connection."""

    try:
        connection = get_database_connection()

        # Simple test query to verify connection
        with connection.cursor() as cursor:
            cursor.execute("SELECT 1;")
            result = cursor.fetchone()
            print("Database connection established successfully.")
            print("Test query result:", result)

        connection.close()

    except Exception as e:
        print(f"An error occurred while connecting to the database: {e}")


if __name__ == "__main__":
    main()