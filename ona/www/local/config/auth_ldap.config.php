<?php

// Common settings and debugging
$conf['auth']['ldap']['debug'] = 'false';
$conf['auth']['ldap']['version'] = '3';
$conf['auth']['ldap']['server'] = 'ldap://windows.franklinamerican.com:389';

// Active Directory DN bind as user
$conf['auth']['ldap']['binddn'] = '%{user}@windows.franklinamerican.com';
$conf['auth']['ldap']['usertree'] = 'dc=windows,dc=franklinamerican,dc=com';
$conf['auth']['ldap']['userfilter']  = '(sAMAccountName=%{user})';
$conf['auth']['ldap']['grouptree'] = 'dc=windows,dc=franklinamerican,dc=com';
$conf['auth']['ldap']['groupfilter']  = '(&(cn=*)(Member=%{dn})(objectClass=group))';
$conf['auth']['ldap']['mapping']['grps'] = array('memberOf'=>'/cn=(.+?),/i');
$conf['auth']['ldap']['referrals'] = '0';
