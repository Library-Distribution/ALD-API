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
			RETURN +1;
		ELSEIF (@pre1 IS NOT NULL AND @pre2 IS NULL) THEN
			RETURN -1;
		ELSEIF (@pre1 IS NOT NULL AND @pre2 IS NOT NULL) THEN
			-- compare each prerelease part
			SELECT (LENGTH(@pre1) - LENGTH(REPLACE(@pre1, '.', ''))) / LENGTH('.') + 1 INTO @pre_parts1; -- count the number of parts
			SELECT (LENGTH(@pre2) - LENGTH(REPLACE(@pre2, '.', ''))) / LENGTH('.') + 1 INTO @pre_parts2;
			SET @m = 1;

			WHILE (@m <= LEAST(@pre_parts1, @pre_parts2)) DO
				SET @part1 = TRIM(LEADING CONCAT(SUBSTRING_INDEX(@pre1, '.', @m - 1), '.') FROM SUBSTRING_INDEX(@pre1, '.', @m));
				SET @part2 = TRIM(LEADING CONCAT(SUBSTRING_INDEX(@pre2, '.', @m - 1), '.') FROM SUBSTRING_INDEX(@pre2, '.', @m));

				IF (@part1 RLIKE '^[[:digit:]]+$' AND @part2 RLIKE '^[[:digit:]]+$') THEN
					SET @part1 = CAST(@part1 AS SIGNED), @part2 = CAST(@part2 AS SIGNED);
					IF (@part1 < @part2) THEN
						RETURN -1;
					ELSEIF (@part1 > @part2) THEN
						RETURN +1;
					END IF;
				ELSEIF ((@cmp := STRCMP(@part1, @part2)) != 0) THEN
					RETURN @cmp;
				END IF;

				SET @m = @m + 1;
			END WHILE;

			IF (@pre_parts1 < @pre_parts2) THEN -- the longer one wins
				RETURN -1;
			ELSEIF (@pre_parts2 > @pre_parts1) THEN
				RETURN +1;
			END IF;
		END IF;

		IF (@build1 IS NULL AND @build2 IS NOT NULL) THEN
			RETURN -1;
		ELSEIF (@build1 IS NOT NULL AND @build2 IS NULL) THEN
			RETURN 1;
		ELSEIF (@build1 IS NOT NULL AND @build2 IS NOT NULL) THEN
			-- compare each build part
			SELECT (LENGTH(@build1) - LENGTH(REPLACE(@build1, '.', ''))) / LENGTH('.') + 1 INTO @build_parts1; -- count the number of parts
			SELECT (LENGTH(@build2) - LENGTH(REPLACE(@build2, '.', ''))) / LENGTH('.') + 1 INTO @build_parts2;
			SET @m = 1;

			WHILE (@m <= LEAST(@build_parts1, @build_parts2)) DO
				SET @part1 = TRIM(LEADING CONCAT(SUBSTRING_INDEX(@build1, '.', @m - 1), '.') FROM SUBSTRING_INDEX(@build1, '.', @m));
				SET @part2 = TRIM(LEADING CONCAT(SUBSTRING_INDEX(@build2, '.', @m - 1), '.') FROM SUBSTRING_INDEX(@build2, '.', @m));

				IF (@part1 RLIKE '^[[:digit:]]+$' AND @part2 RLIKE '^[[:digit:]]+$') THEN
					SET @part1 = CAST(@part1 AS SIGNED), @part2 = CAST(@part2 AS SIGNED);
					IF (@part1 < @part2) THEN
						RETURN -1;
					ELSEIF (@part1 > @part2) THEN
						RETURN +1;
					END IF;
				ELSEIF ((@cmp := STRCMP(@part1, @part2)) != 0) THEN
					RETURN @cmp;
				END IF;

				SET @m = @m + 1;
			END WHILE;

			IF (@build_parts1 < @build_parts2) THEN -- the longer one wins
				RETURN -1;
			ELSEIF (@build_parts1 > @build_parts2) THEN
				RETURN +1;
			END IF;
		END IF;

		RETURN 0;
	END
	//

DELIMITER ;