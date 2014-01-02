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
class Volume extends BaseObject{

    public $size = null;
    public $disk_type = null;
    public $bootable = null;
    public $interface = null;
    public $format = null;
    public $sparse = null;
    public $status = null;
    public $storage_domain = null;
    public $vm = null;
    public $quota = null;

    public function __construct(OvirtApi &$client, SimpleXMLElement $xml) {
        parent::__construct($client, $xml->attributes()['id']->__toString(), $xml->attributes()['href']->__toString(), $xml->name->__toString());
        $this->_parse_xml_attributes($xml);
    }

    protected function _parse_xml_attributes(SimpleXMLElement $xml) {

        $this->size = $xml->size->__toString();
        $this->disk_type = (strlen($xml->type->__toString())>0) ? $xml->type->__toString(): null;
        $this->bootable = $xml->bootable->__toString();
        $this->interface = $xml->interface->__toString();
        $this->format = $xml->format->__toString();
        $this->sparse = $xml->sparse->__toString();
        $this->status = $xml->status->state->__toString();
        $this->storage_domain = $xml->storage_domains->storage_domain->attributes()['id'];
        $this->quota = (strlen($xml->quota->__toString())>0) ? $xml->quota->__toString(): null;
        if(!empty($xml->vm)) {
            $this->vm = $xml->vm->attributes()['id'];
        }
    }

    // TODO: Parse self to XML
}