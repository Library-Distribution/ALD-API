<?php
require_once(dirname(__FILE__) . '/modules/HttpException/HttpException.php');

class FilterHelper {
	private $filters = array();

	public function add($data) { #$name, $db_name = NULL, $method = 'GET', $op = '=', $default = NULL, $force = NULL) {
		$this->filters[] = $data; #array('name' => $name, 'db-name' => $db_name, $method => 'GET', 'operator' => $op, 'default' => $default, 'force-value' => $force);
	}

	public function evaluate($source, $db_connection, $prefix = ' WHERE ') {
		$db_cond = '';

		foreach ($this->filters AS $filter) {
			unset($value);
			$ignore = false;

			# Get the value for comparison
			if (isset($filter['value'])) { # a value has explicitly been specified
				$value = $filter['value'];
			} else if (isset($filter['name'])) { # a name to look for in the parameters (GET or POST) has been specified
				if (isset($source[$filter['name']])) {
					$value = $source[$filter['name']];
				} else if (isset($filter['default'])) { # it is not specified in parameters, but a default was provided
					$value = $filter['default'];
				}
			} else { # neither name nor value specified => error
				throw new HttpException(500, NULL, 'Must specify "name" or "value" for filter');
			}

			if (!isset($filter['name']) && !isset($filter['db-name'])) {
				throw new HttpException(500, NULL, 'Must specify "name" or "db-name" for filter');
			}
			$key = isset($filter['db-name']) ? $filter['db-name'] : '`' . $filter['name'] . '`'; # the name is also used as column name if no other is specified

			$null_check = isset($filter['null']) ? $filter['null'] : NULL;

			if (!isset($value)) {
				continue;
			}

			$value_type = $null_check !== NULL ? 'switch' : (isset($filter['type']) ? $filter['type'] : 'string');
			switch ($value_type) {
				case 'string':
					$value = '"' . mysql_real_escape_string($value, $db_connection) . '"';
					break;
				case 'int':
					$value = (int)$value;
					break;
				case 'bool':
					$value = $value ? 'TRUE' : 'FALSE';
					break;
				case 'binary':
					$value = 'UNHEX("' . mysql_real_escape_string($value, $db_connection) . '")';
					break;
				case 'switch':
					if (in_array($value, array('yes', 'true', 1, '+1'))) {
						$value = 'TRUE';
					} else if (in_array($value, array('no', 'false', -1))) {
						$value = 'FALSE';
					} else if (in_array($value, array('both', '0'))) {
						$ignore = true;
					} else {
						throw new HttpException(400, NULL, 'Invalid value "' . $value . '" for switch specified!');
					}
					break;
				default:
					throw new HttpException(500, NULL, 'Unsupported filter type "' . $value_type . '"');
			}

			if ($ignore) {
				continue;
			}

			$db_cond .= ($db_cond ? ' AND ' : '') . $key;
			if ($null_check === NULL) {
				$operator = isset($filter['operator']) ? $filter['operator'] : '=';
				$db_cond .= ' ' . $operator . ' ' . $value;
			} else {
				$db_cond .= ' IS ' . ((!$null_check xor $value == 'TRUE') ? 'NOT ' : '') . 'NULL';
			}
		}

		return $db_cond ? $prefix . $db_cond : '';
	}

	public static function FromParams($filters, $source = NULL) {
		$source = $source !== NULL ? $source : $_GET;
		if (!is_array($source)) {
			throw new HttpException(500, NULL, 'Must provide valid array as filter source');
		}
		return array_intersect_key($source, array_flip($filters));
	}
}
?>