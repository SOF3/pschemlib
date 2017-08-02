<?php

/*
 *
 * libschematic
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

namespace sofe\libschematic\io;

class UnsignedByteReader{
	/** @var callable */
	private $bufferInput;
	/** @var bool */
	private $hasMore;

	private $buffer = "";
	private $offset = 0;

	public function __construct(callable $bufferInput){
		$this->bufferInput = $bufferInput;
		$this->refreshInput();
	}

	public function read() : int{
		if(!isset($this->buffer{$this->offset})){
			if($this->hasMore){
				$this->refreshInput();
			}else{
				throw new \UnderflowException();
			}
		}
		return ord($this->buffer{$this->offset++});
	}

	private function refreshInput(){
		$this->buffer = substr($this->buffer, $this->offset);
		$this->offset = 0;
		$c = $this->bufferInput;
		list($this->hasMore, $buffer) = $c();
		$this->buffer .= $buffer;
	}
}
