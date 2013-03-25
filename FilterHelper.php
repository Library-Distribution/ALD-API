<?php
require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

class FilterHelper {
	private $filters = array();

	/*
	 * Public class instance interface
	 */
	public function __construct($db_connection) {
		$this->connection = $db_connection;
	}

	public function add($data) { #$name, $db_name = NULL, $method = 'GET', $op = '=', $default = NULL, $force = NULL) {
		$this->filters[] = $data; #array('name' => $name, 'db-name' => $db_name, $method => 'GET', 'operator' => $op, 'default' => $default, 'force-value' => $force);
	}

	public function evaluate($source, $prefix = ' WHERE ') {
		$db_cond = '';
		$this->source = $source;

		foreach ($this->filters AS $filter) {

			if (isset($filter['conditions'])) {
				if ($filter['type'] != 'switch') {
					throw new HttpException(500, NULL, 'Only filters of type "switch" can have conditions');
				}
				$conditions = $filter['conditions'];
			} else {
				$conditions = array($filter);
			}

			# if the filter is a switch and it is disabled: skip the entire thing
			if (self::extractType($filter, $null) == 'switch') {
				if ($this->extractValue($filter, $value)) {
					if (!$this->coerceValue($value, 'switch')) { # returns false for disabled filters
						continue;
					}
				}
			}

			$cond = $this->evaluateConditions($conditions, $filter);
			if ($cond) {
				$db_cond .= ($db_cond ? ' AND ' : '') . $cond;
			}
		}
		return $db_cond ? $prefix . $db_cond : '';
	}

	/*
	 * Main conversion method
	 */
	private function evaluateConditions($conditions, $filter) {
		$logic = self::extractLogic($conditions);

		$db_cond = '';
		foreach ($conditions AS $condition) {
			if (array_key_exists(0, $condition)) { # another array of sub-conditions
				# obey the value of the switch containing this condition set by reversing it if the switch was turned off
				if ($this->should_reverse_logic($filter, $filter_value)) {
					$operator = self::reverseOperator($operator);
					$logic = self::reverseLogic($logic);
				}

				$cond = $this->evaluateConditions($condition, $filter);
				if ($cond) {
					$db_cond .= ($db_cond ? ' ' . $logic . ' ' : '') . $cond;
				}

			} else {
				$data = array_merge(self::FromParams(array('db-name', 'name', 'type', 'default'), $filter), $condition); # collect data

				if (!isset($data['name']) && !isset($data['db-name'])) {
					throw new HttpException(500, NULL, 'Must specify "name" or "db-name" for filter');
				}
				$key = '`' . (isset($data['db-name']) ? $data['db-name'] : $data['name']) . '`'; # the name is also used as column name if no other is specified

				# Get the value for comparison
				if (!$this->extractValue($data, $value)) {
					continue;
				}

				$value_type = self::extractType($data, $null_check);
				if (!$this->coerceValue($value, $value_type)) {
					continue;
				}

				if ($null_check === NULL) {
					$operator = isset($data['operator']) ? $data['operator'] : '=';
					self::validateOperator($operator);

					# obey the value of the switch containing this condition by reversing it if the switch was turned off
					if ($this->should_reverse_logic($filter, $filter_value) && $filter_value != $value) {
						$operator = self::reverseOperator($operator);
						$logic = self::reverseLogic($logic);
					}

					$db_cond .= ($db_cond ? ' ' . $logic . ' ' : '') . $key . ' ' . $operator . ' ' . $value;
				} else {
					$db_cond .= ($db_cond ? ' ' . $logic . ' ' : '') . $key . ' IS ' . (($null_check xor $value == 'TRUE') ? 'NOT ' : '') . 'NULL';
				}
			}
		}
		return $db_cond ? '(' . $db_cond . ')' : $db_cond;
	}

	private function should_reverse_logic($filter, &$filter_value) {
		if ($this->extractValue($filter, $filter_value)) {
			$filter_value_type = self::extractType($filter, $filter_null);
			if ($this->coerceValue($filter_value, $filter_value_type)) {
				if ($filter_value_type == 'switch' && $filter_value == 'FALSE') {
					return true;
				}
			}
		}
		return false;
	}

	/*
	 * Logic handling code
	 */
	private static $logic_map = array('AND' => 'OR', 'OR' => 'AND');

	private static function extractLogic(&$conditions) {
		$logic = isset($conditions['logic']) ? strtoupper($conditions['logic']) : 'AND'; # default is 'AND'
		self::validateLogic($logic);
		unset($conditions['logic']); # avoid iterating through it below

		return $logic;
	}

	private static function validateLogic($logic) {
		if (!in_array($logic, array_keys(self::$logic_map))) {
			throw new HttpException(500, NULL, 'Unsupported filter logic "' . $logic . '"');
		}
	}

	private static function reverseLogic($logic) {
		return self::$logic_map[$logic];
	}

	/*
	 * Type handling code
	 */
	private static function extractType($filter, &$null_check) {
		$null_check = isset($filter['null']) ? $filter['null'] : NULL;
		return $null_check !== NULL ? 'switch' : (isset($filter['type']) ? $filter['type'] : 'string');
	}

	/*
	 * Operator handling code
	 */
	private static $operator_map = array('>' => '<=', '<=' => '>', '<' => '>=', '>=' => '<', '=' => '!=', '!=' => '=');

	private static function reverseOperator($operator) {
		return self::$operator_map[$operator];
	}

	private static function validateOperator($operator) {
		if (!in_array($operator, array_keys(self::$operator_map))) {
			throw new HttpException(500, NULL, 'Unsupported filter operator "' . $operator . '"');
		}
	}

	/*
	 * Value handling code
	 */
	private function extractValue($filter, &$value) {
		if (isset($filter['value'])) { # a value has explicitly been specified
			$value = $filter['value'];
		} else if (isset($filter['name'])) { # a name to look for in the parameters (GET or POST) has been specified
			if (isset($this->source[$filter['name']])) {
				$value = $this->source[$filter['name']];
			} else if (isset($filter['default'])) { # it is not specified in parameters, but a default was provided
				$value = $filter['default'];
			} else {
				return false;
			}
		} else { # neither name nor value specified => error
			throw new HttpException(500, NULL, 'Must specify "name" or "value" for filter');
		}

		return true;
	}

	private function coerceValue(&$value, $type) {
		$success = true;

		switch ($type) {
			case 'string': $value = '"' . mysql_real_escape_string($value, $this->connection) . '"';
				break;
			case 'int': $value = (int)$value;
				break;
			case 'bool': $value = $value ? 'TRUE' : 'FALSE';
				break;
			case 'binary': $value = 'UNHEX("' . mysql_real_escape_string($value, $this->connection) . '")';
				break;
			case 'expr': break;
			case 'switch':
				if (in_array($value, array('yes', 'true', 1, '+1'))) {
					$value = 'TRUE';
				} else if (in_array($value, array('no', 'false', -1))) {
					$value = 'FALSE';
				} else if (in_array($value, array('both', '0'))) {
					$success = false;
				} else {
					throw new HttpException(400, NULL, 'Invalid value "' . $value . '" for switch specified!');
				}
				break;
			default:
				throw new HttpException(500, NULL, 'Unsupported filter type "' . $type . '"');
		}

		return $success;
	}

	/*
	 * Public function for filtering HTTP params for supported filters
	 */
	public static function FromParams($filters, $source = NULL) {
		$source = $source !== NULL ? $source : $_GET;
		if (!is_array($source)) {
			throw new HttpException(500, NULL, 'Must provide valid array as filter source');
		}
		return array_intersect_key($source, array_flip($filters));
	}
}
?>