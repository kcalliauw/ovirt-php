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

    public static function getTicket($expiry) {
        $xml = new SimpleXMLElement('<action/>');
        $ticket = $xml->addChild('ticket');
        $ticket->addChild('expiry', $expiry);

        return $xml->asXML();
    }

    /**
     * @param $array
     * @return SimpleXMLElement
     */
    public static function toXML($data) {
        // Initialize VM XML Element
        $xml = new SimpleXMLElement('<vm/>');
        // Parse array to elements
        # Name
        if(array_key_exists('name', $data)) {
            $xml->addChild('name', $data['name']);
        } # Cluster
        if(array_key_exists('cluster', $data)) {
            $cluster = $xml->addChild('cluster');
            $cluster->addChild('name', $data['cluster']['name']);
        } # Template
        if(array_key_exists('template', $data)) {
        $template = $xml->addChild('template');
        $template->addChild('name', $data['template']['name']);
        } # Memory
        if(array_key_exists('memory', $data)) {
            $xml->addChild('memory', $data['memory']);
        } # OS
        if(array_key_exists('os', $data)) {
            $os = $xml->addChild('os');
            if(array_key_exists('type', $data['os']))
                $os->addAttribute('type', $data['os']['type']);
            // Multiple boot devices, determine which is first
            if(array_key_exists('boot', $data['os'])) {
                $boot1 = $os->addChild('boot');
                $boot2 = $os->addChild('boot');
                if($data['os']['boot']['dev'] == 'hd') {
                    $boot1->addAttribute('dev', 'hd');
                    $boot2->addAttribute('dev', 'cdrom');
                } else {
                    $boot1->addAttribute('dev', 'cdrom');
                    $boot2->addAttribute('dev', 'hd');
                }
            }
        } # Profile
        if(array_key_exists('profile', $data)) {
            // 'server' or 'desktop'
            $xml->addChild('type', $data['profile']);
        } # Display
        if(array_key_exists('display', $data)) {
            $display = $xml->addChild('display');
            $display->addChild('type', $data['display']['type']);
            // TODO: Find out how to set below properties, if at all
            $display->addChild('address', $data['display']['address']);
            $display->addChild('port', $data['display']['port']);
            $display->addChild('secure_port', $data['display']['secure_port']);
            $display->addChild('subject', $data['display']['subject']);
            $display->addChild('monitors', $data['display']['monitors']);
        } # CPU (Cores & Sockets)
        if(array_key_exists('cpu', $data)) {
            $cpu = $xml->addChild('cpu');
            $topology = $cpu->addChild('topology');
            if(array_key_exists('cores', $data['cpu']))
                $topology->addAttribute('cores', $data['cpu']['cores']);
            if(array_key_exists('sockets', $data['cpu']))
                $topology->addAttribute('sockets', $data['cpu']['sockets']);
        } # Quota
        if(array_key_exists('quota', $data)) {
            $xml->addChild('quota', $data['quota']);
        }
        return $xml->asXML();
    }

    /**
     * @param SimpleXMLElement
     * @return $array
     */
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
        // Newly created VMs dont have disks, ergo no disk size can be calculated
        if(!is_null($this->volumes)) {
            # Storage
            $disk_size = 0;
            foreach($this->volumes as $volume) {
                $disk_size += $volume->size;
            }
            $this->storage = $disk_size;
        }
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

    private function getInterfaces() {
        $interfaces = array();
        $nics = $this->client->getResource('vms/' . $this->id . '/nics');
        // Newly created VMs have no nics yet
        if(is_null($nics)) {
            return null;
        } else {
            foreach($nics as $nic) {
                $interfaces[] = new IFace($this->client, $nic);
            }
            return $interfaces;
        }
    }

    public function getQuota() {
        return $this->quota;
    }

    private function getVolumes() {
        $volumes = array();
        $disks = $this->client->getResource('vms/' . $this->id . '/disks');
        // Newly created disks have no disks yet
        if(is_null($disks)) {
            return null;
        } else {
            foreach($disks as $disk) {
                $volumes[] = new Volume($this->client, $disk);
            }
            return $volumes;
        }
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