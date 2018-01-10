<?php
require_once("PoGoDB-SQLite3.php");

class messenger_hook_class_message_strings {
	function switchboard_less($trainerName) {
		return "Hi " . $trainerName .
			   " - what would you like to do next?" . PHP_EOL .
			   "1 ) Submit a medal score " . PHP_EOL .
			   "2 ) View this months in-progress leaderboard" . PHP_EOL .
			   "3 ) See last months scores " . PHP_EOL .
			   "more ) See all options " . PHP_EOL;
	}
	
	function switchboard_buttons_less() {
		return [ 1 => "1 Submit", 
			     2 => "2 Current",
			     3 => "3 Prev",
			     4 => "More"
			    ];
	}
	
	function switchboard_more($trainerName) {
		return 
			   "1 ) Submit a medal score " . PHP_EOL .
			   "2 ) View this months in-progress leaderboard" . PHP_EOL .
			   "3 ) See last months scores " . PHP_EOL .
			   "4 ) Change your trainer name " . PHP_EOL .
			   "5 ) Toggle sending monthly reminders on/off " . PHP_EOL .
			   "6 ) Undo A Score Submission " . PHP_EOL .
			   "Just enter in one of these numbers to get going";
	}
	
	function switchboard_buttons() {
		return [ 1 => "1 Submit", 
			     2 => "2 Current",
			     3 => "3 Prev",
			     4 => "4 Name",
			     5 => "5 Tog",
			     6 => "6 Undo"
			    ];
	}
	
	function welcome_name_not_known() {
		return  "Hi Trainer!" . PHP_EOL .
				"This is the team valor gym leaderboard bot. " . 
				"I am here to collect the number from your hours defended " .
				"badge and put your score on the leaderboard." . PHP_EOL .
				"Lets get started, may I ask what is your " .
				"Pokemon go screenname?";
	}
	
	function user_requested_name_change() {
		return  "Ok what would you like me to call you?";
	}
	
	function nameInvalid($newName) {
		return "Sorry I don't think your name can be " . PHP_EOL .
			   $newName . PHP_EOL .
			   "The name must not contain spaces and obey the Pokemon GO character limit. " . 
			   "Please try again.";
	}
	
	function set_name_response($newName) {
		return  "So you would like me to call you $newName ?";
	}
	
	function set_name_response_buttons() {
		return [ "Yes" , "No" ];
	}
	
	function yesno_buttons() {
		return [ "Yes" , "No" ];
	}
	
	function cancelNameSet($currentName) {
		return "OK I will continue to call you " . $currentName;
	}
	
	function nameSetComplete($newName) {
		return "Great I will call you " . $newName;
	}
	
	function optedOut() {
		return "You have opted out from receiving any messages from  " .
			   "me unless you speak to me first. " . PHP_EOL .
			   "You can change this anytime you like.";
	}
	
	function optedIn() {
		return "You have opted in to receiving messages from me automatically" .
			   PHP_EOL .
			   "I will try and remind you to sumbit scores monthly " .
			   "from now on and I may contact you regarding gym " . 
			   "takeovers in a future update" . PHP_EOL .
			   "You may change this setting anytime you like";
	}
	
	function sumbit_first_score() {
		return "You are about to submit your first score!" . PHP_EOL .
			   "Just enter in the number of hours from the GYM LEADER medal " .
			   "from your game. Next time you submit a score I will " .
			   "average the amount of hours defended over the time " .
			   "between submissions.";
	}
	
	function submit_score($lastScore) {
		$r = "Please enter the total hours on your GYM LEADER medal.";
		$p = "If it is still $lastScore just tap the button below to record this.";
		$r .= ($lastScore>-1) ? PHP_EOL . $p : "";
		return $r;
	}
	
	function submit_score_buttons($lastScore) {
		return ( $lastScore > -1 ) ? [ $lastScore ] : [];
	}
	
	function score_confirm($lastScore) {
		return "So you would like to submit a score of $lastScore?";
	}
	
	function score_confirm_buttons() {
		return [ "Yes" , "No" ];
	}
	
	function score_sub_cancelled() {
		return "Ok I will not save that score.";
	}
	
	function score_error($errText) {
		return "Sorry I could not process your score. The error I got was" .
			   PHP_EOL . $errText . PHP_EOL .
			   "Please try again later.";
	}
	
