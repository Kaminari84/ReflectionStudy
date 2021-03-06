<?php

include_once("dbManager.php");

logDebug("----MESSAGE MANAGER LOAD----");
logDebug("USER IP:".$_SERVER['REMOTE_ADDR']);


$sentence_openings = [
	["text" => "Hi <name>, thinking about the goals you set.", 		"subject" => "goal", "require" => "none"],
	["text" => "Hi <name>, taking your goals into consideration.", 	"subject" => "goal", "require" => "none"],
	["text" => "Hi <name>, thinking about your activity.", 			"subject" => "activity", "require" => "none"],
	["text" => "Hi <name>, regarding your progress.", 				"subject" => "achievement", "require" => "none"],
	["text" => "Hi <name>, I have a good questions for you.", 		"subject" => "none", "require" => "none"],
	["text" => "Hi <name>, take a look at your graph.", 			"subject" => "none", "require" => "graph"],
	["text" => "Hi <name>!", 										"subject" => "none", "require" => "none"],
	["text" => "Hi <name>, when looking at tracking.", 				"subject" => "tracking", "require" => "graph"],
	["text" => "Hi <name>, I was looking at your data.",			"subject" => "none", "require" => "graph"],
	["text" => "Hi <name>, I was checking your data.",				"subject" => "none", "require" => "graph"]
];

$goal_introductions = [
	["text" => "You listed as one of your goals: <goal>."],
	["text" => "One of the goals you indicated is: <goal>."],
	["text" => "You mentioned <goal> as one of your goals."],
	["text" => "Referring to your goal of <goal>."],
	["text" => "In relation to your goal of <goal>."],
	["text" => "Specifically thinking about your goal of <goal>."],
	["text" => "In particular considering the goal of <goal>."]
];

$follow_up_openings = [
	["text" => "Please think for a minute,"],
	["text" => "Please stop and think for a while,"],
	["text" => "Please take a moment to think,"],
	["text" => "Please take a while to consider,"],
	["text" => "Please think a bit,"],
	["text" => "Please consider for a moment,"],
	["text" => "Please reflect for a minute,"],
	["text" => "Please reflect for a while,"],
	["text" => "Please contemplate for a moment,"],
	["text" => "Please take a moment to reflect,"]
];

$confirmations = [
	["text" => "<name>, thanks for your feedback! You sent us: <text>"],
];

$first_reminders = [
	["text" => "Hey <name>, did you have a chance to think about the last question?"],
	["text" => "Hey <name>, I haven’t heard from you. What do you think about the last question?"],
	["text" => "Hey <name>, what do you think about the last question?"],
	["text" => "Hey <name>, I am curious about your thoughts regarding the last question."]
];

$second_reminders = [
	["text" => "Hey <name>, did you have a chance to think about the last question?"],
	["text" => "Hey <name>, I haven’t heard from you. What do you think about the last question?"],
	["text" => "Hey <name>, what do you think about the last question?"],
	["text" => "Hey <name>, I am curious about your thoughts regarding the last question."]
];

$dialoge_end_thank_you = [
	["text" => "<name>, thank you so much for sharing your reflections with us."]
];


/*
$access_approval = [
	["text" => ]
]*/

##############################
#### GET MESAGES AND GLUE ####
##############################

function getMatchingOpening($subjects, $require) {
	global $sentence_openings;

	$selected_openings = [];
	foreach ($sentence_openings as $n => $opening) {
		if (in_array($opening['subject'], $subjects)) {
			if (in_array($opening['require'], $require)) {
				$selected_openings[] = $opening;
			}
		}
	}

	srand(make_seed());
	$opening_no = rand(0,count($selected_openings)-1);

	return $selected_openings[$opening_no];
}

function getMatchingFollowUpOpening() {
	global $follow_up_openings;

	$selected_openings = $follow_up_openings;
	srand(make_seed());
	$opening_no = rand(0,count($selected_openings)-1);

	return $selected_openings[$opening_no];
}

