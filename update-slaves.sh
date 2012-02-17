#!/bin/bash
## manage remote config changes on slave dhcp and dns servers
## last update: 07.06.2011
## by ahurt
#

## script start time
START=$(date +%s.%N)

## ona database
dbase="ona_default"

## ona database user
duser="ona_sys"

## ona user password
dpass="ona2011"

## master dns server IP address
master="172.20.192.145"

## remote dns slaves and paths
## format: host|zonefile name|temp path|final path|zonefile path
rdns="pnnasdns01|slave.conf|/var/tmp|/var/named/etc|slave \
pnnasdns02|slave.conf|/var/tmp|/var/named/etc|slave \
pndfwdns01|slave.conf|/var/tmp|/var/named/etc|slave \
pndfwdns02|slave.conf|/var/tmp|/var/named/etc|slave \
pndmzdns01|slave.conf|/var/tmp|/var/named/etc|slave \
pndmzdns02|slave.conf|/var/tmp|/var/named/etc|slave \
pndmzdns03|slave.conf|/var/tmp|/var/named/etc|slave \
pndmzdns04|slave.conf|/var/tmp|/var/named/etc|slave"

## remote dhcp slaves and paths
## format: host|config name|temp path|final path
rdhcp="pnnasdhcp01|dhcpd.conf.local|/var/tmp|/var/dhcpd/etc \
pndfwdhcp01|dhcpd.conf.local|/var/tmp|/var/dhcpd/etc"

## location of cache and lock files
cachedir="/usr/local/scripts/cache"

## mysql client bin
mysql=`which mysql`

## sqlite client bin
sqlite=`which sqlite3`

## diff bin
diff=`which diff`

## zonenotify bin
zonenotify=`which zonenotify`

## begin script ##

## remove lock and exit
exit_clean() {
  rm "${cachedir}/.update_lock"
  local rtime=$(echo "$(date +%s.%N) - $START" | bc)
  logit "Finished in $rtime seconds"
  exit 0
}

## check lock file .. are we still running
check_lock() {
  ## lockfile name
  local lfile="${cachedir}/.update_lock"
  if [ -f "${lfile}"  ]; then
    ## get contents
    local lpid=`cat "${lfile}"`
    ## check if we are still running
    local ps=`ps auxww | grep $lpid | grep -v grep`
    if [ "${ps}x" != 'x' ]; then
      ## looks like we're still running
      return 1
    else
      ## well the lockfile is there...stale?
      return 2
    fi
  else
    ## well no lockfile..let's make a new one
    echo $$ > "${lfile}"
    return 0
  fi
}

## push confs to remote servers
push_config() {
  logit "Pushing configuration to $2"
  ## send a single ping to the host
  ping -c 1 -q -W 1 $2 > /dev/null 2>&1
  ## only continue if host is alive
  if [ $? != 0 ]; then
    logit "Skipping $2 - host down"
  else
    logit "Pushing $3 to ${2}:${5}/${4}"
    scp ${3} ona@${2}:${5}/${4} > /dev/null 2>&1
    logit "Moving ${2}:${5}/${4} to ${2}:${6}/${4}"
    ssh ona@${2} "sudo mv ${5}/${4} ${6}/${4}" > /dev/null 2>&1
    logit "Setting permissions on $2 ${6}/${4}"
    case ${1} in
      dns)
        ssh ona@${2} "sudo chown root:wheel ${6}/${4}" > /dev/null 2>&1
        logit "Reloading named config on $2"
        ssh ona@${2} "sudo /usr/local/scripts/named-reload.sh" > /dev/null 2>&1
      ;;
      dhcp)
        ssh ona@${2} "sudo chown root:wheel ${6}/${4}" > /dev/null 2>&1
        logit "Restarting dhcpd on $2"
        ssh ona@${2} "sudo /usr/local/scripts/dhcpd-restart.sh" > /dev/null 2>&1
      ;;
    esac
  fi
}

## generate and check configs for listed servers
check_confs() {
  ## loop through config lines
  for foo in ${2}; do
    ## get server and path
    local rhost=`echo $foo | cut -f1 -d'|'`
    local rname=`echo $foo | cut -f2 -d'|'`
    local rtemp=`echo $foo | cut -f3 -d '|'`
    local rpath=`echo $foo | cut -f4 -d '|'`
    local zpath=`echo $foo | cut -f5 -d '|'`
    ## set vars depending on server type
    case ${1} in
      dns)
        ## set dns filename
        local fname=${cachedir}/${rhost}.named.conf
      ;;
      dhcp)
        ## set dhcp filename
        local fname=${cachedir}/${rhost}.dhcp.conf
      ;;
    esac
    ## check if file already exists
    if [ -f ${fname} ]; then
      ## create a copy of the old file
      cp -f ${fname} ${fname}.old
    else
      ## create a blank file ... this is probably a new server
      touch ${fname}.old
    fi
    ## another switch for the dcm command
    case ${1} in
      dns)
        ## build dns config through dcm
        /var/opt/ona/bin/dcm.pl -r build_bind_conf server=$rhost path=$zpath | sed s/{[\ ]*}/\{\ $master\;\ \}/g \
        > ${fname}
      ;;
      dhcp)
        ## build dhcp config through dcm
        /var/opt/ona/bin/dcm.pl -r build_dhcpd_conf server=$rhost > ${fname}
      ;;
    esac
    ## check for changes and ignore comments
    $diff -I '^#' ${fname}.old ${fname} > /dev/null 2>&1
    if [ $? != 0 ]; then
      ## files have changed push config
      push_config ${1} $rhost $fname $rname $rtemp $rpath
    fi
  done
}