	function score_sucess($scoreInfo) {
		$performance =  -1;
		if( isset($scoreInfo["priorScore"]) ) {
			$performance = $scoreInfo["comparision"]["performance"];
		}
		
		if( $performance > 30 ) {
			$msg = "Wow what a score! :-O Turning the town red. Well done!";
		} elseif( $performance > 25 ) {
			$msg = "What an amazing score. Well done! :-)";
		} elseif( $performance > 15 ) {
			$msg = "What a fantastic score well done! :-)";
		} elseif( $performance > 10 ) {
			$msg = "What a great score well done! :-)";
		} elseif( $performance != -1 ) {
			$msg = "Thanks for submitting. Keep taking those gyms :-)";
		} else {
			$msg = "Thanks for submitting your first score";
		}
		/*
		if( $performance != -1 ) {
			$msg .= PHP_EOL . "You defended " . $scoreInfo["comparision"]["acheivedPoints"] .
			        " hours out of a maxiumum of " . 
			        $scoreInfo["comparision"]["maxNewPoints"];
		}
		*/
		return $msg;
	}
	
	function confirmScoreRemoval($scoreValue, $scoreDate) {
		return "OK you would like to remove your previous score submission of " .
			   $scoreValue . " made at " . $scoreDate . "?";
	}
	
	function cancelScoreRemoval() {
		return "OK I will not remove that score";
	}
	
	function completedScoreRemoval() {
		return "OK that score has been removed from the system.";
	}
}

class messenger_hook_class {
	private $hubVerifyToken;
	private $accessToken;
	private $message_strings;
	
	public function __construct($hubVerifyToken, $accessToken) {
		$this->accessToken = $accessToken;
		$this->hubVerifyToken = $hubVerifyToken;
		$this->message_strings = new messenger_hook_class_message_strings();
	}

	public function handleMessages($data) {
		foreach ($data['entry'][0]['messaging'] as $message) {		
			$senderId = $message['sender']['id'];
			$messageText = $message['message']['text'];
			$this->handleMessage($senderId, $messageText);
		}
	}
	
	public function isAValidPogoName($name) {
		if( strlen($name) > 20 ) return false;
		return ( str_replace( " ", "", $name ) == $name);
	}
	
	public function handleMessage_newuser($db, $senderId, $messageText) {
		$message = $this->message_strings->welcome_name_not_known();
		$this->sendMessage_response($senderId, $message);
		$db->db_set_expecting_response_from_user("setname");
	}
	
	public function handleMessage_switchboard_1_submitscore($db, $senderId, $messageText) {
		$scoreInfo = $db->ui_lastscoreinfo();
		if( is_null( $scoreInfo["newestScore"] ) ) {
			$lastscore = -1;
			$message = $this->message_strings->sumbit_first_score();
			$this->sendMessage_response($senderId, $message);
		} else {
			$lastscore = $scoreInfo["newestScore"]["scorevalue"];
		}
		$message = $this->message_strings->submit_score($lastscore);
		$buttons = $this->message_strings->submit_score_buttons($lastscore);
		$this->sendMessage_response($senderId, $message, $buttons);
		$db->db_set_expecting_response_from_user("submitscore");
	}
	
	public function handleMessage_switchboard_2_showscores($db, $senderId, $messageText) {
		$message = $db->ui_thismonthscores();
		$this->sendMessage_response($senderId, $message);
		$this->handleMessage($senderId, "switchboard");
	}
	
	public function handleMessage_switchboard_3_showlastmscores($db, $senderId, $messageText) {
		$message = $db->ui_lastmonthscores();
		$this->sendMessage_response($senderId, $message);
		$this->handleMessage($senderId, "switchboard");
	}
	
	public function handleMessage_switchboard_4_namechange($db, $senderId, $messageText) {
		$message = $this->message_strings->user_requested_name_change();
		$this->sendMessage_response($senderId, $message);
		$db->db_set_expecting_response_from_user("setname");
	}
	
	public function handleMessage_switchboard_5_toggleautomsg($db, $senderId, $messageText) {
		$state = $db->db_get_optout_messages();
		$newState = ! $state;
		$db->db_set_optout_messages($newState);
		if( ! $newState ) {
			$message = $this->message_strings->optedOut();
		} else {
			$message = $this->message_strings->optedIn();
		}
		$this->sendMessage_response($senderId, $message);
		$this->handleMessage($senderId, "switchboard");
	}
	
