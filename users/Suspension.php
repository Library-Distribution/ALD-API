<?php
require_once dirname(__FILE__) . '/../db.php';
require_once dirname(__FILE__) . '/../User.php';
require_once dirname(__FILE__) . '/../SortHelper.php';
require_once dirname(__FILE__) . '/../FilterHelper.php';
require_once dirname(__FILE__) . '/../Assert.php';
require_once dirname(__FILE__) . '/../sql2array.php';
require_once dirname(__FILE__) . '/../modules/HttpException/HttpException.php';
require_once dirname(__FILE__) . '/../config/suspensions.php';

class Suspension {
	public static function create($user, $reason, $expires = NULL, $restricted = true) {
		return self::createForId(User::getID($user), $reason, $expires, $restricted);
	}

	public static function createForId($user, $reason, $expires = NULL, $restricted = true) {
		$db_connection = db_ensure_connection();

		$user = $db_connection->real_escape_string($user);
		if ($expires !== NULL) {
			$expires = $db_connection->real_escape_string($expires);
		}
		$restricted = $restricted ? '1' : '0';
		$reason = $db_connection->real_escape_string($reason);

		$db_query = 'INSERT INTO ' . DB_TABLE_SUSPENSIONS . ' (`user`, `expires`, `restricted`, `reason`) VALUES (UNHEX("' . $user . '"), ' . ($expires !== NULL ? '"' . $expires . '"' : 'NULL') . ', ' . $restricted . ', "' . $reason . '")';
		$db_connection->query($db_query);
		Assert::dbMinRows($db_connection, NULL, 500);

		return $db_connection->insert_id;
	}

	public static function clear() {
		$db_connection = db_ensure_connection();

		$cond = ' `expires` IS NOT NULL AND `expires` <= NOW()';
		if (CLEAR_SUSPENSIONS) {
			$db_query = 'DELETE FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE' . $cond;
		} else {
			$db_query = 'UPDATE ' . DB_TABLE_SUSPENSIONS . ' SET `active` = FALSE WHERE `active` AND' . $cond;
		}

		$db_connection->query($db_query);
	}

	public static function isSuspended($user) {
		return self::isSuspendedById(User::getID($user));
	}

	public static function isSuspendedById($id) {
		return count(self::getSuspensionsById($id)) != 0;
	}

	public static function getSuspensions($user, $filters = array(), $sort = array()) {
		return self::getSuspensionsById(User::getID($user), $filters, $sort);
	}

	public static function getSuspensionsById($id, $filters = array(), $sort = array()) {
		$db_connection = db_ensure_connection();

		if (!is_array($filters)) {
			throw new HttpException(500, 'Must pass a valid array as suspension filter!');
		}

		$filter = new FilterHelper($db_connection, DB_TABLE_SUSPENSIONS);

		$filter->add(array('db-name' => 'user', 'value' => $id, 'type' => 'binary'));

		$filter->add(array('name' => 'expires'));
		$filter->add(array('name' => 'expires-before', 'db-name' => 'expires', 'operator' => '<'));
		$filter->add(array('name' => 'expires-after', 'db-name' => 'expires', 'operator' => '>'));

		$filter->add(array('name' => 'created'));
		$filter->add(array('name' => 'created-before', 'db-name' => 'created', 'operator' => '<'));
		$filter->add(array('name' => 'created-after', 'db-name' => 'created', 'operator' => '>'));

		$filter->add(array('name' => 'infinite', 'db-name' => 'expires', 'null' => true));
		$filter->add(array('name' => 'restricted', 'type' => 'switch'));

		$filter->add(array('name' => 'active', 'type' => 'switch', 'default' => 'true',
			'conditions' => array( # an array of conditions to be satisified
				array('db-name' => 'active'), # the `active` field must be TRUE (see 'default' value of filter)
				array( # a set of sub-conditions, to be combined using 'OR'
					'logic' => 'OR',
					array('db-name' => 'expires', 'null' => true), # [if the filter is set,] 'expires' must either be NULL ...
					array('db-name' => 'expires', 'operator' => '>', 'value' => 'NOW()', 'type' => 'expr') # ... or it must be > NOW()
				)
			)
		));

		$db_cond = $filter->evaluate($filters);
		$sort = SortHelper::getOrderClause($sort, array('created' => '`created`', 'expires' => '`expires`'));

		$db_query = 'SELECT *, HEX(`user`) AS user FROM ' . DB_TABLE_SUSPENSIONS . $db_cond . $sort;
		$db_result = $db_connection->query($db_query);

		return sql2array($db_result, array('Suspension', '_create_inst_'));
	}

	public static function getSuspension($id) {
		$db_connection = db_ensure_connection();
		$id = (int)$db_connection->real_escape_string($id);

		$db_query = 'SELECT *, HEX(`user`) AS user FROM ' . DB_TABLE_SUSPENSIONS . ' WHERE `id` =' . $id;
		$db_result = $db_connection->query($db_query);
		Assert::dbMinRows($db_result);

		return self::_create_inst_($db_result->fetch_assoc());
	}

	public static function _create_inst_($arr) {
		return new self((int)$arr['id'], $arr['user'], $arr['created'], $arr['expires'], (bool)$arr['restricted'], $arr['reason'], $arr['active']);
	}

	####################################

	private function __construct($id, $user, $created, $expires, $restricted, $reason, $active) {
		$this->id = $id;
		$this->user = $user;
		$this->restricted = $restricted;
		$this->reason = $reason;

		$this->created = new DateTime($created);
		$this->expires = ($this->infinite = $expires === NULL) ? NULL : new DateTime($expires);

		$this->active = $active && ($this->infinite || (new DateTime('now')) > $this->expires);
	}

	public function delete() {
		$db_connection = db_ensure_connection();
		$id = $db_connection->real_escape_string($this->id);

		$db_query = 'UPDATE ' . DB_TABLE_SUSPENSIONS . ' SET `active` = FALSE WHERE `id` = "' . $id . '"';
		$db_connection->query($db_query);
	}

	public $id;
	public $user;
	public $created;
	public $expires;
	public $infinite;
	public $restricted;
	public $reason;
	public $active;
}
?>