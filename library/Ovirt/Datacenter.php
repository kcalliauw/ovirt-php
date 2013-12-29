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
class DataCenter extends BaseObject
{

    public $description = null;
    public $status = null;
    public $storage_type = null;
    public $storage_format = null;
    public $supported_versions = array();
    public $version = null;

    public function __construct(SimpleXMLElement $xml) {
        $this->_parse_xml_attributes($xml);
        parent::__construct($xml->attributes()['id']->__toString(), $xml->attributes()['href']->__toString(), $xml->name->__toString());
    }

    protected function _parse_xml_attributes(SimpleXMLElement $xml) {
        $this->description = (strlen($xml->description->__toString())>0) ? $xml->description->__toString(): null;
        $this->status = $xml->status->state->__toString();
        $this->storage_type = $xml->storage_type->__toString();
        $this->storage_format = (strlen($xml->storage_format->__toString())>0) ? $xml->storage_format->__toString(): null;
        foreach((array)$xml->supported_versions as $supported_version) {
            $supported_versions[] = $this->parseVersion($supported_version);
        }
        $this->version = $this->parseVersion($xml->version);
    }
}