<?php
// SELECT * FROM users; SELECT pogoname, datetime(recordedTime, 'unixepoch') as "time", recordedValue from scorelog_entries INNER JOIN users ON users.uuid = user_uuid ORDER BY recordedTime Asc;
require_once("PoGoDB.php");

class PoGoDB_SQLite3 extends PoGoDB {
	protected $db_connection;
	
	protected function db_purgeLatestScoreForUser() {
		$sql = "DELETE FROM scorelog_entries 
				WHERE user_uuid = :uuid
				ORDER BY recordedTime DESC
				LIMIT 1;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$result = $statement->execute();
	}
	
	protected function db_add_NewScore($thisTimeStamp, $newScore) {
		$sql = "INSERT OR IGNORE 
				    INTO scorelog_entries
				    (user_uuid, recordedTime, recordedValue) 
				    VALUES
				    (:uuid, :recordedTime, :recordedValue) ;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$statement->bindValue(':recordedTime', $thisTimeStamp);
		$statement->bindValue(':recordedValue', $newScore);
		$result = $statement->execute();
	}
	
	protected function db_get_TimeStampsAndScoresByNewestFirst($from = -1, $limit = -1) {
		$sql_from = ( $from !=-1 ) ? "AND recordedTime >= '" . $from . "'" : "";
		$sql_limit = ( $limit !=-1 ) ? "LIMIT $limit" : "";
		$sql = "SELECT * FROM scorelog_entries 
				WHERE user_uuid = :uuid
				$sql_from 
				ORDER BY recordedTime DESC
				$sql_limit ;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$result = $statement->execute();
		$returnArray = [];
		while ( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			$row_recordedTime =  $row["recordedTime"];
			$row_recordedValue = $row["recordedValue"];
			$returnArray[$row_recordedTime] = $row_recordedValue;
		}
		return $returnArray;
	}
	
