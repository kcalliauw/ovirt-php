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

class BaseObject
{

    public $client;
    public $id;
    public $href;
    public $name;

    public function __construct(OvirtApi &$client, $id, $href, $name) {
        $this->client = $client;
        $this->id = $id;
        $this->href = $href;
        $this->name = $name;
    }

    /**
     * Extracts the version of the API from the XML
     * @return array
     */
    public function parseVersion($xml) {
        $arr = (array)$xml;
        return $arr['@attributes']['major'] . '.' . $arr['@attributes']['minor'];
    }
}