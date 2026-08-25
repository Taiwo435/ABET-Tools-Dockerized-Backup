"""
provides a helper function to add a user in the database with a given email and password
connecets to the db and adds them with said permissions

running this on prod would be bad, huh?
"""
import mysql.connector
import os

# Precomputed bcrypt hash of the default TEST_PASSWORD ("superSecretPassword1!"),
# generated the same way the app itself hashes passwords (PHP's password_hash,
# PASSWORD_BCRYPT). Avoids pulling in a Python bcrypt dependency just for tests.
DEFAULT_TEST_PASSWORD = "superSecretPassword1!"
DEFAULT_TEST_PASSWORD_HASH = "$2y$10$KJ4Va5FlpbHfVa.eTywtMucyXA64xGY5.7Si2VFfch1crzK8u8D7e"

# Bit values from App\Entity\Permissions (abet_private/src/Entity/User.php)
ROLE_ADMIN = 1 << 0
ROLE_ASSIGNMENTS_GRADES = 1 << 1
ROLE_CANVAS_FORMATTING = 1 << 2
ROLE_REPORTGEN = 1 << 3
ROLE_FACULTY_FORM = 1 << 4
ROLE_COORDINATOR_FORM = 1 << 5
ROLE_ALL = (
    ROLE_ADMIN
    | ROLE_ASSIGNMENTS_GRADES
    | ROLE_CANVAS_FORMATTING
    | ROLE_REPORTGEN
    | ROLE_FACULTY_FORM
    | ROLE_COORDINATOR_FORM
)


def _connect():
    return mysql.connector.connect(
        host=os.environ.get("MYSQL_TEST_HOST", "127.0.0.1"),
        user=os.environ["MYSQL_USER"],
        password=os.environ["MYSQL_PASS"],
        database=os.environ["MYSQL_DATABASE"],
    )


def add_db_user(
    email: str,
    raw_password: str = DEFAULT_TEST_PASSWORD,
    permissions: int = ROLE_ALL,
    is_active: bool = True,
) -> None:
    """
    Inserts (or replaces) a user directly in the database, bypassing the
    UI registration/email-verification flow entirely. Only intended for
    test setup — this is why it insists on a password hash that matches
    the app's own hashing scheme rather than accepting a raw stored
    password.
    """
    password_hash = (
        DEFAULT_TEST_PASSWORD_HASH
        if raw_password == DEFAULT_TEST_PASSWORD
        else _bcrypt_hash(raw_password)
    )

    conn = _connect()
    try:
        cursor = conn.cursor()
        cursor.execute("DELETE FROM users WHERE email = %s", (email,))
        cursor.execute(
            """
            INSERT INTO users (email, password_hash, is_active, permissions)
            VALUES (%s, %s, %s, %s)
            """,
            (email, password_hash, 1 if is_active else 0, permissions),
        )
        conn.commit()
    finally:
        conn.close()


def remove_db_user(email: str) -> None:
    conn = _connect()
    try:
        cursor = conn.cursor()
        cursor.execute("DELETE FROM users WHERE email = %s", (email,))
        conn.commit()
    finally:
        conn.close()


def _bcrypt_hash(raw_password: str) -> str:
    """
    Only used for a non-default password. Requires the optional `bcrypt`
    package (not in requirements.txt by default, since every current test
    uses DEFAULT_TEST_PASSWORD and never hits this path).
    """
    import bcrypt

    return bcrypt.hashpw(raw_password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")
