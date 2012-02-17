<?php

// Lets do some initial install related stuff
if (file_exists(dirname(__FILE__)."/install.php")) {
    printmsg("DEBUG => Found install file for rack_maint plugin.", 1);
    include(dirname(__FILE__)."/install.php");
} else {


// TODO: MP: does having a rack list like this truely scale?? I guess it will do for now.. 

// Check permissions
if (!auth('advanced')) {
    $window['js'] = "alert('Permission denied!'); removeElement('{$window_name}');";
    return;
}

// Set the window title:
$window['title'] = "Rack List";


// Load some html into $window['html']
$form_id = "{$window_name}_filter_form";
$tab = 'racks';
$submit_window = $window_name;
$content_id = "{$window_name}_list";
$window['html'] .= <<<EOL
    <!-- Tabs & Quick Filter -->
    <table width="100%" cellspacing="0" border="0" cellpadding="0" >
        <tr>
            <td id="{$form_id}_{$tab}_tab" nowrap="true" class="table-tab-active">
                Racks <span id="{$form_id}_{$tab}_count"></span>
            </td>

            <td id="{$form_id}_quick_filter" class="padding" align="right" width="100%">
                <form id="{$form_id}" onSubmit="return false;">
                <input id="{$form_id}_page" name="page" value="1" type="hidden">
                <input name="content_id" value="{$content_id}" type="hidden">
                <input name="form_id" value="{$form_id}" type="hidden">
                <div id="{$form_id}_filter_overlay"
                     style="position: relative;
                            display: inline;
                            color: #CACACA;
                            cursor: text;"
                     onClick="this.style.display = 'none'; el('{$form_id}_filter').focus();"
                >Name</div>
                <input
                    id="{$form_id}_filter"
                    name="filter"
                    class="filter"
                    type="text"
                    value=""
                    size="10"
                    maxlength="20"
                    alt="Quick Filter"
                    onFocus="el('{$form_id}_filter_overlay').style.display = 'none';"
                    onBlur="if (this.value == '') el('{$form_id}_filter_overlay').style.display = 'inline';"
                    onKeyUp="
                        if (typeof(timer) != 'undefined') clearTimeout(timer);
                        code = 'if ({$form_id}_last_search != el(\'{$form_id}_filter\').value) {' +
                               '    {$form_id}_last_search = el(\'{$form_id}_filter\').value;' +
                               '    document.getElementById(\'{$form_id}_page\').value = 1;' +
                               '    xajax_window_submit(\'{$submit_window}\', xajax.getFormValues(\'{$form_id}\'), \'display_list\');' +
                               '}';
                        timer = setTimeout(code, 700);"
                >
                </form>
            </td>

        </tr>
    </table>

    <!-- Item List -->
    <div id='{$content_id}'>
        {$conf['loading_icon']}
    </div>
EOL;







// Define javascript to run after the window is created
$window['js'] = <<<EOL
    /* Put a minimize icon in the title bar */
    el('{$window_name}_title_r').innerHTML =
        '&nbsp;<a onClick="toggle_window(\'{$window_name}\');" title="Minimize window" style="cursor: pointer;"><img src="{$images}/icon_minimize.gif" border="0" /></a>' +
        el('{$window_name}_title_r').innerHTML;

    /* Put a help icon in the title bar */
    el('{$window_name}_title_r').innerHTML =
        '&nbsp;<a href="{$_ENV['help_url']}{$window_name}" target="null" title="Help" style="cursor: pointer;"><img src="{$images}/silk/help.png" border="0" /></a>' +
        el('{$window_name}_title_r').innerHTML;

    /* Setup the quick filter */
    el('{$form_id}_filter_overlay').style.left = (el('{$form_id}_filter_overlay').offsetWidth + 10) + 'px';
    {$form_id}_last_search = '';

    /* Tell the browser to load/display the list */
    xajax_window_submit('{$submit_window}', xajax.getFormValues('{$form_id}'), 'display_list');
EOL;




}













// This function displays a list (all?) racks in the DB
function ws_display_list($window_name, $form) {
    global $conf, $self, $onadb;
    global $font_family, $color, $style, $images;

    // Check permissions
    if (!auth('advanced')) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // If the group supplied an array in a string, build the array and store it in $form
    $form = parse_options_string($form);

    // Find out what page we're on
    $page = 1;
    if ($form['page'] and is_numeric($form['page'])) { $page = $form['page']; }


    $html = <<<EOL

    <!-- Results Table -->
    <table cellspacing="0" border="0" cellpadding="0" width="100%" class="list-box">

        <!-- Table Header -->
        <tr>
            <td class="list-header" align="center" style="{$style['borderR']};">Name</td>
            <td class="list-header" align="center" style="{$style['borderR']};">Size</td>
            <td class="list-header" align="center" style="{$style['borderR']};">Location</td>
            <td class="list-header" align="center" style="{$style['borderR']};">Description</td>
            <td class="list-header" align="center">&nbsp;</td>
        </tr>

EOL;

    $where = '`id` > 0';
    if (is_array($form) and $form['filter']) {
        $where = '`name` LIKE ' . $onadb->qstr('%'.$form['filter'].'%');
    }
    // Offset for SQL query
    $offset = ($conf['search_results_per_page'] * ($page - 1));
    if ($offset == 0) { $offset = -1; }

    // Get our groups
    list($status, $rows, $records) = db_get_records($onadb, 'racks', $where, 'name', $conf['search_results_per_page'], $offset);

    // If we got less than serach_results_per_page, add the current offset to it
    // so that if we're on the last page $rows still has the right number in it.
    if ($rows > 0 and $rows < $conf['search_results_per_page']) {
        $rows += ($conf['search_results_per_page'] * ($page - 1));
    }

    // If there were more than $conf['search_results_per_page'] find out how many records there really are
    else if ($rows >= $conf['search_results_per_page']) {
        list ($status, $rows, $tmp) = db_get_records($onadb, 'racks', $where, '', 0);
    }
    $count = $rows;


    // Loop through and display the groups
    foreach ($records as $record) {

        list ($status, $rows, $loc) = db_get_record($onadb, 'locations', "id={$record['location_id']}");

        // Escape data for display in html
        foreach(array_keys($record) as $key) {
            $record[$key] = htmlentities($record[$key], ENT_QUOTES);
        }

        $html .= <<<EOL
        <tr onMouseOver="this.className='row-highlight'" onMouseOut="this.className='row-normal'">

            <td class="list-row">
                <a title="View rack. ID: {$record['id']}"
                   class="act"
                   onClick="xajax_window_submit('work_space','xajax_window_submit(\'rack_maint\', \'id=>{$record['id']}\', \'display\')');toggle_window('{$window_name}');"
                >{$record['name']}</a>&nbsp;
            </td>

            <td class="list-row">
                {$record['size']} Units&nbsp;
            </td>

            <td class="list-row">
                {$loc['reference']}&nbsp;
            </td>

            <td class="list-row">
                {$record['description']}&nbsp;
            </td>

            <td align="right" class="list-row" nowrap="true">
                <a title="Edit rack. ID: {$record['id']}"
                    class="act"
                    onClick="xajax_window_submit('rack_maint', 'id=>{$record['id']}', 'rack_editor');"
                ><img src="{$images}/silk/page_edit.png" border="0"></a>&nbsp;

                <a title="Delete rack: ID: {$record['id']}"
                    class="act"
                    onClick="var doit=confirm('Are you sure you want to delete this rack?');
                            if (doit == true)
                                xajax_window_submit('{$window_name}', 'id=>{$record['id']}', 'rack_delete');"
                ><img src="{$images}/silk/delete.png" border="0"></a>&nbsp;
            </td>

        </tr>
EOL;
    }

    $html .= <<<EOL
    </table>

    <!-- Add a new record -->
    <div class="act-box" style="padding: 2px 4px; border-top: 1px solid {$color['border']}; border-bottom: 1px solid {$color['border']};">
        <!-- ADD LINK -->
        <a title="New rack"
            class="act"
            onClick="xajax_window_submit('rack_maint', ' ', 'rack_editor');"
        ><img src="{$images}/silk/page_add.png" border="0"></a>&nbsp;

        <a title="New rack"
            class="act"
            onClick="xajax_window_submit('rack_maint', ' ', 'rack_editor');"
        >Add new rack</a>&nbsp;
    </div>
EOL;


    // Build page links if there are any
    $html .= get_page_links($page, $conf['search_results_per_page'], $count, $window_name, $form['form_id']);


    // Insert the new table into the window
    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $response->addAssign("{$form['form_id']}_racks_count",  "innerHTML", "({$count})");
    $response->addAssign("{$form['content_id']}", "innerHTML", $html);
    // $response->addScript($js);
    return($response->getXML());
}













