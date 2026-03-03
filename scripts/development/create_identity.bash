
# Prompt user for email
read -p "Enter your email address: " email

# Basic regex validation
if [[ "$email" =~ ^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$ ]]; then
    echo "Valid email: $email"
else
    echo "Invalid email format."
    exit 1
fi

ssh-keygen -t ed25519  -C "$email"  -f ~/.ssh/id_abet_ed25519 -N ""
