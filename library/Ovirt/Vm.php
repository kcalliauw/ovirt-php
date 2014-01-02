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
require_once('Volume.php');

class Vm extends BaseObject{

    public $status = null;
    public $memory = null;
    public $profile = null;
    public $display = null;
    public $host = null;
    public $cluster = null;
    public $template = null;
    public $storage = null;
    public $cores = null;
    public $creation_time;
    public $os = null;
    public $ips = null;
    public $vnc = null;
    public $quota = null;
    public $interfaces = null;
    public $volumes = null;

    public function __construct(OvirtApi &$client, SimpleXMLElement $xml) {
        parent::__construct($client, $xml->attributes()['id']->__toString(), $xml->attributes()['href']->__toString(), $xml->name->__toString());
        $this->_parse_xml_attributes($xml);
    }

    protected function _parse_xml_attributes(SimpleXMLElement $xml) {
        $this->description = (strlen($xml->description->__toString())>0) ? $xml->description->__toString(): null;
        $this->status = $xml->status->state->__toString();
        $this->memory = $xml->memory->__toString();
        $this->profile = $xml->type->__toString();
        $this->host = (strlen($xml->host->__toString())>0) ? $xml->host->__toString(): null;
        $this->cluster = $xml->cluster->attributes()['id']->__toString();
        $this->template = $xml->template->__toString();
        $this->cores = $xml->cpu->topology->attributes()['cores']->__toString();
        $this->creation_time = $xml->creation_time->__toString();
        $this->quota = $xml->quota->__toString();
        $this->interfaces = $this->getInterfaces();
        $this->volumes = $this->getVolumes();
        # Storage
        $disk_size = 0;
        foreach($this->volumes as $volume) {
            $disk_size += $volume->size;
        }
        $this->storage = $disk_size;

        # OS
        $boot = array();
        foreach($xml->os->boot as $dev) {
            $boot[] = $dev->attributes()['dev']->__toString();
        }
        $this->os = array(
            'type'  => $xml->os->attributes()['type']->__toString(),
            'boot'  => $boot,
        );
        # Display
        $this->display = array(
            'type'          => $xml->display->type->__toString(),
            'address'       => $xml->display->address->__toString(),
            'port'          => $xml->display->port->__toString(),
            'secure_port'   => $xml->display->secure_port->__toString(),
            'subject'       => $xml->display->subject->__toString(),
            'monitors'      => $xml->display->monitors->__toString(),
        );
        $this->ips = (strlen($xml->ips->__toString())>0) ? $xml->ips->__toString(): null;
        $this->vnc = array(
            'address'   => $xml->display->address->__toString(),
            'port'      => $xml->display->port->__toString(),
        );
    }

    public function toXML() {
        // Minimum XML needed to create a VM
        //        <vm>
        //  <name>vm1</name>
        //  <cluster>
        //    <name>default</name>
        //  </cluster>
        //  <template>
        //    <name>Blank</name>
        //  </template>
        //  <memory>536870912</memory>
        //  <os>
        //    <boot dev="hd"/>
        //  </os>
        //</vm>

        $xml = new SimpleXMLElement('<vm/>');
        $name = $xml->addChild('name', $this->name);
        $cluster = $xml->addChild('cluster');
        $cluster->addChild('name', $this->cluster);
        $template = $xml->addChild('template');
        $template->addChild('name', $this->template);
        $memory = $xml->addChild('memory', $this->memory);
        $os = $xml->addChild('os');
        $boot = $os->addChild('boot');
        $boot->addAttribute('dev', 'hd');
        $data = $xml->asXML();

        return $data;
    }

    private function getInterfaces() {
        # Interfaces
        $interfaces = array();
        $nics = $this->client->getResource('vms/' . $this->id . '/nics');
        foreach($nics as $nic) {
            $interfaces[] = new IFace($this->client, $nic);
        }
        return $interfaces;
    }

    public function getQuota() {
        return $this->quota;
    }

    private function getVolumes() {
        $volumes = array();
        $disks = $this->client->getResource('vms/' . $this->id . '/disks');
        foreach($disks as $disk) {
            $volumes[] = new Volume($this->client, $disk);
        }
        return $volumes;
    }

    public function isRunning() {
        // Possible states are: up, wait_for_launch, powering_up, powering_down, down, (locked)
        if($this->status == 'down' || $this->status == 'wait_for_launch') {
            return false;
        } else {
            return true;
        }
    }

    public function isReady() {
        // oVirt 3.1 can flag a VM as down and not locked, while it's volumes are locked, if 'true' is is safe to launch VM
        if(!$this->status == 'down') {
            return false;
        } else {
            // Get volumes
            foreach($this->getVolumes() as $volume) {
                if($volume->status == 'locked')
                    return false;
            }
        }
        // Nothing is locked, carry on
        return true;
    }

    public function vm_action($action, $boot_dev = null, $id = null) {
        // TODO: Possible actions can be migrate, ticket, shutdown, start, stop, suspend, detach, move, export
        // Validate action
        switch($action) {
            case 'start':
            break;
            case 'stop':
            break;
            case 'default':
            throw new MissingParametersException('Invalid action specified.');
        }
        // TODO: Keep ID? Makes more sense to perform actions on a vm object, rather than invoke a start-method for VMs by id
        if(is_null($id)) {
            $id = $this->id;
        }
        $xml = new SimpleXMLElement('<action/>');
        $vm = $xml->addChild('vm');
        $os = $vm->addChild('os');
        // Possibility to boot from CD-Rom, HD..
        if(!is_null($boot_dev)) {
            $boot = $os->addChild('boot');
            $boot->addAttribute('dev', $boot_dev);
        }
        $data = $xml->asXML();
        $this->client->postRescource('vms/' . $id . '/' . $action, $data);

    }

    /* TODO
     * ticket           => used as password for SPICE
     */
}