///////////////////////////////////////////////////////////////////////
//  Function: rack_add (string $options='')
//
//  Input Options:
//    $options = key=value pairs of options for this function.
//               multiple sets of key=value pairs should be separated
//               by an "&" symbol.
//
//  Output:
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = rack_add('host=test&type=something');
///////////////////////////////////////////////////////////////////////
function rack_add($options="") {
    global $conf, $self, $onadb;

    // Version - UPDATE on every edit!
    $version = '1.00';

    printmsg("DEBUG => rack_add({$options}) called", 3);

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Return the usage summary if we need to
    if ($options['help'] or !($options['name'] and $options['size']) ) {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

rack_add-v{$version}
Add a new rack

  Synopsis: rack_add [KEY=VALUE] ...

  Required:
    name=NAME                 Name of the rack
    size=INT                  Size of rack in units
    description=STRING        Description
    location=STRING|id        Location of rack


EOM
        ));
    }

    // Check permissions
    if (!auth('advanced')) {
        $self['error'] = "Permission denied!";
        printmsg($self['error'], 0);
        return(array(10, $self['error'] . "\n"));
    }

    // sanitize the numeric values
    if (!is_numeric($options['size'])) {
        $self['error'] = "ERROR => The specified size was not numeric!";
        printmsg($self['error'],3);
        return(array(2, $self['error'] . "\n"));
    }


    // Standardize names to be in upper case and spaces are converted to -'s.
    $options['name'] = trim($options['name']);
    $options['name'] = preg_replace('/\s+/', '-', $options['name']);
    $options['name'] = strtoupper($options['name']);

    // check the rack name
    list($status, $rows, $rackcheck) = db_get_records($onadb, 'racks', "name='{$options['name']}'");
    if ($status or $rows) {
        printmsg("ERROR => There is already a rack with that name!",3);
        $self['error'] = "ERROR => There is already a rack with that name!";
        return(array(30, $self['error'] . "\n"));
    }

    // check the rack name
    if ($options['location']) {
        list($status, $rows, $loc) = ona_find_location($options['location']);
        if ($status or !$rows) {
            printmsg("ERROR => Unable to find specified location: {$options['location']}!",3);
            $self['error'] = "ERROR => Unable to find specified location: {$options['location']}!";
            return(array(31, $self['error'] . "\n"));
        }
        $options['location'] = $loc['id'];
    }

    // Get the next ID for the new dns record
    $id = ona_get_next_id('racks');
    if (!$id) {
        $self['error'] = "ERROR => The ona_get_next_id('racks') call failed!";
        printmsg($self['error'], 0);
        return(array(7, $self['error'] . "\n"));
    }
    printmsg("DEBUG => ID for new rack record: $id", 3);

    // There is an issue with escaping '=' and '&'.  We need to avoid adding escape characters
    $options['description'] = str_replace('\\=','=',$options['description']);
    $options['description'] = str_replace('\\&','&',$options['description']);

    // Add the dns record
    list($status, $rows) = db_insert_record(
        $onadb,
        'racks',
        array(
            'id'                => $id,
            'size'              => $options['size'],
            'description'       => $options['description'],
            'location_id'       => $options['location'],
            'name'              => $options['name']
       )
    );
    if ($status or !$rows) {
        $self['error'] = "ERROR => rack_add() SQL Query failed adding record: " . $self['error'];
        printmsg($self['error'], 0);
        return(array(6, $self['error'] . "\n"));
    }


    // Else start an output message
    $text = "INFO => Added rack '{$options['name']}' with a size of: {$options['size']}.";
    printmsg($text,0);
    $text .= "\n";

    // Return the success notice
    return(array(0, $text));
}












///////////////////////////////////////////////////////////////////////
//  Function: rack_assignment_add (string $options='')
//
//  Input Options:
//    $options = key=value pairs of options for this function.
//               multiple sets of key=value pairs should be separated
//               by an "&" symbol.
//
//  Output:
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = rack_assignment_add('host=test&type=something');
///////////////////////////////////////////////////////////////////////
function rack_assignment_add($options="") {
    global $conf, $self, $onadb;

    // Version - UPDATE on every edit!
    $version = '1.00';

    printmsg("DEBUG => rack_assignment_add({$options}) called", 3);

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Return the usage summary if we need to
    if ($options['help'] or !($options['rack'] and $options['position']) ) {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

rack_assignment_add-v{$version}
Add a new rack unit record

  Synopsis: rack_assignment_add [KEY=VALUE] ...

  Required:
    device=NAME|ID             ONA device name or ID
     OR
    alt_name=NAME              If no ONA device, provide a name (I.E. Patch Panel)
    rack=NAME|ID               Name or id of the rack this device is in
    position=INT               Position of the first rack unit this device uses from top
    mounted_from=front|back    Device is inserted in rack from front or back
    size=INT                   Size of device in rack units
    depth=1|2|3|4|half|full    Depth of device in quarters


EOM
        ));
    }

    // Check permissions
    if (!auth('advanced')) {
        $self['error'] = "Permission denied!";
        printmsg($self['error'], 0);
        return(array(10, $self['error'] . "\n"));
    }

    // Find the rack
    list($status, $rows, $rack) = ona_get_record(array('id' => $options['rack']), 'racks');
    if ($status or !$rows) {
        $self['error'] = "ERROR => Unable to find rack: {$options['rack']}!";
        printmsg($self['error'],3);
        return(array(20, $self['error'] . "\n"));
    }

    // sanitize the numeric values
    if (!is_numeric($options['size'])) {
        $self['error'] = "ERROR => The specified size was not numeric!";
        printmsg($self['error'],3);
        return(array(2, $self['error'] . "\n"));
    }
    if (!is_numeric($options['position'])) {
        $self['error'] = "ERROR => The specified position was not numeric!";
        printmsg($self['error'],3);
        return(array(2, $self['error'] . "\n"));
    }

    // size cant be more than rack total size
    if ($options['size'] > $rack['size']) {
        $self['error'] = "ERROR => Your allocation size is larger than the rack itself!";
        printmsg($self['error'],3);
        return(array(2, $self['error'] . "\n"));
    }

    // the position we start in the rack plus the size we are adding cant exceed rack total size
    if ($options['position']+$options['size'] > $rack['size']+1) {
        $self['error'] = "ERROR => Your allocation size and position extends beyond the rack itself!";
        printmsg($self['error'],3);
        return(array(2, $self['error'] . "\n"));
    }



    // Sanitize the mounted from values and get the opposite value
    $mnt = strtolower($options['mounted_from']);
    if ($mnt == 'front' or $mnt == 'f' or $mnt == '1') {
        $options['mounted_from'] = '1';
        $mnt_opposite = '2';
    }
    else if ($mnt == 'back' or $mnt == 'b' or $mnt == '2') {
        $options['mounted_from'] = '2';
        $mnt_opposite = '1';
    }
    else {
        $self['error'] = "ERROR => Invalid mounted_from type '{$options['mounted_from']}'!";
        printmsg($self['error'],3);
        return(array(2, $self['error'] . "\n"));
    }



    // Sanitize the depth values and get the opposite value    // depth must be 1-4
    $d = strtolower($options['depth']);
    if ($d == 'full' or $d == 'f' or $d == '4') {
        $options['depth'] = '4';
    }
    else if ($d == '3') {
        $options['depth'] = '3';
    }
    else if ($d == 'half' or $d == 'h' or $d == '2') {
        $options['depth'] = '2';
    }
    else if ($d == '1') {
        $options['depth'] = '1';
    }
    else {
        $self['error'] = "ERROR => Invalid depth type '{$options['depth']}'!";
        printmsg($self['error'],3);
        return(array(3, $self['error'] . "\n"));
    }



    // Check that our device does not overlap with any other devices
    // Generate SQL for each position
    for ($i=$options['position']; $i <= $options['position']+$options['size']-1; $i++) {
        if ($possql) $possql = ' '.$possql.' or ';
        $possql .= "{$i} between position and position+size-1";
    }

    // check the positions
    list($status, $rows, $rackcheck) = db_get_records($onadb, 'rack_assignments', "rack_id={$rack['id']} and mounted_from={$options['mounted_from']} and ({$possql})");
    if ($status or $rows) {
        printmsg("ERROR => This device overlaps an existing rack assignment due to its position or size!",3);
        $self['error'] = "ERROR => This device overlaps an existing rack assignment due to its position or size!";
        return(array(30, $self['error'] . "\n"));
    }

    // check the depth
    list($status, $rows, $tmp) = db_get_records($onadb, 'rack_assignments', "rack_id={$rack['id']} and mounted_from={$mnt_opposite} and {$options['depth']} > (4-depth) and ({$possql})");
    if ($status or $rows) {
        printmsg("ERROR => This device overlaps an existing rack assignment due to its position and depth!",3);
        $self['error'] = "ERROR => This device overlaps an existing rack assignment due to its position and depth!";
        return(array(40, $self['error'] . "\n"));
    }

    // Deal with devices or alt names
    if ($options['alt_name']) {
        $device['id'] = 0;
    }
    // find the device and use it.
    if ($options['device']) {
        list($status, $rows, $device) = ona_find_device($options['device']);
        if ($status or !$rows) {
            $self['error'] = "ERROR => Unable to find device using: {$options['device']}!";
            printmsg($self['error'],3);
            return(array(40, $self['error'] . "\n"));
        }

        // check that this device is not already assigned to another rack location
        list($status, $rows, $tmp) = db_get_records($onadb, 'rack_assignments', "device_id = {$device['id']}");
        if ($status or $rows) {
            $self['error'] = "ERROR => This device is already assigned to a rack: {$options['device']}!";
            printmsg($self['error'],3);
            return(array(41, $self['error'] . "\n"));
        }
        $options['alt_name'] = '';
    }

    // Get the next ID for the new dns record
    $id = ona_get_next_id('rack_assignments');
    if (!$id) {
        $self['error'] = "ERROR => The ona_get_next_id('rack_assignments') call failed!";
        printmsg($self['error'], 0);
        return(array(7, $self['error'] . "\n"));
    }
    printmsg("DEBUG => ID for new rack assignment record: $id", 3);

    // There is an issue with escaping '=' and '&'.  We need to avoid adding escape characters
    //$options['notes'] = str_replace('\\=','=',$options['notes']);
    //$options['notes'] = str_replace('\\&','&',$options['notes']);

    // Add the dns record
    list($status, $rows) = db_insert_record(
        $onadb,
        'rack_assignments',
        array(
            'id'                => $id,
            'rack_id'           => $rack['id'],
            'device_id'         => $device['id'],
            'position'          => $options['position'],
            'depth'             => $options['depth'],
            'size'              => $options['size'],
            'mounted_from'      => $options['mounted_from'],
            'alt_name'          => $options['alt_name']
       )
    );
    if ($status or !$rows) {
        $self['error'] = "ERROR => rack_assignment_add() SQL Query failed adding record: " . $self['error'];
        printmsg($self['error'], 0);
        return(array(6, $self['error'] . "\n"));
    }


    // Else start an output message
    $text = "INFO => Rack assignment of {$options['size']} unit(s) in rack {$rack['name']} at position {$options['position']} successfull.";
    printmsg($text,0);
    $text .= "\n";

    // Return the success notice
    return(array(0, $text));
}













