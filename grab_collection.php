#!/usr/bin/php -q

<?php


/**
 * This script takes a collection object pid as arguements and saves
 * all the OBJ datastreams and MODS to a file
 * ./grab_collection.php collectionPid:001
 * So far it has only been used with islandora images but can 
 * easily be modified to grab large image collections by adding
 * to the extensions array in the getExtension function                
 */


require_once './connection.php';

/**
 *This section is where I grab the list of pids from a collection using itql query
 */
$pid = $argv[1];
$format = 'select $object from <#ri> where $object <info:fedora/fedora-system:def/relations-external#isMemberOfCollection> <info:fedora/%s>';
$query = sprintf($format, $pid);

//$query = 'select $object from <#ri> where $object <info:fedora/fedora-system:def/relations-external#isMemberOfCollection> <info:fedora/SMDI:30926>';
$objects = $repository->ri->itqlQuery($query, 'unlimited', '0'); // for itql

  $dir = $pid . ".files";
  mkdir($dir);
echo count($objects);
echo "dir made\n";
foreach ($objects as $k => $v) {
  $pid = $v['object']['value'];

  $fedora_object = $repository->getObject($pid);

  //Get the existing datastream
  print "PID: ". $pid ."\n";
  $tif_tmp = saveDatastream($fedora_object, 'OBJ');
  $xml_tmp = saveDatastream($fedora_object, 'MODS', 'xml');
  $obj_info = pathinfo($tif_tmp);

  $tif_new = $dir . "/" . $pid . "." . $obj_info['extension'];
  $xml_new = $dir . "/" . $pid . ".xml";


  copy($tif_tmp, $tif_new) or die ("Unable to copy object\n");
  copy($xml_tmp, $xml_new) or die ("Unable to copy metadata\n");
  unlink($tif_tmp);
  unlink($xml_tmp);


}


function saveDatastream($fedora_object = NULL, $dsid = NULL, $extension = NULL) {
  if (!isset($dsid)) {
    return;
  }
  $datastream_array = array();
  foreach ($fedora_object as $datastream) {
    $datastream_array[] = $datastream->id;
  }
  if (!in_array($dsid, $datastream_array)) {
    print "Could not find the $dsid datastream!";
  }
  try {
    $datastream = $fedora_object->getDatastream($dsid);
    $mime_type = $datastream->mimetype;
    if (!$extension) {
      $extension = getExtension($mime_type);
    }
    echo $extension ."\n";

    $tempfile = temp_filename($extension);
    $file_handle = fopen($tempfile, 'w');
    fwrite($file_handle, $datastream->content);
    fclose($file_handle);
  } catch (Exception $e) {
    print "Could not save datastream - $e";
  }

  return $tempfile;
}


function getExtension ($mime_type){

  $extensions = array('image/jpeg' => 'jpeg',
                        'text/xml' => 'xml'
		      );

  // Add as many other Mime Types / File Extensions as you like

  return $extensions[$mime_type];

}


function temp_filename($extension = NULL) {
  while (true) {
    $filename = sys_get_temp_dir() . '/' . uniqid(rand()) . '.' . $extension;
    print $filename . "\n";
    if (!file_exists($filename))
      break;
  }
  return $filename;
}



?>
