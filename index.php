<!DOCTYPE html>
<html>
<head>
    <title>oVirt - PHP UserPortal</title>
    <link href="data:image/x-icon;base64,AAABAAEAEBAAAAAAAABoBQAAFgAAACgAAAAQAAAAIAAAAAEACAAAAAAAAAEAAAAAAAAAAAAAAAEAAAAAAAD///8AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" rel="icon" type="image/x-icon" />
    <link href="assets/css/main.css" type="text/css" rel="stylesheet"/>
</head>
<body>
<?php
    require('Ovirt/Api.php');

    $api        = new OvirtApi('https://10.6.0.200/api/', 'admin@internal', 'W5UNJqFU', false);
    $vms        = $api->getResource('vms');
    $clusters   = $api->getResource('clusters');

    if(isset($_POST["vm_action"])) {
        $api->vm_action($_POST);
    }

    echo "<h3>List of virtual machines:</h3>";
    if(count((array)$vms)>0) {
        echo "<table id='vm-table'><th width='75px'>Status</th><th width='150px'>Name</th><th>Actions</th>";
        foreach($vms as $vm) {
            $vm_id = $vm->attributes()->id;

            echo "<tr>";
            # Data
            echo "<td>" . $vm->status->state . "</td>";
            echo "<td>" . $vm->name . "</td>";
            echo "<td>";
            # Actions
            # Start
            echo "<form name='vm-actions' action='' method='post'>";
            echo "<input id='vm_id' name='vm_id' type='hidden' value='" . $vm_id . "'>";
            echo "<input id='vm_action' name='vm_action' type='hidden' value='start'>";
            echo "<input type='submit' value='Start'>";
            echo "</form>";
            # Stop
            echo "<form name='vm-actions' action='' method='post'>";
            echo "<input id='vm_id' name='vm_id' type='hidden' value='" . $vm_id . "'>";
            echo "<input id='vm_action' name='vm_action' type='hidden' value='stop'>";
            echo "<input type='submit' value='Stop'>";
            echo "</form>";

            // foreach($vm->actions->link as $action) {
            // 		echo "[ <a href='" . $url . $action->attributes()->href . "'>" . $action->attributes()->rel . "</a> ]&nbsp";
            // }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No virtual machines found";
    }

    echo "<h3>List of clusters:</h3>";
    if(count((array)$clusters)>0) {
        echo "<ul>";
        foreach($clusters as $item) {
            echo "<li>" . $item->name . "</li>";
        }
        echo "</ul>";
    } else {
        echo "No clusters found";
    }
?>
</body>
</html>