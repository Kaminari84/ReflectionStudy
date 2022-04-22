<?php

include_once("errorReporting.php");

logDebug("----DB MANAGER LOAD----");

$server = "127.0.0.1";
$username = "DB_USER";
$password = "DB_PASS";
$database = "DB_NAME";

$conn = null;

function connectToDB () {
	global $conn, $server, $username, $password, $database;

	// Create connection
	$conn = new mysqli($server, $username, $password, $database);

	// Check connection
	if ($conn->connect_error) {
	    logError("Connection failed: ", $conn->connect_error);
	} 
	logDebug("Connected successfully");
}

function closeDB() {
	global $conn;

	logDebug("Connection closed");

	$conn->close();
}

function executeSimpleSelectQuery($sql) {
	global $conn;

	$entries = array(); //message slot entries

	logDebug("Running get SQL: " . $sql);

	$result = $conn->query($sql);

	logDebug("Fetching results...");
	if ($result != NULL && $result->num_rows > 0) {
	    // output data of each row
	    while($row = $result->fetch_assoc()) {
	    	$entry = array();
	    	foreach ($row as $key => $value) {
	        	$entry[$key] = $value;
			}
			$entries[] = $entry;
	    }
	} 

	return $entries;
}

// seed with microseconds
function make_seed() {
	list($usec, $sec) = explode(' ', microtime());
	return $sec + $usec * 1000000;
}

?>
