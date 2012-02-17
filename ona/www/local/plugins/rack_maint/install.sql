
CREATE TABLE IF NOT EXISTS `racks` (
  `id` int(10) NOT NULL,
  `name` varchar(64) NOT NULL,
  `description` varchar(255) NOT NULL,
  `size` int(10) NOT NULL COMMENT 'How many U are in this rack',
  `location_id` int(10) NOT NULL COMMENT 'location of rack',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Stores info about physical racks';


CREATE TABLE IF NOT EXISTS `rack_assignments` (
  `id` int(10) NOT NULL,
  `rack_id` int(10) NOT NULL,
  `device_id` int(10) NOT NULL,
  `position` int(2) NOT NULL COMMENT 'Which U is this device in',
  `depth` int(1) NOT NULL COMMENT 'Is it 1,2,3,4 depth in quarters.',
  `size` int(2) NOT NULL COMMENT 'How many U this device consumes',
  `mounted_from` int(1) NOT NULL COMMENT 'Is the device in the front(1) or the back(2) of the rack',
  `alt_name` varchar(64) NOT NULL COMMENT 'if there is no ONA device, put another name here',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='Position of a device within a rack';
