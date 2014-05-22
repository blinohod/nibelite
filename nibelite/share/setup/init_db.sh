#!/bin/sh

CFGFILE="/opt/nibelite/etc/common.d/db.main.conf"
NIBELITE_PATH="/opt/nibelite/bin"

NA="$NIBELITE_PATH/nibelite-auth"
NS="$NIBELITE_PATH/nibelite-services"

if [ -n "$1" ]; then
	if [ -f "$1" ]; then
		CFGFILE="$1"
	else
		echo "Error: configuration file $1 does not exist" 1>&2
		exit 1
	fi
fi

# Add default users
$NA user-add admin --password=admin --descr="Platform super user; change password as soon as possible!" --conf="$CFGFILE"

# Add groups
$NA group-add admin
$NA group-add marketing
$NA group-add support

# Add old style services
$NS srv-add ctl-login --uri="login.php" --descr="Access control module" --visible=1 --conf="$CFGFILE"
$NS action-add ctl-login.access --conf="$CFGFILE"

$NS srv-add ctl-config --uri="config.php" --descr="Configuration GUI" --visible=1 --conf="$CFGFILE"
$NS action-add ctl-config.access --conf="$CFGFILE"

$NS srv-add ctl-billing --uri="billing.php" --descr="Reporting GUI" --visible=1 --conf="$CFGFILE"
$NS action-add ctl-billing.access --conf="$CFGFILE"

$NS srv-add ctl-messages --uri="messages.php" --descr="Support service GUI" --visible=1 --conf="$CFGFILE"
$NS action-add ctl-messages.access --conf="$CFGFILE"


# Add rights for old style GUI
$NS action-group-add ctl-login.access admin
$NS action-group-add ctl-login.access marketing
$NS action-group-add ctl-login.access support
$NS action-group-add ctl-config.access admin
$NS action-group-add ctl-billing.access admin
$NS action-group-add ctl-billing.access marketing
$NS action-group-add ctl-messages.access admin
$NS action-group-add ctl-messages.access marketing
$NS action-group-add ctl-messages.access support


# Add new style services
$NS srv-add core-apps --uri="internal" --descr="core-apps" --visible=0 --conf="$CFGFILE"
$NS srv-add core-apps_conf --uri="internal" --descr="core-apps_conf" --visible=0 --conf="$CFGFILE"
$NS srv-add core-i18n --uri="internal" --descr="core-i18n" --visible=0 --conf="$CFGFILE"
$NS srv-add core-portal --uri="internal" --descr="core-portal" --visible=0 --conf="$CFGFILE"
$NS srv-add core-setup --uri="internal" --descr="core-setup" --visible=1 --conf="$CFGFILE"
$NS srv-add core-users --uri="internal" --descr="core-users" --visible=0 --conf="$CFGFILE"
$NS srv-add core-legacy --uri="internal" --descr="Legacy menu" --visible=1 --conf="$CFGFILE"
$NS srv-add core-messages --uri="internal" --descr="Messages queue viewer" --visible=0 --conf="$CFGFILE"

exit 0
