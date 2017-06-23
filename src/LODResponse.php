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

/* A LODResponse is used by a context to encapsulate an HTTP response
 * that can be processed into the model by a LOD context. LODResponses
 * instances are created, processed, and destroyed as part of LOD::fetch()
 * automatically.
 */
class LODResponse
{
    public $status = 0;
    public $error = 0;
    public $errMsg = NULL;

    // URI requested
    public $target = NULL;

    // content location
    public $contentLocation = NULL;

    // content type, e.g. 'text/turtle' or 'application/rdf+xml'
    public $type = NULL;

    // response body
    public $payload = NULL;
}
