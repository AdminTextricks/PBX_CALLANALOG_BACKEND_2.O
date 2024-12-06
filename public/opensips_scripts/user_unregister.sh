#!/bin/bash

# Define variables
REMOTE_USER="root"
REMOTE_HOST="92.42.108.123"
SSH_PORT="18634"
SSH_KEY_PATH="/var/www/.ssh/id_rsa"

# The commands to run on the remote server
#COMMANDS="
#    sudo opensips-cli -x mi ul_dump | grep "AOR" | grep -v '"AORs": \[' | wc -l;
#"

# Check if the required parameter is passed
if [ -z "$1" ]; then
  echo "Usage: $0 <user_id>"
  exit 1
fi

# Get the user ID from the first argument
USER_ID=$1

# Construct and execute the dynamic command
COMMANDS="opensips-cli -x mi ul_rm location {$USER_ID}"


# Execute the commands on the remote server using SSH
ssh -i "$SSH_KEY_PATH" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "$COMMANDS"

