#!/usr/bin/php -q

<?php

require_once './connection.php';
  
/**
 * This script takes a collection object as arguements and outputs
 * all the pids in the collection
 * You can save these by redirecting to file
 * ./get_collection_pids.php collectionPid:001 > allPids.pids 
 */


/**
 *This section is where I grab the list of pids from a collection using itql query
 */
$pid = $argv[1];
$format = 'select $object from <#ri> where $object <info:fedora/fedora-system:def/relations-external#isMemberOfCollection> <info:fedora/%s>';
$query = sprintf($format, $pid);
$objects = $repository->ri->itqlQuery($query, 'unlimited', '0'); // for itql

/**
 *This bit is for barfing out a specific books pids
 */
$start = date("Y-m-d H:i:s");
foreach ($objects as $k => $v) {
  $p = $v['object']['value'];
  print $p ."\n";

}

?>
