
insert into users (id, username, password, level) values ('', 'sys_build', '638c0b71a1677183e7840ae6b5b646a2', 0 ) on duplicate key update username='sys_build';

insert into sys_config (name, value, description, field_validation_rule, failed_rule_text, editable, deleteable) values ('build_dhcp_type', 'isc', 'DHCP build type', '', '', 1, 1) on duplicate key update value='isc';