///////////////////////////////////////////////////////////////////////
//  Function: rack_modify (string $options='')
//
//  $options = key=value pairs of options for this function.
//             multiple sets of key=value pairs should be separated
//             by an "&" symbol.
//
//  Output:
//    Updates an rack record in the IP database.
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = rack_modify('alias=test&host=q1234.something.com');
///////////////////////////////////////////////////////////////////////
function rack_modify($options="") {
    global $conf, $self, $onadb;
    printmsg("DEBUG => rack_modify({$options}) called", 3);

    // Version - UPDATE on every edit!
    $version = '1.00';

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Return the usage summary if we need to
    if ($options['help'] or !(
                                ($options['rack'])
                                 and
                                ($options['set_name'] or
                                 $options['set_description'] or
                                 $options['set_location'] or
                                 $options['set_size'])
                              )
        )
    {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

rack_modify-v{$version}
Modifies a rack in the database

  Synopsis: rack_modify [KEY=VALUE] ...

  Where:
    rack=NAME|ID                   Name or ID of rack

  Optional:
    set_name=NAME                  Name of rack
    set_size=INT                   Size of device in rack units
    set_description=STRING         Description of rack
    set_location=STRING|ID         Location of rack

EOM
        ));
    }

    // find the rack to modify
    if (is_numeric($options['rack']))
        list($status, $rows, $orig_rack) = db_get_record($onadb, 'racks', array('id' => $options['rack']));
    else
        list($status, $rows, $orig_rack) = db_get_record($onadb, 'racks', array('name' => $options['rack']));


    // Test to see that we were able to find the specified record
    if (!$orig_rack['id']) {
        printmsg("DEBUG => Unable to find a rack record: {$options['rack']}!",3);
        $self['error'] = "ERROR => Unable to find the rack record: {$options['rack']}!";
        return(array(4, $self['error']. "\n"));
    }

    printmsg("DEBUG => rack_modify(): Found entry, {$orig_rack['name']}", 3);


    // This variable will contain the updated info we'll insert into the DB
    $SET = array();

    // Deal with devices or alt names
    if ($options['set_name'] and $options['set_name'] != $orig_rack['name']) {

        // Standardize names to be in upper case and spaces are converted to -'s.
        $options['set_name'] = trim($options['set_name']);
        $options['set_name'] = preg_replace('/\s+/', '-', $options['set_name']);
        $options['set_name'] = strtoupper($options['set_name']);

        // check the rack name
        list($status, $rows, $rackcheck) = db_get_records($onadb, 'racks', "name='{$options['set_name']}'");
        if ($status or $rows) {
            printmsg("ERROR => There is already a rack with that name!",3);
            $self['error'] = "ERROR => There is already a rack with that name!";
            return(array(30, $self['error'] . "\n"));
        }
        $SET['name'] = $options['set_name'];
    }

    if ($options['set_description'] and $options['set_description'] != $orig_rack['description']) {
        // There is an issue with escaping '=' and '&'.  We need to avoid adding escape characters
        $options['set_description'] = str_replace('\\=','=',$options['set_description']);
        $options['set_description'] = str_replace('\\&','&',$options['set_description']);

        $SET['description'] = $options['set_description'];
    }

    // define the remaining entries
    if ($options['set_size'] and $options['set_size'] != $orig_rack['size']) {
        // sanitize the numeric values
        if (!is_numeric($options['set_size'])) {
            $self['error'] = "ERROR => The specified size was not numeric!";
            printmsg($self['error'],3);
            return(array(2, $self['error'] . "\n"));
        }

        $SET['size'] = $options['set_size'];
    }

    // check the rack name
    if ($options['set_location']) {
        list($status, $rows, $loc) = ona_find_location($options['set_location']);
        if ($status or !$rows) {
            printmsg("ERROR => Unable to find specified location: {$options['set_location']}!",3);
            $self['error'] = "ERROR => Unable to find specified location: {$options['set_location']}!";
            return(array(31, $self['error'] . "\n"));
        }
        if ($loc['id'] != $orig_rack['location_id']) $SET['location_id'] = $loc['id'];
    }

    // Check permissions
    if (!auth('advanced')) {
        $self['error'] = "Permission denied!";
        printmsg($self['error'], 0);
        return(array(10, $self['error'] . "\n"));
    }


    // Update the record
    list($status, $rows) = db_update_record($onadb, 'racks', array('id' => $orig_rack['id']), $SET);
    if ($status or !$rows) {
        $self['error'] = "ERROR => rack_modify() SQL Query failed: {$self['error']}";
        printmsg($self['error'],0);
        return(array(6, $self['error'] . "\n"));
    }

    // Get the entry again to display details
    list($status, $rows, $new_rack) = db_get_record($onadb, 'racks', "id={$orig_rack['id']}");


    // Return the success notice
    $self['error'] = "INFO => Rack UPDATED: {$new_rack['name']}";

    $log_msg = "INFO => Rack UPDATED:{$new_rack['id']}: ";
    $more="";
    foreach(array_keys($original_rack) as $key) {
        if($original_rack[$key] != $new_rack[$key]) {
            $log_msg .= $more . $key . "[" .$original_rack[$key] . "=>" . $new_rack[$key] . "]";
            $more= ";";
        }
    }

    // only print to logfile if a change has been made to the record
    if($more != '') {
        //printmsg($self['error'], 0);
        printmsg($log_msg, 0);
    }

    return(array(0, $self['error'] . "\n"));
}











///////////////////////////////////////////////////////////////////////
//  Function: rack_assignment_modify (string $options='')
//
//  $options = key=value pairs of options for this function.
//             multiple sets of key=value pairs should be separated
//             by an "&" symbol.
//
//  Output:
//    Updates an rack_assignment record in the IP database.
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = rack_assignment_modify('alias=test&host=q1234.something.com');
///////////////////////////////////////////////////////////////////////
function rack_assignment_modify($options="") {
    global $conf, $self, $onadb;
    printmsg("DEBUG => rack_assignment_modify({$options}) called", 3);

    // Version - UPDATE on every edit!
    $version = '1.00';

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Return the usage summary if we need to
    if ($options['help'] or !(
                                ($options['rack_assignment'])
                                 and
                                ($options['set_device'] or
                                 $options['set_alt_name'] or
                                 $options['set_position'] or
                                 $options['set_mounted_from'] or
                                 $options['set_size'] or
                                 $options['set_depth'])
                              )
        )
    {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

rack_assignment_modify-v{$version}
Modifies a rack_assignment in the database

  Synopsis: rack_assignment_modify [KEY=VALUE] ...

  Where:
    rack_assignment=ID             rack_assignment id

  Optional:
    set_device=NAME|ID             ONA device name or ID
     OR
    set_alt_name=NAME              If no ONA device, provide a name (I.E. Patch Panel)
    set_position=INT               Position of the first rack unit this device uses from top
    set_mounted_from=front|back    Device is inserted in rack from front or back
    set_size=INT                   Size of device in rack units
    set_depth=1|2|3|4|half|full    Depth of device in quarters

EOM
        ));
    }

    // Determine the entry itself exists
    list($status, $rows, $original_rack_assignment) = db_get_record($onadb, 'rack_assignments', "id={$options['rack_assignment']}");

    // Test to see that we were able to find the specified record
    if (!$original_rack_assignment['id']) {
        printmsg("DEBUG => Unable to find a rack_assignment record using ID {$options['rack_assignment']}!",3);
        $self['error'] = "ERROR => Unable to find the rack_assignment record using ID {$options['rack_assignment']}!";
        return(array(4, $self['error']. "\n"));
    }

    printmsg("DEBUG => rack_assignment_modify(): Found entry, {$entry['id']}", 3);


    // This variable will contain the updated info we'll insert into the DB
    $SET = array();

    // Deal with devices or alt names
    if ($options['set_alt_name']) {
        $SET['alt_name']  = $options['set_alt_name'];
        $SET['device_id'] = 0;
    }
    // find the device and use it.
    if ($options['set_device']) {
        list($status, $rows, $device) = ona_find_device($options['set_device']);
        if ($status or !$rows) {
            $self['error'] = "ERROR => Unable to find device using: {$options['set_device']}!";
            printmsg($self['error'],3);
            return(array(40, $self['error'] . "\n"));
        }

        if ($device['id'] != $original_rack_assignment['device_id']) {
            // check that this device is not already assigned to another rack location
            list($status, $rows, $tmp) = db_get_records($onadb, 'rack_assignments', "device_id = {$device['id']}");
            if ($status or $rows) {
                $self['error'] = "ERROR => This device is already assigned to a rack: {$options['device']}!";
                printmsg($self['error'],3);
                return(array(41, $self['error'] . "\n"));
            }

            $SET['device_id'] = $device['id'];
            $SET['alt_name'] = '';
        }
    }

    // define the remaining entries
    if ($options['set_size'])      $SET['size']      = $options['set_size'];
    if ($options['set_position'])  $SET['position']  = $options['set_position'];
    if ($options['set_depth'])     $SET['depth']     = $options['set_depth'];



    // Check permissions
    if (!auth('advanced')) {
        $self['error'] = "Permission denied!";
        printmsg($self['error'], 0);
        return(array(10, $self['error'] . "\n"));
    }


    // Update the record
    list($status, $rows) = db_update_record($onadb, 'rack_assignments', array('id' => $original_rack_assignment['id']), $SET);
    if ($status or !$rows) {
        $self['error'] = "ERROR => rack_assignment_modify() SQL Query failed: {$self['error']}";
        printmsg($self['error'],0);
        return(array(6, $self['error'] . "\n"));
    }

    // Get the entry again to display details
    list($status, $rows, $new_rack_assignment) = db_get_record($onadb, 'rack_assignments', "id={$original_rack_assignment['id']}");


    // Return the success notice
    $self['error'] = "INFO => Rack assignment UPDATED: {$new_rack_assignment['name']}";

    $log_msg = "INFO => Rack assignment UPDATED:{$new_rack_assignment['id']}: ";
    $more="";
    foreach(array_keys($original_rack_assignment) as $key) {
        if($original_rack_assignment[$key] != $new_rack_assignment[$key]) {
            $log_msg .= $more . $key . "[" .$original_rack_assignment[$key] . "=>" . $new_rack_assignment[$key] . "]";
            $more= ";";
        }
    }

    // only print to logfile if a change has been made to the record
    if($more != '') {
        //printmsg($self['error'], 0);
        printmsg($log_msg, 0);
    }

    return(array(0, $self['error'] . "\n"));
}








