<?php
require_once 'Registration.php';
require_once '../../Assert.php';
require_once '../../util.php';
require_once '../../modules/HttpException/HttpException.php';

try {
	Assert::RequestMethod(Assert::REQUEST_METHOD_GET);
	Assert::GetParameters('id');

	$session = Registration::get($_GET['id']);
	$token = $session['token'];

	$char_width = 12;
	$image = imagecreate(20 + $char_width * strlen($token), 50);
	imagecolorallocate($image, 0, 0, 0);
	$color = imagecolorallocate($image, 250, 200, 255);
	$i = 0;
	foreach (str_split($token) AS $char)
	{
		$y = rand(1, 20);
		imagechar($image, 5, 10 + $i * $char_width, $y, $char, $color);
		$i++;
	}
	header("Content-type: image/png");
	imagepng($image);
	exit;

} catch (HttpException $e) {
	handleHttpException($e);
} catch (Exception $e) {
	handleHttpException(new HttpException(500, NULL, $e->getMessage()));
}
?>