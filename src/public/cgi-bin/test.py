#!/usr/bin/env python3
import os
import sys
import html
from datetime import datetime, timezone

print("Content-Type: text/html; charset=utf-8")
print()

now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S %Z")
remote_addr = html.escape(os.environ.get("REMOTE_ADDR", "unknown"))
script_name = html.escape(os.environ.get("SCRIPT_NAME", "unknown"))
query_string = html.escape(os.environ.get("QUERY_STRING", ""))
python_bin = html.escape(sys.executable)

print(f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>CGI Python Test</title>
  <style>
    body {{ font-family: Arial, sans-serif; margin: 2rem; }}
    code {{ background: #f4f4f4; padding: 2px 6px; border-radius: 4px; }}
  </style>
</head>
<body>
  <h1>CGI Python is working</h1>
  <p><strong>UTC time:</strong> {now}</p>
  <p><strong>Your IP:</strong> {remote_addr}</p>
  <p><strong>Script:</strong> <code>{script_name}</code></p>
  <p><strong>Query string:</strong> <code>{query_string}</code></p>
  <p><strong>Python executable:</strong> <code>{python_bin}</code></p>
</body>
</html>""")
