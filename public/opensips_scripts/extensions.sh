#!/bin/bash

# Define variables
REMOTE_USER="root"
REMOTE_HOST="92.42.108.123"
SSH_PORT="18634"
SSH_KEY_PATH="/var/www/.ssh/id_rsa"

# The commands to run on the remote server
COMMANDS="
sudo opensips-cli -x mi ul_dump | awk '/AOR/ {aor=\$2} /User-agent/ {received=\$2} /Received/ {print \"AOR:\", aor, \"| User-agent:\", received, \"| Received:\", \$0}';



    "

# Execute the commands on the remote server using SSH
ssh -i "$SSH_KEY_PATH" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "$COMMANDS"

