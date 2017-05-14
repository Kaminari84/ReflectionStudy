<?php

//if (strpos(getcwd(),"ReflectionStudy") === false) {
//	chdir("public_html/ReflectionStudy/auto_logs/");
//}

include_once("Managers/userManager.php");

$logPath = "log_ReflectionStudy_SUL.txt";
$logFile = fopen($logPath, "a");

// current directory
logDebug("INIT DIR:".getcwd());
//chdir("public_html");
// current directory
//logDebug("CHANGE DIR:".getcwd());

logDebug("----STATE UPDATE LOOP----");

include_once("Managers/mobileManager.php");
include_once("Managers/responseManager.php");
include_once("Managers/fitbit_dataManager.php");
include_once("Managers/fitbit_profileManager.php");
include_once("Managers/fitbit_deviceManager.php");
include_once("Managers/studyManager.php");
include_once("Managers/luisManager.php");

// Check if normal loop or response from Twilio
$sms_from = isset($_REQUEST['From']) ? $_REQUEST['From'] : NULL;
if ($sms_from) {
	logDebug("SMS response triggered loop");
} else {
	logDebug("Cron general triggered loop");
}

date_default_timezone_set('America/Los_Angeles');
$now = time();

logDebug("ACT_TIME:".date("M-d-Y H:i:s", $now));

$STUDY_START_MSG = "Welcome to Reflection Study! You will receive 1 reflective prompt during the day between";
$STUDY_START_MSG_2 = ". Please read the prompt carefully and send a response.";

$STUDY_END_MSG =" This is the end of the Reflection Study. We will send you a survey link shortly.";

#when the new day starts
$DAY_END_TIME_H = 23; $DAY_END_TIME_M = 00;

#reminder delays
$RMD1_DELAY_M = 4;
$RMD2_DELAY_M = 4;

function transitionState($actState, $time, $stopMessage) {
	global $DAY_END_TIME;

	logDebug("Transition State function ...");

	$status = [	"stateChanged" => 0, 
				"prevState" => $actState, 
				"nextState" => $actState];

	logDebug("Time offset: " . date("M-d-Y H:i:s", $time));
	logDebug("Current State: <".$actState.">");

	switch ($actState) {
		case "START_STUDY":
			//if ($time >= $DAY_WELCOME_TIME && $time < $DAY_END_TIME) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "IN_STUDY";
				logDebug("Changing state from ".$actState. " to ".$status["nextState"]." ...");
			//}
			break;
		case "IN_STUDY":
			if ($stopMessage == true) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "END_STUDY";
				logDebug("Changing state from ".$actState. " to ".$status["nextState"]." ...");
			}
			break;
	}

	return $status;
}

