<?php
global $base, $conf, $baseURL, $images;

$title_right_html = '';
$title_left_html  = '';
$modbodyhtml = '';
$modjs = '';

// Get info about this file name
$onainstalldir = dirname($base);
$file = str_replace($onainstalldir.'/www', '', __FILE__);
$thispath = dirname($file);

// future config options
$refresh_interval = '600000'; // every 10 minutes
$boxheight = '300px';
$divid = 'ona_recent_additions';

// Display only on the desktop
if ($extravars['window_name'] == 'html_desktop') {

    $title_left_html .= <<<EOL
        Recent ONA Additions
EOL;

    $title_right_html .= <<<EOL
        <a title="Reload recent additions info" onclick="el('{$divid}').innerHTML = '<center>Reloading...</center>';xajax_window_submit('{$file}', ' ', 'ona_recent_additions_list');"><img src="{$images}/silk/arrow_refresh.png" border="0"></a>
EOL;


    $modbodyhtml .= <<<EOL
<div id="{$divid}" style="max-height: {$boxheight};overflow-y: auto;overflow-x:hidden;font-size:small">
{$conf['loading_icon']}
</div>
EOL;


    // run the function that will update the content of the plugin. update it every 5 mins
    $modjs .= "xajax_window_submit('{$file}', ' ', 'ona_recent_additions_list');setInterval('el(\'{$divid}\').innerHTML = \'{$conf['loading_icon']}\';xajax_window_submit(\'{$file}\', \' \', \'ona_recent_additions_list\');',{$refresh_interval});";

}







/*
Gather information about recent additions and display them
*/
function ws_ona_recent_additions_list($window_name, $form='') {
    global $conf, $self, $onadb, $base, $images, $baseURL;

    // Get info about this file name
    $onainstalldir = dirname($base);
    $file = str_replace($onainstalldir.'/www', '', __FILE__);
    $thispath = dirname($file);

    // If an array in a string was provided, build the array and store it in $form
    $form = parse_options_string($form);


    // Get recent subnets
    list ($status, $rows, $subnets) = db_get_records($onadb,'subnets','id > 0',"id DESC", 5, 0);
    foreach ($subnets as $subnet) {
        $subnet['ip_addr'] = ip_mangle($subnet['ip_addr'], 'dotted');
        $subnet['ip_mask'] = ip_mangle($subnet['ip_mask'], 'cidr');
        $htmllines .= <<<EOL
    <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
        <td align=right class="list-row">SUBNET:</td>
        <td class="list-row">{$subnet['name']}</td>
        <td class="list-row">{$subnet['ip_addr']}</td>
        <td class="list-row">/{$subnet['ip_mask']}</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
    </tr>
EOL;
    }


    // Get recent hosts
    list ($status, $rows, $hosts) = db_get_records($onadb,'hosts','id > 0',"id DESC", 5, 0);
    foreach ($hosts as $host) {
      list($status, $rows, $dnsrecord) = ona_get_dns_record(array('id' => $host['primary_dns_id']));
      list($status, $rows, $dev) = ona_get_device_record(array('id' => $host['device_id']));
      list($status, $rows, $devtype) = ona_get_device_type_record(array('id' => $dev['device_type_id']));
      if ($devtype['id']) {
         list($status, $rows, $model) = ona_get_model_record(array('id' => $devtype['model_id']));
         list($status, $rows, $role)  = ona_get_role_record(array('id' => $devtype['role_id']));
         list($status, $rows, $manu)  = ona_get_manufacturer_record(array('id' => $model['manufacturer_id']));
         $devtype_desc = "{$manu['name']}, {$model['name']} ({$role['name']})";
      }

        $host['ip_addr'] = ip_mangle($host['ip_addr'], 'dotted');
        $htmllines .= <<<EOL
    <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
        <td align=right class="list-row">HOST:</td>
        <td class="list-row">{$dnsrecord['fqdn']}</td>
        <td class="list-row">{$devtype_desc}&nbsp;</td>
        <td class="list-row">&nbsp;</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
    </tr>
EOL;
    }



    // Get recent dns records
    list ($status, $rows, $dnsrecords) = db_get_records($onadb,'dns','id > 0',"id DESC", 5, 0);
    foreach ($dnsrecords as $dns) {
  //list($status, $rows, $dnsrecord) = ona_get_dns_record(array('id' => $host['primary_dns_id']));
    list($status, $rows, $int) = ona_get_interface_record(array('id' => $dns['interface_id']));
    list($status, $rows, $domain) = ona_get_domain_record(array('id' => $dns['domain_id']));

    $dns['fqdn'] = $dns['name'].'.'.$domain['fqdn'];

        $dns['ip_addr'] = ip_mangle($int['ip_addr'], 'dotted');
        $htmllines .= <<<EOL
    <tr onMouseOver="this.className='row-highlight';" onMouseOut="this.className='row-normal';">
        <td align=right class="list-row">DNS:</td>
        <td class="list-row">{$dns['fqdn']}&nbsp;</td>
        <td class="list-row">{$dns['type']}</td>
        <td class="list-row">{$dns['ip_addr']}&nbsp;</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
    </tr>
EOL;
    }



    // If we actually have information.. print the table
    if (!$htmllines) {
        $htmllines = "<tr><td>There was an error gathering data.</td></tr>";
    }
    $html .= '<table class="list-box" cellspacing="0" border="0" cellpadding="0">';
    $html .= $htmllines;
    $html .= "</table>";



    // Insert the new table into the window
    $response = new xajaxResponse();
    $response->addAssign('ona_recent_additions', "innerHTML", $html);
    $response->addScript($js);
    return($response->getXML());
}






?>
