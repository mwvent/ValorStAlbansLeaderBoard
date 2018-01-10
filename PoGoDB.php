<?php
abstract class PoGoDB {
	protected $db_connection;
	protected $current_user_facebook_uuid;
	protected $current_user_realName;
	protected $current_user_pogoName;
	protected $prevScore_is_read = false;
	protected $prevScore_timestamp;
	protected $prevScore_value;
	
	public function __construct($facebook_uuid = null) {
		
		// keep track of who is accessing
		$this->current_user_facebook_uuid = $facebook_uuid;
		
		$this->db_connect();
	}
	 
	// header function to be called for each function that expects a
	// logged in user
	public function expect_loggedIn() {
		if ( is_null ( $this->current_user_facebook_uuid ) ) {
			throw new Exception("Expected logged in user!");
		}
	}
	
	// returns an array containing info
	// about the last two scores the user submitted
	// if a newScore parameter is supplied that will be treated as
	// a hypothetical new score and priorScore will be the latest from the database
	// newestScore - array or null if no scores
	// |-timestamp
	// | humantime
	// | scorevalue
	// | ageDays
	// | ageMins
	// priorScore - array or null if no prior
	// |-timestamp
	// | humantime
	// | scorevalue
	// | ageDays
	// | ageMins
	// comparision - array or no exist if no prior score
	// |- performance - a number from 0-100 representing how much of the maximum was acheived
	//    diffInMins
	//    maxNewPoints
	//    acheivedPoints
	public function ui_lastscoreinfo($newScore = null) {
		$this->expect_loggedIn();
		$returnArray = [];
		$lastScores = $this->db_get_TimeStampsAndScoresByNewestFirst(0,2);
		
		$curentTime = $newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
		$curentTime->setTimezone(new DateTimeZone('Europe/London'));
		
		// if hypotetical new score supplied - add it to the array and sort by
		// timestamp so the newest score is first
		if( ! is_null( $newScore ) ) {
			$lastScores[ $_SERVER['REQUEST_TIME'] ] = $newScore;
		}
		
		// if user has no scores
		if( empty($lastScores) ) {
			return [ "newestScore" => null, "priorScore" => null ];
		}
		
		// populate newestscore
		$newdateTimeObj = new DateTime('@' . array_keys($lastScores)[0]);
		$newdateTimeObj->setTimezone(new DateTimeZone('Europe/London'));
		$interval = $newdateTimeObj->diff($curentTime);
		$minsDiff = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
		$hoursDiff_float = $minsDiff / 60;
		$returnArray["newestScore"] = [
			"timestamp" => array_keys($lastScores)[0],
			"scorevalue" => $lastScores[array_keys($lastScores)[0]],
			"humantime" => $newdateTimeObj->format('Y-m-d H:i:s'),
			"ageDays" => $interval->format('%d'),
			"ageMins" => $minsDiff,
			"ageHours_float" => $hoursDiff_float,
			"maxPossNewPoints" => $hoursDiff_float * 20 // 20 mons * 1 minute
		];
		
		// if no prior score
		if( ! isset( array_keys($lastScores)[1] ) ) {
			$returnArray["priorScore"] = null;
			return $returnArray;
		}
		
		// populate prior score
		$olddateTimeObj = new DateTime('@' . array_keys($lastScores)[1]);
		$olddateTimeObj->setTimezone(new DateTimeZone('Europe/London'));
		$interval = $olddateTimeObj->diff($curentTime);
		$minsDiff = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
		$returnArray["priorScore"] = [
			"timestamp" => array_keys($lastScores)[0],
			"scorevalue" => $lastScores[array_keys($lastScores)[1]],
			"humantime" => $olddateTimeObj->format('Y-m-d H:i:s'),
			"ageDays" => $interval->format('%d'),
			"ageMins" => $minsDiff,
			"maxPossNewPoints" => round(($minsDiff * 20) / 60) // 20 mons * 1 minute
		];
		
		// populate comparison
		$interval = $olddateTimeObj->diff($newdateTimeObj);
		$minsDiff = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
		$maxNewPoints = round(($minsDiff * 20) / 60); // 20 mons * 1 minute
		$acheivedPoints =
			$returnArray["newestScore"]["scorevalue"] - $returnArray["priorScore"]["scorevalue"];
		$performace = round( ($acheivedPoints / $maxNewPoints) * 100 );
		
		$returnArray["comparision"] = [
			"performance" => $performace,
			"diffInMins" => $minsDiff,
			"maxNewPoints" => $maxNewPoints,
			"acheivedPoints" => $acheivedPoints
		];
		
		return $returnArray;
	}
	
