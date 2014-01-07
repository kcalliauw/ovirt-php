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

        $this->url              = $url;
        $this->username         = $username;
        $this->password         = $password;
        $this->datacenter_id    = $datacenter_id;
        $this->cluster_id       = $cluster_id;
        $this->filtered_api     = $filtered_api;
        $this->ovirt_ch         = curl_init();


        $this->resetHeaders();
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
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function getResource($resource = null) {
        if(is_null($resource))
            throw new MissingParametersException('A resource is required');

        $response = $this->_get($this->url . $resource);
        // Newly created VMs do not contain disks / nics, and errors out here.
        // TODO: Only attempt to pregmatch when trying to access disk or nic rescource
        if(preg_match('/Error report/i', $response))
            return null;
        else
            return new SimpleXMLElement($response);
	}

    /**
     * @param $url
     * @return mixed
     * @throws RequestException
     */
    protected function _get($url) {
        curl_setopt($this->ovirt_ch, CURLOPT_URL, $url);
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $this->_getHeaders());
        $response =  curl_exec($this->ovirt_ch);
        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
    }

    /**
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
     * @param string $resource
     * @return SimpleXMLElement
     * @throws MissingParametersException
     */
    public function putResource($resource = null, $data = null) {
        if(is_null($resource)) {
            throw new MissingParametersException('A resource is required');
        }
        $response = $this->_put($this->url . $resource, $data);
        echo($response);
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
        curl_setopt($this->ovirt_ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        if(!is_null($data)) {
            curl_setopt($this->ovirt_ch, CURLOPT_POSTFIELDS, $data);
        }
        // Save headers
        $tmp_headers = $this->http_headers;
        // Headers must be cleared for succesful delete-request
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, array());
        $response =  curl_exec($this->ovirt_ch);
        // Re-initialize original headers for new requests
        curl_setopt($this->ovirt_ch, CURLOPT_HTTPHEADER, $tmp_headers);

        if($response!==false) {
            return $response;
        } else {
            throw new RequestException(curl_error($this->ovirt_ch), curl_errno($this->ovirt_ch));
        }
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
     * @param null $search
     * @return array
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
     * @param $host_id
     * @return Host
     */
    public function getHost($host_id) {
        return new Host($this, $this->getResource(sprintf('hosts/%s', urlencode($host_id))));
    }

    /**
     * @param null $search
     * @return array
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
     * @param $domain_id
     * @return StorageDomain
     */
    public function getStorageDomain($domain_id) {
        return new StorageDomain($this, $this->getResource(sprintf('storagedomains/%s', urlencode($domain_id))));
    }

    /**
     * @param null $search
     * @return array
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
     * @param $vm_id
     * @return Vm
     */
    public function getVm($vm_id) {
        return new Vm($this, $this->getResource(sprintf('vms/%s', urlencode($vm_id))));
    }

    /**
     * @param $data
     * @return Vm
     */
    public function createVm($data) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
         return new Vm($this, $this->postResource('vms/', Vm::toXML($data)));
    }


    /**
     * @param $id
     * @return IFace
     */
    public function deleteVm($id) {
        $this->deleteResource('vms/' . $id);
    }

    /**
     * @param $data
     * @return Vm
     */
    public function updateVm($data) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
        return new Vm($this, $this->putResource('vms/', Vm::toXML($data)));
    }

    /**
     * @param $data
     * @return Vm
     */
    public function setTicket($vm_id, $expiry) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
        return new Vm($this, $this->postResource('vms/' . $vm_id . '/ticket', Vm::getTicket($expiry)));
    }


    /**
     * @param $data
     * @return IFace
     */
    public function createInterface($vm_id, $data) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
        return new IFace($this, $this->postResource('vms/' . $vm_id . '/nics', IFace::toXML($data)));
    }

    /**
     * @param $id
     */
    public function deleteInterface($vm_id, $id) {
       $this->deleteResource('vms/' . $vm_id . '/nics/' . $id);
    }

    /**
     * @param $data
     * @return IFace
     */
    public function updateInterface($vm_id, $id, $data) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
        return new IFace($this, $this->putResource('vms/' . $vm_id . '/nics/' . $id, IFace::toXML($data)));
    }

    /**
     * @param $data
     */
    public function createVolume($vm_id, $data) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
        return new Volume($this, $this->postResource('vms/' . $vm_id . '/disks', Volume::toXML($data)));
    }

    /**
     * @param $id
     * @return IFace
     */
    public function deleteVolume($vm_id, $id) {
        $this->deleteResource('vms/' . $vm_id . '/disks/' . $id);
    }

    /**
     * @param $data
     */
    public function createTemplate($data) {
        // Make sure $data is properly formatted and capitalized where needed e.g. the 'default' cluster is not 'Default'..
        return new Template($this, $this->postResource('templates/', Template::toXML($data)));
    }

    /**
     * @param $id
     * @return IFace
     */
    public function deleteTemplate($id) {
        $this->deleteResource('templates/'. $id);
    }

    /**
     * @param null $search
     * @return array
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
     * @param $host_id
     * @return StorageDomain
     */
    public function getTemplate($template_id) {
        return new Template($this, $this->getResource(sprintf('templates/%s', urlencode($template_id))));
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
     * @return string
     */
    public function base_url() {
        return $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
    }

    /**
     * @param $response
     */
    protected function parseResponse($response) {
        return new SimpleXMLElement($response);
    }
}