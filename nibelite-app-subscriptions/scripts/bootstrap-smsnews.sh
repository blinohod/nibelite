#!/bin/sh

CFGFILE="/opt/nibelite/etc/common.d/db.main.conf"
NIBELITE_PATH="/opt/nibelite/bin"

NA="$NIBELITE_PATH/nibelite-auth"
NS="$NIBELITE_PATH/nibelite-services"
PG="/usr/bin/psql"
SQL_SCRIPT="/opt/nibelite/share/sql/smsnews/smsnews.sql"

if [ -n "$1" ]; then
  if [ -f "$1" ]; then
    CFGFILE="$1"
  else
    echo "Error: configuration file $1 does not exist" 1>&2
    exit 1
  fi
fi

$PG -U nibelite nibelite < $SQL_SCRIPT

# Add service to core.services
$NS srv-add ctl-smsnews --uri="smsnews.php" --descr="SMS News GUI" --visible=1
$NS action-add ctl-smsnews.access

$NA group-add smsnews

$NS action-group-add ctl-smsnews.access admin
$NS action-group-add ctl-smsnews.access smsnews

