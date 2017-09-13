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
// Get the mrss:player triple for a topic
require_once(__DIR__ . '/../vendor/autoload.php');

use bbcarchdev\liblod\LOD;

$lod = new LOD();
$lod->setPrefix('mrss', 'http://search.yahoo.com/mrss/');
$lod->languages = array('en-gb', 'en', 'en-us', 'en-au');

$uri = 'http://acropolis.org.uk/98ae9cd1e55c4055a266dcc2f9570c70#id';
if(!isset($lod[$uri]))
{
  trigger_error("Failed to fetch <$uri>");
  return;
}

$instance = $lod[$uri];

echo "instance = <$instance>\n"; // prints instance = <SUBJECT-URI>

echo "title = [" . $instance['skos:prefLabel,dct:title,rdfs:label'] . "]\n"; // prints the title

if(isset($instance['mrss:player']))
{
  echo "Playable media:\n";
  foreach($instance['mrss:player'] as $value)
  {
    // we don't care about `mrss:player` triples where the object is not a resource
    if($value->isResource())
    {
       echo "• <$value>\n";
    }
  }
}
?>