	public function handleMessage_switchboard_6_undo($db, $senderId, $messageText) {
		$lastScoreInfo = $db->ui_lastscoreinfo();
		if( is_null( $lastScoreInfo["newestScore"] ) ) {
			$message = "You have no scores submitted that can be undone!";
			$this->sendMessage_response($senderId, $message);
			$this->handleMessage($senderId, "switchboard");
			return;
		}
		$message = $this->message_strings->confirmScoreRemoval(
			$lastScoreInfo["newestScore"]["scorevalue"],
			$lastScoreInfo["newestScore"]["humantime"]
		);
		$buttons = $this->message_strings->yesno_buttons();
		$this->sendMessage_response($senderId, $message, $buttons);
		$db->db_set_expecting_response_from_user("confirmscoreremoval");
	}
	
	public function handleMessage_switchboard_show($db, $senderId, $messageText) {
		$username = $db->db_get_pogoName();
		$message = $this->message_strings->switchboard_less($username);
		$buttons = $this->message_strings->switchboard_buttons_less();
		$this->sendMessage_response($senderId, $message, $buttons);
		$db->db_set_expecting_response_from_user("");
	}
	
	public function handleMessage_switchboard_showMore($db, $senderId, $messageText) {
		$username = $db->db_get_pogoName();
		$message = $this->message_strings->switchboard_more($username);
		$buttons = $this->message_strings->switchboard_buttons();
		$this->sendMessage_response($senderId, $message, $buttons);
		$db->db_set_expecting_response_from_user("");
	}
	
	public function handleMessage_switchboard_option_given($db, $senderId, $messageText) {
		switch( strtolower(trim($messageText)) ) {
			case "1" :
			case strtolower($this->message_strings->switchboard_buttons()[1]) :
				$this->handleMessage_switchboard_1_submitscore($db, $senderId, $messageText);
				break;
			case "2" :
			case strtolower($this->message_strings->switchboard_buttons()[2]) :
				$this->handleMessage_switchboard_2_showscores($db, $senderId, $messageText);
				break;
			case "3" :
			case strtolower($this->message_strings->switchboard_buttons()[3]) :
				$this->handleMessage_switchboard_3_showlastmscores($db, $senderId, $messageText);
				break;
			case "more" :
				$this->handleMessage_switchboard_showMore($db, $senderId, $messageText);
				break;
			case "4" : 
			case strtolower($this->message_strings->switchboard_buttons()[4]) :
				$this->handleMessage_switchboard_4_namechange($db, $senderId, $messageText);
				break;
			case "5" :
			case strtolower($this->message_strings->switchboard_buttons()[5]) :
				$this->handleMessage_switchboard_5_toggleautomsg($db, $senderId, $messageText);
				break;
			case "6" :
			case strtolower($this->message_strings->switchboard_buttons()[6]) :
				$this->handleMessage_switchboard_6_undo($db, $senderId, $messageText);
				break;
			default :
				$this->handleMessage_switchboard_show($db, $senderId, $messageText);
				break;
		}
	}

	public function handleResponseTo_setname($db, $senderId, $messageText) {
		if( ! $this->isAValidPogoName($messageText) ) {
			$message = $this->message_strings->nameInvalid($messageText);
			$this->sendMessage_response($senderId, $message);
			return;
		}
		$message = $this->message_strings->set_name_response($messageText);
		$buttons = $this->message_strings->set_name_response_buttons();
		$this->sendMessage_response($senderId, $message, $buttons);
		$db->db_set_expecting_response_from_user("setname_confirm");
		$db->db_set_pending_response_from_user($messageText);
	}
	
	public function handleResponseTo_setname_confirm($db, $senderId, $messageText) {
		$newName = $db->db_get_pending_response_from_user();
		if( trim(strtolower($messageText)) != "yes" ) {
			if( $db->db_get_pogoName() == "" ) {
				$message = $this->message_strings->welcome_name_not_known();
				$db->db_set_expecting_response_from_user("setname");
				$this->sendMessage_response($senderId, $message);
			} else {
				$currentName = $db->db_get_pogoName();
				$message = $this->message_strings->cancelNameSet($currentName);
				$this->sendMessage_response($senderId, $message);
				$this->handleMessage_switchboard_show($db, $senderId, $messageText);
			}
			return;
		} else {
			$db->db_set_pogoName($newName);
			$message = $this->message_strings->nameSetComplete($newName);
			$this->sendMessage_response($senderId, $message);
			$this->handleMessage_switchboard_show($db, $senderId, $messageText);
		}
	}
	
