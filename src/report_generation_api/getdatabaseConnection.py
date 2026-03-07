'''This module provides a function to establish a connection to a MySQL database using the PyMySQL library.'''
import os
import pymysql.cursors

# Connect to the database
def get_database_connection():
    """Establishes a connection to the MySQL database using environment variables for configuration."""
    connection = pymysql.connect(
        host=os.getenv("MYSQL_HOSTNAME", "mysql"),
        user=os.getenv("MYSQL_USER"),
        password=os.getenv("MYSQL_PASS"),
        database=os.getenv("MYSQL_DATABASE"),
        port=3306,
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=10
    )

    return connection



