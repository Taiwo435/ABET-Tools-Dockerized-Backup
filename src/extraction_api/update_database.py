import mysql.connector
from mysql.connector.cursor import MySQLCursor
from mysql.connector.pooling import PooledMySQLConnection
from mysql.connector.errors import ProgrammingError, InternalError, DataError, Error
import asyncio
from typing import Dict, Any, List
import datetime
import json
import time
import os
import logging 
from dotenv import load_dotenv
from pathlib import Path

# load environment variables from docker/.env
load_dotenv(Path(__file__).parent.parent.parent / "docker" / ".env")

# Logging setupoh 
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[logging.StreamHandler()],
)
logger = logging.getLogger(__name__)

dbconfig = {
    'host': os.getenv("MYSQL_HOSTNAME"),
    'user': os.getenv("MYSQL_USER"),
    'password': os.getenv("MYSQL_PASS"),
    'database': os.getenv("MYSQL_DATABASE"),
    'port': os.getenv("MYSQL_PORT")
}

class DatabaseManager:
    
    _pool = None

    def __init__(self):
        self._establish_connection_pool()

    def _establish_connection_pool(self) -> None:

        """
        Creates a pool of connections (5).
        """
        
        cnxpool = mysql.connector.pooling.MySQLConnectionPool(
            pool_name = 'mypool',
            pool_size = 5,
            **dbconfig
        )
        self._pool = cnxpool  # <class 'mysql.connector.pooling.MySQLConnectionPool'>
        
    # def _close_connection(self, cnx_pool_connection: mysql.connector.pooling.PooledMySQLConnection) -> None:
    #     cnx_pool_connection.close()
    
    #Attempt to fetch a connection from the pool of connections
    def _get_connection(self, attempts = 3, delay = 2) -> PooledMySQLConnection | None:
        """
        This method will attempt to get a connection to the database 3 times

        Args:
            attempts (int, optional): Number of tries to get a connection. Defaults to 3.
            delay (int, optional): Exponential backoff exponent. Defaults to 2.

        Returns:
            PooledMySQLConnection | None: If there is no connection
        """
        attempt = 1
        while attempt < attempts + 1:
            try:
                return self._pool.get_connection()
            except (mysql.connector.Error, IOError) as e:
                if attempts == attempt:
                    logger.info("Failed to connect; exiting without connection: %s", e)
                    break
                else:
                    logger.info("Connection Failed: %s. Reconnecting again", e)
                    time.sleep(delay ** attempt)
                    attempt += 1
        return None

    def set_foreign_key_checks(self) -> None:

        connection = self._get_connection()
        if not connection:
            logger.error("Connection unavailable while executing SHOW query")
            raise ValueError("Connection object does not Exist")
    
        cursor = connection.cursor()
        if not cursor:
            logger.error("Cursor unavailable while executing SHOW query")
            raise ValueError("Cursor object does not Exist")

        set_stmt = "SET FOREIGN_KEY_CHECKS=0;"
        try:
            cursor.execute(set_stmt)
            logger.info("Setting foreign key checks to 0")
        except Error as e:
            logger.error(f"Error has occured: {e}")
        finally:
            cursor.close()
            connection.close()

    def show_table_exists_in_database(self, table: str) -> bool:
        """
        Checks to see if table exists in database. Executes SHOW statement

        Args:
            table (str): Name of table

        Raises:
            ValueError: if connection DNE
            ValueError: if cursor object DNE 

        Returns:
            bool: True or False if table exists
        """

        connection = self._get_connection()
        if not connection:
            logger.error("Connection unavailable while executing SHOW query")
            raise ValueError("Connection object does not Exist")
    
        cursor = connection.cursor()
        if not cursor:
            logger.error("Cursor unavailable while executing SHOW query")
            raise ValueError("Cursor object does not Exist")
        
        check_stmt = "SHOW TABLES LIKE %s"

        #Check if table exists. If error, rollback transaction. Finally, close connection
        try:
            cursor.execute(check_stmt, (table,))
            list_of_tuples = cursor.fetchall()                       #e.g of list: [(courses,)]
            if list_of_tuples and list_of_tuples[0][0] == table:
                logger.info(f"{table} exists in database")
                return True
        except Error as e:
            logger.error(f"Error has occured: {e}")
        finally:
            cursor.close()
            connection.close()

        logger.info(f"{table} does not exist in database")
        return False

    #def select_from_database(self):


    def insert_or_update_into_database(self, query: str, param_values: List[tuple | dict] | tuple | dict | None) -> None:
        """
        Summary: Inserts / Updates row(s) in a database

        Args:
            query (str): SQL UPDATE / INSERT statement that may contain placeholders (ex. 'UPDATE table SET col=%s WHERE id=%s)
            param_values: Values to substitute in query
                - If the value is None, then query will already contain the values
                - If the value is tuple / dict, then the values are substituted into the query
                - If the value is list[tuple | dict], then we execute multiple UPDATE statements from the different values

        Raises:
            ValueError: if connection DNE
            ValueError: if cursor object DNE 
            ValueError: if there is no update statement

            DataError: If the data does not match the requested type (ex. Attempting to upload an int value into a column requiring json data)
            InternalError: If deadlock / internal error occurs
        """

        connection = self._get_connection()
        if not connection:
            raise ValueError("Connection object does not Exist")
    
        cursor = connection.cursor()
        if not cursor:
            raise ValueError("Cursor object does not Exist")
        
        if not query:
            raise ValueError("Query Statement does not Exist")
        
        if param_values is not None and not isinstance(param_values, (list, dict, tuple)):
            raise TypeError("param_values is of none of these types")

        #Update report_json into our database, then commit the change. If error, rollback transaction. Finally, close connection
        try:
            logger.info("Executing Query... ")
            if param_values is None:
                cursor.execute(query)
            elif isinstance(param_values, (tuple, dict)):
                cursor.execute(query, param_values)
            elif isinstance(param_values, list):
                cursor.executemany(query, param_values)
            connection.commit()
            logger.info("Transaction Committed")
        except DataError as e:
            logger.error(f"Invalid Data for one of the columns: {e}. \n Rolling back transaction")
            connection.rollback()
        except InternalError as e:
            logger.error(f"Internal error has occursed: {e} \n Rolling back transaction")
            connection.rollback()
        except ProgrammingError as e:
            logger.error(f"Syntax Error / Table not found error has occursed: {e} \n Rolling back transaction")
            connection.rollback()
        except Error as e:
            logger.error(f"Error has occured: {e} \n Rolling back transaction")
            connection.rollback()
        finally:
            logger.info("Closing Connection")
            cursor.close()
            connection.close()

    def update_course_data(self, report_json: Dict[str, Any], user_id: str, table: str) -> None:
        """
        Given course data (report_json) and the table of the database, it will update the 
        course_data column given the course_id in the report_json metadata. 

        Args:
            report_json (Dict[str, Any]): Report created in assignment_extraction_api
            user_id (str): ID of user that is logged in
            table (str): Name of table to insert to

        Raises:
            ValueError: if connection DNE
            ValueError: if cursor object DNE 
            ValueError: if course_id is DNE
            ValueError: if course_code is DNE

        Returns:
            _type_: None
        """
        
        try:
            if not report_json:
                logger.error("Report JSON is NULL")
                raise ValueError("Report JSON does not Exist")
            if not table:
                logger.error("Table is NULL / Not Entered")
                raise ValueError("Table not stated")

            course_id = report_json.get('metadata', {}).get('course_id', '')
            if not course_id: 
                return ValueError("Course ID not Found")
            course_id = int(course_id) #Convert to int because key in database is of integer

            course_code = report_json.get('metadata', {}).get('course_code', '')
            if not course_code:
                raise ValueError("Course Code not Found")

            #Course Term can be null
            course_term = report_json.get('metadata', {}).get("semester", "")

            #if self.show_table_exists_in_database(table=table):
            update_stmt = f"""
            INSERT INTO {table} (course_id, course_code, course_term, professor_id, course_data) VALUES (%(course_id)s, %(course_code)s, %(course_term)s, %(professor_id)s, %(course_data)s) 
            ON DUPLICATE KEY UPDATE course_data = %(course_data)s
            """
            param_values = {
                "course_id": course_id, 
                "course_code": course_code, 
                "course_term": course_term, 
                "professor_id": int(user_id), 
                "course_data": json.dumps(report_json)
            }
            self.insert_or_update_into_database(query=update_stmt, param_values=param_values)
        except ValueError as e:
            logger.error(f"Raised Value Error: {e}")
        except Exception as e:
            logger.error(f"Error has occured: {e}")
    






        






