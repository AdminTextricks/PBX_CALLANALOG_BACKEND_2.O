#!/bin/bash

# Define variables
REMOTE_USER="root"
REMOTE_HOST="92.42.108.123"
SSH_PORT="18634"
SSH_KEY_PATH="/root/.ssh/id_rsa"

# The commands to run on the remote server
COMMANDS="
    sudo opensips-cli -x mi sql_cacher_reload company_id;
    sudo opensips-cli -x mi sql_cacher_reload extension_id;
    sudo opensips-cli -x mi sql_cacher_reload tfn_id;
    sudo opensips-cli -x mi ul_dump | grep "AOR" | grep -v '"AORs": \[' | wc -l;
    opensips-cli -x mi ul_dump | awk '/AOR/ {aor=\$2} /User-agent/ {print \"AOR:\", aor, \"| User-agent:\", \$0}';
"

# Execute the commands on the remote server using SSH
ssh -i "$SSH_KEY_PATH" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "$COMMANDS"

