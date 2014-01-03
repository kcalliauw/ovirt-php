<!DOCTYPE html>
<html>
<head>
    <title>oVirt - PHP UserPortal</title>
    <link href="data:image/x-icon;base64,AAABAAEAEBAAAAAAAABoBQAAFgAAACgAAAAQAAAAIAAAAAEACAAAAAAAAAEAAAAAAAAAAAAAAAEAAAAAAAD///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" rel="icon" type="image/x-icon" />
    <link href="assets/css/main.css" type="text/css" rel="stylesheet"/>
</head>
<body>
<?php

    try {
        require_once('library/Api.php');
        require_once('library/UtilityFunctions.php');
        $api         = new OvirtApi('https://10.11.0.115/api/', 'admin@internal', 'W5UNJqFU', true, null, null, false, true);
        $vms         = $api->getVms();
        $clusters    = $api->getClusters();
        $hosts       = $api->getHosts();
        $domains     = $api->getStorageDomains();
        $templates   = $api->getTemplates();
        $api_version = $api->getApiVersion();
        $dcs         = $api->getDatacenters();
        $dc          = $api->getDatacenter('5849b030-626e-47cb-ad90-3ce782d831b3');

    } catch (Exception $e) {
        echo $e->getMessage();
        die;
    }

     /* ==================================== Testing Grounds ====================================== */
    // VM Creation
    $vm_test = array(
        'name'      => 'VM-' . time() . '-test',
        'cluster'   => array(
            'name'  => 'Default',
        ),
        'template'  => array(
            'name'  => 'Blank',
        ),
        'memory'    => '536870912',
        'os'        => array(
            'type'  => 'linux',
            'boot'  => array(
                'dev'   =>  'hd',
            )
        ),
        'profile'   => 'server',
        'display'   => array (
            'type'      => 'spice',
            'address'   => '10.11.0.116',
            'port'      => '5900',
            'secure_port'   => '',
            'subject'   => '',
            'monitors'  => '1',
        ),
        'cpu'       => array(
            'cores' => '2',
            'sockets'   => '2',
        ),
    );

    $nic_test = array(
        'name'      => 'nic1',
        'interface' => 'virtio',
        'network'   => array(
            'name'  => 'ovirtmgmt',
        ),
    );

    # Delete VM
//    $api->deleteResource('vms/vm_id');
//     TODO :: doesnt create VM straight after a delete request (crashes in postResource), headers probably not set properly after deleteRescource

    # Create VM
//    $newvm = $api->createVm($vm_test);
//    var_dump($newvm);

    /* ============================================================================================= */
    echo "<h1>oVirt User portal</h1>";
    echo "oVirt version: " . $api_version;

    echo "<h3>List of virtual machines:</h3>";
    if(count((array)$vms)>0) {
        echo "<table id='vm-table'><th width='75px'>Status</th><th width='150px'>Name</th><th>ID</th>";
        foreach($vms as $vm) {
            echo "<tr>";
            # Data
            echo "<td>" . $vm->status . "</td>";
            echo "<td>" . $vm->name . "</td>";
            echo "<td>" . $vm->id . "</td>";

            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No virtual machines found";
    }

    echo '<h3>Single datacenter info</h3>';
    echo "Name: $dc->name<br />";
    echo "Description: $dc->description<br />";
    echo "Version: $dc->version<br />";

    echo "<h3>List of datacenters:</h3>";
    if(count($dcs)>0) {
        echo "<ul>";
        foreach($dcs as $item) {
            $description = (strlen($item->description) > 0) ? ' (' . $item->description . ')' : '';
            $id = ' ( ID: ' . $item->id . ' )';
            echo '<li>' . $item->name . $description . $id . '</li>';
        }
        echo "</ul>";
    } else {
        echo "No Datacenters found";
    }

    echo "<h3>List of clusters:</h3>";
    if(count($clusters)>0) {
        echo "<ul>";
        foreach($clusters as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") (Version: " . $item->getVersion() .")</li>";
            echo "- Cluster Networks: <ul>";
            foreach($item->getNetworks() as $network) {
                echo '<li>' . $network->name . " (" . $network->status . ")</li>";
            }
            echo "</ul>";
        }
        echo "</ul>";
    } else {
        echo "No Clusters found";
    }

    echo "<h3>List of hosts:</h3>";
    if(count($hosts)>0) {
        echo "<ul>";
        foreach($hosts as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") </li>";
        }
        echo "</ul>";
    } else {
        echo "No Hosts found";
    }

    echo "<h3>List of storage domains:</h3>";
    if(count($domains)>0) {
        echo "<ul>";
        foreach($domains as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") </li>";
        }
        echo "</ul>";
    } else {
        echo "No Storage Domains found";
    }

    echo "<h3>List of templates:</h3>";
    if(count($templates)>0) {
        echo "<ul>";
        foreach($templates as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") </li>";
        }
        echo "</ul>";
    } else {
        echo "No Templates found";
    }
?>
</body>
</html>