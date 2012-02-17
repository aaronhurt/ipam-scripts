<?php
global $menuitem,$images;

$pluginpath = str_replace($base,'',dirname(__FILE__));

$menuitem = array(
// This is the title that shows in the menu itself, it is also the "alt" name.
'title' => 'Rack Maintenance',

// the silkicon image path would be /images/silk/<imagename>.png
// or to provide your own 16x16 image use the following: {$pluginpath}/menu_item_image.png
'image' => "/images/silk/server.png",

// The type of menuitem call to be made.
//   work_space:  this will do an ajax call to a work_space with the same name as your plugin. it will run the "ws_display" function
//   window:      this opens a floating window only
//'type' => 'work_space',
'type' => 'window',

// Defines the permission type to use for this item to appear.  leave blank for open access.
'authname' => 'advanced'

);

?>
