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

use res\liblod\LODResource;
use res\liblod\LODLiteral;
use res\liblod\LODStatement;
use res\liblod\LODTerm;
use res\liblod\Rdf;

class LODStatement
{
    public $subject;
    public $predicate;
    public $object;

    // $objOrSpec can either be a LODTerm instance or an options array like
    // { 'value' => 'somestring', 'type' => 'uri|literal',
    //   'datatype' => 'xsd:...' || 'lang' => 'en'}
    public function __construct($subj, $pred, $objOrSpec, $prefixes=NULL)
    {
        if($prefixes === NULL)
        {
            $prefixes = Rdf::COMMON_PREFIXES;
        }

        if(!is_a($subj, 'res\liblod\LODResource'))
        {
            $subj = new LODResource(Rdf::expandPrefix($subj, $prefixes));
        }

        if(!is_a($pred, 'res\liblod\LODResource'))
        {
            $pred = new LODResource(Rdf::expandPrefix($pred, $prefixes));
        }

        if(is_a($objOrSpec, 'res\liblod\LODTerm'))
        {
            $obj = $objOrSpec;
        }
        else if($objOrSpec['type'] === 'uri')
        {
            $obj = new LODResource(Rdf::expandPrefix($objOrSpec['value'], $prefixes));
        }
        else
        {
            $obj = new LODLiteral($objOrSpec['value'], $objOrSpec);
        }

        $this->subject = $subj;
        $this->predicate = $pred;
        $this->object = $obj;
    }


    public function __toString()
    {
        $str = '<' . $this->subject->__toString() . '> ' .
               '<' . $this->predicate->__toString() . '> ';

        if($this->object->isResource())
        {
            $objStr = '<' . $this->object->__toString() . '>';
        }
        else
        {
            $objStr = json_encode($this->object->__toString());

            if($this->object->language)
            {
                $objStr .= '@' . $this->object->language;
            }
            else if($this->object->datatype)
            {
                $objStr .= '^^<' . $this->object->datatype . '>';
            }
        }

        return $str . $objStr;
    }

    /**
     * Generate a key which uniquely-identifies the statement, using
     * a hash of its subject+predicate+object values + object datatype/lang
     */
    public function getKey()
    {
        return md5($this->__toString());
    }
}
