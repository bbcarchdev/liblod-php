<?php
// API example provided by Mo McRoberts
require_once(dirname(__FILE__) . '/../src/lod.php');

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