function getGoalIntroduction() {
	global $goal_introductions;

	srand(make_seed());
	$intro_no = rand(0,count($goal_introductions)-1);

	return $goal_introductions[$intro_no];
}

function getFirstReminder($user_name) {
	global $first_reminders;

	srand(make_seed());
	$rmd_no = rand(0,count($first_reminders)-1);

	$rmd_raw_text = $first_reminders[$rmd_no]['text'];
	$final_text = replaceEntities($rmd_raw_text, ["<name>" => $user_name]);

	return $final_text;
}

function getSecondReminder($user_name) {
	global $second_reminders;

	srand(make_seed());
	$rmd_no = rand(0,count($second_reminders)-1);

	$rmd_raw_text = $second_reminders[$rmd_no]['text'];
	$final_text = replaceEntities($rmd_raw_text, ["<name>" => $user_name]);

	return $final_text;
}

function getReplyConfirmationMessage($user_name, $original_msg) {
	global $test_messages, $confirmations, $sentence_openings;	

	$msg = $user_name.", thanks for your feedback! You sent us: ".$original_msg;

	return ["text" => $msg];
}

function getDialogueEndThankYou($user_name) {
	global $dialoge_end_thank_you;

	srand(make_seed());
	$dety_no = rand(0,count($dialoge_end_thank_you)-1);

	$raw_text = $dialoge_end_thank_you[$dety_no]['text'];
	$final_text = replaceEntities($raw_text, ["<name>" => $user_name]);

	return ["text" => $final_text];
}


##############################
#### PATTERN REPLACEMENTS ####
##############################

function replaceEntities($text, $mapping_array) {

	$result = $text;
	foreach ($mapping_array as $original => $replacement) {
		$result = str_replace($original, $replacement, $result);
	}	

	return $result;
}

function fillMessagePattern($raw_msg_params, $user_name, $user_goal) {
	global $sentence_openings, $goal_introductions;	

	logDebug("Got message params to fill template: ".print_r($raw_msg_params, true));

	#get matching opening
	$subjects = [$raw_msg_params["subject"]];
	$subjects[] = "none";

	$require = [];
	if (strcasecmp($raw_msg_params["scope"], "NONE") == 0) {
		$require[] = "none";
	} else {
		$require[] = "graph";
	}

	logDebug("Subjects for openings: ".print_r($subjects, TRUE));
	logDebug("Requirements for openings: ".print_r($require, TRUE));

	#get the opening
	$opening = getMatchingOpening($subjects, $require);
	logDebug("Got opening:".print_r($opening, TRUE));

	$message = $opening["text"];

	$replacement_array = ["<name>" => $user_name];

	if (strcasecmp($raw_msg_params["subject"], "goal") == 0 && array_key_exists("text", $user_goal)) {
		#get goal intro
		$goal_intro = getGoalIntroduction();
		$message .= "  ". $goal_intro["text"];

		$replacement_array["<goal>"] = "\"".$user_goal['text']."\"";
	}

	$message .= " " . $raw_msg_params["text"];
	logDebug("Complete message before replacement: ".$message);

	#replae template slots with data
	$final_text = replaceEntities($message, $replacement_array);
	logDebug("Complete message after replacement: ".$final_text);

	$msg_params = $raw_msg_params;
	$msg_params["text"] = $final_text;

	return $msg_params;
}

function fillFollowUpMessagePattern($raw_msg_params) {
	global $follow_up_openings;

	logDebug("Got follow up message params to fill template: ".print_r($raw_msg_params, true));

	#get the opening
	$opening = getMatchingFollowUpOpening();
	logDebug("Got follow up opening:".print_r($opening, TRUE));

	$message = $opening["text"];

	//The messages start with capital letter, but to glue them on top of opening, we need to convert them to lower case.
	$message .= " " . strtolower($raw_msg_params["text"]);
	logDebug("Complete follow up message: ".$message);

	$final_text = $message;

	$msg_params = $raw_msg_params;
	$msg_params["text"] = $final_text;

	return $msg_params;
}


?>