## send zone notify to hosts
send_notify() {
  logit "Sending zone notify for ${1} to ${2}"
  ${zonenotify} ${1} ${2} > /dev/null 2>&1
  if [ $? != 0 ]; then
    ## something didn't go right - display proper error message
    ## error codes taken from zonenotify.h dns_errors
    case $? in
      1)
        logit "Error: zonenotify returned non-zero - Format error"
      ;;
      2)
        logit "Error: zonenotify returned non-zero - Server failure"
      ;;
      3)
        logit "Error: zonenotify returned non-zero - Non-existent domain"
      ;;
      4)
        logit "Error: zonenotify returned non-zero - Not implemented"
      ;;
      5)
        logit "Error: zonenotify returned non-zero - Query refused"
      ;;
      6)
        logit "Error: zonenotify returned non-zero - Name exists when it should not"
      ;;
      7)
        logit "Error: zonenotify returned non-zero - PR Set exists when it should not"
      ;;
      8)
        logit "Error: zonenotify returned non-zero - PR Set that should exist does not"
      ;;
      9)
        logit "Error: zonenotify returned non-zero - Server not authoritative for zone"
      ;;
      10)
        logit "Error: zonenotify returned non-zero - Name not contained in zone"
      ;;
      16)
        logit "Error: zonenotify returned non-zero - Bad OPT version"
      ;;
      17)
        logit "Error: zonenotify returned non-zero - TSIG signature failure"
      ;;
      18)
        logit "Error: zonenotify returned non-zero - Key not recognized"
      ;;
      19)
        logit "Error: zonenotify returned non-zero - Signature out of time window"
      ;;
      20)
        logit "Error: zonenotify returned non-zero - Bad TKEY mode"
      ;;
      21)
        logit "Error: zonenotify returned non-zero - Duplicate key name"
      ;;
      22)
        logit "Error: zonenotify returned non-zero - Algorithm not supported"
      ;;
      *)
        logit "Error: zonenotify returned non-zero - Unknown error"
      ;;
    esac        
  fi
}

## run sql queries
do_query() {
  case "$1" in
    mysql) 
      local res=`echo "$2" | $mysql --skip-column-names --user=$duser --password=$dpass $dbase`
    ;;
    sqlite)
      local res=`$sqlite ${cachedir}/serial_cache.db "$2"`
    ;;
  esac
  echo $res
}

## check zone serials
check_zones() {
  ## loop through database results
  for zone in `do_query 'mysql' "select zone from dns_records where type = 'soa'"`; do
    ## flag zone clean
    local clean=0
    ## set local current serial
    local lserial=`do_query 'mysql' "select serial from dns_records where type = 'soa' and zone = '$zone'"`
    ## set cached serial
    local cserial=`do_query 'sqlite' "select serial from cache where zone = '$zone'"`
    ## compare serials ... if different we need to trigger an update
    if [ "$lserial" != "$cserial" ]; then
      ## loop through remote slaves for the zone
      for client in `do_query 'mysql' "select inet_ntoa(interfaces.ip_addr) AS client from ( \
	(domains join dns_server_domains on (dns_server_domains.domain_id = domains.id)) \
	join interfaces on (dns_server_domains.host_id = interfaces.host_id) \
	join hosts on (hosts.id = interfaces.host_id) \
	join dns on (dns.interface_id = interfaces.id)) \
	WHERE dns.id = hosts.primary_dns_id AND domains.name = '$zone'"`; do
        ## build client list
        local clients="$clients $client"
      done
      ## clean up client list
      local clients=$(echo $clients | sed s/^'[ ]*'//g)
      ## send update to all clients
      send_notify $zone "$clients"
    fi
    ## update cached serial for this zone
    local res=`do_query 'sqlite' "insert or replace into cache ( zone, serial ) values ( '$zone', '$lserial' )"`
  done
}

## simple logging function
logit() {
  ## push message to stdout
  echo ${1}
  ## push message to syslog
  logger -p user.notice -t update-slaves ${1}
}

## start it all
doit() {
  ## check and create lockfile
  check_lock
  ## check status of lock ... stop on non zero
  if [ $? != 0 ]; then
    logit "Lockfile found ... exiting"
    exit 0
  fi
  ## check and push dhcp config files
  check_confs dhcp "${rdhcp}"
  ## check and push dns config files
  check_confs dns "${rdns}"
  ## check and push zones
  check_zones
  ## clean locks and exit
  exit_clean
}
doit
