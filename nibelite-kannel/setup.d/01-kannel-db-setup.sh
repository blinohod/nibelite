#!/bin/sh

SQLFILE=/opt/nibelite/share/kannel/kannel.sql

# Create user and database in PostgreSQL
/usr/bin/createuser -U postgres --no-superuser --no-createdb --no-createrole kannel
/usr/bin/createdb -U postgres -O kannel -E UTF8 kannel

# Initialize DBMS for Kannel
/usr/bin/psql -U kannel kannel < $SQLFILE

