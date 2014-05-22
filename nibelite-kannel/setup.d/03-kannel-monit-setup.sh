#!/bin/sh

CONF=/opt/nibelite/share/kannel/kannel.monit

echo "Rewriting monit configuration"
[ -f /etc/monitrc.d/kannel ] && cp -fv /etc/monitrc.d/kannel /tmp/kannel.monit
cat $CONF > /etc/monitrc.d/kannel