///////////////////////////////////////////////////////////////////////
//  Function: rack_del (string $options='')
//
//  $options = key=value pairs of options for this function.
//             multiple sets of key=value pairs should be separated
//             by an "&" symbol.
//
//  Input Options:
//    rack=NAME or ID
//
//  Output:
//    Deletes a rack from the IP database.
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = rack_del('rack=test');
///////////////////////////////////////////////////////////////////////
function rack_del($options="") {
    global $conf, $self, $onadb;
    printmsg("DEBUG => rack_del({$options}) called", 3);

    // Version - UPDATE on every edit!
    $version = '1.00';

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Sanitize options[commit] (default is yes)
    $options['commit'] = sanitize_YN($options['commit'], 'N');

    // Return the usage summary if we need to
    if ($options['help'] or !$options['rack'] ) {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

rack_del-v{$version}
Deletes a rack from the database

  Synopsis: rack_del [KEY=VALUE] ...

  Required:
    rack=NAME|ID      Name or ID of the rack to delete

  Optional:
    commit=[Y|N]      commit db transaction (no)
\n
EOM

        ));
    }


    // Check if it exists
    if (is_numeric($options['rack']))
        list($status, $rows, $entry) = db_get_record($onadb, 'racks', array('id' => $options['rack']));
    else
        list($status, $rows, $entry) = db_get_record($onadb, 'racks', array('name' => $options['rack']));

    // Test to see that we were able to find the specified record
    if (!$entry['id']) {
        printmsg("DEBUG => Unable to find a rack record using ID {$options['rack']}!",3);
        $self['error'] = "ERROR => Unable to find the rack record using ID {$options['rack']}!";
        return(array(4, $self['error']. "\n"));
    }

    // Debugging
    printmsg("DEBUG => rack_del(): Found entry, {$entry['id']}", 3);




    // If "commit" is yes, delete the record
    if ($options['commit'] == 'Y') {

        // Check permissions
        if (!auth('advanced')) {
            $self['error'] = "Permission denied!";
            printmsg($self['error'], 0);
            return(array(10, $self['error'] . "\n"));
        }

        // Delete rack assignments using this rack
        list($status, $rows) = db_delete_records($onadb, 'rack_assignments', array('rack_id' => $entry['id']));
        if ($status) {
            $self['error'] = "ERROR => rack_del() SQL Query failed: {$self['error']}";
            printmsg($self['error'],0);
            return(array(9, $self['error'] . "\n"));
        }

        // Delete actual rack
        list($status, $rows) = db_delete_records($onadb, 'racks', array('id' => $entry['id']));
        if ($status) {
            $self['error'] = "ERROR => rack_del() SQL Query failed: {$self['error']}";
            printmsg($self['error'],0);
            return(array(11, $self['error'] . "\n"));
        }

        // Return the success notice
        $self['error'] = "INFO => Rack DELETED: {$entry['name']}";
        printmsg($self['error'],0);
        return(array(0, $self['error'] . "\n"));
    }

    // Otherwise display the record that would have been deleted
//FIXME: make this better output display
    $text = <<<EOL
Record(s) NOT DELETED (see "commit" option)
Displaying record(s) that would have been deleted:

NAME: {$entry['name']}

EOL;

    return(array(6, $text));

}














///////////////////////////////////////////////////////////////////////
//  Function: rack_assignment_del (string $options='')
//
//  $options = key=value pairs of options for this function.
//             multiple sets of key=value pairs should be separated
//             by an "&" symbol.
//
//  Input Options:
//    rack_assignment=NAME or ID
//
//  Output:
//    Deletes a rack_assignment from the IP database.
//    Returns a two part list:
//      1. The exit status of the function.  0 on success, non-zero on
//         error.  All errors messages are stored in $self['error'].
//      2. A textual message for display on the console or web interface.
//
//  Example: list($status, $result) = rack_assignment_del('rack_assignment=test');
///////////////////////////////////////////////////////////////////////
function rack_assignment_del($options="") {
    global $conf, $self, $onadb;
    printmsg("DEBUG => rack_assignment_del({$options}) called", 3);

    // Version - UPDATE on every edit!
    $version = '1.00';

    // Parse incoming options string to an array
    $options = parse_options($options);

    // Sanitize options[commit] (default is yes)
    $options['commit'] = sanitize_YN($options['commit'], 'N');

    // Return the usage summary if we need to
    if ($options['help'] or !$options['rack_assignment'] ) {
        // NOTE: Help message lines should not exceed 80 characters for proper display on a console
        $self['error'] = 'ERROR => Insufficient parameters';
        return(array(1,
<<<EOM

rack_assignment_del-v{$version}
Deletes a rack_assignment from the database

  Synopsis: rack_assignment_del [KEY=VALUE] ...

  Required:
    rack_assignment=ID      ID of the rack_assignment to delete

  Optional:
    commit=[Y|N]            commit db transaction (no)
\n
EOM

        ));
    }


    // Check if it exists
    list($status, $rows, $entry) = db_get_record($onadb, 'rack_assignments', "id={$options['rack_assignment']}");

    // Test to see that we were able to find the specified record
    if (!$entry['id']) {
        printmsg("DEBUG => Unable to find a rack_assignment record using ID {$options['rack_assignment']}!",3);
        $self['error'] = "ERROR => Unable to find the rack_assignment record using ID {$options['rack_assignment']}!";
        return(array(4, $self['error']. "\n"));
    }

    // Debugging
    printmsg("DEBUG => rack_assignment_del(): Found entry, {$entry['id']}", 3);




    // If "commit" is yes, delete the record
    if ($options['commit'] == 'Y') {

        // Check permissions
        if (!auth('advanced')) {
            $self['error'] = "Permission denied!";
            printmsg($self['error'], 0);
            return(array(10, $self['error'] . "\n"));
        }

        // Delete actual rack_assignment
        list($status, $rows) = db_delete_records($onadb, 'rack_assignments', array('id' => $entry['id']));
        if ($status) {
            $self['error'] = "ERROR => rack_assignment_del() SQL Query failed: {$self['error']}";
            printmsg($self['error'],0);
            return(array(9, $self['error'] . "\n"));
        }




        // Return the success notice
        $self['error'] = "INFO => Rack assignment DELETED: {$entry['id']}";
        printmsg($self['error'],0);
        return(array(0, $self['error'] . "\n"));
    }

    // Otherwise display the record that would have been deleted
//FIXME: make this better outpud display
    $text = <<<EOL
Record(s) NOT DELETED (see "commit" option)
Displaying record(s) that would have been deleted:

NAME: {$entry['id']}

EOL;

    return(array(6, $text));

}







