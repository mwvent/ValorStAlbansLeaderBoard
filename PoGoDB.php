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
		$firstDate = $stimeObj->format("Ymd");
		$stimeObj = new DateTime("last day of this month", $tz);
		$lastDate = $stimeObj->format("Ymd");
		$results = $this->getDailyScoresForDateRange($firstDate,$lastDate);
		$resultTxt = "Top Scores from $firstDate to $lastDate are ".PHP_EOL;
		$iteration = 1;
		$yourrank = -1;
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
	
	// user has submitted a new score
	public function action_newScore($newScore) {
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
			if( $this->ui_minsSinceLastSubmission() < 1 ) {
				$errMsg = "Sorry I can only record a score once every two hours" .
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
				$errMsg = "Impossibly low score. " .
						  "The max based on your previous score of $lowestPoss " .
						  "is $highestPoss";
				throw new Exception( $errMsg );
			}
		}
		
		$newScore = (int)$newScore;
		$thisTimeStamp = $_SERVER['REQUEST_TIME'];
			
		$this->db_beginTransaction();
		
		$this->db_add_NewScore($thisTimeStamp, $newScore);
		
		// now insert row(s) into daily score table if this is not the first score
		$this->action_constructDailyValues($thisTimeStamp);
		
		$this->db_completeTransaction();
	}
	
	// user has opted to undo their last score
	public function action_undoPrevScore($newScore) {
		
	}
	
	// construct the entries in the secondary table for a primary entry
	private function action_constructDailyValues($newerScoreTimestamp) {
		// purge previous entries if exist
		$this->db_purge_dailyScoresForParentTimeStamp($newerScoreTimestamp);

		// get the most score for the timestamp given + the one previous
		// if not previous entry exists then this must be a first 
		// so just return
		// TODO this needs to use SQL that just pulls the values we want
		// or shit is going to get slow
		$recentScores = $this->db_get_TimeStampsAndScoresByNewestFirst(-1, -1);
		if ( ! isset ( $recentScores[ $newerScoreTimestamp ] ) ) {
			$err = "Record for score with timestamp " . $newerScoreTimestamp . "not found!";
			throw new Exception($err);
		}
		error_log(json_encode($recentScores),3,"/tmp/shiterr");
		$scoreArrayPosition = array_search($newerScoreTimestamp, array_keys($recentScores));
		$previousScoreArrayPosition = $scoreArrayPosition + 1;
		if( ! isset ( array_keys($recentScores)[$previousScoreArrayPosition] ) ) {
			return;
		}
		$priorScoreTimestamp = array_keys($recentScores)[$previousScoreArrayPosition];
		$priorScore = $recentScores[ $priorScoreTimestamp ];
		$newerScore = $recentScores[ $newerScoreTimestamp ];
		
		// init datetime object for the two timestamps
		$newerTime = new DateTime('@' . $newerScoreTimestamp);
		$newerTime->setTimezone(new DateTimeZone('Europe/London'));
		$priorTime = new DateTime('@' . $priorScoreTimestamp);
		$priorTime->setTimezone(new DateTimeZone('Europe/London'));
		
		// what is the per day average ?
		// dont forget the out by one correction 0 days diff is still 1 days
		$interval = $priorTime->diff($newerTime);
		$averagePerDayScore = ( $newerScore - $priorScore ) / ( $interval->days + 1 );
		
		// iterate over each day and add record to database
		// not forgetting the out-by one on the loop ( e.g. same day entry would
		// start at 0 diff and still need recording ! )
		$dayIterator = $priorTime;
		while( $dayIterator->format('Ymd') <= $newerTime->format('Ymd') ) {
			$localisedDayValue = $dayIterator->format('Ymd');
			$this->db_add_dailyScore($newerScoreTimestamp, $localisedDayValue, $averagePerDayScore);
			$dayIterator->add(new DateInterval('P1D'));
		}
	}
	
	// Actual DB Access functions
	abstract protected function db_connect();
	abstract protected function db_beginTransaction();
	abstract protected function db_completeTransaction();
	abstract protected function db_add_NewScore($timestamp, $newScore);
	abstract protected function db_add_dailyScore($parentTimestamp, $day, $score);
	//abstract protected function db_get_UserScoresSubmittedCount();
	abstract protected function db_get_TimeStampsAndScoresByNewestFirst($from = -1, $limit = -1);
	abstract protected function db_purge_dailyScoresForParentTimeStamp($timestamp);
	abstract public function db_get_pogoName() : string;
	abstract public function db_set_pogoName($newPogoName, $newRealName = "");
	abstract public function db_get_expecting_response_from_user();
	abstract public function db_set_expecting_response_from_user($response);
	abstract public function db_get_optout_messages() : bool;
	abstract public function db_set_optout_messages(bool $optOut);
}


/*
Scribblepad

User has submitted scores 12th=50, 14th=90, 19th am =100, 19th pm=110, 27th=200

We need to turn this into a table of daily results for the user

Firstly time periods with avg per day score
12th_am-14th_pm = 40pts / 3d = 13.3
14th_pm-19th_am = 10pts / 6d = 1.6
19th_am-19th_pm = 10pts / 1d = 10
19th_pm-27th_am = 90pts / 9d = 10

Then full per day table
12th = 13.3
13th = 13.3
14th = 13.3 + 1.6
15th = 1.6
16th = 1.6
17th = 1.6
18th = 1.6
19th = 1.6 + 10 + 10
20th = 10
21st = 10
22nd = 10
23rd = 10
24th = 10
25th = 10
26th = 10
27th = 10

Santity check ( total points ) = 200-50 = 150
Santity check ( sum the above ) = 149.5


Two table Storage method
Primary scores table records raw entries
Secondary scores table is the per day score
	- Multiple values may exist for one user for one day
		these should be summed together
	- The timestamp on the primary records will be used to tag
		each secondary record created from it - allowing
		an erronous score to be undone


User submits first score
* insert into scores table
	entryuid = auto, time = timestamp, value = 50
Next
* insert into scores table
	entryuid = auto, time = timestamp, value = 90

* calc the values for the secondary table
days = days(new timestamp) - days(previous timestamp) + 1
avgperday = (newscore - oldscore) / days
loop starting day(previous timestamp) ending day(new timestamp)
	* insert into scores_daily table
		parenttimestamp = new timestamp, day = loop day value, score = avgperday 



*/
