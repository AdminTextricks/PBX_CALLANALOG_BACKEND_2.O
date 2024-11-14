#!/bin/bash

# Define variables
REMOTE_USER="root"
REMOTE_HOST="92.42.108.123"
SSH_PORT="18634"
SSH_KEY_PATH="/root/.ssh/id_rsa"

# The commands to run on the remote server
COMMANDS="
    sudo opensips-cli -x mi ul_dump | grep "AOR" | grep -v '"AORs": \[' | wc -l;
"

# Execute the commands on the remote server using SSH
ssh -i "$SSH_KEY_PATH" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "$COMMANDS"

