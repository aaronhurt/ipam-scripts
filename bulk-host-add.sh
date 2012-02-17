#!/bin/bash
#

## ona admin user info

ona_user="admin"
ona_pass="xx_pass_xx"

## usage ##

if [ $1x = "x" ]; then
	echo "USAGE: bulk-host-add.sh <subnet> <domain match> <location> [delete]"
	exit 0
fi

## generate host list
tmp=`mktemp`; /var/opt/ona/bin/dcm.pl -l $ona_user -p $ona_pass -r report_run name=ona_nmap_scans subnet=$1 format=csv > $tmp

## loop through list and add to database
for match in `cat $tmp|grep $2|cut -f2,3 -d,`; do
	ip=`echo $match|cut -f1 -d,`
	host=`echo $match|cut -f2 -d,`
	if [ $4x = "x" ]; then
		/var/opt/ona/bin/dcm.pl -l $ona_user -p $ona_pass -r host_add host=$host ip=$ip location=$3 notes='BULK ADD - CHANGE ME' type=6
	elif [ $4 = "delete" ]; then
		/var/opt/ona/bin/dcm.pl -l $ona_user -p $ona_pass -r host_del host=$host commit=yes
	fi
done

## remove temp file
rm $tmp
