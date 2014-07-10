#!/usr/bin/php -q

/**
 * This script is used to bulk add a XACML policy to a collection and water mark
 * the JPEG derivative.
 * The XCAML policy needs to be given as an arguement
 * Please excuse the crap coding it was meant to be a one off for my eyes only :)
 */

<?php
  /*The tuque api needs to be available in this directory*/
require_once 'tuque/HttpConnection.php';
require_once 'tuque/FedoraApi.php';
require_once 'tuque/Repository.php';
require_once 'tuque/RepositoryConnection.php';
require_once 'tuque/Object.php';
require_once 'tuque/Cache.php';
require_once 'tuque/FedoraApiSerializer.php';


/**
 * Make a connection to the repository 
 */
$fedoraUrl = "http://islandora.usask.ca:8080/fedora";
$username = "******";
$password = "******";
$connection = new RepositoryConnection($fedoraUrl, $username, $password);
$connection->reuseConnection = TRUE;
$repository = new FedoraRepository(
       new FedoraApi($connection),
    new SimpleCache());

/**
 *This section is where I grab the list of pids from a collection using itql query
 */ 
$query = 'select $object from <#ri> where $object <info:fedora/fedora-system:def/relations-external#isMemberOfCollection> <info:fedora/SPLLHR:00001>';
$objects = $repository->ri->itqlQuery($query, 'unlimited', '0'); // for itql

$to_mark = 0;
$already_marked = 0;
$cnt = 0;
$file = $argv[1];
$start = date("Y-m-d H:i:s");
  if(file_exists($file)){
    foreach ($objects as $k => $v) {
      $cnt = $cnt+1;
      $pid = $v['object']['value'];
      $fedora_object = $repository->getObject($pid);

      //if($fedora_object['JPG'] && !$fedora_object['JPGRAW'] && !$fedora_object['POLICY']){
	//Comment out the statement above if you are only adding the policy
	//and uncomment the statement below
	if(!$fedora_object['POLICY']){
	//addWatermark($fedora_object);
	addPolicy($fedora_object, $file);
	$to_mark = $to_mark +1;
	print "processed". $pid ."\n";
      } else {
	$already_marked = $already_marked +1;
	print "done: ". $pid ."\n";
      }
      print "found $cnt\n";
      print "$to_mark to mark and $already_marked did not require marking\n";
    }
  }


print "Start time: ". $start ." End time". date("Y-m-d H:i:s") ."\n";

// This function takes as arguements the file that was passed on the command
// line and the fedora object found in the search
function addPolicy($fedora_object, $file){
    $policy_file = file_get_contents($file);
    $log_message = "Xacml derivative created using FITS with SUCCESS";
    addDerivative($fedora_object,'X', 'POLICY', 'Xacml Policy Stream', $file, 'text/xml', $log_message, FALSE);
}

function addWatermark($fedora_object){

//Get the existing datastream
  $temp_file = saveDatastream($fedora_object, 'JPG', 'JPG');

//Delete the current jpg
  $fedora_object->purgeDatastream('JPG');
//Logo to overlay and tempfile
  $logo = "/usr/fedora/local_scripts/localHistoryRoomMark.png";
  $output_file = $temp_file .'_WM';
//do processing
  $command = "composite -gravity north -geometry +10+10 $logo $temp_file $output_file";
    //$command = "composite -dissolve 5 -tile  $logo $temp_file $output_file";
  exec($command, $jpg_output, $return);
//Add new derivitives
  $log_message = "JPG derivative created using FITS with command - $command || SUCCESS";
  addDerivative($fedora_object, 'M', 'JPG', 'JPG', $output_file, 'image/jpeg', $log_message);
  $log_message = "JPGRAW derivative created using FITS with command - $command || SUCCESS";
  addDerivative($fedora_object, 'M', 'JPGRAW', 'JPGRAW', $temp_file, 'image/jpeg', $log_message);


}


function addDerivative($fedora_object, $ctrlgrp, $dsid, $label, $output_file, $mimetype, $log_message = NULL, $delete = TRUE) {
  $datastream = new NewFedoraDatastream($dsid, $ctrlgrp, $fedora_object, $fedora_object->repository);
  $datastream->setContentFromFile($output_file);
  $datastream->label = $label;
  $datastream->mimetype = $mimetype;
  $datastream->state = 'A';
  $datastream->checksum = TRUE;
  $datastream->checksumType = 'MD5';
  if ($log_message) {
    $datastream->logMessage = $log_message;
  }
  $return = $fedora_object->ingestDatastream($datastream);
  if ($delete) {
    unlink($output_file);
  }
  //$this->log->lwrite('Finished processing', 'COMPLETE_DATASTREAM', $this->pid, $dsid);
  return $return;
}




function watermarkJPG($dsid = 'JPEG', $label = 'JPEG image', $resize = '800') {
  $this->log->lwrite('Starting processing', 'PROCESS_DATASTREAM', $this->pid, $dsid);
  try {
    $output_file = $this->temp_file . '_JPG.jpg';
    $command = "convert -resize $resize $this->temp_file $output_file";
    exec($command, $jpg_output, $return);
    $log_message = "$dsid derivative created using ImageMagick with command - $command || SUCCESS";
    $this->add_derivative($dsid, $label, $output_file, 'image/jpeg', $log_message);
  } catch (Exception $e) {
    $this->log->lwrite("Could not create the $dsid derivative!", 'FAIL_DATASTREAM', $this->pid, $dsid, NULL, 'ERROR');
    unlink($output_file);
  }
  return $return;
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
      $extension = system_mime_type_extension($mime_type);
    }
    $tempfile = temp_filename($extension);
    $file_handle = fopen($tempfile, 'w');
    fwrite($file_handle, $datastream->content);
    fclose($file_handle);
    //The tempfile needs to be removed because I am lazy I just deleted from the tmp directory
    //I think its just unlink($tempfile) 
  } catch (Exception $e) {
    print "Could not save datastream - $e";
  }

  return $tempfile;
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
