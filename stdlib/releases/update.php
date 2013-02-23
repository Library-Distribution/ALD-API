<?php
	require_once('../../Assert.php');
	require_once('../../modules/HttpException/HttpException.php');
	require_once('../StdlibPending.php');
	require_once('../../util.php');

	try
	{
		Assert::RequestMethod(Assert::REQUEST_METHOD_POST);
		Assert::GetParameters('version');

		# todo: user validation

		$libs = StdlibPending::GetEntries($_GET['version']);

		# write to 'stdlib' table (deleting old entries for that release first)

		# stdlib_actions > stdlib_pending > stdlib > release published
	}
	catch (HttpException $e)
	{
		handleHttpException($e);
	}
	catch (Exception $e)
	{
		handleHttpException(new HttpException(500, NULL, $e->getMessage()));
	}
?>