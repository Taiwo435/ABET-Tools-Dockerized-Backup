"""
provides a helper function to add a user in the database with a given email and password
connecets to the db and adds them with said permissions

running this on prod would be bad, huh?
"""
import mysql.connector
import os

mydb = mysql.connector.connect(
  host = "127.0.0.1",
  user = os.environ["MYSQL_USER"],
  password = os.environ["MYSQL_PASS"],
  database = os.environ["MYSQL_DATABASE"]
) 

def add_db_user(email: str, raw_password: str):
    pass