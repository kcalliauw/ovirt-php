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
//        var_dump($xml);
//        var_dump('===================================================================');
        $this->description = (strlen($xml->description->__toString())>0) ? $xml->description->__toString(): null;
        $this->status = $xml->status->state->__toString();
        $this->memory = $xml->memory->__toString();
        $this->profile = $xml->type->__toString();
        $this->display = array(
            'type'          => $xml->display->type->__toString(),
            'address'       => $xml->display->address->__toString(),
            'port'          => $xml->display->port->__toString(),
            'secure_port'   => $xml->display->secure_port->__toString(),
            'subject'       => $xml->display->subject->__toString(),
            'monitors'      => $xml->display->monitors->__toString(),
        );
        $this->host = $xml->host->__toString();
        $this->cluster = $xml->cluster->__toString();
        $this->template = $xml->template->__toString();
        // ???
        // $this->storage = $xml->disks->disk->size->__toString();
        $this->cores = $xml->cpu->topology->__toString();
        $this->creation_time = $xml->creation_time->__toString();
        $this->os = array(
            'type'  => $xml->os->__toString(),
            'boot'  => $xml->os->boot->__toString(),
        );
        // No Guest Info
        // $this->ips = $xml->guest_info->ips->ip->__toString();
        $this->vmc = array(
            'address'   => $xml->display->address->__toString(),
            'port'      => $xml->display->port->__toString(),
        );
        $this->quota = $xml->quota->__toString();

        // TODO: NICs / Disk need to be checked

        // Get NICs
        $interfaces = array();
        $nics = $this->client->getResource('vms/' . $this->id . '/nics');
        foreach($nics as $nic) {
            $interfaces[] = new IFace($this->client, $nic);
        }
        $this->interfaces = $interfaces;
        // Get Disks
        $volumes = array();
        $disks = $this->client->getResource('vms/' . $this->id . '/disks');
        foreach($disks as $disk) {
            $volumes[] = new Volume($this->client, $disk);
        }
        $this->volumes = $volumes;
    }
}