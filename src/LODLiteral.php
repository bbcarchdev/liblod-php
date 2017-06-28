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

/**
 * An RDF literal.
 */
class LODLiteral extends LODTerm
{
    /**
     * RDF data type for the literal, in full URI form, e.g.
     * 'http://www.w3.org/2001/XMLSchema#string'.
     * @property string $datatype
     */
    public $datatype = NULL;

    /**
     * RDF language tag for the literal, e.g. 'en-gb'.
     * @property string $language
     */
    public $language = NULL;

    /**
     * Constructor.
     *
     * @param string $value Value for the literal
     * @param array $spec May contain a 'lang' or 'datatype' key;
     * 'lang' should be an RDF language tag;
     * 'datatype' should be an expanded (not prefixed) URI for the datatype
     * of the literal.
     */
    public function __construct($value, $spec = array())
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

    /**
     * Check whether this is a resource. Always returns FALSE.
     *
     * @return bool
     */
    public function isResource()
    {
        return FALSE;
    }
}
