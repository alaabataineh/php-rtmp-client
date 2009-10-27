<?php

require "RtmpPacket.class.php";
require "RtmpStream.class.php";
require "RtmpMessage.class.php";
require "RtmpOperation.class.php";
require "RtmpSocket.class.php";

class RTMPClient
{
	
	const RTMP_SIG_SIZE = 1536;
	
	/**
	 * Socket object
	 *
	 * @var RtmpSocket
	 */
	private $socket;
	
	private $host;
	private $application;
	private $port;
	
	private $chunkSize = 0;
	
	private $operations = array();
	
	private $connected = false;
	

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
		
		
		
		if($this->initSocket())
		{
			$aReadSockets = array($this->socket);
			$this->handshake();
			$this->send_ConnectPacket();
		}
	}
	/**
	 * Close connection
	 *
	 */
	public function close()
	{
		$this->socket && $this->socket->close();
		$this->chunkSize = 128;
	}
	/**
	 * Call remote procedure (RPC)
	 *
	 * @param string $procedureName
	 * @param array $args array of arguments, null if not args
	 * @param callback $handler
	 * 
	 * @return mixed result of RPC
	 */
	public function call($procedureName,$args = null,$handler = null)
	{
		return $this->sendOperation(new RtmpOperation(new RtmpMessage($procedureName,null,$args), $handler));
	}
	
	
	
	
	//------------------------------------
	//		Socket
	//------------------------------------
	private function initSocket()
	{
		$this->socket = new RtmpSocket();
		return $this->socket->connect($this->host, $this->port);
	}
	private function socketRead($length)
	{
		return $this->socket->read($length);
	}
	private function socketWrite(RtmpStream $data, $n = -1)
	{
		return $this->socket->write($data, $n);
	}
	
	//-------------------------------------
	
	private $listening = false;
	/**
	 * listen socket
	 *
	 * @return mixed last result
	 */
	private function listen()
	{
		if($this->listening)
			return;
		if(!$this->socket)
			return;
		$this->listening = true;
		$stop = false;
		$return = null;
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
						$return = $this->handle_invoke($p);
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
		return $return;
	}
	/**
	 * Previous packet
	 * @internal 
	 *
	 * @var RtmpPacket
	 */
	private $prevReadingPacket = array();
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
		
		switch($p->chunkType)
		{
			case RtmpPacket::CHUNK_TYPE_3:
				$p->timestamp = $this->prevReadingPacket[$p->chunkStreamId]->timestamp;
			case RtmpPacket::CHUNK_TYPE_2:
				$p->length = $this->prevReadingPacket[$p->chunkStreamId]->length;
				$p->type = $this->prevReadingPacket[$p->chunkStreamId]->type;
			case RtmpPacket::CHUNK_TYPE_1:
				$p->streamId = $this->prevReadingPacket[$p->chunkStreamId]->streamId;
			case RtmpPacket::CHUNK_TYPE_0:
				break;
		}
		$this->prevReadingPacket[$p->chunkStreamId] = $p;
		$headerSize = RtmpPacket::$SIZES[$p->chunkType];
		 
		if($headerSize == RtmpPacket::MAX_HEADER_SIZE)
			$p->hasAbsTimestamp = true;
		
		//If not operation exists, create it
		if(!isset($this->operations[$p->chunkStreamId]))
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
	 * Previous packet
	 * @internal 
	 *
	 * @var RtmpPacket
	 */
	private $prevSendingPacket = array();
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
		if(isset($this->prevSendingPacket[$packet->chunkStreamId]))
		{
			if($packet->length == $this->prevSendingPacket[$packet->chunkStreamId]->length)
				$packet->chunkType = RtmpPacket::CHUNK_TYPE_2;
			else
				$packet->chunkType = RtmpPacket::CHUNK_TYPE_1;
		}
		if($packet->chunkType > 3) //sanity
			throw new Exception("sanity failed!! tring to send header of type: 0x%02x");
		
		$this->prevSendingPacket[$packet->chunkStreamId] = $packet;
		
		$headerSize = RtmpPacket::$SIZES[$packet->chunkType];
		//Initialize header
		$header = new RtmpStream();
		$header->writeByte($packet->chunkType << 6 | $packet->chunkStreamId);
		if($headerSize > 1)
		{
			$packet->timestamp = time();
			$header->writeInt24($packet->timestamp);
		}	
		if($headerSize > 4)
		{
			$header->writeInt24($packet->length);
			$header->writeByte($packet->type);
		}
		if($headerSize > 8)
			$header->writeInt32LE($packet->streamId);

		// Send header
		$this->socketWrite($header);
		
		$headerSize = $packet->length;
		$buffer = new RtmpStream($packet->payload);
		
		
		while($headerSize)
		{
			$chunkSize = $packet->type == RtmpPacket::TYPE_INVOKE_AMF0 || $packet->type == RtmpPacket::TYPE_INVOKE_AMF3 ? $this->chunkSize : $packet->length;
			if($headerSize < $this->chunkSize)
				$chunkSize = $headerSize;
			
			if(!$this->socketWrite($buffer, $chunkSize))
				throw new Exception("Socket write error (write : $chunkSize)");
			
			$headerSize -= $chunkSize;
			//$buffer = substr($buffer,$chunkSize);
			
			if($headerSize > 0)
			{
				$sep = (0xc0 | $packet->chunkStreamId);
				if(!$this->socketWrite(new RtmpStream(chr($sep)),1))
					return false;
			}
			
		}
		return true;
		
	}
	
	protected function sendOperation(RtmpOperation $op)
	{
		$this->operations[$op->getChunkStreamID()] = $op;
		$this->sendPacket($op->getCall()->getPacket());
		return $this->listen();
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
		$this->socketWrite($chunk);
		
		///	Wrining C1 chunk
		$ctime = time();
		$chunk->writeInt32(microtime(true)); //Time
		$chunk->write("\x80\x00\x01\x02");	//Zero zone? Flex put : 0x80 0x00 0x01 0x02, maybe new handshake style?
		
		$crandom = "";
		for($i=0; $i<self::RTMP_SIG_SIZE - 8; $i++)
			$crandom .= chr(rand(0,256)); //TODO: better method to randomize
		
		$chunk->write($crandom);
		$this->socketWrite($chunk);
		
		///Read S0
		$s0 = $this->socketRead(1)->readTinyInt();
		if($s0 != 0x03)
			throw new Exception("Packet version ".$s0." not supported");
		///Read S1
		$serversig = $this->socketRead(self::RTMP_SIG_SIZE);
		$resp = $this->socketRead(self::RTMP_SIG_SIZE);
		//TODO check integrity
			
		$this->socketWrite($serversig);
		
		return true;
		
	}

	private function send_ConnectPacket()
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

	private function handle_setChunkSize($p)
	{
		
	}
	
	private function handle_invoke(RtmpPacket $p)
	{
		$op = $this->operations[$p->chunkStreamId];
		$op->getResponse()->decode($p);
		unset($this->operations[$p->chunkStreamId]);
		$op->invokeHandler();
		$data = $op->getResponse()->arguments instanceof SabreAMF_AMF3_Wrapper ? $op->getResponse()->arguments->getData() : null;
		if($op->getResponse()->isError())
			throw new Exception($data->description);
		return $data;
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
		unset($this->prevSendingPacket[$m->getResponse()->getPacket()->chunkStreamId]);
		
	}
}
