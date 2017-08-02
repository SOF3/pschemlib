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

use pocketmine\item\Item;
use sofe\nbtstreams\NbtReader;
use sofe\nbtstreams\NbtTagConsts;

class SchematicElement{
	public static $CONFIG_TEMP_FOLDER = null;
	public static $CONFIG_BYTE_ARRAY_MEMORY_THRESHOLD = 10 << 20; // 10 MB

	public $propertyName;
	public $tagType;
	public $tagName, $fallbackName = null;
	public $optional = false;
	public $compoundType = null;

	public static function parseSpec(string $className, &$elements, bool $force = false){
		if(!$force and isset($elements)){
			return;
		}
		$elements = [];
		$class = new \ReflectionClass($className);
		$consts = (new \ReflectionClass(NbtTagConsts::class))->getConstants();
		foreach($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property){
			$docs = [];
			if(preg_match_all('/^[ \t]+\*[ \t]+@([a-zA-Z]+)([ \t]+(.+))?$/', $property->getDocComment(), $matches, PREG_SET_ORDER)){
				foreach($matches as $match){
					$docs[$match[1]] = $match[3] ?: true;
				}
			}
			assert(isset($docs["tag"], $docs["name"]), serialize($docs));

			$element = new SchematicElement();
			$element->propertyName = $property->getName();
			$element->tagType = $consts["TAG_" . $docs["tag"]];
			$element->tagName = $docs["name"];
			if(isset($docs["fallbackName"])){
				$element->fallbackName = $docs["fallbackName"];
			}
			$element->optional = isset($docs["optional"]);
			if($element->tagType === "Compound"){
				$element->compoundType = $docs["compoundType"];
			}
			$elements[$element->tagName] = $element;
			if(!isset($elements[$element->fallbackName])){
				$elements[$element->fallbackName] = $element;
			}
		}
	}

	public function readValue(NbtReader $reader){
		return self::readValueByType($reader, $this->tagType, $this->compoundType);
	}

	private static function readValueByType(NbtReader $reader, string $type, string $compoundType = null){
		if($type === NbtTagConsts::TAG_List){
			$reader->startList($subtype, $size);
			$value = [];
			for($i = 0; $i < $size; ++$i){
				$value[] = self::readValueByType($reader, $subtype, $compoundType);
			}
			$reader->endList();
			return $value;
		}
		if($type === NbtTagConsts::TAG_Compound){
			if($compoundType === null){
				throw new \RuntimeException("Cannot parse compound with unknown compoundType");
			}

			switch($compoundType){
				case "itemMapping":
					$reader->startCompound();
					$mapping = [];
					while(($name = $reader->readName()) !== null){
						$mapping[$name] = $reader->readShort();
					}
					return $mapping;
				case "ItemNoSlot":
					$reader->startCompound();
					while(($name = $reader->readName()) !== null){
						switch($name){
							case "id":
								$id = $reader->readString();
								break;
							case "Damage":
								$damage = $reader->readShort();
								break;
							case "Count":
								$count = $reader->readByte(false);
								break;
							case "tag":
								$tag = self::readValueByType($reader, NbtTagConsts::TAG_Compound, "stdClass");
								break;
						}
					}
					$reader->endCompound();
					if(!isset($id, $damage, $count)){
						throw new \RuntimeException("Incomplete ItemNoSlot");
					}
					$id = Item::fromString($id)->getId();
					$item = Item::get($id, $damage, $count); // TODO support tag
					return $item;
				case "Entity":
					// TODO Unsupported, skipped to stdClass
				case "Tile":
					// TODO Unsupported, skipped to stdClass
				case "stdClass":
					$reader->startCompound();
					$values = [];
					while(($name = $reader->readName($subSubType)) !== null){
						$values[$name] = self::readValueByType($reader, $subSubType, "stdClass");
					}
					$reader->endCompound();
					return $values;
			}
			throw new \InvalidArgumentException("Unknown compoundType $compoundType");
		}
		if($type === NbtTagConsts::TAG_ByteArray){
			$size = $reader->peekInt();
			if($size > self::$CONFIG_BYTE_ARRAY_MEMORY_THRESHOLD){
				$tmpFile = tempnam(self::$CONFIG_TEMP_FOLDER, "psc");
				$fh = fopen($tmpFile, "wb");
				foreach($reader->generateByteArrayReader() as $buffer){
					fwrite($fh, $buffer);
				}
				fclose($fh);
				return [true, $tmpFile];
			}else{
				return [false, $reader->readByteArray()];
			}
		}
		return $reader->readValue($type);
	}
}