//////////////////////////////////////////////////////////////////////////////
// Function:
//     Display Edit Form
//
// Description:
//     Displays a form for creating/editing rack unit entries.
//     If a entry name is found in $form it is used to display an existing
//     entry for editing.  When "Save" is pressed the save()
//     function is called.
//////////////////////////////////////////////////////////////////////////////
function ws_rack_editor($window_name, $form='') {
    global $conf, $self, $onadb;
    global $font_family, $color, $style, $images;



    // Check permissions
    if (!auth('advanced')) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // If the user supplied an array in a string, build the array and store it in $form
    $form = parse_options_string($form);


    // Find the rack we are looking at
    //list($status, $rows, $rack) = db_get_record($onadb, 'racks', array('name' => $form['rack']));
    // Load the record
    if (is_numeric($form['id']))
        list($status, $rows, $rack) = db_get_record($onadb, 'racks', array('id' => $form['id']));
    else
        list($status, $rows, $rack) = db_get_record($onadb, 'racks', array('name' => $form['id']));

    // get location info
    list ($status, $rows, $loc) = db_get_record($onadb, 'locations', "id={$rack['location_id']}");
    if ($loc['id']) $rack['location_id'] = $loc['reference'];

    // Set the window title:
    $window['title'] = "Add Rack";
    if ($rack['id'])
        $window['title'] = "Edit Rack";


    // Escape data for display in html
    foreach(array_keys((array)$rack) as $key) {$rack[$key] = htmlentities($rack[$key], ENT_QUOTES);}


    $window['js'] .= <<<EOL
        /* Put a minimize icon in the title bar */
        el('{$window_name}_title_r').innerHTML =
            '&nbsp;<a onClick="toggle_window(\'{$window_name}\');" title="Minimize window" style="cursor: pointer;"><img src="{$images}/icon_minimize.gif" border="0" /></a>' +
            el('{$window_name}_title_r').innerHTML;

        /* Put a help icon in the title bar */
        el('{$window_name}_title_r').innerHTML =
            '&nbsp;<a href="{$_ENV['help_url']}{$window_name}" target="null" title="Help" style="cursor: pointer;"><img src="{$images}/silk/help.png" border="0" /></a>' +
            el('{$window_name}_title_r').innerHTML;

        suggest_setup('location', 'suggest_location');
        el('name').focus();
EOL;


    // Load some html into $window['html']
    $window['html'] .= <<<EOL

    <!-- Simple Edit Form -->
    <form id="rack_edit_form" onSubmit="return false;">
    <input name="rack_id" type="hidden" value="{$rack['id']}">
    <table cellspacing="0" border="0" cellpadding="0" style="background-color: {$color['window_content_bg']}; padding-left: 20px; padding-right: 20px; padding-top: 5px; padding-bottom: 5px;">

        <tr>
            <td class="input_required" align="right" nowrap="true">
                Name
            </td>
            <td class="padding" align="left" width="100%">
                <input
                    name="name"
                    alt="Name of rack"
                    value="{$rack['name']}"
                    class="edit"
                    type="text"
                    size="25" maxlength="35"
                >
            </td>
        </tr>

        <tr>
            <td class="input_required" align="right">
                Size
            </td>
            <td class="padding" align="left" width="100%">
                <input
                    name="size"
                    alt="Size (Height) of rack"
                    value="{$rack['size']}"
                    class="edit"
                    type="text"
                    size="5" maxlength="5"
                >
            </td>
        </tr>

        <tr>
            <td align="right" nowrap="true">
                Description
            </td>
            <td class="padding" align="left" width="100%">
                <textarea name="description" class="edit" cols="35" rows="4">{$rack['description']}</textarea>
            </td>
        </tr>

        <tr>
            <td align="right"  nowrap="true">
                Location
            </td>
            <td class="padding" align="left" width="100%" nowrap="true">
                <input
                    id="location"
                    name="location"
                    alt="location"
                    value="{$rack['location_id']}"
                    class="edit"
                    type="text"
                    size="25" maxlength="35"
                >
                <div id="suggest_location" class="suggest"></div>
            </td>
        </tr>

        <tr>
            <td align="right" valign="top">
                &nbsp;
            </td>
            <td class="padding" align="right" width="100%">
                <input type="hidden" name="overwrite" value="{$overwrite}">
                <input class="edit" type="button" name="cancel" value="Cancel" onClick="removeElement('{$window_name}');">
                <input class="edit" type="button"
                    name="submit"
                    value="Save"
                    onClick="xajax_window_submit('{$window_name}', xajax.getFormValues('rack_edit_form'), 'rack_save');"
                >
            </td>
        </tr>

    </table>
    </form>

EOL;


    // Lets build a window and display the results
    return(window_open($window_name, $window));

}






//////////////////////////////////////////////////////////////////////////////
// Function:
//     Display Edit Form
//
// Description:
//     Displays a form for creating/editing rack unit entries.
//     If a entry name is found in $form it is used to display an existing
//     entry for editing.  When "Save" is pressed the save()
//     function is called.
//////////////////////////////////////////////////////////////////////////////
function ws_ru_editor($window_name, $form='') {
    global $conf, $self, $onadb;
    global $font_family, $color, $style, $images;



    // Check permissions
    if (!auth('advanced')) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // If the user supplied an array in a string, build the array and store it in $form
    $form = parse_options_string($form);
    $rackunit['mounted_from'] = $form['mounted_from'];

    // Find the rack we are looking at
    list($status, $rows, $rack) = db_get_record($onadb, 'racks', array('name' => $form['rack']));


    // Check that our device does not overlap with any other devices
    // Generate SQL for each position
    for ($i=$form['position']; $i <= $form['position']; $i++) {
        if ($possql) $possql = ' '.$possql.' or ';
        $possql .= "{$i} between position and position+size-1";
    }

    list($status, $rows, $rackunit) = db_get_record($onadb, 'rack_assignments', "rack_id={$rack['id']} and mounted_from={$form['mounted_from']} and ({$possql})");

    // get names for mount direction
    if (!$rackunit['mounted_from']) $rackunit['mounted_from'] = $form['mounted_from'];
    if ($rackunit['mounted_from'] == 1) $rackunit['mounted_from'] = 'front';
    if ($rackunit['mounted_from'] == 2) $rackunit['mounted_from'] = 'back';
    if (!$rackunit['position']) $rackunit['position'] = $form['position'];

    if ($rackunit['device_id']) {
        // FIXME: this wont work when the primary device id stuff is set up right!
        //list($status, $rows, $device) = ona_find_device($rackunit['device_id']);
        list($status, $rows, $device) = ona_get_host_record(array('device_id' => $rackunit['device_id']));
        if ($status or !$rows) {
            $self['error'] = "ERROR => Unable to find device using: {$rackunit['device_id']}!";
            printmsg($self['error'],3);
        }
        $rackunit['device'] = $device['fqdn'];
    }

    $depth_list = "<option value=\"\"></option>\n";
    $depths = array('1' => '1','2' => '2 (Half)','3' => '3','4' => '4 (Full)');
    foreach (array_keys((array)$depths) as $id) {
        //$device_types[$id] = htmlentities($device_types[$id]);
        $selected = '';

        if ($id == $rackunit['depth']) { $selected = 'SELECTED'; }
        $depth_list .= "<option value=\"{$id}\" {$selected}>{$depths[$id]}</option>\n";
    }

    // Set the window title:
    $window['title'] = "Add Rack Unit";
    if ($rackunit['id'])
        $window['title'] = "Edit Rack Unit";


    // Escape data for display in html
    foreach(array_keys((array)$rackunit) as $key) {$rackunit[$key] = htmlentities($rackunit[$key], ENT_QUOTES);}


    $window['js'] .= <<<EOL
        /* Put a minimize icon in the title bar */
        el('{$window_name}_title_r').innerHTML =
            '&nbsp;<a onClick="toggle_window(\'{$window_name}\');" title="Minimize window" style="cursor: pointer;"><img src="{$images}/icon_minimize.gif" border="0" /></a>' +
            el('{$window_name}_title_r').innerHTML;

        /* Put a help icon in the title bar */
        el('{$window_name}_title_r').innerHTML =
            '&nbsp;<a href="{$_ENV['help_url']}{$window_name}" target="null" title="Help" style="cursor: pointer;"><img src="{$images}/silk/help.png" border="0" /></a>' +
            el('{$window_name}_title_r').innerHTML;

        suggest_setup('hostname', 'suggest_hostname');
        el('position').focus();
EOL;


    // Load some html into $window['html']
    $window['html'] .= <<<EOL

    <!-- Simple Edit Form -->
    <form id="ru_edit_form" onSubmit="return false;">
    <input name="rack" type="hidden" value="{$rack['id']}">
    <input name="rack_assignment_id" type="hidden" value="{$rackunit['id']}">
    <input name="mounted_from" type="hidden" value="{$rackunit['mounted_from']}">
    <table cellspacing="0" border="0" cellpadding="0" style="background-color: {$color['window_content_bg']}; padding-left: 20px; padding-right: 20px; padding-top: 5px; padding-bottom: 5px;">

        <tr>
            <td class="input_required" align="right" nowrap="true">
                Rack Name
            </td>
            <td class="padding" align="left" width="100%">
                {$form['rack']}
            </td>
        </tr>

        <tr>
            <td class="input_required" align="right">
                Insert from
            </td>
            <td class="padding" align="left" width="100%">
                {$rackunit['mounted_from']}
            </td>
        </tr>

        <tr>
            <td class="input_required" align="right">
                Position
            </td>
            <td class="padding" align="left" width="100%">
                <input
                    id="position"
                    name="position"
                    alt="Unit position of device"
                    value="{$rackunit['position']}"
                    class="edit"
                    type="text"
                    size="5" maxlength="5"
                >
            </td>
        </tr>

        <tr>
            <td class="input_required" align="right">
                Depth
            </td>
            <td class="padding" align="left" width="100%">
                <select
                    name="depth"
                    alt="Depth of device"
                    class="edit"
                    {$depth_list}</select>
            </td>
        </tr>

        <tr>
            <td class="input_required" align="right">
                Size
            </td>
            <td class="padding" align="left" width="100%">
                <input
                    name="size"
                    alt="Size (Height) of device"
                    value="{$rackunit['size']}"
                    class="edit"
                    type="text"
                    size="5" maxlength="5"
                > Units
            </td>
        </tr>

        <tr>
            <td align="right"  nowrap="true" alt="Existing ONA host FQDN">
                Device (FQDN)
            </td>
            <td class="padding" align="left" width="100%" nowrap="true">
                <input
                    id="hostname"
                    name="device"
                    alt="ONA Device Name"
                    value="{$rackunit['device']}"
                    class="edit"
                    type="text"
                    size="25" maxlength="35"
                >
                <div id="suggest_hostname" class="suggest"></div>
            </td>
        </tr>

        <tr>
            <td align="right"  nowrap="true" alt="Non ONA devices (i.e. patch panel)">
                <u>OR</u> Alt Name
            </td>
            <td class="padding" align="left" width="100%" nowrap="true">
                <input
                    name="alt_name"
                    alt="Alternate Name"
                    value="{$rackunit['alt_name']}"
                    class="edit"
                    type="text"
                    size="25" maxlength="35"
                >
            </td>
        </tr>

        <tr>
            <td align="right" valign="top">
                &nbsp;
            </td>
            <td class="padding" align="right" width="100%">
                <input type="hidden" name="overwrite" value="{$overwrite}">
                <input class="edit" type="button" name="cancel" value="Cancel" onClick="removeElement('{$window_name}');">
                <input class="edit" type="button"
                    name="submit"
                    value="Save"
                    onClick="xajax_window_submit('{$window_name}', xajax.getFormValues('ru_edit_form'), 'save');"
                >
            </td>
        </tr>

    </table>
    </form>

EOL;


    // Lets build a window and display the results
    return(window_open($window_name, $window));

}








