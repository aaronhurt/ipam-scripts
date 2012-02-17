#!/bin/bash
## /usr/local/scripts/dbdump.sh
## last update 04.14.2011 by ahurt
##
####
#### NOTE: This script requires a ~/.my.cnf file
#### containing the user password to function.
#### The Script will fail if this file is not present.
####

## path to logfiles
LOG_BASE="/mnt/dump/ona-dbdump"

## path to database dumps
DUMP_BASE="/mnt/dump/ona-dbdump"

## number of backups to keep of each database
DUMP_KEEP="72"

## number of log files to keep
LOG_KEEP="72"

## regular expression of databases to skip
DUMP_SKIP="information_schema"

## get the current date info
DOW=$(date +"%a")
MOY=$(date "+%m")
DOM=$(date "+%d")
NOW=$(date "+%s")
CYR=$(date "+%Y")

## backup adn logfile names
NAMETAG="${MOY}${DOM}${CYR}-${NOW}"

## logfile naming convention
LOGFILE="${NAMETAG}.log"

######################
#### begin script ####
######################

## check file count and delete old
## syntax: check_old <path> <prefix> <ext> <count>
check_old() {
   ## declare old file array
   declare -a files
   ## initialize index
   local index=0
   ## find existing files
   for file in `find ${1} -maxdepth 1 -type f -name ${2}\*${3}`; do
      ## append logs to array with creation time
      files[$index]="$(stat --format %Y ${file})\t${file}\n"
      ## increase index
      let "index += 1"
   done
   ## set file count
   local fcount=${#files[@]}
   ## check count ... if greater than keep loop ... delete
   if [ $fcount -gt ${4} ]; then
      ## build new array in descending age order and reset index
      declare -a sfiles; local index=0
      ## loop through existing array
      for file in `echo -e ${files[@]:0} | sort -rn | cut -f2`; do
         ## append log to array
         sfiles[$index]=${file}
         ## increase index
         let "index += 1"
      done
      ## delete old files
      echo "Deleting old files: ${sfiles[@]:${4}} ..."
      rm -rf ${sfiles[@]:${4}}
   fi
}

## exit 0 and delete old log files
## syntax: exit_clean <message>
exit_clean() {
   ## print errors
   if [ "${1}x" != "x" ] && [ ${1} != 0 ]; then
      echo "Last operation returned error code: ${1}"
   fi
   ## always exit 0
   echo "Exiting..."
   exit 0
}

## lockfile creation and maintenance
check_lock () {
   ## check our lockfile status
   if [ -f "${1}" ]; then
      ## get lockfile contents
      local lpid=`cat "${1}"`
      ## see if this pid is still running
      local ps=`ps auxww|grep $lpid|grep -v grep`
      if [ "${ps}x" != 'x' ]; then
         ## looks like it's still running
         echo "ERROR: This script is already running as: $ps"
      else
         ## well the lockfile is there...stale?
         echo "ERROR: Lockfile exists: '${1}'"
         echo -n "However, the contents do not match any "
         echo "currently running process...stale lock?"
      fi
      ## tell em what to do...
      echo -n "To run script please delete: "
      echo "'${1}'"
      ## exit...
      exit_clean
   else
      ## well no lockfile..let's make a new one
      echo "Creating lockfile: ${1}"
      echo $$ > "${1}"
   fi
}

## delete lockiles
clear_lock() {
   ## delete lockfiles...and that's all we do here
   if [ -f "${1}" ]; then
      echo "Deleting lockfile: ${1}"
      rm "${1}"
   fi
}

## do the dumps
do_dump() {
   ## make sure we aren't running this over another operation
   check_lock "${LOG_BASE}/.dbdump.lock"
   ## get list of local databases
   local db_list=$(mysqlshow | grep -vE '^\+|^\|\s+Databases\s+\|$' | awk '/^|/ { print $2 }' | \
	grep -vE ${DUMP_SKIP} | xargs echo)
   ## get ready to dump...
   echo "Preparing to dump databases: ${db_list} ..."
   ## loop through databases and dump
   for db in ${db_list}; do
     ## set dumpfile name
     local dump=${DUMP_BASE}/dbdump-${db}-${NAMETAG}.gz
     echo "Dumping ${db} to ${dump} ..."
     mysqldump ${db} | gzip -c > ${dump}
     echo "Dump of ${db} complete."
     ## check dumps
     check_old ${DUMP_BASE} dbdump-${db} gz ${DUMP_KEEP}
   done
   ## clear lock file
   clear_lock "${LOG_BASE}/.dbdump.lock"
}

## it all starts here...
doit() { 
   ## check for ~/.my.cnf
   if [ ! -f ~/.my.cnf ]; then
     echo "ERROR: Could not find \"~/.my.cnf\""
     exit_clean
   fi
   ## do the dumps
   echo "Starting backup of all database..."
   do_dump
   ## check log files
   check_old ${LOG_BASE} dbdump- log ${LOG_KEEP}
   ## that's it...
   echo "Finished all operations"
   ## exit clean
   exit_clean
}

## make sure our log dir exits
[ ! -d ${LOG_BASE} ] && mkdir -p ${LOG_BASE}

## make sure our dump dir exists
[ ! -d ${DUMP_BASE} ] && mkdir -p ${DUMP_BASE}

## this is where it all starts
doit > "${LOG_BASE}/dbdump-${LOGFILE}" 2>&1
