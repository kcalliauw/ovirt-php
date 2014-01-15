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
require_once('Ovirt/Host.php');
require_once('Ovirt/StorageDomain.php');
require_once('Ovirt/Vm.php');
require_once('Ovirt/Quota.php');
require_once('Ovirt/IFace.php');
require_once('Ovirt/Template.php');

class OvirtApi
{

	private $url;
	private $username;
	private $password;
    private $ovirt_ch;
    private $http_headers;

	private $datacenter_id;
	private $cluster_id;
    private $filtered_api;
    private $insecure;

    /**
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
    public function __construct ($insecure = false, $datacenter_id = null, $cluster_id = null, $filtered_api = false, $debug = false) {

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

        // Initialize API using Config file
        $config = parse_ini_file("config/config.ini");
        $this->url              = $config['url'];
        $this->username         = $config['username'];
        $this->password         = $config['password'];

        $this->filtered_api     = ($config['filtered']) ? true : false;
        $this->insecure         = ($config['insecure']) ? true : false;

        $this->datacenter_id    = $datacenter_id;
        $this->cluster_id       = $cluster_id;

        // Initialize connection
        $this->initCurl();

    }
    /**
     * (re-)Initializes the connection with necessary options
     * @return void
     */
    public function initCurl() {
        $this->ovirt_ch= curl_init();

        $this->resetHeaders();
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        curl_setopt($this->ovirt_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ovirt_ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

        if($this->insecure) {
            curl_setopt($this->ovirt_ch, CURLOPT_SSL_VERIFYPEER, false);
        }
    }

    /**
     * Returns the HTTP headers of the current connection
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
     * Adds new headers to the existing HTTP_HEADERS of the current connection
     * @param array $headers Associative array of header values to add or set new values for
     * @return void
     */
    public function addHeaders(array $headers) {
        foreach($headers as $k => $v) {
            $this->http_headers[$k] = $v;
        }
    }

    /**
     * Resets the headers to their original, desired state as if the connection was just initialized
     * This function is used to prevent custom requests from hogging the channel
     * @return bool
     */
    public function resetHeaders() {
        $this->http_headers     = array(
            'Content-Type' => 'application/xml',
            'Accept' => 'application/xml'
        );
        return true;
    }

    /**
     * Determines whether or not the previous request was a PUT / DELETE request
     * The function assists in resetting the connection after such a custom request
     * @return bool
     */
    private function isCustomRequest() {
        $info = curl_getinfo($this->ovirt_ch);
        // If URL contains an ID of any kind, a custom request (PUT / DELETE) was made
        $isCustom = preg_match('/[a-z0-9]{8}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{12}/', $info['url']);
        return $isCustom;
    }


    /**
     * Perform a HTTP GET request to obtain the specified resource
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function getResource($resource = null) {
        if(is_null($resource))
            throw new MissingParametersException('A resource is required');

        $response = $this->_get($this->url . $resource);
         return new SimpleXMLElement($response);
	}

    /**
     * @param $url
     * @return mixed
     * @throws RequestException
     */
    protected function _get($url) {
        // Re-initialize CURL after PUT / DELETE request
        if($this->isCustomRequest($url))
            $this->initCurl($this->insecure);

        // When retrieving a VM we need to make sure to get all information
        if(preg_match('/vms/', $url)) {
            $headers = array(
                'Accept' => "application/xml; detail=disks; detail=nics; detail=hosts",
            );
            $this->addHeaders($headers);
        }

        curl_setopt($this->ovirt_ch, CURLOPT_URL, $url);
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        curl_setopt($this->ovirt_ch, CURLOPT_POST, false);
        $response =  curl_exec($this->ovirt_ch);
        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
    }

    /**
     * Perform a HTTP POST request to create / add the specified resource
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function postResource($resource = null, $data = null) {
        if(is_null($resource)) {
            throw new MissingParametersException('A resource is required');
        }

        $response = $this->_post($this->url . $resource, $data);
        $xml = new SimpleXMLElement($response);
        return $xml;
    }

    /**
     * @param $url
     * @return mixed
     * @throws RequestException
     */
    protected function _post($url, $data) {
        // Re-initialize CURL after PUT / DELETE request
        if($this->isCustomRequest($url))
            $this->initCurl($this->insecure);

        curl_setopt($this->ovirt_ch, CURLOPT_URL, $url);
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        curl_setopt($this->ovirt_ch, CURLOPT_POST, true);
        curl_setopt($this->ovirt_ch, CURLOPT_POSTFIELDS, $data);

        $response =  curl_exec($this->ovirt_ch);

        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
    }

    /**
     * Perform a HTTP PUT request to update the specified resource
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function putResource($resource = null, $data = null) {
        if(is_null($resource)) {
            throw new MissingParametersException('A resource is required');
        }
        $response = $this->_put($this->url . $resource, $data);
        $xml = new SimpleXMLElement($response);
        return $xml;
    }

    /**
     * @param $url
     * @return mixed
     * @throws RequestException
     */
    protected function _put($url, $data) {
        curl_setopt($this->ovirt_ch, CURLOPT_URL, $url);
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        curl_setopt($this->ovirt_ch, CURLOPT_POST, false);
        curl_setopt($this->ovirt_ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($this->ovirt_ch, CURLOPT_POSTFIELDS, $data);
        $response =  curl_exec($this->ovirt_ch);
        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
    }

    /**
     * Perform a HTTP DELETE request to delete the specified resource
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function deleteResource($resource = null, $data = null) {
        if(is_null($resource)) {
            throw new MissingParametersException('A resource is required');
        }

        $response = $this->_delete($this->url . $resource, $data);
        $xml = new SimpleXMLElement($response);
        return $xml;
    }

    /**
     * @param $url
     * @return mixed
     * @throws RequestException
     */
    protected function _delete($url, $data=null) {
        curl_setopt($this->ovirt_ch, CURLOPT_URL, $url);
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        curl_setopt($this->ovirt_ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($this->ovirt_ch, CURLOPT_POST, false);
        curl_setopt($this->ovirt_ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        if(!is_null($data)) {
            curl_setopt($this->ovirt_ch, CURLOPT_POSTFIELDS, $data);
        }
        // Save headers
        $tmp_headers = $this->http_headers;
        // Headers must be cleared for succesful delete-operation with Ovirt API
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, array());
        $response = curl_exec($this->ovirt_ch);
        // Re-initialize original headers for new requests
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $tmp_headers);

        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
    }

    /**
     * Returns current active datacenter, if none is set, return the first
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
     * Retrieve information about all the datacenters
     * @param null $search
     * @return array datacenters[]
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
     * Retrieve a single datacenter with id $datacenter_id
     * @param string $datacenter_id
     * @return DataCenter
     */
    public function getDatacenter($datacenter_id) {
        return new DataCenter($this, $this->getResource(sprintf('datacenters/%s', urlencode($datacenter_id))));
    }

    /**
     * Returns current active Cluster, if none is set, return the first
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
     * Retrieve all clusters
     * @param null $search
     * @return array clusters[]
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
     * Retrieve a single cluster with id $cluster_id
     * @param string $cluster_id
     * @return Cluster
     */
    public function getCluster($cluster_id) {
        return new Cluster($this, $this->getResource(sprintf('clusters/%s', urlencode($cluster_id))));
    }

    /**
     * Retrieve all the hosts
     * @param null $search
     * @return array hosts[]
     */
    public function getHosts($search = null) {
        $search = is_null($search) ? '' : $search;
        $response = $this->getResource(sprintf('hosts?search=%s', urlencode($search)));
        $hosts = array();
        foreach($response as $item) {
            $hosts[] = new Host($this, $item);
        }
        return $hosts;
    }

    /**
     * Retrieve a specific host with id $host_id
     * @param string $host_id
     * @return Host
     */
    public function getHost($host_id) {
        return new Host($this, $this->getResource(sprintf('hosts/%s', urlencode($host_id))));
    }

    /**
     * Retrieve all the storage domains
     * @param null $search
     * @return array storageDomains[]
     */
    public function getStorageDomains($search = null) {
        $search = is_null($search) ? '' : $search;
        $response = $this->getResource(sprintf('storagedomains?search=%s', urlencode($search)));
        $domains = array();
        foreach($response as $item) {
            $domains[] = new StorageDomain($this, $item);
        }
        return $domains;
    }

    /**
     * Retrieve a specific storage domain with id $domain_id
     * @param string $domain_id
     * @return StorageDomain
     */
    public function getStorageDomain($domain_id) {
        return new StorageDomain($this, $this->getResource(sprintf('storagedomains/%s', urlencode($domain_id))));
    }

    /**
     * Retrieve all vms
     * @param null $search
     * @return array vms[]
     */
    public function getVms($search = null) {
        $search = is_null($search) ? '' : $search;
        $response = $this->getResource(sprintf('vms?search=%s', urlencode($search)));
        $vms = array();
        foreach($response as $item) {
            $vms[] = new Vm($this, $item);
        }
        return $vms;
    }

    /**
     * Retrieve a specific vm with id $vm_id
     * @param string $vm_id
     * @return Vm
     */
    public function getVm($vm_id) {
        $vm = new Vm($this, $this->getResource(sprintf('vms/%s', urlencode($vm_id))));
//        $this->resetHeaders();

        return $vm;
    }

    /**
     * Create a new VM
     * @param array $data
     * @return Vm
     */
    public function createVm($data) {
        /**
         * Make sure $data is properly formatted and capitalized where needed e.g. the 'Default' cluster is not 'default'..
         * To fully know what options / values are allowed we refer to your API (<your-base-url>/api/capabilities)
         *
         * Example:
         * $data = array(
         *   'name'      => 'VM-test',
         *   'cluster'   => array(
         *      'name'  => 'Default',
         *   ),
         *   'template'  => array(
         *      'name'  => 'Blank',
         *   ),
         *   'memory'    => '536870912',
         *   'os'        => array(
         *      'type'  => 'linux',
         *      'boot'  => array(
         *          'dev'   =>  'hd',
         *   )
         *   ),
         *   'profile'   => 'server',
         *   'display'   => array (
         *      'type'      => 'spice',
         *      'address'   => '10.11.0.116',
         *      'port'      => '5900',
         *      'secure_port'   => '',
         *      'subject'   => '',
         *      'monitors'  => '1',
         *   ),
         *   'cpu'       => array(
         *      'cores' => '2',
         *      'sockets'   => '2',
         *   ),
         *   );
         *
         */

        $vm = $this->postResource('vms/', Vm::toXML($data));
        return new Vm($this, $vm);
    }


    /**
     * Delete a VM resource with id $vm_id
     * @param string $vm_id
     * @return void
     */
    public function deleteVm($vm_id) {
        $this->deleteResource('vms/' . $vm_id);
    }

    /**
     * Update a VM with new options / values
     * @param string $vm_id
     * @param array $data
     * @return Vm
     */
    public function updateVm($vm_id, $data) {
        /*
         *  Make sure $data is properly formatted and capitalized, only supply keys for the values you wish to change.
         *  Everything else will remain as is. Refer to createVm for a complete syntax example
         */
        return new Vm($this, $this->putResource('vms/' . $vm_id, Vm::toXML($data)));
    }

    /**
     * Create a new NIC for the specified VM with id $vm_id
     * @param array $data
     * @return IFace
     */
    public function createInterface($vm_id, $data) {
        /**
         * Make sure $data is properly formatted and capitalized where needed
         * To fully know what options / values are allowed we refer to your API (<your-base-url>/api/capabilities)
         *     $nic = array(
         *          'name'      => 'nic3',
         *          'interface' => 'virtio',
         *          'plugged'   => 'true',
         *          'network'   => array(
         *              'name'  => 'ovirtmgmt',
         *          ),
         *      );
         */
        return new IFace($this, $this->postResource('vms/' . $vm_id . '/nics', IFace::toXML($data)));
    }

    /**
     * Delete NIC $nic_id on the specified VM with id $vm_id
     * @param string $vm_id
     * @param string $nic_id
     * @return void
     */
    public function deleteInterface($vm_id, $nic_id) {
       $this->deleteResource('vms/' . $vm_id . '/nics/' . $nic_id);
    }

    /**
     * Update an interface with new options / values
     * @param array $data
     * @return IFace
     */
    public function updateInterface($vm_id, $nic_id, $data) {
        /*
         *  Make sure $data is properly formatted and capitalized, only supply keys for the values you wish to change.
         *  Everything else will remain as is. Refer to createInterface for an example
         */
        return new IFace($this, $this->putResource('vms/' . $vm_id . '/nics/' . $nic_id, IFace::toXML($data)));
    }

    /**
     * Create a new disk for the specified VM with id $vm_id
     * @param string $vm_id
     * @param array $data
     * @return Volume
     */
    public function createVolume($vm_id, $data) {
        /**
         * Make sure $data is properly formatted and capitalized where needed
         * To fully know what options / values are allowed we refer to your API (<your-base-url>/api/capabilities)
         *    $disk_test = array(
         *      'name'              => 'new-disk',
         *      'storage_domain'    => '23b600ed-0d96-415e-b356-08c336f4415e',
         *      'interface'         => 'virtio',
         *      'size'              => '10737418240',
         *      'type'              => 'system',
         *      'format'            => 'cow',
         *      'bootable'          => 'false',
         *      'shareable'         => 'false',
         *      'sparse'            => 'true',
         *    );
         */
        return new Volume($this, $this->postResource('vms/' . $vm_id . '/disks', Volume::toXML($data)));
    }

    /**
     * Delete disk $disk_id for the specified VM with id $vm_id
     * @param string $vm_id
     * @param string $disk_id
     * @return void
     */
    public function deleteVolume($vm_id, $disk_id) {
        $this->deleteResource('vms/' . $vm_id . '/disks/' . $disk_id);
    }

    /**
     * Create a new template for the specified VM with id $vm_id
     * @param string $vm_id: the VM of which to make a template of
     * @param array $data
     * @return Template
     */
    public function createTemplate($data) {
        /*
         * Make sure your $data is formatted properly. An example:
         *     $template_test = array(
         *       'name'      => 'template-test',
         *       'vm_id'     => 'a27d2ff7-33e4-4bcb-a748-99e9204d9b61',
         *     );
         */
        return new Template($this, $this->postResource('templates/', Template::toXML($data)));
    }

    /**
     * Delete template with id $template_id
     * @param string $template_id
     * @return void
     */
    public function deleteTemplate($template_id) {
        $this->deleteResource('templates/'. $template_id);
    }

    /**
     * Get all templates
     * @param null $search
     * @return array templates[]
     */
    public function getTemplates($search = null) {
        $search = is_null($search) ? '' : $search;
        $response = $this->getResource(sprintf('templates?search=%s', urlencode($search)));
        $templates = array();
        foreach($response as $item) {
            $templates[] = new Template($this, $item);
        }
        return $templates;
    }

    /**
     * Get a specific template with id $id
     * @param string $template_id
     * @return Template
     */
    public function getTemplate($template_id) {
        return new Template($this, $this->getResource(sprintf('templates/%s', urlencode($template_id))));
    }

    /**
     * Returns the version of the currently used Ovirt Installation
     * @return string
     */
    public function getApiVersion() {
        $response = $this->getResource('/');
        $info = (array)$response->product_info->version;
        return $info['@attributes']['major'] . '.' . $info['@attributes']['minor'];
    }

    /**
     * Checks wether or not the floppy hook is present
     * @return boolean
     */
    public function floppyHook() {
        $response = $this->getResource('capabilities');
        if(!$response->xpath('version/custom_properties/custom_property[@name="floppyinject"]'))
            return false;

        return true;
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

    /**
     * Returns the base url of the application
     * @return string
     */
    public function base_url() {
        return $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
    }

    /**
     * Convert response to XML
     * @param $response
     */
    protected function parseResponse($response) {
        return new SimpleXMLElement($response);
    }
}