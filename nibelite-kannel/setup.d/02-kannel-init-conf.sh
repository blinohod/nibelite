#!/bin/sh

SHAREDIR=/opt/nibelite/share/kannel
CONFDIR=$SHAREDIR/conf

STAMP=`/bin/date '+%Y%m%d'`

/sbin/service kannel.smsbox stop
/sbin/service kannel.bearerbox stop

echo -ne "Backup original Kannel configuration..."
tar zcf /tmp/kannel-conf-backup-$STAMP.tar.gz /etc/kannel
echo " done"

echo -ne "Remove original configuration..."
rm -rf /etc/kannel/*
echo " done"

echo -ne "Copy new configuration..."
# Install default configuration files
/bin/cp -rp $CONFDIR/* /etc/kannel
echo " done"

/sbin/service kannel.bearerbox start
/sbin/service kannel.smsbox start

/bin/mv /tmp/kannel-conf-backup-$STAMP.tar.gz /etc/kannel

echo "==================================================="
echo "Kannel configuration was changed for Nibelite."
echo
echo "You can find old configuration backup here:"
echo /etc/kannel/kannel-conf-backup-$STAMP.tar.gz
echo "==================================================="
