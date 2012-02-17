#!/bin/sh

## check for root
if [ $(id -u) != 0 ]; then
  echo "Error ... This script requires root permissions."
  exit 1
fi

## test config file syntax before reconfig
/usr/sbin/named-checkconf -t /var/named > /dev/null 2>&1

## check return
if [ $? == 0 ]; then
  echo "Syntax OK ... Reloading named configuration."
  ## call rndc to reload configuration files
  /usr/sbin/rndc reconfig
else
  echo "Syntax Error ... Config test returned non-zero. Named configuration not reloaded."
fi
