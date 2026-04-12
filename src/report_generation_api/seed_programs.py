import re
import requests
from bs4 import BeautifulSoup
import mysql.connector
from datetime import datetime
import os
import time
import pymysql

from config import (
    MYSQL_HOSTNAME,
    MYSQL_USER,
    MYSQL_PASSWORD,
    MYSQL_DATABASE,
    MYSQL_PORT
)


# ASU major map URLs for supported programs
PROGRAM_URLS = [
    "https://degrees.apps.asu.edu/major-map/ASU00/ESCSEBS/null/ALL/2022",   # Computer Science BS
    "https://degrees.apps.asu.edu/major-map/ASU00/ESCSEBSE/null/ALL/2022",  # Computer Systems Engineering BSE
]


"""
This module is responsible for seeding the 'programs' table in the MySQL database with information about ASU programs.
It fetches program details from the ASU major map pages, extracts relevant information, and inserts it into the database.

This is doesn't use the get_database_connection function from getdatabaseConnection.py because below includes retry logic.
We want to ensure that the seeding process can handle cases where the database might not be immediately available, such as 
during initial setup or when running in a containerized environment. The retry logic will attempt to connect multiple times 
with a delay between attempts, improving the robustness of the seeding process.
"""
def connect_db(max_attempts=20, delay=3):
    for attempt in range(1, max_attempts + 1):
        try:
            connection = pymysql.connect(
                host=MYSQL_HOSTNAME,
                user=MYSQL_USER,
                password=MYSQL_PASSWORD,
                database=MYSQL_DATABASE,
                port=MYSQL_PORT,
                cursorclass=pymysql.cursors.DictCursor,
            )
            print("Connected to MySQL")
            return connection
        except pymysql.MySQLError as e:
            print(f"MySQL not ready yet (attempt {attempt}/{max_attempts}): {e}")
            time.sleep(delay)

    raise RuntimeError("Could not connect to MySQL after multiple attempts")

def get_program_name(bs: BeautifulSoup) -> str:
    """Extract program name from the ASU major map page."""
    #Using css selection to find the 2 span. This is based on the ASU website.
    element = bs.select_one(".left.mm_t1Div.majorMap-header span:nth-of-type(2)")
    print(f"Extracted program name element: {element}")
    if element:
        return element.get_text(strip=True)
    return ""

def get_program_year() -> str:
    now = datetime.now()
    return now.strftime("%Y")  # Get current year as string


def get_program_info(url: str) -> dict:
    """Extract program name and code from the ASU major map page."""
    headers = {
        "Accept": "text/html",
        "User-Agent": "Mozilla/5.0",
    }

    response = requests.get(url, headers=headers, timeout=30)
    response.raise_for_status()

    soup = BeautifulSoup(response.text, "html.parser")

    # Extract program name from page title or heading
    title = get_program_name(soup)
    program_name = title if title else ""
    program_name = re.sub(r"\s+", " ", program_name).strip()[:-1]

    # Extract program code from URL e.g. ESCSEBSE -> BSE is the degree code
    url_parts = url.split("/")
    program_code_raw = url_parts[5]  # e.g. ESCSEBSE or CSCSBSE
    program_code_match = re.search(r'(BSE|BS|MS|PHD)$', program_code_raw)
    program_code = program_code_match.group(0) if program_code_match else program_code_raw

    return {
        "program_name": program_name,
        "program_code": program_code,
        "program_year": get_program_year(),
        "url": url
    }


def seed_programs():
    """Fetch program info from ASU and insert into programs table."""
    db = connect_db()

    cursor = db.cursor()

    for url in PROGRAM_URLS:
        try:
            info = get_program_info(url)
            print(f"Found: {info['program_name']} - {info['program_code']} - {info['program_year']}")

            for i in range(4):
                # Insert only if program does not already exist
                cursor.execute("""
                    INSERT IGNORE INTO programs (program_name, program_code, program_year)
                    VALUES (%s, %s, %s)
                """, (info['program_name'], info['program_code'], info['program_year']))

                db.commit()
                print(f"Inserted: {info['program_name']} - {info['program_code']} - {info['program_year']}")  

                info['program_year'] = str(int(info['program_year']) - 1)  # Increment year for next entry  

        except Exception as e:
            print(f"Error processing {url}: {e}")

    cursor.close()
    db.close()


if __name__ == "__main__":
    seed_programs()
