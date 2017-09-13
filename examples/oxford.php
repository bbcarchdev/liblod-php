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

// API example provided by Mo McRoberts
// Get the topic "Oxford" and show related web pages
require_once(__DIR__ . '/../vendor/autoload.php');

use bbcarchdev\liblod\LOD;

$lod = new LOD();
$lod->setPrefix('rdfs', 'http://www.w3.org/2000/01/rdf-schema#');
$lod->setPrefix('foaf', 'http://xmlns.com/foaf/0.1/');

$uri = 'http://www.dbpedialite.org/things/22308#id';

$inst = $lod->resolve($uri);
if($inst === false)
{
  trigger_error("Could not retrieve URI $uri");
}
echo '<h1>' . htmlspecialchars($inst['rdfs:label']) . '</h1>';
echo '<ul>';
foreach($inst['foaf:primaryTopicOf,foaf:page'] as $url)
{
  $str = htmlspecialchars($url);
  echo '<li><a href="' . $str . '">' . $str . '</a></li>';
}
echo '</ul>';
?>