//////////////////////////////////////////////////////////////////////////////
// Function:
//     Save Form
//
// Description:
//     Creates/updates a rack assignment record.
//////////////////////////////////////////////////////////////////////////////
function ws_rack_save($window_name, $form='') {
    global $base, $include, $conf, $self, $onadb;

    // Check permissions
    if (! (auth('advanced')) ) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $js = '';


    // Decide if we're editing or adding
    $module = 'rack_add';
    if ($form['rack_id']) {
        $module = 'rack_modify';
        $form['set_description'] = $form['description'];
        $form['set_size'] = $form['size'];
        $form['set_name'] = $form['name'];
        $form['set_location'] = $form['location'];
        $form['rack'] = $form['rack_id'];
    }

    // If there's no "refresh" javascript, add a command to view the new block
    if (!preg_match('/\w/', $form['js']))
         $form['js'] = "xajax_window_submit('work_space', 'xajax_window_submit(\'rack_maint\', \'id=>{$form['name']}\', \'display\')');";

    // Run the module
    list($status, $output) = run_module($module, $form);

    // If the module returned an error code display a popup warning
    if ($status)
        $js .= "alert('Save failed. ". preg_replace('/[\s\']+/', ' ', $self['error']) . "');";
    else {
        $js .= "removeElement('{$window_name}');";
        if ($form['js']) $js .= $form['js'];
    }

    // Insert the new table into the window
    $response->addScript($js);
    return($response->getXML());
}









//////////////////////////////////////////////////////////////////////////////
// Function:
//     Save Form
//
// Description:
//     Creates/updates a rack assignment record.
//////////////////////////////////////////////////////////////////////////////
function ws_save($window_name, $form='') {
    global $base, $include, $conf, $self, $onadb;

    // Check permissions
    if (! (auth('advanced')) ) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $js = '';


    // Decide if we're editing or adding
    $module = 'rack_assignment_add';
    if ($form['rack_assignment_id']) {
        $module = 'rack_assignment_modify';
        $form['set_mounted_from'] = $form['mounted_from'];
        $form['set_position'] = $form['position'];
        $form['set_size'] = $form['size'];
        $form['set_depth'] = $form['depth'];
        $form['set_alt_name'] = $form['alt_name'];
        $form['set_device'] = $form['device'];
        $form['rack_assignment'] = $form['rack_assignment_id'];
    }

    // If there's no "refresh" javascript, add a command to view the new block
     //if (!preg_match('/\w/', $form['js']))
         $form['js'] = "xajax_window_submit('work_space', 'xajax_window_submit(\'rack_maint\', \'id=>{$form['rack']}\', \'display\')');";

    // Run the module
    list($status, $output) = run_module($module, $form);

    // If the module returned an error code display a popup warning
    if ($status)
        $js .= "alert('Save failed. ". preg_replace('/[\s\']+/', ' ', $self['error']) . "');";
    else {
        $js .= "removeElement('{$window_name}');";
        if ($form['js']) $js .= $form['js'];
    }

    // Insert the new table into the window
    $response->addScript($js);
    return($response->getXML());
}







//////////////////////////////////////////////////////////////////////////////
// Function:
//     Delete
//
// Description:
//     Deletes a group.
//////////////////////////////////////////////////////////////////////////////
function ws_rack_delete($window_name, $form='') {
    global $include, $conf, $self, $onadb;

    // Check permissions
    if (!auth('advanced')) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);

    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $js = '';

    // Run the module
    list($status, $output) = run_module('rack_del', array('rack' => $form['id'], 'commit' => 'Y'));

    // Set up a refresh
    $form['js'] = "xajax_window_submit('work_space', 'xajax_window_submit(\'rack_maint\', \'id=>{$form['rack']}\', \'display\')');";

    // If the module returned an error code display a popup warning
    if ($status)
        $js .= "alert('Delete failed. " . preg_replace('/[\s\']+/', ' ', $self['error']) . "');";
    else if ($form['js'])
        $js .= $form['js'];  // usually js will refresh the window we got called from

    // Return an XML response
    $response->addScript($js);
    return($response->getXML());
}







//////////////////////////////////////////////////////////////////////////////
// Function:
//     Delete Form
//
// Description:
//     Deletes a record.  $form should be an array with a 'domain_id'
//     key defined and optionally a 'js' key with javascript to have the
//     browser run after a successful delete.
//////////////////////////////////////////////////////////////////////////////
function ws_delete($window_name, $form='') {
    global $include, $conf, $self, $onadb;

    // Check permissions
    if (!auth('advanced')) {
        $response = new xajaxResponse();
        $response->addScript("alert('Permission denied!');");
        return($response->getXML());
    }

    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);

    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $js = '';

    // Run the module
    list($status, $output) = run_module('rack_assignment_del', array('rack_assignment' => $form['rack_assignment'], 'commit' => 'Y'));

    // Set up a refresh
    $form['js'] = "xajax_window_submit('work_space', 'xajax_window_submit(\'rack_maint\', \'id=>{$form['rack']}\', \'display\')');";

    // If the module returned an error code display a popup warning
    if ($status)
        $js .= "alert('Delete failed. " . preg_replace('/[\s\']+/', ' ', $self['error']) . "');";
    else if ($form['js'])
        $js .= $form['js'];  // usually js will refresh the window we got called from

    // Return an XML response
    $response->addScript($js);
    return($response->getXML());
}




/*
//////////////////////////////////////////////////////////////////////////////
// Function: ws_display()
//
// Description:
//   Displays a rack record and all associated info in the work_space div.
//////////////////////////////////////////////////////////////////////////////
function ws_display_info($window_name, $form='') {
    global $conf, $self, $onadb;

    $html = '';
    $js = '';

    // If the user supplied an array in a string, build the array and store it in $form
    $form = parse_options_string($form);

    list($status, $frows, $assignment_front) = db_get_record($onadb, 'rack_assignments', array('rack_id' => $form['rackid'],'position' => $form['pos'],'mounted_from' => 1));
    list($status, $brows, $assignment_back)  = db_get_record($onadb, 'rack_assignments', array('rack_id' => $form['rackid'],'position' => $form['pos'],'mounted_from' => 2));


    // Lets start printing the row, now that we have all our info
    $html = <<<EOL
<table cellspacing=0 cellpadding=0 style='font-size: xx-small;'>
<tr><td colspan=2>Front</td><td colspan=2>Back</td></tr>
<tr><td>Name:</td><td>{$ufname}</td><td>Name:</td><td>{$ubname}</td></tr>
<tr><td>Height:</td><td>{$assignment_front['size']} Units</td><td>Height:</td><td>{$assignment_back['size']} Units</td></tr>
<tr><td>Depth:</td><td>{$assignment_front['depth']}</td><td>Depth:</td><td>{$assignment_back['depth']}</td></tr></table>
EOL;

    // Insert the new html into the window
    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $response->addAssign("rack_unit_info", "innerHTML", $html);
    if ($js) { $response->addScript($js); }
    return($response->getXML());

}*/



//////////////////////////////////////////////////////////////////////////////
// Calculates the percentage of a rack that is in "use".
// Returns an array:
//    list($back_percentage_used, $back_number_used, $back_percentage_used, $back_number_used, $number_total)
//////////////////////////////////////////////////////////////////////////////
function get_rack_usage($rack_id) {
    global $conf, $self, $onadb;

    // TODO: maybe remove this and just pass in the size??
    list($status, $rows, $rack) = db_get_record($onadb, 'racks', array('id' => $rack_id));
    if ($status or !$rows) { return(0); }

    // Calculate the percentage used (total size - allocated hosts - dhcp pool size)
    list($status, $frows, $front) = db_get_records($onadb, 'rack_assignments', array('rack_id' => $rack_id,'mounted_from' => 1));
    list($status, $brows, $back)  = db_get_records($onadb, 'rack_assignments', array('rack_id' => $rack_id,'mounted_from' => 2));

    $f_size = 0;
    $b_size = 0;
    foreach ($front as $f) {
        $f_size += $f['size'];
        if ($f['depth'] == 4) $b_size += $f['size'];
    }


    foreach ($back as $b) {
        $b_size += $b['size'];
        if ($b['depth'] == 4) $f_size += $b['size'];
    }

    $fpercent = sprintf('%d', ($f_size / $rack['size']) * 100);
    $bpercent = sprintf('%d', ($b_size / $rack['size']) * 100);
    return(array($fpercent, $f_size, $bpercent, $b_size, $rack['size']));
}





