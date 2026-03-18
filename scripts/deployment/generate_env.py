#!/usr/bin/env python3

# reads an .env file and converts it into .htaccess SetEnv directives for the Apache container. 
# the goal is to have the environment variables be available in our dirctory, and only ours. 

import sys
import os
import shlex

def parse_env_file(filepath):
    env_vars = {}

    with open(filepath, "r") as f:
        for line in f:
            line = line.strip()

            # Skip empty lines and comments
            if not line or line.startswith("#"):
                continue

            # Split only on first =
            if "=" in line:
                key, value = line.split("=", 1)

                key = key.strip()
                value = value.split("#")[0].strip()

                env_vars[key] = value

    return env_vars


def convert_to_setenv(env_vars):
    for key, value in env_vars.items():
        # Escape value safely for Apache
        escaped_value = value.replace('"', '\\"')
        print(f'SetEnv {key} "{escaped_value}"')


def main():
    if len(sys.argv) < 2:
        print("Usage: python env_to_setenv.py <path_to_env_file>")
        sys.exit(1)

    env_file = sys.argv[1]

    if not os.path.isfile(env_file):
        print(f"Error: File '{env_file}' not found.")
        sys.exit(1)

    env_vars = parse_env_file(env_file)
    convert_to_setenv(env_vars)


if __name__ == "__main__":
    main()