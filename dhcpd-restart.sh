#!/bin/sh

## test config file syntax using params from /etc/rc.conf.local
/usr/local/sbin/dhcpd -t `sed -n -e 's/^iscdhcpd_flags=\"\(.*\)\"$/\1/p' /etc/rc.conf.local` > /dev/null 2>&1

## check return
if [ $? == 0 ]; then
  echo "Syntax OK ... Restarting dhcpd."
  ## kill dhcpd by pid chroot file
  if [ -f /var/dhcpd/var/run/dhcpd/dhcpd.pid ]; then
    kill `cat /var/dhcpd/var/run/dhcpd/dhcpd.pid`
  fi
  ## wait for dhcpd to die
  sleep 5
  ## pkill
  pkill dhcpd
  ## start dhcpd with params from /etc/rc.conf.local
  /usr/local/sbin/dhcpd `sed -n -e 's/^iscdhcpd_flags=\"\(.*\)\"$/\1/p' /etc/rc.conf.local`
else
  echo "Syntax Error ... Config test returned non-zero.  Services not restarted."
fi
