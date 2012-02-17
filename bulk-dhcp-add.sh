#!/bin/bash
#

## ona admin user info
ona_user="admin"
ona_pass="xx_pass_xx"

## dhcp server host
dhcp_host="pldhcp01"

## nameserver address
name_server="192.168.1.47"

## usage
if [ $1x = "x" ]; then
	echo "USAGE: bulk-dhcp-add.sh <location> [delete]"
	exit 0
fi

## set location
location=$1

## create a temp file
temp=`mktemp`

## pull hosts from dhcp server
ssh $dhcp_host "cat /etc/dhcp3/*.conf|grep fixed-address|egrep -v '^#|^\s+#'" > $temp

## loop through list and add to database
for match in `cat $temp | grep -i $location | awk '{print $2";"$6$8}'`; do

	name=`echo $match|cut -f1 -d";"`
	mac=`echo $match|cut -f2 -d";"`
	host=`echo $match|cut -f3 -d";"`

	case $host in
		*franklinamerican*)
			ip=`/usr/bin/host $host $name_server|tail -1|cut -f4 -d" "`
			if [ $? != 0 ]; then
				echo "ERROR: Could not resolve $host -> $ip"
				sleep 5
				continue
			fi
		;;
		*)
			ip=$host
			host=$name.$location.franklinamerican.com
		;;
	esac

	echo -en "name=$name\nmac=$mac\nhost=$host\nip=$ip\n\n\n"

	if [ $? = 0 ]; then
		if [ ${2}x = "x" ]; then
			## switch to map old locations to new
			case $1 in
				acy)
					loc="mjx"
				;;
				*)
					loc="$1"
				;;
			esac
			/var/opt/ona/bin/dcm.pl -l $ona_user -p $ona_pass -r host_add host=$host ip=$ip mac=$mac location=$loc notes='BULK ADD - CHANGE ME' type=6
		elif [ ${2} = "delete" ]; then
			/var/opt/ona/bin/dcm.pl -l $ona_user -p $ona_pass -r host_del host=$host commit=yes
		fi
	else
		echo "ERROR: Failed to resolve $host -> $temp"
	fi
done

## delete temp file
rm $temp