	public function handleResponseTo_submitscore($db, $senderId, $messageText) {
		$messageText = str_replace(',', '', $messageText);
		$messageText = preg_replace('/[^0-9]/s', '', $messageText);
		if( trim($messageText) == "" ) {
			$message = "Sorry I did not catch that please enter a number.";
			$this->sendMessage_response($senderId, $message);
			$db->db_set_expecting_response_from_user("submitscore");
			return;
		}
		$firsttext=explode(" ", trim($messageText))[0];
		if( ! is_numeric( $firsttext ) ) {
			$message = "Sorry I did not catch that please enter a number.";
			$this->sendMessage_response($senderId, $message);
			$db->db_set_expecting_response_from_user("submitscore");
			return ;
		}
		try {
			$db->action_validate_potentialNewScore($firsttext);
			$db->db_set_expecting_response_from_user("scoresubmission_confirm");
			$db->db_set_pending_response_from_user($firsttext);
			$message = $this->message_strings->score_confirm($firsttext);
			$buttons = $this->message_strings->score_confirm_buttons();
			$this->sendMessage_response($senderId, $message, $buttons);
		} catch (Exception $e) {
			$message = $e->getMessage();
			$this->sendMessage_response($senderId, $message);
			$this->handleMessage_switchboard_show($db, $senderId, $messageText);
			return ;
		}
	}
	
	public function handleResponseTo_scoresubmission_confirm($db, $senderId, $messageText) {
		try {
			$newScore = $db->db_get_pending_response_from_user();
			if( trim(strtolower($messageText)) == "yes" ) {
				$db->action_newScore($newScore);
				$scoreInfo = $db->ui_lastscoreinfo();
				$message = $this->message_strings->score_sucess($scoreInfo);
				$this->sendMessage_response($senderId, $message);
			} else {
				$message = $this->message_strings->score_sub_cancelled();
				$this->sendMessage_response($senderId, $message);
			}
		} catch (Exception $e) {
			$message = $e->getMessage();
			$this->sendMessage_response($senderId, $message);
		}
		$this->handleMessage_switchboard_show($db, $senderId, $messageText);
	}
	
	public function handleResponseTo_confirmscoreremoval($db, $senderId, $messageText) {
		try {
			if( trim(strtolower($messageText)) == "yes" ) {
				$db->action_undoPrevScore();
				$message = $this->message_strings->completedScoreRemoval();
				$this->sendMessage_response($senderId, $message);
			} else {
				$message = $this->message_strings->cancelScoreRemoval();
				$this->sendMessage_response($senderId, $message);
			}
		} catch (Exception $e) {
			$message = $e->getMessage();
			$this->sendMessage_response($senderId, $message);
		}
		$this->handleMessage_switchboard_show($db, $senderId, $messageText);
	}

	public function handleMessage($senderId, $messageText) {
		$db = new PoGoDB_SQLite3($senderId);
		$expectingResponse = $db->db_get_expecting_response_from_user();
		
		// a new user has spoken to bot
		if( $expectingResponse == "" and $db->db_get_pogoName() == "" ) {
			$this->handleMessage_newuser($db, $senderId, $messageText);
			return;
		}
		
		// expecting a response - call the correct handler from here
		switch( $expectingResponse ) {
			case "" : // no response expected - send to switchboard handler
				$this->handleMessage_switchboard_option_given($db, $senderId, $messageText);
				break;
			case "setname" :
				$this->handleResponseTo_setname($db, $senderId, $messageText);
				break;
			case "setname_confirm" :
				$this->handleResponseTo_setname_confirm($db, $senderId, $messageText);
				break;
			case "submitscore" :
				$this->handleResponseTo_submitscore($db, $senderId, $messageText);
				break;
			case "scoresubmission_confirm" :
				$this->handleResponseTo_scoresubmission_confirm($db, $senderId, $messageText);
				break;
			case "confirmscoreremoval" :
				$this->handleResponseTo_confirmscoreremoval($db, $senderId, $messageText);
				break;
		}	
	}
	
	public function sendMessage_response($recipientID, $messageText, $buttons = []) {
		$response = [
				'recipient' =>	[ 'id' => $recipientID ],
				'message' =>	[ 'text' => $messageText ],
				'messaging_type' =>  "RESPONSE"
			];
		if( ! empty( $buttons ) ) {
			$quickreplies = [];
			foreach( $buttons as $buttontxt ) {
				$quickreplies[] = [
									"content_type" => "text",
									"title" => $buttontxt,
									"payload" => $buttontxt
								  ];
			}
			$response["message"]["quick_replies"]=$quickreplies;
		}
		$ch = curl_init('https://graph.facebook.com/v2.6/me/messages?access_token='.$this->accessToken);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_exec($ch);
		curl_close($ch);
	}
	
}
