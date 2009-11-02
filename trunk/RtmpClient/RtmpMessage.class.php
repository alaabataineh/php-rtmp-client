<?php
require 'SabreAMF/OutputStream.php';
require 'SabreAMF/InputStream.php';

require 'SabreAMF/AMF0/Serializer.php';
require 'SabreAMF/AMF0/Deserializer.php';
/*
require 'SabreAMF/AMF3/Serializer.php';
require 'SabreAMF/AMF3/Deserializer.php';*/
class RtmpMessage
{
	private static $currentTransactionID = 0;
	
	public $commandName;
	public $transactionId;
	public $commandObject;
	public $arguments;
	
	private $packet;
	
	public function __construct($commandName = "",$commandObject = null,$arguments = null)
	{
		$this->commandName = $commandName;
		$this->commandObject = $commandObject;
		$this->arguments = $arguments;
		
	}
	/**
	 * getPacket
	 *
	 * @return RtmpPacket
	 */
	public function getPacket()
	{
		return $this->packet;
	}
	public function setPacket($packet)
	{
		$this->packet = $packet;
	}
	/**
	 * Encode Message
	 *
	 * @param int $amfVersion
	 * @return RtmpPacket
	 */
	public function encode()
	{
		$amfVersion = 3; //Using AMF3
		
		//Increment transaction id
		$this->transactionId = self::$currentTransactionID++;
		
		//Create packet
		$p = new RtmpPacket();
		if($this->commandName == "connect")
		{
			$this->transactionId = 1;
			$amfVersion = 0; //Connect packet must be in AMF0
		}
		$p->chunkStreamId = 3;
		$p->streamId = 0;
		$p->chunkType = RtmpPacket::CHUNK_TYPE_0;
		$p->type = $amfVersion == 0 ? RtmpPacket::TYPE_INVOKE_AMF0 : RtmpPacket::TYPE_INVOKE_AMF3 ; //Invoke
		
		//Encoding payload
		$stream = new SabreAMF_OutputStream();
		$serializer = new SabreAMF_AMF0_Serializer($stream);
		$serializer->writeAMFData($this->commandName);
		$serializer->writeAMFData($this->transactionId);
		
		$serializer->writeAMFData($this->commandObject);		
		if($this->arguments != null)
			foreach($this->arguments as $arg)
				$serializer->writeAMFData($arg);
		$p->payload = '';
		if($amfVersion == 3)
			$p->payload = "\x00"; //XXX: put empty bytes in amf3 mode...I don't know why..*/
		$p->payload .= $stream->getRawData();
		
		$this->packet = $p;
		
		return $p;
	}
	public function decode(RtmpPacket $p)
	{
		$this->packet = $p;
		$amfVersion = $p->type == RtmpPacket::TYPE_INVOKE_AMF0?0:3;
		$stream = new SabreAMF_InputStream($p->payload);
		$deserializer = $amfVersion == 0 ? new SabreAMF_AMF0_Deserializer($stream) : new SabreAMF_AMF3_Deserializer($stream);
		$this->commandName = $deserializer->readAMFData();
		$this->transactionId = $deserializer->readAMFData();
		$this->commandObject = $deserializer->readAMFData();
		$this->arguments = $deserializer->readAMFData();
		if($this->commandName == "_error")
			$this->_isError = true;
	}
	private $_isError = false;
	public function isError()
	{
		return $this->_isError;
	}
}