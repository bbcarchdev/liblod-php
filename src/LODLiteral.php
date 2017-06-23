<?php
/*
 * Copyright 2017 BBC
 *
 * Author: Elliot Smith <elliot.smith@bbc.co.uk>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace res\liblod;

use res\liblod\LODTerm;

class LODLiteral extends LODTerm
{
    public $datatype = NULL;
    public $language = NULL;

    public function __construct($value, $spec)
    {
        parent::__construct($value);

        if(isset($spec['lang']))
        {
            $this->language = $spec['lang'];
        }
        else if(isset($spec['datatype']))
        {
            $this->datatype = $spec['datatype'];
        }
    }

    public function isResource()
    {
        return FALSE;
    }
}
