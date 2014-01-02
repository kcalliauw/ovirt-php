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

require_once('BaseObject.php');
require_once('Network.php');
class Cluster extends BaseObject
{

    public $description = null;
    public $datacenter = null;
    public $version = null;

    public function __construct(OvirtApi &$client, SimpleXMLElement $xml) {
        parent::__construct($client, $xml->attributes()['id']->__toString(), $xml->attributes()['href']->__toString(), $xml->name->__toString());
        $this->_parse_xml_attributes($xml);
    }

    protected function _parse_xml_attributes(SimpleXMLElement $xml) {
        $this->description = (strlen($xml->description->__toString())>0) ? $xml->description->__toString(): null;
        $this->version = $this->parseVersion($xml->version);
        $this->datacenter = $xml->data_center->attributes()['id']->__toString();
    }

    public function getVersion() {
        return $this->version;
    }

    public function getNetworks($id = null) {
        if(is_null($id)) {
            $id = $this->id;
        }

        $networks = array();
        $response = $this->client->getResource('clusters/' . $id . '/networks');
        foreach($response as $item) {
            $networks[] = new Network($this->client, $item);
        }

        return $networks;
    }
}