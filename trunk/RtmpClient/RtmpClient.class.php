<?php

require "RtmpPacket.class.php";
require "RtmpStream.class.php";
require "RtmpMessage.class.php";
require "RtmpOperation.class.php";

class RTMPClient
{
	
	const RTMP_SIG_SIZE = 1536;
	
	
	private $socket;
	
	private $host;
	private $application;
	private $port;
	
	private $chunkSize = 0;
	
	private $methodCalls = array();
	
	private $channel2packet = array();
	private $channel2timestamp = array();
	
	private $operations = array();
	
	private $connected = false;
	
	//------------------------------------
	//		Socket
	//------------------------------------
	/**
	 * Init socket
	 *
	 * @return bool
	 */
	private function createSocket()
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
	private function socketRead($length)
	{
		$buff = "";
		do
		{ 
			$recv = ""; 
			$recv = socket_read($this->socket, $length - strlen($buff), PHP_BINARY_READ); 
			if($recv != "")
				$buff .= $recv;
		}
		while($recv != "" && strlen($buff) < $length);
		$this->recvBuffer = substr($buff,$length);
		return new RtmpStream(substr($buff,0,$length));
	}
	private function socketWrite($data, $n = null)
	{
		$n = $n == null?strlen($data):$n;
		while($n>0)
		{
			
			$nBytes = socket_write($this->socket,$data,$n);
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
	
	//------------------------------------
	//		RTMP Methods
	//------------------------------------
	/**
	 * Perform handshake
	 *
	 */
	private function handshake()
	{
		///	Writing C0 chunk, the version
		$chunk = new RtmpStream();
		
		$chunk->writeByte("\x03"); //"\x03";
		$this->socketWrite($chunk->flush());
		
		///	Wrining C1 chunk
		$ctime = time();
		$chunk->writeInt32(microtime(true)); // pack('N', $this->ctime); //Time
		$chunk->write("\x80\x00\x01\x02");	//Zero zone? Flex put : 0x80 0x00 0x01 0x02, maybe new handshake style?
		
		$crandom = "";
		for($i=0; $i<self::RTMP_SIG_SIZE - 8; $i++)
			$crandom .= chr(rand(0,256)); //TODO: better method to randomize
		
		$chunk->write($crandom);
		$this->socketWrite($chunk->flush());
		
		///Read S0
		$s0 = $this->socketRead(1)->readTinyInt();
		if($s0 != 0x03)
			throw new Exception("Packet version ".$s0." not supported");
		///Read S1
		$serversig = $this->socketRead(self::RTMP_SIG_SIZE);
		$resp = $this->socketRead(self::RTMP_SIG_SIZE);
		//TODO check integrity
			
		$this->socketWrite($serversig->flush());
		
		return true;
		
	}

	private function sendConnectPacket()
	{
		$this->sendOperation(
			new RtmpOperation(new RtmpMessage("connect",array(
					"app" => $this->application,
					"flashVer" => "LNX 10,0,22,87",
					"swfUrl" => "http://localhost/weyzit/Weyzit.swf",
					"tcUrl" => "rtmp://$this->host:$this->port/$this->application",
					"fpad" => false,
					"capabilities" => 0.0,
					"audioCodecs" => 0x01,
					"videoCodecs" => 0xFF,
					"videoFunction" => 0,
					"pageUrl" => 'http: //localhost/weyzit/Weyzt.html#',
					"objectEncoding" => 0x03
				)), array($this,"onConnect"))
			);
	}
	private function send_SetChunkSize()
	{
		
	}
	private function send_AbortMessage()
	{
		
	}
	private function send_Acknowledgement()
	{
		
	}
	private function send_UserControlMessage()
	{
		
	}
	private function send_WindowAcknowledgementSize()
	{
		
	}
	private function send_SetPeerBandwidth()
	{
		
	}
	/**
	 * Read packet
	 *
	 * @return RtmpPacket
	 */
	private function readPacket()
	{
		$p = new RtmpPacket();
		
		$header = $this->socketRead(1)->readTinyInt();
		$p->chunkType = (($header & 0xc0) >> 6);
		$p->chunkStreamId = $header & 0x3f;

		switch($p->chunkStreamId)
		{
			case 0: //range of 64-319, second byte + 64
				$p->chunkStreamId = 64 + $this->socketRead(1)->readTinyInt();
				break;
			case 1: //range of 64-65599,thrid byte * 256 + second byte + 64
				$p->chunkStreamId = 64 + $this->socketRead(1)->readTinyInt() + $this->socketRead(1)->readTinyInt()*256;
				break;
			case 2:
				break;
			default: //range of 3-63
				// complete stream ids
		}
		
		
		$headerSize = RtmpPacket::$SIZES[$p->chunkType];
		 
		if($headerSize == RtmpPacket::MAX_HEADER_SIZE)
			$p->hasAbsTimestamp = true;
		
		//If not operation exists, create it
		if(!$this->operations[$p->chunkStreamId])
			$this->operations[$p->chunkStreamId] = new RtmpOperation();
		
		if($this->operations[$p->chunkStreamId]->getResponse())
		{	
			//Operation chunking....
			$p = $this->operations[$p->chunkStreamId]->getResponse()->getPacket();
			$headerSize = 0; //no header
		}
		else
		{
			//Create response from packet
			$this->operations[$p->chunkStreamId]->createResponse($p);
		}
		
		
		$headerSize--;
		$header;
		if($headerSize>0)
			$header = $this->socketRead($headerSize);

		if($headerSize >= 3)
			$p->timestamp = $header->readInt24();
		if($headerSize >= 6)
		{
			$p->length = $header->readInt24();
			
			$p->bytesRead = 0;
			$p->free();
		}
		if($headerSize > 6)
			$p->type = $header->readTinyInt();
		
		if($headerSize == 11)
			$p->streamId = $header->readInt32LE();
		
			
		$nToRead = $p->length - $p->bytesRead;
		$nChunk = $this->chunkSize;
		
		if($nToRead < $nChunk)
			$nChunk = $nToRead;

		if($p->payload == null)
			$p->payload = "";
		
		$p->payload .= $this->socketRead($nChunk)->flush();
		if($p->bytesRead + $nChunk != strlen($p->payload))
			throw new Exception("Read failed, have read ".strlen($p->payload)." of ".($p->bytesRead + $nChunk));
		$p->bytesRead += $nChunk;
		
		if($p->isReady())
		{
			return $p;
		}
		
		return null;
		/*else
			$p->payload = null;*/
		
	}
	
	/**
	 * Send packet
	 *
	 * @param RtmpPacket $packet
	 * @return bool
	 */
	private function sendPacket(RtmpPacket $packet)
	{
		
		if(!$packet->length)
			$packet->length = strlen($packet->payload);
		if($packet->chunkType != RtmpPacket::CHUNK_TYPE_0)
		{
			//TODO compress a bit by using the prev packet's attributes
		}
		if($packet->chunkType > 3) //sanity
			throw new Exception("sanity failed!! tring to send header of type: 0x%02x");
		
		
		$headerSize = RtmpPacket::$SIZES[$packet->chunkType];
		//Initialize header
		$header = new RtmpStream();
		$header->writeByte($packet->chunkType << 6 | $packet->chunkStreamId);
		if($headerSize > 1)
			$header->writeInt24($packet->timestamp);
			
		if($headerSize > 4)
		{
			$header->writeInt24($packet->length);
			$header->writeByte($packet->type);
		}
		if($headerSize > 8)
			$header->writeInt32LE($packet->streamId);

		// Send header
		$this->socketWrite($header->flush());
		
		$headerSize = $packet->length;
		$buffer = $packet->payload;
		
		
		while($headerSize)
		{
			$chunkSize = $packet->type == RtmpPacket::TYPE_INVOKE_AMF0 || $packet->type == RtmpPacket::TYPE_INVOKE_AMF3 ? $this->chunkSize : $packet->length;
			if($headerSize < $this->chunkSize)
				$chunkSize = $headerSize;
			
			if(!$this->socketWrite($buffer, $chunkSize))
			{
				print "Socket write error (write : $chunkSize)";
				return false;
			}
			$headerSize -= $chunkSize;
			$buffer = substr($buffer,$chunkSize);
			
			if($headerSize > 0)
			{
				$sep = (0xc0 | $packet->chunkStreamId);
				if(!$this->socketWrite(chr($sep),1))
					return false;
			}
			
		}
		return true;
		
	}
	/**
	 * Connect
	 *
	 * @param string $host
	 * @param string $application
	 * @param int $port
	 */
	public function connect($host,$application,$port = 1935)
	{
		$this->close();
		
		$this->host = $host;
		$this->application = $application;
		$this->port = $port;
		
		if($this->createSocket())
		{
			$aReadSockets = array($this->socket);
			$this->handshake();
			$this->sendConnectPacket();
			$this->listen();
		}
	}
	
	private $listening = false;
	private function listen()
	{
		if($this->listening)
			return;
		if(!$this->socket)
			return;
		$this->listening = true;
		$stop = false;
		while (!$stop)
		{
			if($p = $this->readPacket())
			{
				switch($p->type)
				{
					case 0x01; //Chunk size
						
						break;
					case 0x03:
						
						break;
					case 0x04: //ping ?
						unset($this->operations[$p->chunkStreamId]);
						break;
					case 0x05:
						
						break;
					case 0x06:
						
						break;
					case 0x08:
						
						break;
					case 0x09:
						
						break;
					case 0x12:
						
						break;
					
					case RtmpPacket::TYPE_INVOKE_AMF0:
					case RtmpPacket::TYPE_INVOKE_AMF3: //Invoke
						$this->handle_invoke($p);
						if(sizeof($this->operations) == 0)
							$stop = true;
						break;
					case 0x16:
						
						break;
					case 0x36: //agregate
						
						break;
					default:
						
						break;
				}
			}
			usleep(1);
		}
		$this->listening = false;
	}
	
	protected function sendOperation(RtmpOperation $op)
	{
		$this->operations[$op->getChunkStreamID()] = $op;
		$this->sendPacket($op->getCall()->getPacket());
	}
	private $pendingCalls = array();
	public function call($procedureName,$args = null,$handler = null)
	{
		$this->pendingCalls[] = new RtmpOperation(new RtmpMessage($procedureName,null,$args), $handler);
		$this->processPendingCalls();
	}
	private function processPendingCalls()
	{
		if(!$this->connected)
			return;
		$calls = $this->pendingCalls;
		$this->pendingCalls = array();
		foreach($calls as $call)
			$this->sendOperation($call);
		$this->listen();
	}
	/**
	 * Close connection
	 *
	 */
	public function close()
	{
		if($this->socket)
		{
			socket_close($this->socket);
		}
		$this->chunkSize = 128;
	}
	
	
	private function handle_setChunkSize($p)
	{
		
	}
	
	private function handle_invoke(RtmpPacket $p)
	{
		$op = $this->operations[$p->chunkStreamId];
		$op->getResponse()->decode($p->payload,0);
		unset($this->operations[$p->chunkStreamId]);
		$op->invokeHandler();
	}
	
	
	//------------------------------------
	//	Internal handlers
	//------------------------------------
	/**
	 * On connect handler
	 * @internal 
	 * @param RtmpMessage $m
	 * 
	 */
	public function onConnect(RtmpOperation $m)
	{
		$this->connected = true;
		$this->processPendingCalls();
	}
}
