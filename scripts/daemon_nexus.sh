#!/bin/bash

# === MySQL Connection Info ===
DB_HOST="localhost"
DB_USER="root"
DB_PASS="your_mysql_password"
DB_NAME="users_db"

# Paths to your external scripts
NEXUS_SCRIPT="/etc/scripts/nexus_backup.sh"
BACKUP_4948_SCRIPT="/etc/scripts/4948_backup.sh"

# Query the database and loop through results
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -D "$DB_NAME" -B -e \
"SELECT ip, username,  password,  location  FROM cisco;" | \
tail -n +2 | while IFS=$'\t' read -r ip username pass  location 
do

    # Debug output to check values

        echo "Running 4948_backup.sh for $ip"
        "$NEXUS_SCRIPT" "$ip" "$username" "$pass" "$location"
done