	public function ui_thismonthscores($rankOnly = false) {
		$tz=new DateTimeZone('Europe/London');
		$curentTime = $newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
		$curentTime->setTimezone(new DateTimeZone('Europe/London'));
		$stimeObj = new DateTime("first day of this month", $tz);
		$firstDateTS = $stimeObj->getTimestamp();
		$stimeObj = new DateTime("last day of this month", $tz);
		$lastDateTS = $stimeObj->getTimestamp();
		$results = $this->getTotalScoresForDateRange($firstDateTS,$lastDateTS);
		$resultTxt = "Top Scores for this month are ".PHP_EOL;
		$iteration = 1;
		$yourrank = -1;
		if( empty( $results ) ) {
			return "Sorry there are no scores for this month yet";
		}
		foreach( $results as $user => $scoreinfo ) {
			$score = $scoreinfo[ "score" ];
			$lastSubTS = $scoreinfo[ "lastSubTS" ];
			$subTime = new DateTime('@' . $lastSubTS);
			$subTime->setTimezone(new DateTimeZone('Europe/London'));
			$subInterval = $subTime->diff($curentTime);
			$subInterval_human = "";
			// format human readable age
			if( (int)$subInterval->format('%d') > 0 ) {
				$subInterval_human = $subInterval->format('%dd ago');
			} elseif( (int)$subInterval->format('%h') > 0 ) {
				$subInterval_human = $subInterval->format('%hh ago');
			} elseif( (int)$subInterval->format('%i') > 10 ) {
				$subInterval_human = $subInterval->format('%im ago');
			} else {
				$subInterval_human = "just now";
			}
			if( is_null($score) ) continue;
			if($user == $this->db_get_pogoName()) {
				$yourrank = $iteration;
			}
			if( $iteration > 10 ) continue;
			$resultTxt .= $iteration . ") " . 
						  $user . " : " . round($score,2) . " hours " .
						  "  (" . $subInterval_human . ")" . PHP_EOL;
			$iteration += 1;
		}
		
		if( $rankOnly ) {
			$resultTxt = "";
		}
		
		if( $yourrank > -1 ) {
			$resultTxt .= "Your rank is $yourrank";
		} else {
			$resultTxt .= "Your score will not be calulated until you have made two submissions";
		}
		return $resultTxt;
	}
	
	public function ui_lastmonthscores() {
		$tz=new DateTimeZone('Europe/London');
		$curentTime = $newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
		$curentTime->setTimezone(new DateTimeZone('Europe/London'));
		$stimeObj = new DateTime("first day of last month", $tz);
		$firstDateTS = $stimeObj->getTimestamp();
		$stimeObj = new DateTime("last day of last month", $tz);
		$lastDateTS = $stimeObj->getTimestamp();
		$results = $this->getTotalScoresForDateRange($firstDateTS,$lastDateTS);
		$resultTxt = "Top Scores from last month are ".PHP_EOL;
		$iteration = 1;
		$yourrank = -1;
		if( empty( $results ) ) {
			return "Sorry there are no scores for last month";
		}
		foreach( $results as $user => $score ) {
			$score = $scoreinfo[ "score" ];
			$lastSubTS = $scoreinfo[ "lastSubTS" ];
			if( is_null($score) ) continue;
			if($user == $this->db_get_pogoName()) {
				$yourrank = $iteration;
			}
			if( $iteration > 10 ) continue;
			$resultTxt .= $iteration . ") " . 
						  $user . " : " . round($score,2) . " hours" . PHP_EOL;
			$iteration += 1;
		}
		if( $yourrank > -1 ) {
			$resultTxt .= "Your rank is $yourrank";
		}
		return $resultTxt;
	}
	
