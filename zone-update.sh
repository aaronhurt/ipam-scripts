#!/bin/bash

## script start time
START=$(date +%s.%N)

## pull data in from host and parse
get_it() {
  ## set index
  local index=0
  ## create tempfile to store results
  local temps=$(mktemp /tmp/zone-update.XXXXXX)
  ## get data via host
  host -l -a ${1} ${2}|grep IN|grep -v '^;'|(while read line; do
    ## parse out what we want for all records
    host[$index]=$(echo ${line}|sed 's/.$//'|awk '{print $1}')
    type[$index]=$(echo ${line}|awk '{print $4}')
    data[$index]=$(echo ${line}|sed 's/.$//'|awk '{print $5}')
    ## increase index
    let "index += 1"
  done
  ## workaround subshell bug...store arrays in tempfile
  echo "local host=( ${host[@]} )" > $temps
  echo "local type=( ${type[@]} )" >> $temps
  echo "local data=( ${data[@]} )" >> $temps)
  ## return temp file
  echo $temps
}

## check data
check_it() {
  ## get data from lookup function
  local temps=$(get_it ${1} ${2})
  ## log it
  log_it "Checking records for ${1} from ${2}"
  ## source arrays and remove temp file
  source $temps; rm -f $temps > /dev/null 2>&1
  ## get host count and set index
  local count=${#host[@]}; local index=0
  ## loop through arrays and check hosts
  while [ $index -lt $count ]; do
    ## clean up host to use below ... trim training '.'
    local host=$(echo ${host[$index]}|sed 's/\.$//')
    ## switch based on record type
    case ${type[$index]} in
      A)
        ## handle check/add here
        log_it "Checking host: $host"
        check=$(/var/opt/ona/bin/dcm.pl -r host_display host=$host)
        if [ $? != 0 ]; then
          log_it "Adding host: $host"
          add=$(/var/opt/ona/bin/dcm.pl -r host_add host=$host type=6 ip=${data[$index]})
          if [ $? != 0 ]; then
            log_it "ERROR: $add"
          else
            log_it "OK"
          fi
        fi
      ;;
      PTR)
        ## do nothing
#        log_it "${host[$index]} IN ${type[$index]} ${data[$index]}"
      ;;
      CNAME)
        ## do nothing
#        log_it "${host[$index]} IN ${type[$index]} ${data[$index]}"
      ;;
      SOA)
        ## do nothing
#        log_it "${host[$index]} IN ${type[$index]} ${data[$index]}"
      ;;
      SRV)
        ## do nothing
#        log_it "${host[$index]} IN ${type[$index]} ${data[$index]}"
      ;;
      NS)
        ## do nothing
#        log_it "${host[$index]} IN ${type[$index]} ${data[$index]}"
      ;;
      AAAA)
        ## do nothing
        log_it "${host[$index]} IN ${type[$index]} ${data[$index]}"
      ;;
    esac
    ## increase index
    let "index += 1"
  done
}

## simple logging function
log_it() {
  ## push message to stdout
  echo ${1}
  ## push message to syslog
  #logger -p user.notice -t zone-update ${1}
}

## finish up
exit_clean() {
  local rtime=$(echo "$(date +%s.%N) - $START" | bc)
  log_it "Finished in $rtime seconds"
  exit 0
}

## start it
do_it() {
  ## check syntax
  if [ "${1}x" == "x" ] || [ "${2}x" == "x" ]; then
    echo "Usage: ${0} <zone> <nameserver>"
    exit 0
  fi
  ## log it
  log_it "Starting transfer and parsing of ${1} from ${2}"
  ## fire it off
  check_it ${1} ${2}
  ## exit
  exit_clean
}
do_it ${1} ${2}
