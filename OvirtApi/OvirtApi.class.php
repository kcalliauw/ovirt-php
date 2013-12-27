<?php

#
# Copyright ( c) 2013 Layer7 BVBA
#
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
#
#           http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.
#

class OvirtApi {

	private $url;
	private $username;
	private $password;

	private $dc_name;
	private $cluster_name;
	private $host_name;
	private $storage_name;
	// private $export_name;
	private $vm_name;

	public function __construct () {
		// TODO: Remove before production
		# Debug settings
		ini_set('xdebug.var_display_max_depth', '10'); 
		# Variables
		$this->url = 'https://10.11.0.115/api/';
		$this->username = 'admin@internal';
		$this->password = 'W5UNJqFU';
	}	

	public function get_url() {
		return preg_replace('/api\//', '', $this->url);
	}

	public function get_vms () {

		# Perform login request.
		$ovirt_ch = curl_init();
		curl_setopt($ovirt_ch, CURLOPT_URL, $this->url . 'vms');
		curl_setopt($ovirt_ch, CURLOPT_POST, false);
		curl_setopt($ovirt_ch, CURLOPT_HEADER, 'application/xml');
		curl_setopt($ovirt_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ovirt_ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		curl_setopt($ovirt_ch, CURLOPT_SSL_VERIFYPEER, false);
		$response =  curl_exec($ovirt_ch);

		# Parse response
		$vms =  new SimpleXMLElement($response);

		curl_close($ovirt_ch);
		unset($ovirt_ch);

		# Dumps
		// foreach($vms as $vm) {
		// 	echo '========== HREF ==========';
		// 	var_dump($vm->attributes()->href);

		// 	echo '========== ID ==========';
		// 	var_dump($vm->attributes()->id);

		// 	echo '========== ACTIONS ==========</br>';
		// 	$actions = $vm->actions->link;
		// 	foreach($actions as $action) {
		// 		echo '#HREF';
		// 		var_dump($action->attributes()->href);
		// 		echo '#REL';
		// 		var_dump($action->attributes()->rel);
		// 	}

		// 	echo '========== ALL INFO ==========';
		// 	var_dump($vm);
		// } die();

		return $vms;
	}

	public function vm_action($action = null, $id = null){
		// If not set, retrieve from form
		if(is_null($action) || is_null($id)) {
			$action = $_POST['vm_action'];
			$id = $_POST['vm_id'];
		}

		# Set curl params
		$ovirt_ch = curl_init();

		# action
		# 	vm
		# 		os
		# 			boot dev = cd-rom		/boot
		# 		/os
		# 	/vm
		# /action

		$xml = new SimpleXMLElement('<action/>');
		$vm = $xml->addChild('vm');
		$os = $vm->addChild('os');
		$boot = $os->addChild('boot');
		$boot->addAttribute('dev', 'hd');
		$data = $xml->asXML();

		curl_setopt($ovirt_ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
		curl_setopt($ovirt_ch, CURLOPT_HEADER, 'application/xml');
		curl_setopt($ovirt_ch, CURLOPT_POST, true); 
		curl_setopt($ovirt_ch, CURLOPT_POSTFIELDS, $data); 
		curl_setopt($ovirt_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ovirt_ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		curl_setopt($ovirt_ch, CURLOPT_SSL_VERIFYPEER, false);

		# Determine action
		switch($action) {
			case 'start':
				echo 'Starting';
				curl_setopt($ovirt_ch, CURLOPT_URL, $this->url . 'vms/' . $id . '/start');
			case 'stop':
				echo 'Stopping';
				curl_setopt($ovirt_ch, CURLOPT_URL, $this->url . 'vms/' . $id . '/stop');
				break;
			default:
				echo 'No action specified';
		}

		// Perform action
		$response =  curl_exec($ovirt_ch);
		echo $response;

		curl_close($ovirt_ch);
		unset($ovirt_ch);
	}

	public function create_vm($params) {
		// Get VM Parameters
		$name = $params['name'];
		$cluster = $params['cluster'];
		$template = $params['template'];
		// Memory in bytes
		$memory = $params['memory'];
		$boot_dev = $params['boot_dev'];

		# Set curl params
		$ovirt_ch = curl_init();

		$xml = new SimpleXMLElement('<vm/>');
		$name = $xml->addChild('name', $name);
		$cluster = $xml->addChild('cluster');
		$cluster->addChild('name', $cluster)
		$template = $xml->addChild('template');
		$template->addChild('name', $template)
		$memory = $xml->addChild('memory', $memory)
		$os = $vm->addChild('os');
		$boot = $os->addChild('boot');
		$boot->addAttribute('dev', $boot_dev);
		$data = $xml->asXML();

		curl_setopt($ovirt_ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
		curl_setopt($ovirt_ch, CURLOPT_HEADER, 'application/xml');
		curl_setopt($ovirt_ch, CURLOPT_POST, true); 
		curl_setopt($ovirt_ch, CURLOPT_POSTFIELDS, $data); 
		curl_setopt($ovirt_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ovirt_ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
		curl_setopt($ovirt_ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ovirt_ch, CURLOPT_URL, $this->url . 'vms/');

		// TODO: Create / attach NICs and disk

		// Perform action
		$response =  curl_exec($ovirt_ch);
		echo $response;

		curl_close($ovirt_ch);
		unset($ovirt_ch);
	}

}

?>