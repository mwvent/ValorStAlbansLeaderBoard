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
	
	// a different score taking UI will be presented for first score
	// taking, this function can determine if this will be the users first
	// submission
	public function ui_userHasScoreRecorded() : bool {
		$this->expect_loggedIn();
		return ! empty( $this-> db_get_TimeStampsAndScoresByNewestFirst(0,1) );
	}
	
	public function ui_minsSinceLastSubmission() {
		$this->expect_loggedIn();
		$lastScore = $this->db_get_TimeStampsAndScoresByNewestFirst(0,1);
		$priorScoreTimestamp = array_keys($lastScore)[0];
		$priorScoreValue = $lastScore[ $priorScoreTimestamp ];
		$newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
		$newerTime->setTimezone(new DateTimeZone('Europe/London'));
		$priorTime = new DateTime('@' . $priorScoreTimestamp);
		$priorTime->setTimezone(new DateTimeZone('Europe/London'));
		$interval = $priorTime->diff($newerTime);
		$minsDiff = $interval->days * 24 * 60;
		$minsDiff += $interval->h * 60;
		$minsDiff += $interval->i;
		return $minsDiff;
	}
	
	public function ui_previousScore() {
		$this->expect_loggedIn();
		$lastScore = $this->db_get_TimeStampsAndScoresByNewestFirst(0,1);
		if( ! isset( array_keys($lastScore)[0] ) ) {
			return -1;
		}
		$priorScoreTimestamp = array_keys($lastScore)[0];
		$priorScoreValue = $lastScore[ $priorScoreTimestamp ];
		return $priorScoreValue;
	}
	
	// the score taking UI will need a maximum possible score
	// to validate user input
	public function ui_maxScorePossibleSinceLastSubmission() {
		$this->expect_loggedIn();
		$lastScore = $this->db_get_TimeStampsAndScoresByNewestFirst(0,1);
		$priorScoreTimestamp = array_keys($lastScore)[0];
		$priorScoreValue = $lastScore[ $priorScoreTimestamp ];
		$newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
		$newerTime->setTimezone(new DateTimeZone('Europe/London'));
		$priorTime = new DateTime('@' . $priorScoreTimestamp);
		$priorTime->setTimezone(new DateTimeZone('Europe/London'));
		$interval = $priorTime->diff($newerTime);
		$minsDiff = $interval->days * 24 * 60;
		$minsDiff += $interval->h * 60;
		$minsDiff += $interval->i;
		// you can have 20 mons in a gym so max of 20 mins per minute
		$maxNewPoints = round(($minsDiff * 20) / 60);
		// after writing the last line I have discovered that
		// the medal only updates when your defender leaves the gym
		// so that logic will not work - you could gain many hours
		// more in an hour - just try an return a semi sensible max value
		// for now
		$maxNewPoints = $maxNewPoints * 10;
		return $maxNewPoints + $priorScoreValue;
	}
	
	// the score taking UI will need a minimum possible score
	// to validate user input
	public function ui_minScorePossibleSinceLastSubmission() {
		$this->expect_loggedIn();
		$lastScore = $this->db_get_TimeStampsAndScoresByNewestFirst(0,1);
		$priorScoreTimestamp = array_keys($lastScore)[0];
		$priorScoreValue = $lastScore[ $priorScoreTimestamp ];
		return $priorScoreValue;
	}
	
	// returns an array with various indicators to how well
	// the performance was on the last submission
	public function ui_lastscoreinfo() {
		$this->expect_loggedIn();
		$lastScore = $this->db_get_TimeStampsAndScoresByNewestFirst(0,1);
		$priorScoreTimestamp = array_keys($lastScore)[0];
		$priorScoreValue = $lastScore[ $priorScoreTimestamp ];
		$newerTime = new DateTime('@' .  $_SERVER['REQUEST_TIME']);
		$newerTime->setTimezone(new DateTimeZone('Europe/London'));
		$priorTime = new DateTime('@' . $priorScoreTimestamp);
		$priorTime->setTimezone(new DateTimeZone('Europe/London'));
		$interval = $priorTime->diff($newerTime);
		$minsDiff = $interval->days * 24 * 60;
		$minsDiff += $interval->h * 60;
		$minsDiff += $interval->i;
		// you can have 20 mons in a gym so max of 20 mins per minute
		$maxNewPoints = round(($minsDiff * 20) / 60);
		return $maxNewPoints + $priorScoreValue;
	}
	
	public function ui_thismonthscores() {
		$tz=new DateTimeZone('Europe/London');
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
		foreach( $results as $user => $score ) {
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
		} else {
			$resultTxt .= "Your score will not be calulated until you have made two submissions";
		}
		return $resultTxt;
	}
	
	public function ui_lastmonthscores() {
		$tz=new DateTimeZone('Europe/London');
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
		
		
		// min/max check only if not a first score record
		if( $this->ui_userHasScoreRecorded() ) {
			// check a sensible amount of time has elapsed since
			// previous submission
			$minsSinceLast = $this->ui_minsSinceLastSubmission();
			if( $this->ui_minsSinceLastSubmission() < 60 ) {
				$errMsg = "Sorry I can only record a score once every hour" .
						  " it would appear you submitted one $minsSinceLast mins ago";
				throw new Exception( $errMsg );
			}
			$lowestPoss = $this->ui_minScorePossibleSinceLastSubmission();
			$highestPoss = $this->ui_maxScorePossibleSinceLastSubmission();
			if( $newScore < $lowestPoss ) {
				$errMsg = "Impossibly low score. Your last score was $lowestPoss";
				throw new Exception( $errMsg );
			}
			if( $newScore > $highestPoss ) {
				$errMsg = "Score seems too high " .
						  "based on your previous score of $lowestPoss.".
						  "please check again and contact Matthew Watts if it is correct";
				throw new Exception( $errMsg );
			}
		}
		
		$newScore = (int)$newScore;
		$thisTimeStamp = $_SERVER['REQUEST_TIME'];
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
	public function action_undoPrevScore($newScore) {
		
	}
	
	// Actual DB Access functions
	abstract protected function db_connect();
	abstract protected function db_beginTransaction();
	abstract protected function db_completeTransaction();
	abstract protected function db_add_NewScore($timestamp, $newScore);
	abstract protected function db_get_TimeStampsAndScoresByNewestFirst($from = -1, $limit = -1);
	abstract public function db_get_pogoName() : string;
	abstract public function db_set_pogoName($newPogoName, $newRealName = "");
	abstract public function db_get_expecting_response_from_user();
	abstract public function db_set_expecting_response_from_user($response);
	abstract public function db_get_optout_messages() : bool;
	abstract public function db_set_optout_messages(bool $optOut);
}

