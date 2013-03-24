DROP PROCEDURE IF EXISTS semver_parts;

DELIMITER //

CREATE PROCEDURE semver_parts(IN version VARCHAR(50), OUT major VARCHAR(50), OUT minor VARCHAR(50), OUT patch VARCHAR(50), OUT prerelease VARCHAR(50), OUT build VARCHAR(50))
	LANGUAGE SQL
	DETERMINISTIC
	BEGIN
		SET @pre_start = INSTR(version, '-'), @build_start = INSTR(version, '+');
		SET @end_patch = LEAST(IF(@pre_start = 0, LENGTH(version), @pre_start - 1), IF(@build_start = 0, LENGTH(version), @build_start - 1));

		SELECT SUBSTRING_INDEX(version, '.', 1) INTO major;
		SELECT TRIM(LEADING CONCAT(major, '.') FROM SUBSTRING_INDEX(version, '.', 2)) INTO minor;
		SELECT SUBSTRING(version, LOCATE('.', version, @t := LENGTH(CONCAT(major, '.', minor, '.'))) + 1, @end_patch - @t) INTO patch;

		SELECT (CASE @pre_start WHEN 0 THEN NULL ELSE SUBSTRING(version, @pre_start + 1, IF(@build_start = 0, LENGTH(version), @build_start - @pre_start - 1)) END) INTO prerelease;
		SELECT (CASE @build_start WHEN 0 THEN NULL ELSE SUBSTRING(version, @build_start + 1, LENGTH(version)) END) INTO build;
	END;
	//

DELIMITER ;