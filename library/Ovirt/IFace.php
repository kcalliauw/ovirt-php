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
class IFace extends BaseObject{

    public $mac = null;
    public $interface = null;
    public $network = null;
    public $vm = null;

    public function __construct(OvirtApi &$client, SimpleXMLElement $xml) {
        parent::__construct($client, $xml->attributes()['id']->__toString(), $xml->attributes()['href']->__toString(), $xml->name->__toString());
        $this->_parse_xml_attributes($xml);
    }

    public static function toXML($data) {
        // Initialize IFace XML Element
        $xml = new SimpleXMLElement('<nic/>');
        # Name
        if(array_key_exists('name', $data))
            $xml->addChild('name', $data['name']);
        # VM
        // Is set automatically after the interface is added to the VM
        # Network
        if(array_key_exists('network', $data)) {
            $network = $xml->addChild('network');
            $network->addChild('name', $data['network']['name']);
        }
        # Link State (UP / DOWN) Default: UP
        if(array_key_exists('linked', $data))
            $xml->addChild('linked', $data['linked']);
        # Interface
        if(array_key_exists('interface', $data))
            $xml->addChild('interface', $data['interface']);
        # Mac
        if(array_key_exists('mac', $data))
            $xml->addChild('mac', $data['mac']);
        # Active
        // Is set automatically depending on 'plugged'
        # Plugged
        if(array_key_exists('plugged', $data))
            $xml->addChild('plugged', $data['plugged']);

        return $xml->asXML();
    }

    protected function _parse_xml_attributes(SimpleXMLElement $xml) {
        // Templates do not have these variables
        if(!empty($xml->mac->attributes['address']) || !empty($xml->vm->attributes()['id'])) {
            $this->mac = $xml->mac->attributes()['address']->__toString();
            $this->vm = $xml->vm->attributes()['id']->__toString();
        }
        $this->interface = $xml->interface->__toString();
        $this->network = $xml->network->attributes()['id']->__toString();
    }
}