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

namespace sofe\libschematic;

use pocketmine\block\Block;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use sofe\libschematic\io\BitReader;
use sofe\libschematic\io\UnsignedByteReader;
use sofe\nbtstreams\NbtReader;

class SchematicFile{
	/**
	 * Maximum separation on the X axis
	 *
	 * @tag Short
	 * @name Width
	 */
	public $xRange;
	/**
	 * Maximum separation on the Y axis
	 *
	 * @tag Short
	 * @name Height
	 */
	public $yRange;
	/**
	 * Maximum separation on the Z axis
	 *
	 * @tag Short
	 * @name Length
	 */
	public $zRange;
	/**
	 * The name of the set of IDs used for blocks. Possible values are "Classic", "Pocket" And "Alpha".
	 *
	 * @tag String
	 * @name Materials
	 */
	public $materials;
	/**
	 * A byte array containing block IDs.
	 *
	 * Access the block at {x, y, z} as <code>Blocks[y * Length * Width + z * Width + x]</code>
	 *
	 * @tag ByteArray
	 * @name Blocks
	 */
	public $blocks;
	/**
	 * @tag          ByteArray
	 * @name AddBlocks
	 * @fallbackName Add
	 */
	public $addBlocks;
	/**
	 * @tag ByteArray
	 * @name Data
	 */
	public $data;
	/**
	 * @tag          Compound
	 * @compoundType Entity
	 * @name Entities
	 */
	public $entities;
	/**
	 * @tag          Compound
	 * @compoundType Tile
	 * @name TileEntities
	 */
	public $tiles;
	/**
	 * @tag          Compound
	 * @compoundType ItemNoSlot
	 * @name Icon
	 */
	public $icon;
	/**
	 * @tag          Compound
	 * @compoundType itemMapping
	 * @name SchematicaMapping
	 */
	public $schematicaMapping;
	/**
	 * @tag          Compound
	 * @compoundType stdClass
	 * @name ExtendedMetadata
	 */
	public $metadata;
	/**
	 * https://github.com/sk89q/WorldEdit/blob/master/worldedit-core/src/main/java/com/sk89q/worldedit/CuboidClipboard.java#L114-L115
	 *
	 * @tag Short
	 * @name WEOriginX
	 * @optional
	 */
	public $originX;
	/**
	 * @tag Short
	 * @name WEOriginY
	 * @optional
	 */
	public $originY;
	/**
	 * @tag Short
	 * @name WEOriginZ
	 * @optional
	 */
	public $originZ;
	/**
	 * https://github.com/sk89q/WorldEdit/blob/master/worldedit-core/src/main/java/com/sk89q/worldedit/CuboidClipboard.java#L116
	 *
	 * i.e. the anchor minus the origin point
	 *
	 * @tag Short
	 * @name WEOffsetX
	 * @optional
	 */
	public $offsetX;
	/**
	 * @tag Short
	 * @name WEOffsetY
	 * @optional
	 */
	public $offsetY;
	/**
	 * @tag Short
	 * @name WEOffsetZ
	 * @optional
	 */
	public $offsetZ;
	/**
	 * @tag ByteArray
	 * @name POpaque
	 * @optional
	 */
	public $opaque;

	public function applyBlocks(Vector3 $anchor) : \Generator{
		$origin = $anchor->subtract($this->offsetX,$this->offsetY,$this->offsetZ);
		$size = $this->xRange * $this->yRange * $this->zRange;
		$streams = [];

		if($this->blocks[0]){
			$blockFh = fopen($this->blocks[1], "rb");
			$blockStream = new UnsignedByteReader(function() use ($blockFh){
				$buffer = fread($blockFh, 2048);
				return [!feof($blockFh), $buffer];
			});
			$streams[] = $blockFh;
		}else{
			$blockStream = new UnsignedByteReader(function(){
				return [false, $this->blocks[1]];
			});
		}
		if($this->data[0]){
			$dataFh = fopen($this->data[1], "rb");
			$dataStream = new UnsignedByteReader(function() use ($dataFh){
				$buffer = fread($dataFh, 2048);
				return [!feof($dataFh), $buffer];
			});
			$streams[] = $dataFh;
		}else{
			$dataStream = new UnsignedByteReader(function(){
				return [false, $this->data[1]];
			});
		}
		if(isset($this->opaque)){
			if($this->opaque[0]){
				$opaqueFh = fopen($this->opaque[1], "rb");
				$opaqueStream = new BitReader(function() use ($opaqueFh){
					$buffer = fread($opaqueFh, 2048);
					return [!feof($opaqueFh), $buffer];
				});
				$streams[] = $opaqueFh;
			}else{
				$opaqueStream = new BitReader(function(){
					return [false, $this->opaque[1]];
				});
			}
		}

		for($i = 0; $i < $size; ++$i){
			$x = $i % $this->xRange;
			$yz = (int) ($i / $this->xRange);
			$z = $yz % $this->zRange;
			$y = (int) ($yz / $this->zRange);
			$block = Block::get($blockStream->read(), $dataStream->read(), new Position($origin->x + $x, $origin->y + $y, $origin->z + $z));
			if(isset($opaqueStream)){
				if(!$opaqueStream->read()){
					continue;
				}
			}
			yield $block;
		}

		foreach($streams as $stream){
			fclose($stream);
		}
	}

	/** @var SchematicElement[]|null */
	private static $format = null;

	public static function getFormat() : array{
		SchematicElement::parseSpec(SchematicFile::class, self::$format);
		return self::$format;
	}

	public static function parseCompound(NbtReader $reader) : SchematicFile{
		$instance = new SchematicFile();
		while(($name = $reader->readName($type)) !== null){
			if(isset(self::$format[$name])){
				$element = self::$format[$name];
				$instance->{$element->propertyName} = $element->readValue($reader);
			}
		}
		foreach(self::$format as $element){
			if(!$element->optional and !isset($instance->{$element->propertyName})){
				throw new \RuntimeException("Missing value " . $element->tagName);
			}
		}
		return $instance;
	}
}
