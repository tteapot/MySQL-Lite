<?php

/**
 * MySQL Lite
 *
 * A very lightweight MySQL interface class for PHP
 *
 * @author     James Brumond
 * @version    0.1.1-b
 * @created    2 November 2010
 * @copyright  Copyright 2010 James Brumond
 * @license    Dual licensed under MIT and GPL
 * @link       http://www.github.com/kbjr/MySQL-Lite
 */

// The database handling class
class Database_connection {

	var $connection = null;
	var $error_stack = array();
	
	// Connects to the database
	function __construct($config) {
		if (is_array($config)) {
			$conf = $config;
		} elseif (is_string($config)) {
			$conf = $this->read_db_config($config);
		} else {
			trigger_error('Invalid value for Database_connection config');
		}
		$this->connection = mysql_connect($conf['hostname'], $conf['username'], $conf['password']);
		mysql_select_db($conf['database'], $this->connection);
	}
	
	// Disconnects from the database
	function __destruct() {
		mysql_close($this->connection);
	}
	
	// Reads the database config file
	function read_db_config($config_file) {
		require $config_file;
		return array_merge(array(
			'hostname' => null,
			'username' => null,
			'password' => null,
			'database' => null
		), @$db);
	}
	
	// Returns the last error that occured
	function last_error() {
		if (! count($this->error_stack)) return null;
		return $this->error_stack[count($this->error_stack) - 1];
	}
	
	// Returns the entire error stack
	function errors() {
		return $this->error_stack;
	}
	
	// Clears out all stored error messages, returning the
	// state of the stack before clearing
	function clear_error_stack() {
		$errors = $this->error_stack;
		$this->error_stack = array();
		return $errors;
	}
	
	// Querys the database
	function query($query, &$err = null) {
		$result = mysql_query($query, $this->connection);
		$err = mysql_error($this->connection);
		if (! empty($err)) {
			$this->error_stack[] = $err;
		}
		return $result;
	}
	
	// Source a SQL file
	function source($filepath, $stop_on_error = false) {
		// Make sure the file exists
		if (! is_file($filepath)) {
			$this->error_stack[] = 'Cannot find the source file "'.$filepath.'"';
			return false;
		}
		// Read the SQL file
		$sql = file_get_contents($filepath);
		// Normalize line endings
		$sql = str_replace("\r\n", "\n", $sql);
		// Remove blanks and comments
		$sql = explode("\n", $sql);
		$new = array();
		foreach ($sql as $line) {
			$line = trim($line);
			if (!(empty($line) || ($line[0] == '-' && $line[1] == '-'))) {
				$new[] = $line;
			}
		}
		$sql = implode("\n", $new);
		// Seperate the individual commands
		$sql = explode(';', $sql);
		// Run the commands
		$return_value = true;
		foreach ($sql as $command) {
			$this->query($command, $err);
			if (! empty($err)) {
				$return_value = false;
				if ($stop_on_error) break;
			}
		}
		// Return
		return $return_value;
	}
	
	// Run a select query, parsing the result into an array
	function select($query, &$err = null) {
		// Run the query
		$query = 'select '.$query;
		$result = $this->query($query, $err);
		// Make sure there wasn't an error before continuing
		if (! $err) {
			$result_array = array();
			// Parse each row into the array
			if (mysql_num_rows($result)) {
				while ($row = mysql_fetch_assoc($result)) {
					$result_array[] = $row;
				}
			}
			$result = $result_array;
		}
		return $result;
	}

}

// Shortcut to database functionalities, only avalible in PHP5+
if (! function_exists('DB')) {
	if (version_compare(PHP_VERSION, '5.0.0') >= 0) {
		function &DB($config_file = null) {
			static $DB;
			if (! isset($DB)) {
				if ($config_file) {
					$DB = new Database_connection($config_file);
				} else {
					trigger_error('Cannot start the DB() helper without a configuration file');
				}
			}
			return $DB;
		}
	} else {
		function DB() {
			trigger_error('Cannot use the DB shortcut function before PHP5');
		}
	}
}

/* End of file mysql-lite.php */