function transitionMState($actMState, $time, $user_id) {
	global $STUDY_START_MSG, $STUDY_START_MSG_2, $DAY_END_TIME, $DAY_MSG_TIME,
		   $RMD1_DELAY_M, $RMD2_DELAY_M;

	logDebug("Transition MState function ...");

	$status = [	"stateChanged" => 0, 
				"prevState" => $actMState, 
				"nextState" => $actMState];

	logDebug("Time offset: " . date("M-d-Y H:i:s", $time));
	logDebug("Current M state: <".$actMState.">");

	switch ($actMState) {
		case "DAY_START":
			logDebug("Checking transitions for state: DAY_START");
			logDebug("DAY_MSG_TIME:".date("M-d-Y H:i:s", $DAY_MSG_TIME));
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));
	
			if ($time >= $DAY_MSG_TIME && $time < $DAY_END_TIME) {
				$status["stateChanged"] = 1;
				$status["nextState"] = "DAY_MSG_SENT";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			} 
			break;
		case "DAY_MSG_SENT":
			logDebug("Checking transitions for state: DAY_MSG_SENT");
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));

			$log_entry = getUserDayStudyLog($user_id, -1, false);

            $msgID = $log_entry[0]['msg_id'];
            $dateSent = $log_entry[0]['msg_sent_time'];
            $rmd1Sent = $log_entry[0]['rmd1_sent_time'];
            logDebug("MSG ID:".$msgID.", MsgSent:". $dateSent.", rmd1Sent:".$rmd1Sent);

			#get the response
			$msg_responses = getUserResponsesToMsg($user_id, $log_entry[0]['id']);
			logDebug("Number of responses in DAY_MSG_SENT:".count($msg_responses));

			#is there a response?
			if(count($msg_responses) > 0) {
				#test if there is a follow-up by matching anything
				if ( count(getTestFollowUpForMessageID($msgID,"")) > 0 ) {
					#is the response recognized?
					$intent = $msg_responses[0]['intent'];
					logDebug("Intent of the first response:".$intent);

					// unrecognized followup, end the dialogue
					if (strcasecmp($intent, "None") == 0) {
						logDebug("Unrecognized intent, ending dialogue...");

						#change the state
						$status["stateChanged"] = 1;
						$status["nextState"] = "DIALOGUE_COMPLETE";
						logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
					} else {
						#recognized intent, but still can be "Any" or specific
						logDebug("Reconized intent, changing state to sent followup...");
						#change the state
						$status["stateChanged"] = 1;
						$status["nextState"] = "FOLLOWUP_SENT";
						logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
					}
				# There is not follow up to this message, directly go to the dialogue end state
				} else {
					#recognized intent, but still can be "Any" or specific
					logDebug("No followup, ending the dialogue here...");
					#change the state
					$status["stateChanged"] = 1;
					$status["nextState"] = "DIALOGUE_COMPLETE";
					logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
				}
			#check the timeout
			} else {
				logDebug("No responses so far, let's check the timeout for reminder 1");

				$user_timezone = getUserTimezone($user_id);
				date_default_timezone_set($user_timezone);

				$rmd_timeout = strtotime("+".$RMD1_DELAY_M." minutes", strtotime($dateSent) );
				logDebug("Comparing dates to check if we need to send reminder, current:".date("M-d-Y H:i:s", $time).", timeout:".date("M-d-Y H:i:s",$rmd_timeout));
				if ($time > $rmd_timeout && $rmd1Sent == 0) {
					#change the state
					$status["stateChanged"] = 1;
					$status["nextState"] = "RMD1_SENT";
					logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
				}

			}
			break;
		case "RMD1_SENT":
			logDebug("Checking transitions for state: RMD1_SENT");
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));

			$log_entry = getUserDayStudyLog($user_id, -1, false);
			$msgID = $log_entry[0]['msg_id'];
          
            #get the response
			$msg_responses = getUserResponsesToMsg($user_id, $log_entry[0]['id']);
			logDebug("Number of responses in DAY_MSG_SENT:".count($msg_responses));

			#is there a response?
			if(count($msg_responses) > 0) {
				#test if there is a follow-up by matching anything
				if ( count(getTestFollowUpForMessageID($msgID,"")) > 0 ) {
					#is the response recognized?
					$intent = $msg_responses[0]['intent'];
					logDebug("Intent of the first response:".$intent);

					// unrecognized response, end the dialogue
					if (strcasecmp($intent, "None") == 0) {
						logDebug("Unrecognized intent, ending dialogue...");

						#change the state
						$status["stateChanged"] = 1;
						$status["nextState"] = "DIALOGUE_COMPLETE";
						logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
					} else {
						#recognized intent, but still can be "Any" or specific
						logDebug("Reconized intent, changing state to sent followup...");
						#change the state
						$status["stateChanged"] = 1;
						$status["nextState"] = "FOLLOWUP_SENT";
						logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
					}
				# There is not follow up to this message, directly go to the dialogue end state
				} else {
					#recognized intent, but still can be "Any" or specific
					logDebug("No followup, ending the dialogue here...");
					#change the state
					$status["stateChanged"] = 1;
					$status["nextState"] = "DIALOGUE_COMPLETE";
					logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
				}
			} elseif ($time >= $DAY_END_TIME) {
				logDebug("Day ended timeout in RMD1_SENT");
				$status["stateChanged"] = 1;
				$status["nextState"] = "DAY_START";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			}
			break;
		case "FOLLOWUP_SENT":
			logDebug("Checking transitions for state: FOLLOWUP_SENT");
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));

			$log_entry = getUserDayStudyLog($user_id, -1, false);

            $msgID = $log_entry[0]['msg_id'];
            $dateSent = $log_entry[0]['followup_sent_time'];
            $rmd2Sent = $log_entry[0]['rmd2_sent_time'];
            logDebug("MSG ID:".$msgID.", FollowupSent:". $dateSent.", rmd2Sent:".$rmd2Sent);

			#get the response
			$followup_responses = getUserResponsesToFollowup($user_id, $log_entry[0]['id']);
			logDebug("Number of responses in FOLLOWUP_SENT:".count($followup_responses));
			#is there a response?
			if(count($followup_responses) > 0) {
				#change the state
				$status["stateChanged"] = 1;
				$status["nextState"] = "DIALOGUE_COMPLETE";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			#check the timeout
			} else {
				logDebug("No responses so far, let's check the timeout for reminder 2");

				$user_timezone = getUserTimezone($user_id);
				date_default_timezone_set($user_timezone);

				$rmd_timeout = strtotime("+".$RMD2_DELAY_M." minutes", strtotime($dateSent) );
				logDebug("Comparing dates to check if we need to send reminder, current:".date("M-d-Y H:i:s", $time).", timeout:".date("M-d-Y H:i:s",$rmd_timeout));
				if ($time > $rmd_timeout && $rmd2Sent == 0) {
					#change the state
					$status["stateChanged"] = 1;
					$status["nextState"] = "RMD2_SENT";
					logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
				}

			}

			break;
		case "RMD2_SENT":
			logDebug("Checking transitions for state: RMD2_SENT");
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));

			$log_entry = getUserDayStudyLog($user_id, -1, false);
          
            #get the response
			$msg_responses = getUserResponsesToFollowup($user_id, $log_entry[0]['id']);
			logDebug("Number of responses in RMD2_SENT:".count($msg_responses));

			#is there a response?
			if(count($msg_responses) > 0) {
				#change the state
				$status["stateChanged"] = 1;
				$status["nextState"] = "DIALOGUE_COMPLETE";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			} elseif ($time >= $DAY_END_TIME) {
				logDebug("Day ended timeout in RMD1_SENT");
				$status["stateChanged"] = 1;
				$status["nextState"] = "DAY_START";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			}
			break;
		case "DIALOGUE_COMPLETE":
			logDebug("Checking transitions for state: DIALOGUE_COMPLETE");
			logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));

			if ($time >= $DAY_END_TIME) {
				logDebug("Day ended timeout in RMD1_SENT");
				$status["stateChanged"] = 1;
				$status["nextState"] = "DAY_START";
				logDebug("Changing state from ".$actMState. " to ".$status["nextState"]." ...");
			}
			break;
	}

	return $status;
}

