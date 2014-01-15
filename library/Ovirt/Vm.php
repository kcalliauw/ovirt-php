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
require_once('IFace.php');

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
    /**
     * @param $array
     * @return SimpleXMLElement
     */
    public static function toXML($data) {
//        d($data);
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
     * Parses XML to an easy to read / manipulate array
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
        // Get NICs
        if(isset($xml->nics)) {
            $ifs = array();
            $data = $xml->nics;
            foreach($data->nic as $nic) {
                $nic = array(
                    'name'  =>  $nic->name->__toString(),
                    'id'    =>  $nic->attributes()->id->__toString(),
                );
                $ifs[] = $nic;
            }
            $this->interfaces = $ifs;
        }
        // Get disks
        if(isset($xml->disks)) {
            $disks = array();
            $data = $xml->disks;
            foreach($data->disk as $disk) {
                $disk = array(
                    'name'  =>  $disk->name->__toString(),
                    'id'    =>  $disk->attributes()->id->__toString(),
                    'size'  =>  $disk->size->__toString(),
                );
                $disks[] = $disk;
            }
            $this->volumes = $disks;
        }

        // Calculate total disk space spread over all volumes
        $total_disk_size = 0;
        if(!is_null($this->volumes)) {
            foreach($this->volumes as $volume)
                $total_disk_size += $volume['size'];
        }
        $this->storage = $total_disk_size;

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

    /**
     * This function verifies whether or not the machine is actually running up and running
     * Use this as a check before doing certain at runtime operations
     * @return bool
     */
    public function isRunning() {
        // Possible states are: up, wait_for_launch, powering_up, powering_down, down, (locked)
        if($this->status == 'down' || $this->status == 'wait_for_launch') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Verifies whether the machine is up and open for writing transactions
     * @return bool
     */
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

    /**
     * Perform VM-specific action; used to manage VM
     * @param string $action        Action to undertake
     * @param array $opts           Depending on what action is being undertaken, supply additional options for the corresponding action
     *                              Any settings altered by suppling additional options will be reset to it's original value when the VM reboots
     * @return void
     */
    public function action($action, $opts = null) {
        // Action XML Element
        $xml = new SimpleXMLElement('<action/>');
        // Validate action
        switch($action) {
            case 'start':
                $vm = $xml->addChild('vm');
                if(array_key_exists('stateless', $opts['vm']))
                    $vm->addChild('stateless', $opts['vm']['stateless']);
                if(array_key_exists('display', $opts['vm'])) {
                    $display = $vm->addChild('display');
                    $display->addChild('type', $opts['vm']['display']);
                }
                if(array_key_exists('boot_dev', $opts['vm'])) {
                    $os = $vm->addChild('os');
                    $boot = $os->addChild('boot');
                    $boot->addAttribute('dev', $opts['vm']['boot_dev']);
                }
                if(array_key_exists('cdrom', $opts['vm'])) {
                    $cdroms = $vm->addChild('cdroms');
                    $cdrom = $cdroms->addChild('cdrom');
                    $file = $cdrom->addChild('file');
                    $file->addAttribute('id', $opts['vm']['cdrom']);
                }
                if(array_key_exists('domain', $opts['vm'])) {
                    $domain = $vm->addChild('domain');
                    $domain->addChild('name', $opts['vm']['domain']['name']);
                    $user = $domain->addChild('user');
                    $user->addChild('user_name', $opts['vm']['domain']['username']);
                    $user->addChild('password', $opts['vm']['domain']['password']);
                }
                if(array_key_exists('host_id', $opts['vm'])) {
                    $placement = $vm->addChild('placement_policy');
                    $host = $placement->addChild('host');
                    $host->addAttribute('id', $opts['vm']['host_id']);
                }
                // Only for Windows machines
                if(array_key_exists('domain', $opts['vm'])) {
                    $domain = $vm->addChild('domain');
                    $domain->addChild('name', $opts['vm']['domain']['name']);
                    $user = $domain->addChild('user');
                    $user->addChild('user_name', $opts['vm']['domain']['username']);
                    $user->addChild('password', $opts['vm']['domain']['password']);
                }
                break;
            case 'stop':
                break;
            case 'suspend':
                break;
            case 'shutdown':
                break;
            case 'migrate':
                if(array_key_exists('host_id', $opts['vm'])) {
                    $host = $xml->addChild('host');
                    $host->addAttribute('id', $opts['host_id']);
                }
                break;
            case 'detach':
                break;
            case 'move':
                $domain = $xml->addChild('storage_domain');
                $domain->addChild('name', $opts['storage_name']);
                break;
            case 'export':
                $domain = $xml->addChild('storage_domain');
                $domain->addChild('name', $opts['storage_name']);
                $xml->addChild('overwrite', $opts['overwrite']);
                $xml->addChild('discard_snapshots', $opts['discard_snapshots']);
                break;
            case 'default':
            throw new MissingParametersException('Invalid action specified.');
        }

        $data = $xml->asXML();
        $this->client->postResource('vms/' . $this->id . '/' . $action, $data);
    }

    /**
     * Set a ticket which will be used as the SPICE password in order to access to the VM, limited by time in seconds
     * @param int $expiry   Time left until expiration
     * @return string Ticket
     */
    public function setTicket($expiry) {
        $response = $this->postResource('vms/' . $this->id . '/ticket', Vm::getTicket($expiry));
        return $response->ticket->value;
    }

}