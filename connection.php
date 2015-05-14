#!/usr/bin/php -q

<?php
require_once '../tuque/HttpConnection.php';
require_once '../tuque/FedoraApi.php';
require_once '../tuque/Repository.php';
require_once '../tuque/RepositoryConnection.php';
require_once '../tuque/Object.php';
require_once '../tuque/Cache.php';
require_once '../tuque/FedoraApiSerializer.php';

/**
 * Make a connection to the repository 
 */
$fedoraUrl = "http://islandora.usask.ca:8080/fedora";
$username = "*******";
$password = "****";
$connection = new RepositoryConnection($fedoraUrl, $username, $password);
$connection->reuseConnection = TRUE;
$repository = new FedoraRepository(
       new FedoraApi($connection),
    new SimpleCache());
?>
