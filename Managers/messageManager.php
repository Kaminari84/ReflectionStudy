<?php

include_once("dbManager.php");

logDebug("----MESSAGE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);

$luis_subscription_key = "SUBSCRIPTION_KEY"

$test_messages = [ 
	["id" => 1, "text" => "How many days did you meet that goal?", 						"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["daily"], "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/019f89a7-f3bd-4d4f-8bd3-2d6cc464714b?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q="],
	["id" => 2, "text" => "Have you made progress towards that goal?", 					"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["long_term"] ],
	["id" => 3, "text" => "Which day(s) were you most physically active?", 				"scope" => "WEEK", "source" => "STEPS", "subject" => "activity"],
	["id" => 4, "text" => "Which day(s) were you least active?", 						"scope" => "WEEK", "source" => "STEPS", "subject" => "activity"],
	["id" => 5, "text" => "Can you spot any weekly patterns in your data?", 			"scope" => "2WEEKS", "source" => "STEPS", "subject" => "pattern", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/8501ae69-54dd-4cd8-909b-ef46bd5d9758?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q="],
	["id" => 6, "text" => "Do you remember to wear your fitbit every day?", 			"scope" => "WEEK", "source" => "STEPS", "subject" => "activity", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/f6524bd8-0c1e-4269-9097-03c683ecec2f?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q="],
	["id" => 7, "text" => "How many steps total have you taken last week?", 			"scope" => "WEEK", "source" => "STEPS", "subject" => "achievement"],

	["id" => 8, "text" => "Why is physical activity important for you?", 								"scope" => "NONE", "source" => "NONE", "subject" => "activity"],
	["id" => 9, "text" => "What changes have you made to improve your level of physical activity?", 	"scope" => "2WEEKS", "source" => "STEPS", "subject" => "activity"],
	["id" => 10, "text" => "Is the amount of calories burned directly connected to your steps?", 			"scope" => "WEEK", "source" => "STEPS", "subject" => "tracking"],
	["id" => 11, "text" => "What helped you during the week to make progress towards that goal?", 		"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["weekly","long_term"] ],
	["id" => 12, "text" => "What are some of the ways that your work has impacted your physical activity this week?", 		"scope" => "WEEK", "source" => "STEPS", "subject" => "activity", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/937718a3-fdc4-4447-a530-25a17c7bd068?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q="],
	["id" => 13, "text" => "Is fitbit tracking your data accurately? Why or why not?", 					"scope" => "WEEK", "source" => "STEPS", "subject" => "tracking"],
	["id" => 14, "text" => "Is this goal easy/difficult to achieve for you?", 							"scope" => "WEEK", "source" => "STEPS", "subject" => "goal", "goal_scope" => ["daily","weekly"] ],

	["id" => 15, "text" => "What is the next step for reaching that goal?", 				"scope" => "NONE", "source" => "NONE", "subject" => "goal", "goal_scope" => ["long_term"] ],
	["id" => 16, "text" => "Do your friends exercise more than you do?", 					"scope" => "NONE", "source" => "NONE", "subject" => "social", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/2767ae60-5e0f-44f3-9295-372a8e684eb6?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q=" ],
	["id" => 17, "text" => "Do you think you can be more active?", 							"scope" => "WEEK", "source" => "STEPS", "subject" => "achievement", "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/63397085-3639-4db6-a9ce-c8921596713a?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q=" ],
	["id" => 18, "text" => "What kinds of activities would help you burn more calories?", 	"scope" => "NONE", "source" => "NONE", "subject" => "activity"],
	["id" => 19, "text" => "Is there anything happening next week that could reduce your level of physical activity?", 	"scope" => "NONE", "source" => "NONE", "subject" => "activity",  "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/4cb588e9-f7e2-47ea-9fbf-1c10e0f9cd3f?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&q="],
	["id" => 20, "text" => "Is there anything else that you would like to track besides what Fitbit already tracks?", 	"scope" => "NONE", "source" => "NONE", "subject" => "activity",  "luis_url" => "https://westus.api.cognitive.microsoft.com/luis/v2.0/apps/abb42dd7-d7ac-4962-a0ba-ef4a1ede8bc7?subscription-key=".$luis_subscription_key."&verbose=true&timezoneOffset=0&spellCheck=true&q="]
];

$test_followup_messages = [
	#response options for Q1
	["msg_id" => 1, "text" => "What did you do on days when you met that daily goal?", 				"intent_match" => "Met_the_goal"],
	["msg_id" => 1, "text" => "Why didn’t you meet your goal on any day?", 							"intent_match" => "Did_not_meet_the_goal"],
	["msg_id" => 1, "text" => "Why did you set such a goal for yourself?", 							"intent_match" => "None"],
	
	["msg_id" => 2, "text" => "What activities help you to reach your goal?",						"intent_match" => "Any"],
	["msg_id" => 3, "text" => "What was unique on that day(s) and how can you repeat it?",			"intent_match" => "Any"],
	["msg_id" => 4, "text" => "What happened and how can you better plan for it in the future?",	"intent_match" => "Any"],
	
	#response options for Q5
	["msg_id" => 5, "text" => "What can weekly patterns tell you?",									"intent_match" => "Found_pattern"],
	["msg_id" => 5, "text" => "Do you follow a consistent schedule for physical activities?",		"intent_match" => "Did_not_find_pattern"],
	["msg_id" => 5, "text" => "What could patterns tell you?",										"intent_match" => "None"],
	
	#response options for Q6
	["msg_id" => 6, "text" => "What helps you to remember to wear it every day?",					"intent_match" => "Remember"],
	["msg_id" => 6, "text" => "What can you do to wear your fitbit more consistently?",				"intent_match" => "Do_not_remember"],
	["msg_id" => 6, "text" => "What is helping you remember?",										"intent_match" => "None"],
	

	["msg_id" => 7, "text" => "Does this match what you would like to achieve in a week?",								"intent_match" => "Any"],
	["msg_id" => 8, "text" => "What steps can you take to be more active?",												"intent_match" => "Any"],
	["msg_id" => 9, "text" => "What can you do better next week?",														"intent_match" => "Any"],
	["msg_id" => 10, "text" => "What else can you track to better understand total calories burned?",					"intent_match" => "Any"],
	["msg_id" => 11, "text" => "What could you do in the future to help you reach your goal?",							"intent_match" => "Any"],
	
	#response options for Q12
	["msg_id" => 12, "text" => "What could you do to prevent your work from impacting your physical activity?",			"intent_match" => "Negative_impact"],
	["msg_id" => 12, "text" => "How could you set up your work to help you be more active in the future?",				"intent_match" => "Positive_impact"],
	["msg_id" => 12, "text" => "Do you think it might be a problem next week?",											"intent_match" => "No_impact"],
	["msg_id" => 12, "text" => "How can you modify your work routines to improve your physical activity?",				"intent_match" => "None"],

	/* No follow up for message 12*/

	["msg_id" => 14, "text" => "Should you re-adjust your goal based on this data?",									"intent_match" => "Any"],

	/* No follow up for message 12*/
	["msg_id" => 15, "text" => "How has your recent level of physical activity helped you towards reaching that goal?",	"intent_match" => "Any"],

	#response options for Q16
	["msg_id" => 16, "text" => "How does it make you feel?",												"intent_match" => "Yes_they_do"],
	["msg_id" => 16, "text" => "How does it make you feel?",												"intent_match" => "No_they_dont"],
	["msg_id" => 16, "text" => "Would you find it useful to know your friends' level of physical activity?",		"intent_match" => "None"],

	#response options for Q17
	["msg_id" => 17, "text" => "What small changes (daily repeatable) can you make to be more active?",		"intent_match" => "Yes_I_can"],
	["msg_id" => 17, "text" => "What barriers prevent you from being more active?",						"intent_match" => "No_I_cant"],
	["msg_id" => 17, "text" => "Do you want to be more physically active?",									"intent_match" => "None"],

	["msg_id" => 18, "text" => "Do you burn more calories during the week or on the weekends?",				"intent_match" => "Any"],

	#response options for Q19
	["msg_id" => 19, "text" => "which days do you foresee being more stationary for work or other reasons?",					"intent_match" => "Yes_there_is"],
	["msg_id" => 19, "text" => "Is there anything that is going on that could help you increase your activity?",	"intent_match" => "No_there_isnt"],
	["msg_id" => 19, "text" => "Is there anything that is going on that could help you increase your activity?",	"intent_match" => "None"],

	#response options for Q20
	["msg_id" => 20, "text" => "Why would you like to track this?",													"intent_match" => "Yes_there_is"],
	["msg_id" => 20, "text" => "Do you think you get all the information you need from what you track already?",	"intent_match" => "No_there_isnt"],
	["msg_id" => 20, "text" => "Why do you use Fitbit?",															"intent_match" => "None"]

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
	//logDebug("Messages in func: ".print_r($test_followup_messages, TRUE));

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
	//logDebug("Messages in func: ".print_r($test_messages, TRUE));

	return $test_messages[$msg_id];
}

function getTestMessageforID($msg_id) {
	global $test_messages;	

	logDebug("Getting test message of id: ".$msg_id);
	//logDebug("Messages in func: ".print_r($test_messages, TRUE));

	$selected_messages = [];
	foreach ($test_messages as $n => $test_message) {
		if ($test_message['id'] == $msg_id) {
			$selected_messages[] = $test_message;
		}
	}

	return $selected_messages[0];
}

?>
