<?php
$path = getcwd();
#echo "This Is Your Absolute Path: ";
#echo $path;

$logPath = "log_ReflectionStudy.txt";
$logFile = fopen($logPath, "a");

ini_set('display_errors', 1); 
ini_set('log_errors', 1); 
//ini_set('error_log', $logPath);  
error_reporting(E_ALL);

date_default_timezone_set('America/Los_Angeles');

function logDebug($message) {
	global $logFile;	
	$currentDate = date('d/m/Y H:i:s');
    fwrite($logFile, $currentDate."-".$message."\r\n");
}

function logError($message, $code) {
	global $logFile;
	$currentDate = date('d/m/Y H:i:s');
    fwrite($logFile, $currentDate."-ERROR(".$code.")-".$message."\r\n");
	//print "ERROR(".$code.")-".$message."<br />";
}

//error handler function
function allError($errno, $errstr)
{
  logError("<b>Error:</b> [$errno] $errstr<br />", $errno);
  //die();
}

//set error handler
set_error_handler("allError",E_ALL);
?>