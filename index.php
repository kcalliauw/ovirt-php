<!DOCTYPE html>
<html>
	<head>
		<style>
		body {
			width: 1200px;
			padding-top: 10px;
			margin: auto;
		}
			#vm-table {
				font-family:"Trebuchet MS", Arial, Helvetica, sans-serif;
				width:100%;
				border-collapse:collapse;
			}
			#vm-table td, #vm-table th {
				font-size:1em;
				border:1px solid #98bf21;
				padding:3px 7px 2px 7px;
			}
			#vm-table th {
				font-size:1.1em;
				text-align:left;
				padding-top:5px;
				padding-bottom:4px;
				background-color:#A7C942;
				color:#ffffff;
			}
			#vm-table tr:nth-child(odd) {
				background-color: #E0EDB9;
			}
		</style>
	</head>
	<body>
	<?php
		include 'OvirtApi/OvirtApi.class.php';

		$api = new OvirtApi();
		$vms = $api->get_vms();

		if(isset($_POST["vm_action"])) {
			$api->vm_action($_POST);
		}
	?>


	<!-- Start table -->
	<table id='vm-table'>
	<!-- Table Headers -->
	<th width='75px'>Status</th>
	<th width='150px'>Name</th>
	<th>Actions</th>

	<?php
	# Table Content
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
	?>

	<!-- End table -->
	</table>

	</body>
</html>