function exitState($state) {
	logDebug("Exit State function ...");
}

function exitMState($mstate) {
	logDebug("Exit MState function ...");
}

function enterState($state, $user_id, $number, $email) {
	global $STUDY_START_MSG, $STUDY_START_MSG_2, $DAY_MSG_TIME;

	logDebug("Enter State function ...");
	logDebug("Enter State activity for state ".$state. " ...");
			
	switch ($state) {
		case "ENROLLED":
			logDebug("In ENROLLED");
			
			break;
		case "START_STUDY":
			logDebug("In STUDY_START");
			
			break;
		case "IN_STUDY":
			logDebug("In IN_STUDY");
			$minTime = getUserMinTime($user_id);
			$maxTime = getUserMaxTime($user_id);

			$message_text = $STUDY_START_MSG . " " . $minTime . " and " . $maxTime . $STUDY_START_MSG_2;

			sendSMS($number, $message_text);
			#sendEmail($email, "Fitness Challenges Study - UW", $STUDY_START_MSG);
			$user_timezone = getUserTimezone($user_id);

			break;
		case "END_STUDY":
			logDebug("In END_STUDY");
			//This is actually handled by the SMS response part
			break;
	}
}

function enterMState($mstate, $user_id, $time) {
	global $STUDY_START_MSG, $STUDY_START_MSG_2, $STUDY_END_MSG;

	logDebug("Enter MState function ...");
	logDebug("Enter MState activity for state ".$mstate. " ...");

	switch ($mstate) {
		case "DAY_START":
			logDebug("entering DAY_START");
			//Is there a need to generate new message mappings?
			//logDebug("Day message assignment status:".getDayStudyMappingForUser($user_id, "m1"));
			
			break;
		case "DAY_MSG_SENT":
			logDebug("Entering DAY_MSG_SENT");
			$log_entry = getUserDayStudyLog($user_id, -1, false);

            $msgID = $log_entry[0]['msg_id'];
            $dateSent = $log_entry[0]['msg_sent_time'];
            logDebug("MSG ID:".$msgID.", dateSent:", $dateSent);
            
            //Sending only if this message has not been sent yet
            if ($dateSent == 0) {
            	logDebug("Not sent yet, decided to send now!");
            
            	//Get ID for the message
	            $message_entry = getMessageForID($msgID);

	            $d=7;
	            if (strcasecmp($message_entry['scope'], "2weeks") == 0) {
	            	$d=14;
	            }

	            date_default_timezone_set(getUserTimezone($user_id));
				$edate = date('Y-m-d', $time);
	            $sdate = date('Y-m-d', strtotime("-".$d." days", strtotime($edate)));
	            logDebug("Start date:" . $sdate . ", End date:" . $edate);

	            //Set status for already sent
            	setMsgSentTimeForUserLog($user_id, $log_entry[0]['id'], date("Y-m-d H:i:s",$time));
	            
	            logDebug("Trying to send messsage to user ".$user_id.", msg_id:".$message_entry['id']);
	            #sendMessagetoUser($user_id, $message_entry['id'], $sdate, $edate);
	            sendTestMessageToUser($user_id, $message_entry['id'], $sdate, $edate);
	        }
			break;
		case "RMD1_SENT":
			logDebug("Entering RMD1_SENT");
			
			$log_entry = getUserDayStudyLog($user_id, -1, false);
			$user_name = getUserName($user_id);
			$number = getUserMobileNumber($user_id);

			#time to send the reminder
			$rmd_text = getFirstReminder($user_name);
			sendSMS($number, $rmd_text);

			#set the time when reminder was sent and indicate it has already been sent by that
			date_default_timezone_set(getUserTimezone($user_id));
			setRmd1SentTimeForUserLog($user_id, $log_entry[0]['id'], date("Y-m-d H:i:s",$time));

			break;
		case "FOLLOWUP_SENT":
			logDebug("Entering FOLLOWUP_SENT");
			$log_entry = getUserDayStudyLog($user_id, -1, false);

            $msgID = $log_entry[0]['msg_id'];
            $dateSent = $log_entry[0]['followup_sent_time'];
            logDebug("MSG ID:".$msgID.", dateSent:", $dateSent);
            
            //Sending only if this message has not been sent yet
            if ($dateSent == 0) {
            	logDebug("Not sent yet, decided to send now!");
            
            	//Get ID for the message
	            $message_entry = getMessageForID($msgID);
          
            	#get the response
				$msg_responses = getUserResponsesToMsg($user_id, $log_entry[0]['id']);
				logDebug("Number of responses in state FOLLOWUP_SENT:".count($msg_responses));

				#is there a response?
				if(count($msg_responses) > 0) {
				
					#is the response recognized?
					$intent = $msg_responses[0]['intent'];
					$text = $msg_responses[0]['text'];
					logDebug("Intent of the first response:".$intent);

					// unrecognized response, end the dialogue
					if (strcasecmp($intent, "None") == 0) {
						logDebug("ERROR:We should never be here! - FOLLOWUP_SENT and no intent");
					} else {
						//Set status for already sent
						date_default_timezone_set(getUserTimezone($user_id));
            			setFollowupSentTimeForUserLog($user_id, $log_entry[0]['id'], date("Y-m-d H:i:s",$time));
						
						logDebug("Trying to send followup to user ".$user_id.", msg_id:".$message_entry['id']);
	            		#sendMessagetoUser($user_id, $message_entry['id'], $sdate, $edate);
	            		sendTestFollowUpMessageToUser($user_id, $message_entry['id'], $intent, $text);

					}
				} else {
					logDebug("ERROR:We should never be here! - FOLLOWUP_SENT and no responses");
				}
			}

			break;
		case "RMD2_SENT":
			logDebug("Entering RMD2_SENT");

			$log_entry = getUserDayStudyLog($user_id, -1, false);
			$user_name = getUserName($user_id);
			$number = getUserMobileNumber($user_id);

			#time to send the reminder
			$rmd_text = getSecondReminder($user_name);
			sendSMS($number, $rmd_text);

			#set the time when reminder was sent and indicate it has already been sent by that
			date_default_timezone_set(getUserTimezone($user_id));
			setRmd2SentTimeForUserLog($user_id, $log_entry[0]['id'], date("Y-m-d H:i:s",$time));
			break;
		case "DIALOGUE_COMPLETE":
			logDebug("Entering DIALOGUE_COMPLETE");

			$log_entry = getUserDayStudyLog($user_id, -1, false);
			$user_name = getUserName($user_id);
			$number = getUserMobileNumber($user_id);

			#time to send thank you for providing all the information
			$thank_you_text = getDialogueEndThankYou($user_name);
			sendSMS($number, $thank_you_text);

			#set the time when thank you was etn
			date_default_timezone_set(getUserTimezone($user_id));
			setDialogueCompleteSentTimeForUserLog($user_id, $log_entry[0]['id'], date("Y-m-d H:i:s",$time));

			break;
	}
}

