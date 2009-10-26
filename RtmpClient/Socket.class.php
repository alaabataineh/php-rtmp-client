<?php

/**
 * NOT USED, maybe someday ...
 *
 */
class Socket
{
	
	
	private $host;
	private $port;
	private $socket;
	
	public function __construct($host, $port)
	{
		$this->host = $host;
		$this->port = $port;
	}
	
	/**
	 * Init socket
	 *
	 * @return bool
	 */
	private function create()
	{
		
	    if (($this->socket = socket_create(AF_INET, SOCK_STREAM, 0)) == false)
			die("Unable to create socket.\n");

	    if ((@socket_connect($this->socket, $this->host, $this->port)) == false)
			die("Could not connect\n");

	    return $this->socket != null;
	}
	/**
	 * Read socket
	 *
	 * @param int $length
	 * @return RtmpStream
	 */
	private function read($length)
	{
		$buff = "";
		//print "Read $length, initial buffer length : ".strlen($buff) . "\n";
		do
		{ 
			$recv = ""; 
			$recv = socket_read($this->socket, $length - strlen($buff), PHP_BINARY_READ); 
			if($recv != "")
				$buff .= $recv;
			//print "Read ".strlen($buff)." of $length \n";	
		}
		while($recv != "" && strlen($buff) < $length);
		$this->recvBuffer = substr($buff,$length);
		return new RtmpStream(substr($buff,0,$length));
	}
	/**
	 * Enter description here...
	 *
	 * @param mixed $data
	 * @param int $n
	 * @return bool
	 */
	private function write($data, $n = null)
	{
		$n = $n == null?strlen($data):$n;
		while($n>0)
		{
			
			$nBytes = socket_write($this->socket,$data,$n);
			//print "Writing $n bytes of ".strlen($data).", writed $nBytes\n";
			if($nBytes === false)
			{
				$this->close();
				return false;
			}
			
			if($nBytes == 0)
				break;
			
			$n -= $nBytes;
			$data = substr($data, $nBytes);
		}
		return true;
	}
}
