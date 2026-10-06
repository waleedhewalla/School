#!/bin/sh
# Dumps the database and archives uploaded files into /backups, then
# deletes backups older than KEEP_DAYS. Copy /backups off the server
# (inside Saudi Arabia, for PDPL) with your provider's object storage.
set -e
stamp=$(date +%Y%m%d-%H%M)
pg_dump --format=custom --no-owner --file="/backups/db-$stamp.dump"
tar -czf "/backups/files-$stamp.tar.gz" -C /files .
find /backups -type f -mtime +"${KEEP_DAYS:-14}" -delete
echo "backup $stamp done"
