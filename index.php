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
        $api_version = $api->getApiVersion();
        $dcs         = $api->getDatacenters();
        $dc          = $api->getDatacenter('5849b030-626e-47cb-ad90-3ce782d831b3');

    } catch (Exception $e) {
        echo $e->getMessage();
        die;
    }

    echo "<h1>oVirt User portal</h1>";
    echo "oVirt version: ".$api_version;

    echo "<h3>List of virtual machines:</h3>";
    if(count((array)$vms)>0) {
        echo "<table id='vm-table'><th width='75px'>Status</th><th width='150px'>Name</th>";
        foreach($vms as $vm) {

            echo "<tr>";
            # Data
            echo "<td>" . $vm->status . "</td>";
            echo "<td>" . $vm->name . "</td>";

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
        //d($dcs);
    } else {
        echo "No clusters found";
    }

    echo "<h3>List of clusters:</h3>";
    if(count($clusters)>0) {
        echo "<ul>";
        foreach($clusters as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") (Version: " . $item->getVersion() .")</li>";
                echo "- Networks: ";
                foreach($item->getNetworks() as $network) {
                    echo $network->name . " (" . $network->status . ")";
                }
        }
        echo "</ul>";
    } else {
        echo "No clusters found";
    }

    echo "<h3>List of hosts:</h3>";
    if(count($hosts)>0) {
        echo "<ul>";
        foreach($hosts as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") </li>";
        }
        echo "</ul>";
    } else {
        echo "No clusters found";
    }

    echo "<h3>List of storage domains:</h3>";
    if(count($domains)>0) {
        echo "<ul>";
        foreach($domains as $item) {
            echo "<li>" . $item->name . " (ID: " . $item->id . ") </li>";
        }
        echo "</ul>";
    } else {
        echo "No clusters found";
    }
?>
</body>
</html>