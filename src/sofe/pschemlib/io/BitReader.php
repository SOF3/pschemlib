<?php

/*
 *
 * pschemlib
 *
 * Copyright (C) 2017 SOFe
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace sofe\pschemlib\io;

class BitReader implements \IteratorAggregate{
	/** @var callable */
	private $bufferInput;
	/** @var bool */
	private $moreBuffer;

	private $buffer = "";
	private $bitOffset = 0;

	private $lastLength;

	public function __construct(callable $bufferInput){
		$this->bufferInput = $bufferInput;
		$this->refreshBuffer();
		if($this->buffer === ""){
			throw new \RuntimeException("Buffer is empty");
		}
	}

	public function getIterator() : \Traversable{
		$i = 0;
		while(true){
			try{
				yield $this->read();
				++$i;
			}catch(\UnderflowException $e){
				return $i;
			}
		}
	}

	/**
	 * @return bool true for a set bit, false for an unset bit
	 * @throws \UnderflowException end of stream
	 */
	public function read() : bool{
		$byteOffset = $this->bitOffset >> 3;
		$bitOffset = ($this->bitOffset++) & 7;
		if(!isset($this->buffer{$byteOffset + 2})){
			if($this->moreBuffer){
				$this->refreshBuffer();
			}else{
				if(!isset($this->buffer{$byteOffset}) || $bitOffset >= $this->lastLength){
					throw new \UnderflowException();
				}
			}
		}

		return (ord($this->buffer{$byteOffset}) & (0x80 >> $bitOffset)) !== 0;
	}

	private function refreshBuffer(){
		$byteOffset = $this->bitOffset >> 3;
		$this->buffer = substr($this->buffer, $byteOffset);
		$this->bitOffset &= 7;
		$c = $this->bufferInput;
		list($this->moreBuffer, $buffer) = $c();
		if($this->moreBuffer){
			$this->buffer .= $buffer;
		}else{
			$this->buffer = substr($buffer, 0, -1);
			$last = substr($buffer, -1);
			$this->lastLength = ord($last) & 7;
			if($this->lastLength === 0) $this->lastLength = 8;
		}
	}
}