function inState($state, $user_id) {
	logDebug("In State function: $state");

	switch ($state) {
		case "START_STUDY":
			logDebug("In START_STUDY");
		case "IN_STUDY":
			logDebug("In IN_STUDY");

			//Check if we have access to fitbit data
			logDebug("Getting fitbit access status for user:".$user_id);
			$fitbit_id = getUserFitbitID($user_id);
			$fitbit_profile = getFitbitProfile($fitbit_id);
			logDebug("Current access token is:".$fitbit_profile['access_token']);

			//do we need to ask for access approval?
			if ($fitbit_profile['access_token'] == NULL) {
				logDebug("Access token empty!");
					
				$last_call_date = strtotime(getFitbitLastCallTime($user_id));
			    $next_call_date = strtotime("+2 hours", $last_call_date);
			    
			    logDebug("Last call time:".date('Y-m-d H:i:s',$last_call_date).", next call time:".date('Y-m-d H:i:s',$next_call_date));

				if (time() > $next_call_date) {
					logDebug("Time to ask for access approval again!");
					setFitbitLastCallTime($user_id, date('Y-m-d H:i:s',time()));

					//reguest approval
					requestFitbitAccessApproval($user_id);
				}
			} else {
				//check if it is time to call Fitbit for data update
				$last_call_date = strtotime(getFitbitLastCallTime($user_id));
				$next_call_date = strtotime(getFitbitNextCallTime($user_id));

				logDebug("Last call time:".date('Y-m-d H:i:s',$last_call_date).", next call time:".date('Y-m-d H:i:s',$next_call_date));

				if (time() > $next_call_date) {
					logDebug("Time to call fitbit for data update!");

					//random delay between 2 and 4 hours, helps with multipe users connected to the same fitbit account
					srand(make_seed());
					$call_delay = rand(2*60, 4*60); 
					setFitbitLastCallTime($user_id, date('Y-m-d H:i:s',time()));

					// Getting Fitbit data
					logDebug("Requested all sources of Fitbit in update loop...");
					$sources = getAvailableDataSources();
					logDebug("We have ".count($sources)." sources, getting all...");
					
					$error = 0;
					foreach ($sources as $key => $value) {
						logDebug("Getting data for source: ".$value);
						$result = callFitbitAPIForData($user_id, $value, "1m");
						if (count($result["data"]) > 0) {
							logDebug("Length of data to add: ". count($result["data"]));
							addFitbitDataForUser($user_id, $value, $result["data"]);
						} elseif (strcasecmp($result["result_response"], "too_many_requests") == 0) {
							$call_delay = rand(7*60, 12*60);
							$error = 1;
							logDebug("WARNING: We called too many times, let's call in: ".$call_delay." minutes.");
							break;
						} else {
							$call_delay = rand(7*60, 12*60);
							$error = 1;
							logDebug("ERROR: UNKNOWN PROBLEM!");
							break;
						}
					}

					if ($error == 0) {
						logDebug("Trying to get the device data!");
						#get the devices status data
						logDebug("Getting devices for user...");
						$result = callFitbitAPIForDevices($user_id);
						if (count($result["data"]) > 0) {
							logDebug("Length of devices data to add: ". count($result["data"]));
							addDevicesForUser($user_id, $result["data"]);
						} elseif (strcasecmp($result["result_response"], "too_many_requests") == 0) {
							$call_delay = rand(7*60, 12*60);
							$error = 1;
							logDebug("WARNING: We called too many times, let's call in: ".$call_delay." minutes.");
						}
					}

					$next_call_date = strtotime($call_delay." minutes", time());	
					logDebug("Setting next call to: ".date('Y-m-d H:i:s', $next_call_date));
					setFitbitNextCallTime($user_id, date('Y-m-d H:i:s', $next_call_date));				
				}
			}
		break;
	}

}

