#!/bin/bash

do_lookup() {
	## initialize indexes
	local server_index=0; local serial_index=0
	## create tempfile with host output
	local temps=$(mktemp /tmp/host-check.XXXXXX)
	## read host output
	host -C ${1}|(while read line; do
		## find and add nameservers to array
		if [ "$(echo $line|grep Nameserver)x" != 'x' ]; then
			servers[$server_index]="$(echo $line|cut -f2 -d ' '|sed s/\://g)"
			## increase index
			let "server_index += 1"
		fi
		## find and add serials to array
		if [ "$(echo $line|grep SOA)x" != 'x' ]; then
			serials[$serial_index]="$(echo $line|cut -f7 -d ' ')"
			## increase index
			let "serial_index += 1"
		fi
	done
	## bash subshell bug workaround
	## store arrays to tempfile
	echo "local serials=( ${serials[@]} )" > $temps
	echo "local servers=( ${servers[@]} )" >> $temps)
	## return temp file
	echo $temps
}

check_it() {
	## call lookup function to create arrays
	local temps=$(do_lookup ${1})
	## source arrays and remote temp file
	source $temps; rm -f $temps
	## set default returns
	local ok=0
	local smsg="OK"
	## generate performance data
	local perf="${1}:"
	local index=0; while [ $index -lt ${#servers[@]} ]; do
		## write out performance data
		local perf="${perf} ${servers[$index]}@${serials[$index]}"
		## increase index
		let "index += 1"
	done
	## pull master serial
	local mserial=$(host -t SOA ${1} plnasipm01.franklinamerican.com|grep SOA|cut -f7 -d ' ')
	## create index
	local index=0
	## compare serials
	local index=0; for serial in ${serials[@]}; do
		if [ $mserial -ne ${serials[$index]} ]; then
			## oops...no match...calculate difference
			if [ $mserial -gt $serial ]; then
				diff=$(echo "$mserial - $serial"|bc)
			else
				diff=$(echo "$serial - $mserial"|bc)
			fi
			## critical if greater than 120
			if [ $diff -gt 120 ]; then
				local ok=2
			## warning if less than 120 and greater than 60
			elif [ $diff -lt 120 ] && [ $diff -gt 60 ]; then
				local ok=1
			fi
		fi
		## set status and break on 'ok' greater than 0
		if [ $ok -gt 0 ]; then
			local smsg="Serial for ${1} on ${servers[$index]} has drifted $diff seconds from master!"
			break
		fi
		## increase index
		let "index += 1"
	done
	## show output
	echo "REPLICATION - $smsg|$perf"
	## exit with correct code
	exit $ok
}

## check syntax
if [ "${1}x" == "x" ]; then
  echo "Usage: ${0} <zone>"
  exit 0
fi

## check it
check_it $1

