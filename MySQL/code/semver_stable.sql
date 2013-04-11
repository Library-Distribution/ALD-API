DROP FUNCTION IF EXISTS semver_stable;

DELIMITER //

CREATE FUNCTION semver_stable(version varchar(50))
	RETURNS BOOLEAN
	LANGUAGE SQL
	DETERMINISTIC
	BEGIN
		CALL semver_parts(version, @maj, @min, @pat, @pre, @build);
		RETURN @pre IS NULL;
	END
	//

DELIMITER ;