function inMState($mstate) {
	logDebug("In MState function ...");

}

function processSMSMessages($state, $mstate) {
	global $sms_from;

	logDebug("Processing SMS messages <".$state.">,<".$mstate.">...");

	switch ($mstate) {
		case "DAY_START":
		case "DIALOGUE_COMPLETE":
			logDebug("In DAY_START!");
			$user_entry = getUserByMobileNumber($sms_from);
			if(count($user_entry)>0) {
				$contents = $_REQUEST['Body'];
				$user_name = $user_entry[0]['name'];
				logDebug("Contents: ".$contents.", ". $user_name);

				// now greet the sender
				header("content-type: text/xml");
				echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
				echo "<Response>";
				echo "<Message>";
				echo $user_name.", thanks for your feedback! You sent us: ".$contents;
				echo "</Message>";
				echo "</Response>";

				//Add to the messages
				addUserResponse($user_entry[0]['id'], $contents);
			}

			break;
		case "RMD1_SENT":
		case "DAY_MSG_SENT":
			logDebug("In DAY_MSG_SENT or RMD1_SENT!");
			$user_entry = getUserByMobileNumber($sms_from);
			if(count($user_entry)>0) {
				$user_id = $user_entry[0]['id'];
				$contents = $_REQUEST['Body'];
				logDebug("Contents: ".$contents.", ". $user_id);

				//get the log of cureent message
				$log_entry = getUserDayStudyLog($user_id, -1, false);
				$msg_id = $log_entry[0]['msg_id'];
				$msg_entry = getTestMessageforID($msg_id);
				logDebug("Message entry for log:".print_r($msg_entry, TRUE));

				#check if this message requires luis smarts
				$msg_intent = "Any";
				if (array_key_exists("luis_url", $msg_entry)) {
					logDebug("Has luis!");	

					$msg_luis_url = $msg_entry['luis_url'];
					$intent_entry = getIntent($msg_luis_url, $contents, $return_type="Array");
					$msg_intent = $intent_entry['intent'];
					logDebug("Luis intent: ".print_r($intent_entry,TRUE));
				}

				//Add to the messages
				addUserResponse($user_id, $contents, $msg_intent);
			}
			break;
		case "RMD2_SENT":
		case "FOLLOWUP_SENT":
			logDebug("In FOLLOWUP_SENT or RMD2_SENT!");
			$user_entry = getUserByMobileNumber($sms_from);
			if(count($user_entry)>0) {
				$user_id = $user_entry[0]['id'];
				$contents = $_REQUEST['Body'];
				logDebug("Contents: ".$contents.", ". $user_id);

				//get the log of cureent message
				$log_entry = getUserDayStudyLog($user_id, -1, false);
				$msg_id = $log_entry[0]['msg_id'];
				$msg_entry = getTestMessageforID($msg_id);
				logDebug("Message entry for log:".print_r($msg_entry, TRUE));

				#we are not evaluation the intet in these messages
				$msg_intent = "";

				//Add to the messages
				addUserResponse($user_id, $contents, $msg_intent);
			}
			break;

	}
}