//////////////////////////////////////////////////////////////////////////////
// Returns the html for a "percentage of rack used" bar graph
//////////////////////////////////////////////////////////////////////////////
function get_rack_usage_html($rack_id, $width=30, $height=8) {
    global $conf, $self, $onadb;
    list($fuse, $fsize, $buse, $bsize, $total) = get_rack_usage($rack_id);
    $css='';
    if (strpos($_SERVER['HTTP_USER_AGENT'],'MSIE') != false)
        $css = "font-size: " . ($height - 2) . "px;";

    $fusage = <<<EOL
    <div style="white-space: nowrap; width: 100%; text-align: left; padding-top: 2px; padding-bottom: 2px; vertical-align: middle; font-size: 8px;">
        <div title="{$fuse}% used" style="{$css} float: left; width: {$width}px; height: {$height}px; text-align: left; vertical-align: middle; background-color: #ABFFBC; border: 1px solid #000;">
            <div style="{$css} width: {$fuse}%; height: {$height}px; vertical-align: middle; background-color: #FF3939;"></div>
        </div>
        <span style="font-size: 8px;">&nbsp;{$fsize} / {$total} Units</span>
    </div>
EOL;


    $busage = <<<EOL
    <div style="white-space: nowrap; width: 100%; text-align: left; padding-top: 2px; padding-bottom: 2px; vertical-align: middle; font-size: 8px;">
        <div title="{$buse}% used" style="{$css} float: left; width: {$width}px; height: {$height}px; text-align: left; vertical-align: middle; background-color: #ABFFBC; border: 1px solid #000;">
            <div style="{$css} width: {$buse}%; height: {$height}px; vertical-align: middle; background-color: #FF3939;"></div>
        </div>
        <span style="font-size: 8px;">&nbsp;{$bsize} / {$total} Units</span>
    </div>
EOL;

    return(array($fusage, $busage));
}

