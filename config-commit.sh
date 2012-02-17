#!/bin/bash

## config stuff
watch_path="/var/tmp/configs"
match_patt="*juniper.conf.gz*"
git_base="/var/cache/git"

## begin script ##

## do the git commit
git_commit() {
  ## parse out what we want from the filename
  local hostname=$(echo ${2} | cut -f1 -d_)
  local date=$(echo ${2} | cut -f3 -d_)
  local utime=$(echo ${2} | cut -f4 -d_)
  ## check if project already exists
  if [ ! -d ${git_base}/${hostname} ]; then
    ## log it
    logit "Creating project repository ${git_base}/${hostname}"
    ## if it doesn't exist create it and initialize the repository
    mkdir -p ${git_base}/${hostname}
    git init ${git_base}/${hostname} > /dev/null 2>&1
    echo "Configuration archive for ${hostname}" > ${git_base}/${hostname}/.git/description
  fi
  ## extract and place in repo then delete uploaded file
  zcat ${1}${2} > ${git_base}/${hostname}/${hostname}.conf; rm ${1}${2}
  ## change directory to our project
  cd ${git_base}/${hostname}
  ## add it
  git add ${hostname}.conf > /dev/null 2>&1
  ## commit it
  git commit -m "Configuration update for ${hostname} on ${date}@${utime}UTC" > /dev/null 2>&1
  ## log it
  logit "Configuration for ${hostname} committed"
}

## check for matches
check_file() {
  ## log it
  logit "Detected file: ${1}${2}"
  ## check for pattern match
  if [[ ${1}${2} == ${match_patt} ]]; then
   ## commit the config file
   git_commit "${1}" "${2}"
  fi
}

## simple logging function
logit() {
  ## push message to syslog
  ${logger} -p local0.notice -t config-commit ${1}
}

## main loop
startit() {
  ## enter the wait loop
  inotifywait -q -m -r -e close_write ${watch_path} | while read temps; do
    ## cleanup text ... remove any non-wanted characters
    local line=$(echo ${temps}|sed -e 's/[^a-zA-Z0-9_\.\/\,\ ]//g')
    local fpath=$(echo ${line} | awk '{ print $1 }')
    local fname=$(echo ${line} | awk '{ print $3 }')
    ## check file after write completes
    check_file "${fpath}" "${fname}"
  done
}

## script init .. make sure watch path exists
if [ ! -d ${watch_path} ]; then
  mkdir -p ${watch_path}
fi

## start the loop
startit
