<?php
require_once dirname(__FILE__) . '/modules/HttpException/HttpException.php';

class FilterHelper {
	private $filters = array();

	/*
	 * Public class instance interface
	 */
	public function __construct($db_connection, $table) {
		$this->connection = $db_connection;
		$this->table = $table;
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
					throw new HttpException(500, 'Only filters of type "switch" can have conditions');
				}
				$conditions = $filter['conditions'];
			} else {
				$conditions = array($filter);
			}

			# if the filter is a switch and it is disabled: skip the entire thing
			if (self::extractType($filter, $null) == 'switch') {
				if ($this->extractValue($filter, $value)) {
					if (!$this->coerceValue($value, 'switch', $filter)) { # returns false for disabled filters
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

	public function evaluateJoins() {
		$table_list = array();

		foreach ($this->filters AS $filter) { # joins are only supported on filter-level
			if (isset($filter['db-table']) && $filter['db-table'] != $this->table) {
				if (!isset($filter['join-ref']) || !isset($filter['join-key'])) {
					throw new HttpException(500, 'Must specify JOIN key and reference for filters');
				}

				if (!isset($table_list[$filter['db-table']])) {
					$table_list[$filter['db-table']][$filter['join-ref']] = $filter['join-key'];
				} else {
					$table_list[$filter['db-table']] = array($filter['join-ref'] => $filter['join-key']);
				}
			}
		}

		if (count($table_list) > 0) {
			array_walk($table_list, array($this, 'extractJoinCondition'));
			return ' LEFT JOIN ' . implode(' AND ', array_keys($table_list)) . ' ON (' . implode(' AND ', $table_list) . ')';
		}
		return '';
	}

	/*
	 * Table join condition evaluation
	 */
	function extractJoinCondition(&$arr, $tbl) {
		$join = '';
		foreach ($arr AS $ref => $key) {
			$join .= ($join ? ' AND ' : '') . '`' . $this->table . '`.`' . $ref . '` = `' . $tbl . '`.`' . $key . '`';
		}
		$arr = $join;
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
				$data = array_merge(self::FromParams(array('db-name', 'name', 'type', 'default', 'db-table', 'join-key', 'join-on'), $filter), $condition); # collect data

				if (!isset($data['name']) && !isset($data['db-name'])) {
					throw new HttpException(500, 'Must specify "name" or "db-name" for filter');
				}
				$key = '`' . (isset($data['db-table']) ? $data['db-table'] : $this->table) . '`.`' . (isset($data['db-name']) ? $data['db-name'] : $data['name']) . '`'; # the name is also used as column name if no other is specified
				if (isset($data['db-function'])) {
					$key = $data['db-function'] . '(' . $key . ')';
				}

				# Get the value for comparison
				if (!$this->getValue($data, $value, $null_check)) {
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
		return $this->getValue($filter, $filter_value, $filter_null)
			&& $this->extractType($filter, $filter_null) == 'switch'
			&& !$filter_null
			&& $filter_value == 'FALSE';
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
			throw new HttpException(500, 'Unsupported filter logic "' . $logic . '"');
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
	private static $operator_map = array('>' => '<=', '<=' => '>', '<' => '>=', '>=' => '<', '=' => '!=', '!=' => '=', 'REGEXP' => 'NOT REGEXP', 'NOT REGEXP' => 'REGEXP');

	private static function reverseOperator($operator) {
		return self::$operator_map[$operator];
	}

	private static function validateOperator($operator) {
		if (!in_array($operator, array_keys(self::$operator_map))) {
			throw new HttpException(500, 'Unsupported filter operator "' . $operator . '"');
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
			throw new HttpException(500, 'Must specify "name" or "value" for filter');
		}

		return true;
	}

	private function getValue($filter, &$value, &$null_check) {
		$type = $this->extractType($filter, $null_check);
		return $this->extractValue($filter, $value) AND $this->coerceValue($value, $type, $filter);
	}

	private function coerceValue(&$value, $type, $filter) {
		switch ($type) {
			case 'string': $value = '"' . $this->connection->real_escape_string($value) . '"';
				break;
			case 'int': $value = (int)$value;
				break;
			case 'bool': $value = $value ? 'TRUE' : 'FALSE';
				break;
			case 'binary': $value = 'UNHEX("' . $this->connection->real_escape_string($value) . '")';
				break;
			case 'expr': break;
			case 'custom':
				if (!isset($filter['coerce']) || !is_callable($filter['coerce'])) {
					throw new HttpException(500, 'None or invalid callback for filter value coerce');
				}
				$value = call_user_func($filter['coerce'], $value, $this->connection);
				break;
			case 'switch':
				if (in_array($value, array('yes', 'true', 1, '+1'))) {
					$value = 'TRUE';
				} else if (in_array($value, array('no', 'false', -1))) {
					$value = 'FALSE';
				} else if (in_array($value, array('both', '0'))) {
					return false;
				} else {
					throw new HttpException(400, 'Invalid value "' . $value . '" for switch specified!');
				}
				break;
			default:
				throw new HttpException(500, 'Unsupported filter type "' . $type . '"');
		}
		return true;
	}

	/*
	 * Public function for filtering HTTP params for supported filters
	 */
	public static function FromParams($filters, $source = NULL) {
		$source = $source !== NULL ? $source : $_GET;
		if (!is_array($source)) {
			throw new HttpException(500, 'Must provide valid array as filter source');
		}
		return array_intersect_key($source, array_flip($filters));
	}
}
?>