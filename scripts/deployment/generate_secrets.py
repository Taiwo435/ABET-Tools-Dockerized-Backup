#!/usr/bin/env python3

"""
Generate secrets for nektos/act to simulate GitHub Actions.
outputs into a .secrets file in the project directory, which is used by nektos/act to set secrets for the simulated GitHub Actions environment.

This pulls the private key from $REPO_ROOT/.ssh/id_abet_ed25519 and sets it as a secret named ABET_PRIVATE_KEY.
"""

import os
import sys
import subprocess

# REPO_ROOT=$(git rev-parse --show-toplevel)
REPO_ROOT = subprocess.run(["git", "rev-parse", "--show-toplevel"], capture_output=True, text=True).stdout.strip()

secrets = dict()

def main():
    # Read the private key from the file
    private_key_path = os.path.join(REPO_ROOT, ".ssh", "id_abet_ed25519")
    if not os.path.isfile(private_key_path):
        print(f"[ERROR] Private key file '{private_key_path}' not found.")
        sys.exit(1)

    with open(private_key_path, "r") as f:
        private_key = f.read().strip()

    secrets["ABET_PRIVATE_KEY"] = private_key

    # Write secrets to .secrets file
    with open(os.path.join(REPO_ROOT, ".secrets"), "w") as f:
        for key, value in secrets.items():
            f.write(f"{key}=\"{value}\"\n")

if __name__ == "__main__":
    main()