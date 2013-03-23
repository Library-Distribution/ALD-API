DROP FUNCTION IF EXISTS semver_compare;

DELIMITER //

CREATE FUNCTION semver_compare(version1 varchar(50), version2 varchar(50))
	RETURNS INT(1)
	LANGUAGE SQL
	DETERMINISTIC
	BEGIN
		CALL semver_parts(version1, @maj1, @min1, @pat1, @pre1, @build1);
		CALL semver_parts(version2, @maj2, @min2, @pat2, @pre2, @build2);

		SET @maj1 = CAST(@maj1 AS UNSIGNED), @maj2 = CAST(@maj2 AS UNSIGNED);
		IF (@maj1 != @maj2) THEN
			RETURN IF(@maj1 < @maj2, -1, 1);
		END IF;

		SET @min1 = CAST(@min1 AS UNSIGNED), @min2 = CAST(@min2 AS UNSIGNED);
		IF (@min1 != @min2) THEN
			RETURN IF(@min1 < @min2, -1, 1);
		END IF;

		SET @pat1 = CAST(@pat1 AS UNSIGNED), @pat2 = CAST(@pat2 AS UNSIGNED);
		IF (@pat1 != @pat2) THEN
			RETURN IF(@pat1 < @pat2, -1, 1);
		END IF;

		IF (@pre1 IS NULL AND @pre2 IS NOT NULL) THEN
			RETURN 1;
		ELSEIF (@pre1 IS NOT NULL AND @pre2 IS NULL) THEN
			RETURN -1;
		END IF;

		-- TODO: no full prerelease / build support so far
		RETURN 0;
	END
	//

DELIMITER ;