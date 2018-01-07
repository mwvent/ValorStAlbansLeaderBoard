<?php
require_once("PoGoDB-SQLite3.php");

class messenger_hook_class_message_strings {
	function switchboard($trainerName) {
		return "Hi " . $trainerName . PHP_EOL .
			   "I have several options for you" . PHP_EOL .
			   "1 ) Submit a badge score " . PHP_EOL .
			   "2 ) View this months in-progress leaderboard" . PHP_EOL .
			   "3 ) See last months scores " . PHP_EOL .
			   "4 ) Change your trainer name " . PHP_EOL .
			   "5 ) Toggle sending monthly reminders on/off " . PHP_EOL .
			   "Just enter in one of these numbers to get going";
	}
	
	function switchboard_buttons() {
		return [ "1", "2", "3", "4", "5" ];
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
			   "Please try again.";
	}
	
	function set_name_response($newName) {
		return  "So you would like me to call you $newName ?";
	}
	
	function set_name_response_buttons() {
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
	
	function submit_score() {
		return "Please enter the total hours on your GYM LEADER medal.";
	}
	
	function score_error($errText) {
		return "Sorry I could not process your score. The error I got was" .
			   PHP_EOL . $errText . PHP_EOL .
			   "Please try again later.";
	}
	
	function score_sucess($hoursDefended, $maxPossibleHoursDefended) {
		$percentageOfPossibleMax = ($hoursDefended / $maxPossibleHoursDefended);
		if( $percentageOfPossibleMax_as1to100 > 90 ) {
			return "Wow what a score! :-O Turning the town red. Well done!";
		} elseif( $percentageOfPossibleMax_as1to100 > 50 ) {
			return "What an amazing score. Well done! :-)";
		} elseif( $percentageOfPossibleMax_as1to100 > 30 ) {
			return "What a fantastic score well done! :-)";
		} elseif( $percentageOfPossibleMax_as1to100 > 20 ) {
			return "What a great score well done! :-)";
		} else {
			return "Thanks for submitting. Keep taking those gyms :-)";
		}
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
		return ( str_replace( " ", "", $name ) == $name);
	}

	public function handleMessage($senderId, $messageText) {
		$db = new PoGoDB_SQLite3($senderId);
		
		
		switch( $db->db_get_expecting_response_from_user() ) {
			case "" :
				if( $db->db_get_pogoName() == "" ) {
					$message = $this->message_strings->welcome_name_not_known();
					$this->sendMessage_response($senderId, $message);
					$db->db_set_expecting_response_from_user("setname");
				} else {
					// $this->sendMessage_response($senderId, "hi");
					// $db->db_set_expecting_response_from_user("");
					// TODO woah this is getting very nested
					switch( trim($messageText) ) {
						case "1" :
							if( ! $db->ui_userHasScoreRecorded() ) {
								$message = $this->message_strings->sumbit_first_score();
								$this->sendMessage_response($senderId, $message);
							}
							$message = $this->message_strings->submit_score();
							$this->sendMessage_response($senderId, $message);
							$db->db_set_expecting_response_from_user("submitscore");
							break;
						case "2" :
							$message = $db->ui_thismonthscores();
							$this->sendMessage_response($senderId, $message);
							$this->handleMessage($senderId, "switchboard");
							break;
						case "3" :
							$message = "Sorry this is not online yet - we need a bit more data";
							$this->sendMessage_response($senderId, $message);
							break;
						case "4" : 
							$message = $this->message_strings->user_requested_name_change();
							$this->sendMessage_response($senderId, $message);
							$db->db_set_expecting_response_from_user("setname");
							break;
						case "5" :
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
							break;
						default :
							$username = $db->db_get_pogoName();
							$message = $this->message_strings->switchboard($username);
							$buttons = $this->message_strings->switchboard_buttons();
							$this->sendMessage_response($senderId, $message, $buttons);
							break;
					}
				}
				break;
			case "setname" :
				if( ! $this->isAValidPogoName($messageText) ) {
					$message = $this->message_strings->nameInvalid($newName);
					$this->sendMessage_response($senderId, $message);
					return;
				}
				$message = $this->message_strings->set_name_response($messageText);
				$buttons = $this->message_strings->set_name_response_buttons();
				$this->sendMessage_response($senderId, $message, $buttons);
				$db->db_set_expecting_response_from_user("setname_confirm");
				$db->db_set_pending_response_from_user($messageText);
				break;
			case "setname_confirm" :
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
						$db->db_set_expecting_response_from_user("");
						$this->handleMessage($senderId, "switchboard");
					}
					return;
				} else {
					$db->db_set_pogoName($newName);
					$db->db_set_expecting_response_from_user("");
					$message = $this->message_strings->nameSetComplete($newName);
					$this->sendMessage_response($senderId, $message);
					$this->handleMessage($senderId, "switchboard");
				}
				break;
			case "submitscore" :
				$messageText = str_replace(',', '', $messageText);
				if( trim($messageText) == "" ) {
					$message = $this->message_strings->submit_score();
					$this->sendMessage_response($senderId, $message);
					$db->db_set_expecting_response_from_user("submitscore");
				}
				$firsttext=explode(" ", trim($messageText))[0];
				if( ! is_numeric( $firsttext ) ) {
					$message = $this->message_strings->submit_score();
					$this->sendMessage_response($senderId, $message);
					$db->db_set_expecting_response_from_user("submitscore");
					return ;
				}
				try {
					$db->action_newScore($firsttext);
					$message = "Thanks your score has been recorded";
					$this->sendMessage_response($senderId, $message);
					$db->db_set_expecting_response_from_user("");
					$this->handleMessage($senderId, "switchboard");
				} catch (Exception $e) {
					$message = $this->message_strings->score_error($e->getMessage());
					$this->sendMessage_response($senderId, $message);
					$db->db_set_expecting_response_from_user("");
					$this->handleMessage($senderId, "switchboard");
					return ;
				}
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