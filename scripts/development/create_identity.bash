#!/usr/bin/env bash
REPO_ROOT=$(git rev-parse --show-toplevel)

# Prompt user for email
if [ $# -ge 1 ]; then
    echo "First argument is: $1"
    email = $1
else
    read -p "Enter your email address: " email
fi

email_regex = "^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$"
# Basic regex validation
if [[ "$email" =~ $email_regex ]]; then
    echo "Valid email: $email"
else
    echo "Invalid email format: $email"
    exit 1
fi

ssh-keygen -t ed25519  -b 4096 -C "$email"  -f "$REPO_ROOT/.ssh/id_abet_ed25519" -N ""
