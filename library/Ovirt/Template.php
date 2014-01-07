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
require_once('IFace.php');
require_once('Volume.php');
class Template extends BaseObject{

    public $description = null;
    public $status = null;
    public $cluster = null;
    public $creation_time = null;
    public $os = null;
    public $storage = null;
    public $display = null;
    public $profile = null;
    public $memory = null;

    public function __construct(OvirtApi &$client, SimpleXMLElement $xml) {
        parent::__construct($client, $xml->attributes()['id']->__toString(), $xml->attributes()['href']->__toString(), $xml->name->__toString());
        $this->_parse_xml_attributes($xml);
    }

    /**
     * @param $array
     * @return SimpleXMLElement
     */
    public static function toXML($data) {
        // Initialize VM XML Element
        $xml = new SimpleXMLElement('<template/>');
        // Parse array to elements
        # Name
        if(array_key_exists('name', $data)) {
            $xml->addChild('name', $data['name']);
        } # VM
        if(array_key_exists('vm_id', $data)) {
            $vm = $xml->addChild('vm');
//            $vm->addChild('id', $data['vm_id']);
            $vm->addAttribute('id', $data['vm_id']);
        }
        var_dump($xml);
        return $xml->asXML();
    }

    /**
     * @param SimpleXMLElement
     * @return $array
     */
    protected function _parse_xml_attributes(SimpleXMLElement $xml) {
        $this->description = (strlen($xml->description->__toString())>0) ? $xml->description->__toString(): null;
        $this->status = $xml->status->state->__toString();
        $this->cluster = $xml->cluster->attributes()['id']->__toString();
        $this->creation_time = $xml->creation_time->__toString();
        $this->profile = $xml->type->__toString();
        $this->memory = $xml->memory->__toString();
        # OS
        $boot = array();
        foreach($xml->os->boot as $dev) {
            $boot[] = $dev->attributes()['dev']->__toString();
        }
        $this->os = array(
            'type'  => $xml->os->attributes()['type']->__toString(),
            'boot'  => $boot,
        );
        # Storage
        $disks = $this->client->getResource('templates/' . $this->id . '/disks');
        $disk_size = 0;
        foreach($disks as $disk) {
            $disk_size += $disk->size->__toString();
        }
        $this->storage = $disk_size;
        # Display
        $this->display = array(
            'type'      => $xml->display->type->__toString(),
            'monitors'  => $xml->display->monitors->__toString(),
        );
    }

    /**
     * @param $id
     * @return IFace[]
     */
    public function getInterfaces($id = null) {
        if(is_null($id)) {
            $id = $this->id;
        }
        $interfaces = array();
        $response = $this->client->getResource('templates/' . $id . '/nics');
        foreach($response as $item) {
            $interfaces[] = new IFace($this->client, $item);
        }
        return $interfaces;
    }

    /**
     * @param $id
     * @return Volume[]
     */
    public function getVolumes($id = null) {
        if(is_null($id)) {
            $id = $this->id;
        }

        $volumes = array();
        $response = $this->client->getResource('templates/' . $id . '/disks');
        foreach($response as $item) {
            $volumes[] = new Volume($this->client, $item);
        }

        return $volumes;
    }

    // TODO: create_template, destroy_template
}