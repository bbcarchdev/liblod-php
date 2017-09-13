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

namespace bbcarchdev\liblod;

/**
 * Abstract class for RDF terms (URIs and literals).
 */
abstract class LODTerm
{
    /**
     * Value for the term.
     * @property string $value
     */
    public $value;

    /**
     * Constructor.
     * @param string $value Sets the value for the term
     */
    public function __construct($value)
    {
        $this->value = $value;
    }

    /**
     * Check whether this term is a resource or not.
     * @return bool TRUE if term is a resource, FALSE otherwise.
     */
    public abstract function isResource();

    /**
     * Get string representation of term.
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}
