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
    public function __construct($subj, $pred, $objOrSpec, $prefixes=Rdf::COMMON_PREFIXES, $rdf=NULL)
    {
        if(empty($rdf))
        {
            $rdf = new Rdf();
        }

        if(!($subj instanceof LODResource))
        {
            $subj = new LODResource($rdf->expandPrefix($subj, $prefixes));
        }

        if(!($pred instanceof LODResource))
        {
            $pred = new LODResource($rdf->expandPrefix($pred, $prefixes));
        }

        $obj = NULL;

        // already a term
        if($objOrSpec instanceof LODTerm)
        {
            $obj = $objOrSpec;
        }

        // fallback: it's a spec for a literal or URI
        if(empty($obj))
        {
          if($objOrSpec['type'] === 'literal')
          {
              if(isset($objOrSpec['datatype']))
              {
                  $objOrSpec['datatype'] =
                      $rdf->expandPrefix($objOrSpec['datatype'], $prefixes);
              }
              $obj = new LODLiteral($objOrSpec['value'], $objOrSpec);
          }
          else if($objOrSpec['type'] === 'uri')
          {
              $obj = new LODResource($rdf->expandPrefix($objOrSpec['value'], $prefixes));
          }
        }

        $this->subject = $subj;
        $this->predicate = $pred;
        $this->object = $obj;
    }


    public function __toString()
    {
        $str = '<' . $this->subject->__toString() . '> ' .
               '<' . $this->predicate->__toString() . '> ';

        // uri
        if($this->object->isResource())
        {
            return $str . '<' . $this->object->__toString() . '>';
        }

        // literal
        $objStr = json_encode($this->object->__toString());

        if($this->object->language)
        {
            $objStr .= '@' . $this->object->language;
        }
        else if($this->object->datatype)
        {
            $objStr .= '^^<' . $this->object->datatype . '>';
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
