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

/**
 * A LODResponse is used by a LOD to encapsulate an HTTP response so
 * that it can be processed into its index. LODResponses instances are created,
 * processed, and destroyed automatically as part of LOD::fetch().
 */
class LODResponse
{
    /**
     * HTTP status of the response.
     * @property int $status
     */
    public $status = 0;

    /**
     * Error code for the response (typically 1 if an error occurred). If this
     * is 0, no error occurred.
     * @property int $error
     */
    public $error = 0;

    /**
     * Error message for the response; if the error happened on the HTTP side
     * (e.g. 500 error), this is typically the reason phrase from the HTTP
     * response.
     * @property string $errMsg
     */
    public $errMsg = NULL;

    /**
     * The URI which was originally requested. If the response was redirected,
     * this remains set to the original URI.
     */
    public $target = NULL;

    /**
     * Content location, either from the 'Content-Location' header (if set)
     * or the URI (if no 'Content-Location' is available).
     * @property string $contentLocation
     */
    public $contentLocation = NULL;

    /**
     * Content type of the response, e.g. 'text/turtle', 'application/rdf+xml'
     * @property string $type
     */
    public $type = NULL;

    /**
     * Response body.
     * @property string $payload
     */
    public $payload = NULL;
}