function isStopMessage($user_id) {
	$messages = getAllUserResponses($user_id);
	
	foreach ($messages as $id => $message) {
		if (strcasecmp($message["text"], "stop") == 0) {
			return true;
		}
	}

	return false;
}

logDebug("Trying to connect to DB in StateUpdateLoop...");
connectToDB();

//No request from user message
$users = array();
if (!$sms_from) {
	logDebug("Looping through all users...");
	$users = getUserList();
//Request from specifc user SMS response
} else {
	$user_entry = getUserByMobileNumber($sms_from);
	logDebug("Request from one user...");
	if(count($user_entry)>0) {
		logDebug("Processing messages of one user:".$sms_from."->".$user_entry[0]['name']);
		$users = $user_entry;

		processSMSMessages($users[0]["state"], $users[0]["m_state"]);
	}
}

logDebug("Going through user list to update their states");

foreach ($users as $n => $user) {
	logDebug("****Updating user [".$n."]->".$user["id"].", ".$user["number"].", ".$user["timezone"]."****");

	$user_id = $user["id"];
	$stopMessage = isStopMessage($user_id);
	$user_timezone = $user["timezone"];

	//Indicate we are updating this user now
	date_default_timezone_set('America/Los_Angeles');
	setLastUpdate($user_id, date("Y-m-d H:i:s", time()));

	if ($user_timezone !== null) {
		logDebug("Setting user timezone: ".$user_timezone);
		date_default_timezone_set($user_timezone);
		$now = time();

		logDebug("USER time:".date("M-d-Y H:i:s", $now));
	}

	logDebug("Getting user log entry in user main loop");

	$log_entry = getUserDayStudyLog($user_id, -1, false);
	$planned_time = $log_entry[0]['planned_time'];
	logDebug("Raw planned time for user: ".$planned_time);
	$pieces = explode(":", $planned_time);
	$msg_hour = $pieces[0];
	$msg_minute = $pieces[1];
	$msg_second = $pieces[2];

	logDebug("Message time components -> H:".$msg_hour.", M:".$msg_minute.", S:".$msg_second);

	$DAY_END_TIME = mktime($DAY_END_TIME_H, $DAY_END_TIME_M, 0, date("m"), date("d"), date("Y"));
	$DAY_MSG_TIME = mktime($msg_hour, $msg_minute, 0, date("m"), date("d"), date("Y"));

	logDebug("DAY_END_TIME:".date("M-d-Y H:i:s", $DAY_END_TIME));
	logDebug("DAY_MSG_TIME:".date("M-d-Y H:i:s", $DAY_MSG_TIME));

	//state transitons
	$status = transitionState($user["state"], $now, $stopMessage);
	if ($status["stateChanged"] == 1) {
		setUserState($user_id, $status["nextState"]);
	}

	$m_status = [	"stateChanged" => 0, 
					"prevState" => $user["m_state"], 
					"nextState" => $user["m_state"] ];

	if ($status["nextState"] == "IN_STUDY") {
		$m_status = transitionMState($user["m_state"],$now, $user_id);
		if ($m_status["stateChanged"] == 1) {
			setUserMState($user_id, $m_status["nextState"]);
		}
	}

	logDebug("State changed: ".$status["stateChanged"].", MState changed: ".$m_status["stateChanged"]);

	//state exit functions
	if ($status["stateChanged"] == 1) {
		exitState($status["prevState"]);
	}
	if ($m_status["stateChanged"] == 1) {
		exitMState($m_status["prevState"]);
	}

	//state entry functions
	if ($status["stateChanged"] == 1) {
		enterState($status["nextState"], $user_id, $user["number"], $user["e_mail"]);
	}
	if ($m_status["stateChanged"] == 1) {
		enterMState($m_status["nextState"], $user_id, $now);
	}

	//state loop functions
	if ($status["stateChanged"] == 0) {
		inState($status["nextState"], $user_id);
	}
	if ($m_status["stateChanged"] == 0) {
		inMState($m_status["nextState"]);
	}

}

logDebug("Update finished!");


?>