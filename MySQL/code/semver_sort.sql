DROP PROCEDURE IF EXISTS semver_sort;

DELIMITER //

CREATE PROCEDURE semver_sort()
	LANGUAGE SQL
	NOT DETERMINISTIC
	MODIFIES SQL DATA
	BEGIN
		SELECT COUNT(*) INTO @length FROM `semver_index`;
		SET @gap := @length, @swapped = FALSE;

		WHILE (@gap > 1 || @swapped) DO -- an implementation of the comb sort algorithm (see <http://en.wikipedia.org/wiki/Comb_sort>)
			IF @gap > 1 THEN
				SET @gap := FLOOR(@gap / 1.3);
			END IF;

			SET @i := 1, @swapped := FALSE;
			WHILE ((@j := @i + @gap) <= @length) DO
				SELECT `version` INTO @a FROM `semver_index` WHERE `position` = @i; -- read the entries for comparison
				SELECT `version` INTO @b FROM `semver_index` WHERE `position` = @j;

				IF (semver_compare(@a, @b) > 0) THEN
					UPDATE `semver_index` SET `position` = -1 WHERE `position` = @i; -- temp item
					UPDATE `semver_index` SET `position` = @i WHERE `position` = @j;
					UPDATE `semver_index` SET `position` = @j WHERE `position` = -1;

					SET @swapped := TRUE;
				END IF;
				SET @i := @i + 1;
			END WHILE;
		END WHILE;
	END;
	//

DELIMITER ;