<?php

include_once("dbManager.php");

logDebug("----MESSAGE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

/*$messages = [ 
	["text" => "How many days did you meet your goal?", "cat" => "NOTICE", "sub_cat" => "GAC", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "Did you meet your goal?", "cat" => "NOTICE", "sub_cat" => "GAC", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "Which day did you perform well and move closer to your goal?", "cat" => "NOTICE", "sub_cat" => "GAC", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "Which day were you most active?", "cat" => "NOTICE", "sub_cat" => "PAA", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "Is there a weekly pattern?", "cat" => "NOTICE", "sub_cat" => "PAA", "scope" => "2WEEKS", "source" => "STEPS"],

	["text" => "What was unique and how can you repeat it?", "cat" => "UNDERSTAND", "sub_cat" => "CTX", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "What happened and how can you avoid the same?", "cat" => "UNDERSTAND", "sub_cat" => "CTX", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "What did you do out of the ordinary on days that are outliers in the data?", "cat" => "UNDERSTAND", "sub_cat" => "CTX", "scope" => "WEEK", "source" => "STEPS"],
	["text" => "Why were some days better or worse than others?", "cat" => "UNDERSTAND", "sub_cat" => "CTX", "scope" => "WEEK", "source" => "STEPS"],

	["text" => "How can you be consistent in achieving the goal?", "cat" => "FUTURE", "sub_cat" => "FI", "scope" => "WEEK", "source" => "NONE"],
	["text" => "What measures can you take to hit your goal every day?", "cat" => "FUTURE", "sub_cat" => "FI", "scope" => "WEEK", "source" => "NONE"],
	["text" => "What small changes (daily repeatable) can you make today?", "cat" => "FUTURE", "sub_cat" => "FI", "scope" => "WEEK", "source" => "NONE"],
	["text" => "How can you be more active?", "cat" => "FUTURE", "sub_cat" => "FI", "scope" => "WEEK", "source" => "NONE"],
	["text" => "Is your goal too low?", "cat" => "FUTURE", "sub_cat" => "GR", "scope" => "WEEK", "source" => "NONE"],
];*/

$test_messages = [ 
	["id" => 1, "text" => "How many days did you meet that goal?", 						"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["daily"], "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/3ca81f13-52ab-41f4-98a8-d7090b474baa?subscription-key=***REMOVED***&timezoneOffset=0&verbose=true&q="],
	["id" => 2, "text" => "Have you made progress towards your?", 						"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["weekly","long-term"],
	["id" => 3, "text" => "Which day(s) were you most physically active?", 				"scope" => "WEEK", "source" => "STEPS", "subject" => "goal"],
	["id" => 4, "text" => "Which day(s) were you least active?", 						"scope" => "WEEK", "source" => "STEPS", "subject" => "activity"],
	["id" => 5, "text" => "Can you spot any weekly patterns in your data?", 			"scope" => "2WEEKS", "source" => "STEPS", "subject" => "pattern", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/3ca81f13-52ab-41f4-98a8-d7090b474baa?subscription-key=***REMOVED***&timezoneOffset=0&verbose=true&q="],
	["id" => 6, "text" => "Do you remember to wear your fitbit everyday?", 				"scope" => "WEEK", "source" => "STEPS", "subject" => "activity", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/3ca81f13-52ab-41f4-98a8-d7090b474baa?subscription-key=***REMOVED***&timezoneOffset=0&verbose=true&q="],
	["id" => 7, "text" => "How many steps total have you taken last week?", 			"scope" => "WEEK", "source" => "STEPS", "subject" => "achievement"],

	["id" => 8, "text" => "Why is physical activity important for you?", 								"scope" => "WEEK", "source" => "STEPS", "subject" => "activity"],
	["id" => 9, "text" => "What changes have you done to improve your level of physical activity?", 	"scope" => "2WEEKS", "source" => "STEPS", "subject" => "activity"],
	["id" => 10, "text" => "Is your caloric expenditure directly connected to your steps?", 			"scope" => "WEEK", "source" => "STEPS", "subject" => "tracking"],
	["id" => 11, "text" => "What helped you during the week to make progress towards your goal?", 		"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["weekly","long-term"],
	["id" => 12, "text" => "What are some of the ways that your work has impacted your physical activity this week?", 		"scope" => "WEEK", "source" => "STEPS", "subject" => "activity", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/937718a3-fdc4-4447-a530-25a17c7bd068?subscription-key=***REMOVED***&verbose=true&timezoneOffset=0&q="],
	["id" => 13, "text" => "Is fitbit tracking your data accurately? Why or why not?", 					"scope" => "WEEK", "source" => "STEPS", "subject" => "tracking"],
	["id" => 14, "text" => "Is your goal easy/difficult to achieve for you?", 							"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["daily","weekly"],
];

$test_followup_messages = [
	["msg_id" => 1, "text" => "What did you do on days when you met that daily goal?", 				"intent_match" => "Met_the_goal"],
	["msg_id" => 1, "text" => "Why didn’t you meet your goal on any day?", 							"intent_match" => "Did_not_meet_the_goal"],
	["msg_id" => 2, "text" => "What activities help you to reach your goal?",						"intent_match" => "Any"],
	["msg_id" => 3, "text" => "What was unique on that day(s) and how can you repeat it?",			"intent_match" => "Any"],
	["msg_id" => 4, "text" => "What happened and how can you avoid it in the future?",				"intent_match" => "Any"],
	["msg_id" => 5, "text" => "Why is there this pattern?",											"intent_match" => "Found_pattern"],
	["msg_id" => 5, "text" => "Do you follow a consistent schedule for physical activities?",		"intent_match" => "Did_not_find_pattern"],
	["msg_id" => 6, "text" => "What is effective in helping you to remember to wear it everyday?",	"intent_match" => "Remember"],
	["msg_id" => 6, "text" => "What can you do to wear your fitbit more consistently?",				"intent_match" => "Do_not_remember"],
	["msg_id" => 7, "text" => "Is this what you would like to achieve in a week?",					"intent_match" => "Any"],

	["msg_id" => 8, "text" => "How can you be more active?",															"intent_match" => "Any"],
	["msg_id" => 9, "text" => "What can you do better next week?",														"intent_match" => "Any"],
	["msg_id" => 10, "text" => "What other metrics do you want to track to understand you caloric expenditure better?",	"intent_match" => "Any"],
	["msg_id" => 11, "text" => "What should you do in the future to help you reach your goal?",							"intent_match" => "Any"],
	["msg_id" => 12, "text" => "What could you do to prevent your work from impacting your physical activity?",			"intent_match" => "Work_has_impacted"],
	["msg_id" => 12, "text" => "Do you think it might be a problem next week?",											"intent_match" => "Work_has_not_impacted"],
	/* No follow up for message 12*/
	["msg_id" => 14, "text" => "Should you readjust your goal based on previous data?",									"intent_match" => "Any"],
];

/*function generateOldAllMessages() {
	global $conn, $messages;
	
	logDebug("Setting user state...");

	#add each message if does not exist
	foreach ($messages as $i => $message_params) {
		$text = $message_params["text"];
		$cat = $message_params["cat"];
		$sub_cat = $message_params["sub_cat"];
		$scope = $message_params["scope"];
		$source = $message_params["source"];

		logDebug("Adding message: ".$text);

		$sql = "INSERT INTO RS_message(text,cat,sub_cat,scope,source) VALUES(\"$text\",\"$cat\",\"$sub_cat\",\"$scope\",\"$source\")";
		logDebug("Running update SQL: " . $sql);

		if ($conn->query($sql) === TRUE) {
		    logDebug("Record updated successfully");
		} else {
		    logError("Error: " . $sql . "<br>", $conn->error);
		}
	}
}*/

function getAllMessages() {
	/*$sql = "SELECT * FROM RS_message";
	logDebug("Getting a list of all messages...");

	return executeSimpleSelectQuery($sql);*/
	return getAllTestMessages();
}

/*function clearOldAllMessages() {
	global $conn;

	$sql = "DELETE FROM RS_message";
	logDebug("Running delete SQL: " . $sql);

	if ($conn->query($sql) === TRUE) {
		logDebug("Records deleted successfully");
	} else {
		logError("Error: " . $sql . "<br>", $conn->error);
	}
}*/

function getMessageForID($msg_id) {
	/*$sql = "SELECT * FROM RS_message WHERE `id`=\"$msg_id\"";
	logDebug("Getting message for id:".$msg_id);

	return executeSimpleSelectQuery($sql)[0];*/
	return getTestMessageforID($msg_id);
}

#########################################
###### TEST MESSAGES -> not in DB #######
#########################################

function getAllTestMessages() {
	global $test_messages;

	return $test_messages;
}

function getTestFollowUpForMessageID($msg_id, $intent = "") {
	global $test_followup_messages;	

	logDebug("Getting follow up message for message: ".$msg_id);
	logDebug("Messages in func: ".print_r($test_followup_messages, TRUE));

	$selected_followups = [];
	foreach ($test_followup_messages as $n => $followup_message) {
		if ($followup_message['msg_id'] == $msg_id) {
			#no intent is given, check if there is any followup
			if (strcasecmp($intent, "") == 0) {
				$selected_followups[] = $followup_message;
			} elseif (strcasecmp($followup_message['intent_match'], $intent) == 0) {
				$selected_followups[] = $followup_message;
			}
		}
	}

	if (count($selected_followups) > 0) {
		srand(make_seed());
		$followup_no = rand(0,count($selected_followups)-1);
		return $selected_followups[$followup_no];
	} else {
		return $selected_followups;
	}
}

function getTestRandomMessage() {
	global $test_messages;	

	$msg_id = rand(0, count($test_messages));
	logDebug("Getting random test message: ".$msg_id);
	logDebug("Messages in func: ".print_r($test_messages, TRUE));

	return $test_messages[$msg_id];
}

function getTestMessageforID($msg_id) {
	global $test_messages;	

	logDebug("Getting test message of id: ".$msg_id);
	logDebug("Messages in func: ".print_r($test_messages, TRUE));

	$selected_messages = [];
	foreach ($test_messages as $n => $test_message) {
		if ($test_message['id'] == $msg_id) {
			$selected_messages[] = $test_message;
		}
	}

	return $selected_messages[0];
}

?>