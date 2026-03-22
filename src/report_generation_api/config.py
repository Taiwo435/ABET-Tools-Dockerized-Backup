import os
from pathlib import Path
from dotenv import load_dotenv

print("Loading configuration...")
# Locate project root (where .env should live)
BASE_DIR = Path(__file__).resolve().parents[2]

# Load .env only if it exists (dev environments)
env_file = BASE_DIR / "docker" / ".env"
if env_file.exists():
    load_dotenv(env_file, override=False)
    print(".env loaded")
else:
    print(".env not found")


# --- Database configuration ---
MYSQL_HOSTNAME = os.getenv("MYSQL_HOSTNAME", "mysql")
MYSQL_USER = os.getenv("MYSQL_USER")
MYSQL_PASSWORD = os.getenv("MYSQL_PASS")
MYSQL_DATABASE = os.getenv("MYSQL_DATABASE")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", 3306))

# --- API keys ---
OPENAI_API_KEY = os.getenv("OPENAI_API_KEY")

# --- Validate required variables ---
required_vars = {
    "MYSQL_USER": MYSQL_USER,
    "MYSQL_PASSWORD": MYSQL_PASSWORD,
    "MYSQL_DATABASE": MYSQL_DATABASE,
}

missing = [key for key, value in required_vars.items() if not value]

if missing:
    raise RuntimeError(
        f"Missing required environment variables: {', '.join(missing)}"
    )