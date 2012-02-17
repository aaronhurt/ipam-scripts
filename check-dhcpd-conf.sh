#!/bin/sh

## test config file syntax using params from /etc/rc.conf.local
/usr/local/sbin/dhcpd -t `sed -n -e 's/^iscdhcpd_flags=\"\(.*\)\"$/\1/p' /etc/rc.conf.local` > /dev/null 2>&1

## check return...exit critical on error
if [ $? != 0 ]; then
	echo "DHCP Config - Syntax ERROR - Service not reloading!"
	exit 2
fi
## exit 0 on everything okay
echo "DHCP Config - Syntax OK - Service functioning normally."
exit 0
