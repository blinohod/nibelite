#!/bin/sh

DBNAME=nibelite
DBUSER=nibelite

pushd /opt/nibelite/share/sql
psql -q -U $DBUSER $DBNAME -f init.psql
popd

