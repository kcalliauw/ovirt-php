<?php

#
# Copyright (c) 2013 Layer7 BVBA
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

require_once('Exception.php');
require_once('UtilityFunctions.php');
require_once('Ovirt/DataCenter.php');
require_once('Ovirt/Cluster.php');

class OvirtApi
{

	private $url;
	private $username;
	private $password;
    private $ovirt_ch;

	private $datacenter_id;
	private $cluster_id;
    private $filtered_api;

    /**
     * @param string $url server url (format "http(s)://server[:port]/api")
     * @param string $username user (format user@domain)
     * @param string $password password
     * @param bool $insecure signals to not demand site trustworthiness for ssl enabled connection (default is false)
     * @param int $datacenter_id the selected datacenter id
     * @param int $cluster_id the selected cluster id
     * @param bool $filtered_api enables filtering based on user's permissions (default is false)
     * @param bool $debug debug
     *
     * @throws NoCertificatesException: raised when CA certificate is not provided for SSL site (can be disabled using 'insecure=True' argument).
     * @throws UnsecuredConnectionAttemptException: raised when HTTP protocol is used in url against server running HTTPS.
     * @throws ImmutableException: raised on sdk < 3.2 when sdk initiation attempt occurred while sdk instance already exist under the same domain.
     * @throws DisconnectedException: raised when sdk usage attempt occurred after it was explicitly disconnected.
     * @throws MissingParametersException: raised when get() method invoked without id or name been specified.
     * @throws ConnectionException: raised when any kind of communication error occurred.
     * @throws RequestException: raised when any kind of oVirt server error occurred.
     * @throws FormatException: raised when server replies in non-XML format.
     * @throws GeneralException: raised when no more specific exception is applicable.
     */
    public function __construct ($url, $username, $password, $insecure = false, $datacenter_id = null, $cluster_id = null, $filtered_api = false, $debug = false) {

        if($debug) {
            if(extension_loaded('xdebug')) {
                if( ini_set('xdebug.var_display_max_depth', '10') === false ) {
                    throw new GeneralException('Failed to set XDebug ini settings');
                }
            } else {
                throw new GeneralException('The XDebug extension is required when using $debug=true');
            }
        }
        if(!extension_loaded('curl')) {
            throw new GeneralException('cURL extension is required to use this project');
        }

        $this->url = $url;
        $this->username      = $username;
        $this->password      = $password;
        $this->datacenter_id = $datacenter_id;
        $this->cluster_id    = $cluster_id;
        $this->filtered_api  = $filtered_api;
        $this->ovirt_ch      = curl_init();
        $this->http_headers  = array(
            'Content-Type' => 'application/xml',
            'Accept' => 'application/xml'
        );

        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        curl_setopt($this->ovirt_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ovirt_ch, CURLOPT_USERPWD, $username . ':' . $password);

        if($insecure) {
            curl_setopt($this->ovirt_ch, CURLOPT_SSL_VERIFYPEER, false);
        }

    }

    /**
     * @return array
     */
    protected function _getHeaders() {
        $headers = array();
        foreach($this->http_headers as $k => $v) {
            $headers[] = $k . ': ' . $v;
        }
        return $headers;
    }

    /**
     * @param array $headers Associative array of header values to add or set new values for
     */
    public function addHeaders(array $headers) {
        foreach($headers as $k => $v) {
            $this->http_headers[$k] = $v;
        }
    }

    /**
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function getResource($resource = null) {

        if(is_null($resource)) {
            throw new MissingParametersException('A resource is required');
        }

        $response = $this->_get($this->url . $resource);
        $data = new SimpleXMLElement($response);
		return $data;
	}

    /**
     * @return DataCenter
     */
    public function getCurrentDatacenter() {
        if(is_null($this->datacenter_id)) {
            $datacenters = $this->getDatacenters();
            return $datacenters[0];
        } else {
            return $this->getDatacenter($this->datacenter_id);
        }
    }

    /**
     * @param null $search
     * @return array
     */
    public function getDatacenters($search = null) {
        $search = is_null($search) ? '' : $search;
        $response = $this->getResource(sprintf('datacenters?search=%s', urlencode($search)));
        $dcs = array();
        foreach($response->data_center as $dc) {
            $dcs[] = new DataCenter($this, $dc);
        }
        return $dcs;
    }

    /**
     * @param $datacenter_id
     * @return DataCenter
     */
    public function getDatacenter($datacenter_id) {
        return new DataCenter($this, $this->getResource(sprintf('datacenters/%s', urlencode($datacenter_id))));
    }

    /**
     * @return Cluster
     */
    public function getCurrentCluster() {
        if(is_null($this->cluster_id)) {
            $clusters = $this->getClusters();
            return $clusters[0];
        } else {
            return $this->getCluster($this->cluster_id);
        }
    }

    /**
     * @param null $search
     * @return array
     */
    public function getClusters($search = null) {
        $search = is_null($search) ? '' : $search;
        $response = $this->getResource(sprintf('clusters?search=%s', urlencode($search)));
        $clusters = array();
        foreach($response->cluster as $cluster) {
            $clusters[] = new Cluster($this, $cluster);
        }
        return $clusters;
    }

    /**
     * @param $cluster_id
     * @return Cluster
     */
    public function getCluster($cluster_id) {
        return new Cluster($this, $this->getResource(sprintf('clusters/%s', urlencode($cluster_id))));
    }

    /**
     * @return string
     */
    public function getApiVersion() {
        $response = $this->getResource('/');
        $info = (array)$response->product_info->version;
        return $info['@attributes']['major'] . '.' . $info['@attributes']['minor'];
    }

    /**
     * @param $url
     * @return mixed
     * @throws RequestException
     */
    protected function _get($url) {
        curl_setopt($this->ovirt_ch, CURLOPT_URL, $url);
        $response =  curl_exec($this->ovirt_ch);
        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
    }

    /**
     * @param $options
     * @return string
     */
    protected function _search_url($options) {
        $current_datacenter = $this->getCurrentDatacenter();
        $search = (is_null($options)) ? sprintf('datacenter=%s', $current_datacenter->name) : $options;
        return sprintf('?search=%s', $search);
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
		$cluster->addChild('name', $cluster);
		$template = $xml->addChild('template');
		$template->addChild('name', $template);
		$memory = $xml->addChild('memory', $memory);
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