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

class BitWriter{
	/** @var callable */
	private $bufferOutput;

	private $buffer = "";
	private $lastByte = 0;
	private $bitOffset = 0;

	public function __construct(callable $bufferOutput){
		$this->bufferOutput = $bufferOutput;
	}
	
	public function write(bool $bit){
		if($bit){
			$this->lastByte |= 0x80 >> $this->bitOffset;
		}

		if((++$this->bitOffset) === 8){
			$this->bitOffset = 0;
			$this->buffer .= chr($this->lastByte);
			$this->lastByte = 0;

			if(strlen($this->buffer) > 2048){
				$c = $this->bufferOutput;
				$c($this->buffer);
				$this->buffer = "";
			}
		}

	}

	public function close(){
		$this->buffer .= chr($this->bitOffset);
		$c = $this->bufferOutput;
		$c($this->buffer);
	}
}