//////////////////////////////////////////////////////////////////////////////
// Function: ws_display()
//
// Description:
//   Displays a rack record and all associated info in the work_space div.
//////////////////////////////////////////////////////////////////////////////
function ws_display($window_name, $form='') {
    global $conf, $self, $onadb;
    global $images, $color, $style;
    $html = '';
    $js = '';
    $debug_val = 3;  // used in the auth() calls to supress logging

    // If the user supplied an array in a string, build the array and store it in $form
    $form = parse_options_string($form);

    // Load the record
    if (is_numeric($form['id']))
        list($status, $rows, $record) = db_get_record($onadb, 'racks', array('id' => $form['id']));
    else {
        // Standardize names to be in upper case and spaces are converted to -'s.
        $form['id'] = trim($form['id']);
        $form['id'] = preg_replace('/\s+/', '-', $form['id']);
        $form['id'] = strtoupper($form['id']);

        list($status, $rows, $record) = db_get_record($onadb, 'racks', array('name' => $form['id']));
    }

    if ($status or !$rows) {
        array_pop($_SESSION['ona']['work_space']['history']);
        $html .= "<br><center><font color=\"red\"><b>Rack doesn't exist!</b></font></center>";
        $response = new xajaxResponse();
        $response->addAssign("work_space_content", "innerHTML", $html);
        return($response->getXML());
    }

    // get location info
    list ($status, $rows, $loc) = db_get_record($onadb, 'locations', "id={$record['location_id']}");

    // Update History Title (and tell the browser to re-draw the history div)
    $history = array_pop($_SESSION['ona']['work_space']['history']);
    $js .= "xajax_window_submit('work_space', ' ', 'rewrite_history');";
    if ($history['title'] == $window_name) {
        $history['title'] = $record['name'];
        array_push($_SESSION['ona']['work_space']['history'], $history);
    }

    // Create some javascript to refresh the current page
    $refresh = htmlentities(str_replace(array("'", '"'), array("\\'", '\\"'), $history['url']), ENT_QUOTES);
    $refresh = "xajax_window_submit('work_space', '{$refresh}');";

    $style['label_box'] = <<<EOL
        font-weight: bold;
        padding: 2px 4px;
        border: solid 1px {$color['border']};
        background-color: {$color['window_content_bg']};
EOL;

    // get usage info on this rack
    list($fuseage, $buseage) = get_rack_usage_html($record['id']);

    // Escape data for display in html
    foreach(array_keys($record) as $key) { $record[$key] = htmlentities($record[$key], ENT_QUOTES); }

    $html .= <<<EOL
    <!-- FORMATTING TABLE -->
    <div class="content_box">
    <table cellspacing="0" border="0" cellpadding="0"><tr>

        <!-- START OF FIRST COLUMN OF SMALL BOXES -->
        <td nowrap="true" valign="top" style="padding-right: 15px;">
EOL;


    // RACK INFORMATION
    $html .= <<<EOL
            <table width=100% cellspacing="0" border="0" cellpadding="0" style="margin-bottom: 8px;">

                <tr><td colspan="99" nowrap="true" style="{$style['label_box']}">
                    <!-- LABEL -->
                    <form id="form_rack_{$record['id']}"
                        ><input type="hidden" name="id" value="{$record['id']}"
                        ><input type="hidden" name="js" value="{$refresh}"
                    ></form>
EOL;

    if (auth('advanced',$debug_val)) {
        $html .= <<<EOL

                    <a title="Edit rack. ID: {$record['id']}"
                       class="act"
                       onClick="xajax_window_submit('rack_maint', xajax.getFormValues('form_rack_{$record['id']}'), 'rack_editor');"
                    ><img src="{$images}/silk/page_edit.png" border="0"></a>&nbsp;

                    <a title="Delete rack. ID: {$record['id']}"
                       class="act"
                       onClick="var doit=confirm('Are you sure you want to delete this rack?');
                                if (doit == true)
                                    xajax_window_submit('rack_maint', xajax.getFormValues('form_rack_{$record['id']}'), 'rack_delete');"
                    ><img src="{$images}/silk/delete.png" border="0"></a>&nbsp;
                    {$record['name']}</a>
EOL;
    }
    else {
        $html .= "                    &nbsp;{$record['name']}";
    }

        $html .= <<<EOL


                    &nbsp;&nbsp;<a href="?work_space={$window_name}&id={$record['id']}"><img title="Direct link to rack {$record['name']}" src="{$images}/silk/application_link.png" border="0"></a>
                </td></tr>

                <tr>
                    <td align="right" nowrap="true"><b>Rack Name</b>&nbsp;</td>
                    <td class="padding" align="left">{$record['name']}&nbsp;</td>
                </tr>

                <tr>
                    <td align="right" nowrap="true"><b>Height</b>&nbsp;</td>
                    <td class="padding" align="left">{$record['size']} Units&nbsp;</td>
                </tr>

                <tr>
                    <td align="right" nowrap="true"><b>Front Usage</b>&nbsp;</td>
                    <td class="padding" align="left" nowrap="true">{$fuseage}</td>
                </tr>

                <tr>
                    <td align="right" nowrap="true"><b>Back Usage</b>&nbsp;</td>
                    <td class="padding" align="left" nowrap="true">{$buseage}</td>
                </tr>

                <tr>
                    <td align="right" nowrap="true"><b>Location</b>&nbsp;</td>
                    <td class="padding" align="left">{$loc['reference']}&nbsp;</td>
                </tr>

                <tr>
                    <td align="right" nowrap="true"><b>Description</b>&nbsp;</td>
                    <td nowrap="true" class="padding" align="left"><textarea size="256" cols=25 rows=3 class="display_notes">{$record['description']}</textarea></td>
                </tr>

            </table>
EOL;
    // END RACK INFORMATION
    //$wspl = workspace_plugin_loader('location_detail',$record,$extravars);
    //$html .= $wspl[0]; $modbodyjs .= $wspl[1];

    $html .= <<<EOL
        <!-- END OF FIRST COLUMN OF SMALL BOXES -->
        </td>

        <td nowrap="true" valign="top" style="padding-right: 15px;">
            Warning: The accuracy of this information is subject only to how well the IT staff has maintained the information.
        </td>
    </tr></table>
    </div>
    <!-- END OF TOP SECTION -->

<center>
    <div style="width: 80%;font-size: xx-small;">

        <table cellspacing=0 cellpadding=0>

EOL;




$rackunit .= <<<EOL
<tr>

<td width="1%" style="border-bottom: 1px solid;">&nbsp;</td>

<td style="border-bottom: 1px solid;text-align: center;font-weight: bold;">Front</td>

<td style="font-size: xx-small;"> </td>

<td colspan=4 style="border-bottom: 1px solid;text-align: center;font-weight: bold;">Side</td>

<td style="font-size: xx-small;"> </td>

<td style="border-bottom: 1px solid;text-align: center;font-weight: bold;">Back </td>
<td style="border-bottom: 1px solid;text-align: center;font-weight: bold;" nowrap="true">
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Actions&nbsp;&nbsp;&nbsp;&nbsp;
 </td>
<td rowspan={$record['size']} style="width: 450px;" id="rack_unit_info">&nbsp;</td>

</tr>
EOL;

    $i = 1;
    $used_color = '#AA66CC';
    $used_color_lite = '#EEDDEE';
    // Start at the top rack U and start a loop of this rack until we reach its SIZE
    while ($i <= $record['size']):
        // Get info about front/back assignments
        list($status, $frows, $assignment_front) = db_get_record($onadb, 'rack_assignments', array('rack_id' => $record['id'],'position' => $i,'mounted_from' => 1));
        list($status, $brows, $assignment_back)  = db_get_record($onadb, 'rack_assignments', array('rack_id' => $record['id'],'position' => $i,'mounted_from' => 2));


        if ($assignment_back['depth'])  $bdepth        = $assignment_back['depth'];
        if ($assignment_back['id'])     $bsize_counter = $bsize = $assignment_back['size'];
        if ($assignment_back['id'])     $bid           = $assignment_back['id'];
        if ($assignment_front['depth']) $fdepth        = $assignment_front['depth'];
        if ($assignment_front['id'])    $fsize_counter = $fsize = $assignment_front['size'];
        if ($assignment_front['id'])    $fid           = $assignment_front['id'];

        $assignmentf_name = '';
        $assignmentb_name = '';

        unset($bkcolor);
        $fborder = '';
        $bborder = '';


        // If we have an assignment, process it
        // Determine if the device was mounted from the front or the back
        if ($assignment_front['id'] or $fsize_counter > 0) {

            if ($bsize_counter <= 0) $bused_color = $used_color_lite;

            if ($fsize_counter > 1) {
                $fborder = 'border-bottom: 0px;';
                if ($fdepth == 4) {
                    $bused_color = $used_color;
                    $bborder = 'border-bottom: 0px;';
                }
            }

            // installed in the front
            $y = 1;
            while ($y <= $fdepth):
                $cap = ($y == $fdepth) ? '' : 'border-right: 1px;';
                $bkcolor[$y++]="background-color: {$used_color};{$fborder}{$cap}";
            endwhile;

            $fused_color = $used_color;



            // If it is a full depth, then make the back color used as well
            if ($fdepth == 4) {
                $bused_color = $used_color;
                $bsize = $fsize;
                $bdepth = $fdepth;
                $bname = $fname;
            }

            //list($status, $rows, $device) = ona_get_device_record(array('id' => $assignment['device_id']));
            // FIXME: this wont work when the primary device id stuff is set up right!
            if ($assignment_front['device_id']) {
                list($status, $rows, $host) = ona_get_host_record(array('device_id' => $assignment_front['device_id']));
                // if we have a device id but we cant find it.. say so
                if (!$rows) $assignmentf_name = $fname = "device_id not valid.";
                // if we found a device, then make a link for it
                if ($host['fqdn']) {
                    $assignmentf_name = "<a title=\"Click to view device\" onClick=\"xajax_window_submit('display_host', 'host=>{$host['fqdn']}', 'display');\" style=\"color: black;\">{$host['fqdn']}</a>";
                    $fname = $host['fqdn'];
                }
            }
            if ($assignment_front['alt_name']) $assignmentf_name = $fname = $assignment_front['alt_name'];

        } else {
            if ($bsize_counter <= 0) {
                $fused_color = '';
            }

            if ($fsize_counter <= 0) {
                $fsize = '';
                $fdepth = '';
                $fname = '';
                $fid = '';
            }

        }


        // installed in the back
        if ($assignment_back['id'] or $bsize_counter > 0) {

            if ($fsize_counter <= 0) $fused_color = $used_color_lite;

            if ($bsize_counter > 1) {
                $bborder = 'border-bottom: 0px;';
                // If it is a full depth, then make the front color used as well
                if ($bdepth == 4) {
                    $fused_color = $used_color;
                    $fborder = 'border-bottom: 0px;';
                }
            }

            $x = 4;
            $y = 1;
            while ($y <= $bdepth):
                $cap = ($y > $bdepth) ? '' : 'border-right: 1px;';
                $bkcolor[$x--]="background-color: {$used_color};{$bborder}{$cap}";
                $y++;
            endwhile;

            if ($bdepth == 4) $fused_color = $used_color;
            $bused_color = $used_color;

            if ($assignment_back['device_id']) {
                list($status, $rows, $host) = ona_get_host_record(array('device_id' => $assignment_back['device_id']));
                // if we have a device id but we cant find it.. say so
                if (!$rows) $assignmentb_name = $bname = "device_id not valid.";
                // if we found a device, then make a link for it
                if ($host['fqdn']) {
                    $assignmentb_name = "<a title=\"Click to view device\" onClick=\"xajax_window_submit('display_host', 'host=>{$host['fqdn']}', 'display');\" style=\"color: black;\">{$host['fqdn']}</a>";
                    $bname = $host['fqdn'];
                }
            }
            if ($assignment_back['alt_name']) $assignmentb_name = $bname = $assignment_back['alt_name'];

        } else {
            if ($fsize_counter <= 0) {
                $bused_color = '';
            }

            if ($bsize_counter <= 0) {
                $bsize = '';
                $bdepth = '';
                $bname = '';
                $bid = '';
            }

        }

        // make it easier to read
        $bdepth_name = $bdepth;
        $fdepth_name = $fdepth;
        if ($bdepth == 4) $bdepth_name = 'Full';
        if ($bdepth == 2) $bdepth_name = 'Half';
        if ($fdepth == 4) $fdepth_name = 'Full';
        if ($fdepth == 2) $fdepth_name = 'Half';


        // Lets start printing the row, now that we have all our info, formatted for javascript
        $unit_detail = "'<table width=100% cellspacing=0  style=\'font-size: xx-small;\'> \
<tr><td colspan=4 style=\'text-align: center;font-weight: bold;\'>Unit {$i} Detail</td></tr> \
<tr><td width=225px colspan=2 style=\'text-align: center;font-weight: bold;\'>Front</td><td width=225px colspan=2 style=\'text-align: center;font-weight: bold;\'>Back</td></tr> \
<tr><td width=15% style=\'text-align: right;font-weight: bold;\'>Name:</td><td width=35% nowrap>{$fname}&nbsp;</td><td width=15% style=\'text-align: right;font-weight: bold;\'>Name:</td><td width=35% >{$bname}&nbsp;</td></tr> \
<tr><td width=15% style=\'text-align: right;font-weight: bold;\'>Height:</td><td width=35% >{$fsize}</td><td width=15% style=\'text-align: right;font-weight: bold;\'>Height:</td><td width=35% >{$bsize}</td></tr> \
<tr><td width=15% style=\'text-align: right;font-weight: bold;\'>Depth:</td><td width=35% >{$fdepth_name}&nbsp;</td><td width=15% style=\'text-align: right;font-weight: bold;\'>Depth:</td><td width=35% >{$bdepth_name}&nbsp;</td></tr></table>'";



        // Start a new row and show the Rack unit number
        $rackunit .= "<tr onMouseOver=\"this.className='row-highlight';el('act-{$i}').style.display='block';el('act-{$i}').style.visibility='visible';el('rack_unit_info').innerHTML = {$unit_detail};\"  onMouseOut=\"this.className='row-normal';el('act-{$i}').style.visibility='hidden';el('act-{$i}').style.display='none';\"><td id\"row-{$i}\" width=1% style=\"text-align: right;border-bottom: 1px solid;font-size: xx-small;\">{$i}</td>";


        // Front of the rack
        $rackunit .= "
<td width=15% style=\"text-align: center;font-size: xx-small;border: 1px solid;border-top: 0px;{$fborder}background-color: {$fused_color};\">{$assignmentf_name}&nbsp;</td>
<td width=5 style=\"font-size: xx-small;\">&nbsp;</td>";


        // (right) side of the rack
        $rackunit .= "
<td width=4% style=\"font-size: xx-small;border: 1px solid;border-top:  0px;{$bkcolor[1]}\">&nbsp;</td>
<td width=4% style=\"font-size: xx-small;border: 1px solid;border-left: 0px;border-top: 0px;{$bkcolor[2]}\">&nbsp;</td>
<td width=4% style=\"font-size: xx-small;border: 1px solid;border-left: 0px;border-top: 0px;{$bkcolor[3]}\">&nbsp;</td>
<td width=4% style=\"font-size: xx-small;border: 1px solid;border-left: 0px;border-top: 0px;{$bkcolor[4]}border-right: 1px solid;\">&nbsp;</td>";

        // Back of the rack
        $rackunit .= <<<EOL
    <td style="font-size: xx-small;">&nbsp;</td>
    <td width=15%
        style="text-align: center;font-size: xx-small;border: 1px solid;border-top: 0px;{$bborder}background-color: {$bused_color};">&nbsp;{$assignmentb_name}&nbsp;
    </td>
    <td id="act-{$i}" style="background-color: #ffffff;visibility: hidden;display: none;padding-bottom: 2px;" nowrap="true">
        <span style="border:1px solid;border-left:0px;padding-bottom: 2px;">
EOL;


 if ($bdepth < 4) {
        $rackunit .= <<<EOL
            &nbsp;<a title="Edit FRONT rack assignment"
               class="act"
               onClick="xajax_window_submit('rack_maint', 'rack=>{$record['name']},position=>{$i},mounted_from=>1', 'ru_editor');"
            ><img src="{$images}/silk/page_edit.png" border="0"></a>
EOL;
}

    if ($fused_color == $used_color and $fsize > 0 and $bdepth < 4) {
        $rackunit .= <<<EOL
            <a title="Delete FRONT rack assignment"
               class="act"
               onClick="var doit=confirm('Are you sure you want to delete this assignment?');
                            if (doit == true)
                                xajax_window_submit('rack_maint', 'rack=>{$record['name']},rack_assignment=>{$fid}', 'delete');"
            ><img src="{$images}/silk/delete.png" border="0"></a>&nbsp;
EOL;
}

 if ($fdepth < 4) {
        $rackunit .= <<<EOL
        </span>
        <span style="border:1px solid;border-left:0px;padding-bottom: 2px;">
            &nbsp;<a title="Edit BACK rack assignment"
               class="act"
               onClick="xajax_window_submit('rack_maint', 'rack=>{$record['name']},position=>{$i},mounted_from=>2', 'ru_editor');"
            ><img src="{$images}/silk/page_edit.png" border="0"></a>
EOL;
}

    if ($bused_color == $used_color and $bsize > 0 and $fdepth < 4) {
        $rackunit .= <<<EOL
            <a title="Delete BACK rack assignment"
               class="act"
               onClick="var doit=confirm('Are you sure you want to delete this assignment?');
                            if (doit == true)
                                xajax_window_submit('rack_maint', 'rack=>{$record['name']},rack_assignment=>{$bid}', 'delete');"
            ><img src="{$images}/silk/delete.png" border="0"></a>&nbsp;
EOL;
}

        $rackunit .= <<<EOL
        </span>
    </td>
</tr>
EOL;

        $fsize_counter--;
        $bsize_counter--;
        $i++;
    endwhile;



    $html .= <<<EOL
        {$rackunit}
    </table>
<br>
    </div>
<center>

EOL;


    // Insert the new html into the window
    // Instantiate the xajaxResponse object
    $response = new xajaxResponse();
    $response->addAssign("work_space_content", "innerHTML", $html);
    if ($js) { $response->addScript($js); }
    return($response->getXML());
}













?>