	// throws exeption or returns true
	public function action_validate_potentialNewScore($newScore) {
		$newScore = str_replace(',', '', $newScore);
		$this->expect_loggedIn();
		// re-validate what the UI should do, the UI is in userspace
		// after all :-) 
		if ( ! is_numeric ( $newScore ) ) {
			throw new Exception("Score is not a number");
		}
		if ( (int)$newScore <>  $newScore ) {
			throw new Exception("Score is not a whole number");
		}
		if ( (int)$newScore < 0 ) {
			throw new Exception("Score cannot be negative");
		}
		
		$lastScores = $this->ui_lastscoreinfo();
		$userHasScoreRecorded = ! is_null( $lastScores["newestScore"] );
		
		// min/max check only if not a first score record
		if( $userHasScoreRecorded ) {
			// check a sensible amount of time has elapsed since
			// previous submission
			$minsSinceLast = $lastScores["newestScore"]["ageMins"];
			
			if( $minsSinceLast < 60 ) {
				$errMsg = "Sorry I can only record a score once every hour" .
						  " it would appear you submitted one $minsSinceLast mins ago";
				throw new Exception( $errMsg );
			}
			
			// check newer score is not lower than older one
			$lastScore = $lastScores["newestScore"]["scorevalue"];
			if( $lastScore > $newScore ) {
				$errMsg = "Impossibly low score. Your last score was $lastScore" .
				          ". If your previous score was wrong please use option 6 " .
				          "to undo it";
				throw new Exception( $errMsg );
			}
			
			// check newer score is not impossibly higher than older one
			// we will set a sensible value of 28 days increase per submission
			// for now to allow long standing gym defenders returning
			$newPoints = $newScore - $lastScore;
			// $highestPoss = $lastScores["newestScore"]["maxPossNewPoints"];
			$highestPoss = 28 * 24;
			if( $newPoints > ($highestPoss * 2) ) {
				$errMsg = "Score seems too high " .
						  "based on your previous score of $lastScore.".
						  "please check again and contact Matthew Watts if it is correct";
				throw new Exception( $errMsg );
			}
		}
	}
	
	// user has submitted a new score
	public function action_newScore($newScore) {
		$this->expect_loggedIn();
		$newScore = str_replace(',', '', $newScore);
		$this->action_validate_potentialNewScore($newScore);
		$newScore = (int)$newScore;
		$thisTimeStamp = $_SERVER['REQUEST_TIME'];
		
		$this->db_beginTransaction();
		$this->db_add_NewScore($thisTimeStamp, $newScore);
		$this->db_completeTransaction();
	}
	
	// user has opted to undo their last score
	public function action_undoPrevScore() {
		$this->expect_loggedIn();
		$this->db_purgeLatestScoreForUser();
	}
	
	// Actual DB Access functions
	abstract protected function db_connect();
	abstract protected function db_beginTransaction();
	abstract protected function db_completeTransaction();
	abstract protected function db_add_NewScore($timestamp, $newScore);
	abstract protected function db_get_TimeStampsAndScoresByNewestFirst($from = -1, $limit = -1);
	abstract protected function getTotalScoresForDateRange($startunixts, $endunixts);
	abstract public function db_get_pogoName() : string;
	abstract public function db_set_pogoName($newPogoName, $newRealName = "");
	abstract public function db_get_expecting_response_from_user();
	abstract public function db_set_expecting_response_from_user($response);
	abstract public function db_get_optout_messages() : bool;
	abstract public function db_set_optout_messages(bool $optOut);
	abstract protected function db_purgeLatestScoreForUser();
}