	// return array of arrays with elements score, lastSubTS, pogoname, isCurrentUser
	protected function getTotalScoresForDateRange($startunixts, $endunixts) {
		$sql = "
			SELECT
				users.pogoname AS pogoname,
				sum(
					scorelog_entries.recordedValue - ( 
						SELECT
							max(scorelog_entries2.recordedValue) 
						FROM scorelog_entries AS scorelog_entries2
						WHERE scorelog_entries2.recordedTime < scorelog_entries.recordedTime
						  AND scorelog_entries2.user_uuid = scorelog_entries.user_uuid
					)
				) AS score,
				max(scorelog_entries.recordedTime) AS lastSubmittedUTS
			FROM 
				scorelog_entries
			INNER JOIN users 
				ON users.uuid = user_uuid
			WHERE
				scorelog_entries.recordedTime >= :startunixts 
				AND scorelog_entries.recordedTime <= :endunixts 
			GROUP BY
				pogoname
			ORDER BY score DESC; ";
		$currentUserName = $this->user_loggedIn() ? $this->db_get_pogoName() : "";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':startunixts', $startunixts);
		$statement->bindValue(':endunixts', $endunixts);
		$result = $statement->execute();
		$returnArray = [];
		$iteration = 0;
		while ( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			$iteration++;
			$isCurrentUser = ($currentUserName == $row["pogoname"]);
			$returnArray[$iteration] = [
				"score" => $row["score"],
				"lastSubTS" => $row["lastSubmittedUTS"],
				"pogoname" => $row["pogoname"],
				"isCurrentUser" => ($currentUserName == $row["pogoname"])
			];
		}
		return $returnArray;
			
	}
	
	protected function db_connect() {
		$this->db_connection = new SQLite3('leaderboard.db');
		// inital db setups
		$dbSetupQueries = [];
		// users table
		$dbSetupQueries[] = "
			CREATE TABLE IF NOT EXISTS users
				(	uuid text PRIMARY KEY,
					realname text,
					pogoname text,
					expecting_response text,
					pending_response_data text,
					opt_out_messages text
				);
		";
		// score log primary
		$dbSetupQueries[] = "
			CREATE TABLE IF NOT EXISTS scorelog_entries
				(	user_uuid text,
					recordedTime TIMESTAMP,
					recordedValue INTEGER
				);
		";
		
		// run setups
		foreach( $dbSetupQueries as $sql ) {
			$this->db_connection->exec( $sql );
		}
		
		// add user if not exists
		if( ! is_null( $this->current_user_facebook_uuid ) ) {
			$sql = "INSERT OR IGNORE 
				    INTO users(uuid) 
				    VALUES(:uuid) ;";
			$statement = $this->db_connection->prepare($sql);
			$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
			$result = $statement->execute();
		}
	}	
	
	protected function db_beginTransaction() {
		$this->db_connection->exec('BEGIN;');
		return true;
	}
	
	protected function db_completeTransaction() {
		$this->db_connection->exec('COMMIT;');
		return true;
	}
	
	
	private $cached_db_get_pogoName = null;
	public function db_get_pogoName() : string {
		if( ! is_null( $this->cached_db_get_pogoName ) ) {
			return $this->cached_db_get_pogoName;
		}
		$this->expect_loggedIn();
		$sql = "SELECT pogoname FROM users WHERE uuid=:uuid";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$result = $statement->execute();
		if ( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			$this->cached_db_get_pogoName = $row["pogoname"];
			if( is_null( $this->cached_db_get_pogoName ) ) {
				return "";
			}
			return $this->cached_db_get_pogoName;
		} else {
			// not found create user
			$this->db_set_pogoName("", "");
			return "";
		}
	}
	
	public function db_set_pogoName($newPogoName, $newRealName = "") {
		$this->cached_db_get_pogoName = $newPogoName;
		$sql = "UPDATE users SET 
				pogoname = :pogoname,
				realname = :realname
				WHERE
				uuid = :uuid ;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$statement->bindValue(':pogoname', $newPogoName);
		$statement->bindValue(':realname', $newRealName);
		$result = $statement->execute();
	}
	
	public function db_get_expecting_response_from_user() {
		$sql = "SELECT expecting_response FROM users WHERE uuid=:uuid";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$result = $statement->execute();
		if ( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			return $row["expecting_response"];
		} else {
			// not found
			return "";
		}
	}
	
	public function db_set_expecting_response_from_user($response) {
		$sql = "UPDATE users SET expecting_response = :response
				WHERE uuid = :uuid ;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$statement->bindValue(':response', $response);
		$result = $statement->execute();
	}
	
	public function db_get_pending_response_from_user() {
		$sql = "SELECT pending_response_data FROM users WHERE uuid=:uuid";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$result = $statement->execute();
		if ( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			return $row["pending_response_data"];
		} else {
			// not found
			return "";
		}
	}
	
	public function db_set_pending_response_from_user($responsedata) {
		$sql = "UPDATE users SET pending_response_data = :responsedata
				WHERE uuid = :uuid ;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$statement->bindValue(':responsedata', $responsedata);
		$result = $statement->execute();
	}
	
	public function db_get_optout_messages() : bool {
		$sql = "SELECT opt_out_messages FROM users WHERE uuid=:uuid";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$result = $statement->execute();
		if ( $row = $result->fetchArray(SQLITE3_ASSOC) ) {
			if ( $row["opt_out_messages"] == "" ) {
				return false;
			} elseif( is_null( $row["opt_out_messages"] ) ) {
				return false;
			} else {
				return $row["opt_out_messages"] == "Yes" ? true : false;
			}
		}
	}
	
	public function db_set_optout_messages(bool $optOut) {
		$optOutDBValue = $optOut ? "Yes" : "No";
		$sql = "UPDATE users SET opt_out_messages = :optOutDBValue
				WHERE uuid = :uuid ;";
		$statement = $this->db_connection->prepare($sql);
		$statement->bindValue(':uuid', $this->current_user_facebook_uuid);
		$statement->bindValue(':optOutDBValue', $optOutDBValue);
		$result = $statement->execute();
	}
}
