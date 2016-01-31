# A Rtmp client for PHP #

## Project moved on github! https://github.com/qwantix/php-rtmp-client ##


---



## Contributors Search ##
You are more and more people to use php-rtmp-client, but I do not have time to answer all your questions.
If anyone is interested to ensure the continuity of the project, please contact me.

### Usage ###
```
<?
require "RtmpClient.class.php";

$client = new RtmpClient();
$client->connect("localhost","myApp");
$result = $client->myRemoteMethod($arg1,$arg2);
var_dump($result);
$client->close();
?>

```
[How to here!](HowTo.md)

**feedbacks and patchs will be appreciated**...


Enjoy!


---

sources are available on [svn](http://php-rtmp-client.googlecode.com/svn/trunk/) :
```
svn checkout http://php-rtmp-client.googlecode.com/svn/trunk/
```


---

Inspiration :
  * [RTMP Specifications by Adobe](http://www.adobe.com/devnet/rtmp/)
  * [Red5 (java server)](http://osflash.org/red5)
  * [Rtmpd (c++ server)](http://www.rtmpd.com/)
  * [source of XBMC](http://www.xbmc.org/trac/browser/branches/linuxport/XBMC/xbmc/lib/libRTMP?rev=23011)


---

Used librairy :
  * [Sabre Amf](http://code.google.com/p/sabreamf/)