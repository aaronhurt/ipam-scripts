#!/bin/bash

## set server groups
INTERNAL_DNS="pnnasdns01 pnnasdns02 pndfwdns01 pndfwdns02"
EXTERNAL_DNS="pndmzdns01 pndmzdns02 pndmzdns03 pndmzdns04"

## end settings ##

## check syntax
if [ "${1}" != 'internal' ] && [ "${1}" != 'external' ]; then
	echo "Usage: $0 <internal|external>"; exit 1
fi

## make sure we are root
if [ $(id -u) != 0 ]; then
	echo "ERROR: This script must be run as root!"; exit 1
fi

## flush dns function
flush_dns() {
	echo -n "Flushing DNS resolver cache on ... "
	for foo in ${1}; do
		echo -n "$foo ... "
		ssh ona@$foo "sudo /usr/sbin/rndc flush" > /dev/null 2>&1
	done
	echo "done!"
}

## switch to call function with correct servers
case ${1} in
	internal)
		flush_dns "$INTERNAL_DNS"
	;;
	external)
		flush_dns "$EXTERNAL_DNS"
	;;
esac

## exit clean
exit 0
