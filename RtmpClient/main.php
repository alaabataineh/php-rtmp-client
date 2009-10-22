<?
require "RtmpClient.class.php";

$client = new RtmpClient();
$client->connect("localhost","myApp",1935);
$client->call("getId",null, onId);
function onId(RtmpOperation $op)
{
	print "ID!";
}

?>
