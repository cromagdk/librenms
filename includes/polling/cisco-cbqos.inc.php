<?php
/*
 * LibreNMS module to capture Cisco Class-Based QoS Details
 *
 * Copyright (c) 2015 Aaron Daniels <aaron@daniels.id.au>
 *
 * This program is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 3 of the License, or (at your
 * option) any later version.  Please see LICENSE.txt at the top level of
 * the source code distribution for details.
 */

if ($device['os_group'] == "cisco") {

    $module = 'Cisco-CBQOS';

    $component = new LibreNMS\Component();
    $options['filter']['type'] = array('=',$module);
    $options['filter']['disabled'] = array('=',0);
    $options['filter']['ignore'] = array('=',0);
    $components = $component->getComponents($device['device_id'],$options);

    // We only care about our device id.
    $components = $components[$device['device_id']];

    // Only collect SNMP data if we have enabled components
    if (count($components > 0)) {
        // Let's gather the stats..
        $tblcbQosClassMapStats = snmpwalk_array_num($device, '.1.3.6.1.4.1.9.9.166.1.15.1.1', 2);

        // Loop through the components and extract the data.
        foreach ($components as $key => $array) {
            $type = $array['qos-type'];

            // Get data from the class table.
            if ($type == 2) {
                // Let's make sure the rrd is setup for this class.
                $ifIndex = $array['ifindex'];
                $spid = $array['sp-id'];
                $spobj = $array['sp-obj'];
                $rrd_name = array('port', $ifIndex, 'cbqos', $spid, $spobj);
                $rrd_def = array(
                    'DS:postbits:COUNTER:600:0:U',
                    'DS:bufferdrops:COUNTER:600:0:U',
                    'DS:qosdrops:COUNTER:600:0:U'
                );

                // Let's print some debugging info.
                d_echo("\n\nComponent: ".$key."\n");
                d_echo("    Class-Map: ".$array['label']."\n");
                d_echo("    SPID.SPOBJ: ".$array['sp-id'].".".$array['sp-obj']."\n");
                d_echo("    PostBytes:   1.3.6.1.4.1.9.9.166.1.15.1.1.10.".$array['sp-id'].".".$array['sp-obj']." = ".$tblcbQosClassMapStats['1.3.6.1.4.1.9.9.166.1.15.1.1.10'][$array['sp-id']][$array['sp-obj']]."\n");
                d_echo("    BufferDrops: 1.3.6.1.4.1.9.9.166.1.15.1.1.21.".$array['sp-id'].".".$array['sp-obj']." = ".$tblcbQosClassMapStats['1.3.6.1.4.1.9.9.166.1.15.1.1.21'][$array['sp-id']][$array['sp-obj']]."\n");
                d_echo("    QOSDrops:    1.3.6.1.4.1.9.9.166.1.15.1.1.17.".$array['sp-id'].".".$array['sp-obj']." = ".$tblcbQosClassMapStats['1.3.6.1.4.1.9.9.166.1.15.1.1.17'][$array['sp-id']][$array['sp-obj']]."\n");

                $fields = array(
                    'postbits' => $tblcbQosClassMapStats['1.3.6.1.4.1.9.9.166.1.15.1.1.10'][$array['sp-id']][$array['sp-obj']],
                    'bufferdrops' => $tblcbQosClassMapStats['1.3.6.1.4.1.9.9.166.1.15.1.1.21'][$array['sp-id']][$array['sp-obj']],
                    'qosdrops' => $tblcbQosClassMapStats['1.3.6.1.4.1.9.9.166.1.15.1.1.17'][$array['sp-id']][$array['sp-obj']]
                );

                $tags = compact('rrd_name', 'rrd_def', 'ifIndex', 'spid', 'spobj');
                data_update($device, 'cbqos', $tags, $fields);
            }
        } // End foreach components

    } // end if count components

    // Clean-up after yourself!
unset($type, $components, $component, $options, $module);
}
