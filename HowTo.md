# Basic usage #

```
require "pathToLib/RtmpClient.class.php";
//Create RtmpClient Object
$client = new RtmpClient();
//Connect to server
$client->connect($host,$appName);
/*
 your remote procedure calls
*/
//Closing connection
$client->close();
```
# Remote method call #
## Syntax ##
2 ways to call remote method :
  * using call method
> `$clientInstance->call(string methodName,array args[, callback resultHandler ])`
  * using magic call method
> `$clientInstance->methodName(... args)`

call return result of method.

## Call method without argument ##

```
$r = $client->call("myMethod");
```
or
```
$r = $client->myMethod();
```

## Call method with arguments ##
```
$r = $client->call("myMethod",array($arg1, $arg2));
```
or
```
$r = $client->myMethod($arg1,$arg2);
```

## Call method with typed object arguments ##
call method with typed object using [class mapping](http://code.google.com/p/sabreamf/wiki/ClassMapping)

```
$data = array(
  'property1' => 'value1',
  'property2' => 'value2',
);

$object = new SabreAMF_TypedObject('mypackage.MyFlashClass',$data);
$r = $client->myMethod($object);
```