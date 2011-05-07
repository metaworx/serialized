<?php
/**
 * Serialized - PHP Library for Serialized Data
 *
 * Copyright (C) 2010-2011 Tom Klingenberg, some rights reserved
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program in a file called COPYING. If not, see
 * <http://www.gnu.org/licenses/> and please report back to the original
 * author.
 *
 * @author Tom Klingenberg <http://lastflood.com/>
 * @version 0.1.6
 * @package Serialized
 */

Namespace Serialized;
Use \OutOfRangeException;

/**
 * Serialize Parser
 */
class Parser implements Value, ValueTypes {
	private $typeNames = array(
		self::TYPE_INVALID => 'invalid',
		self::TYPE_BOOL => 'bool',
		self::TYPE_FLOAT => 'float',
		self::TYPE_INT => 'int',
		self::TYPE_NULL => 'null',
		self::TYPE_RECURSION => 'recursion',
		self::TYPE_RECURSIONREF => 'recursionref',
		self::TYPE_ARRAY => 'array',
		self::TYPE_OBJECT => 'object',
		self::TYPE_STRING => 'string',
		self::TYPE_CLASSNAME => 'classname',
		self::TYPE_MEMBERS => 'members',
		self::TYPE_MEMBER => 'member',
		self::TYPE_VARIABLES => 'variables',
		self::TYPE_VARNAME => 'name',
	);
	private $typeChars = array(
		self::TYPE_ARRAY => 'a',
		self::TYPE_BOOL => 'b',
		self::TYPE_FLOAT => 'd',
		self::TYPE_INT => 'i',
		self::TYPE_NULL => 'N',
		self::TYPE_OBJECT => 'O',
		self::TYPE_STRING => 's',
		self::TYPE_RECURSION => 'r',
		self::TYPE_RECURSIONREF => 'R',
	);
	/**
	 * @var string serialized
	 */
	protected $data = '';
	public function __construct($serialized = 'N;') {
		$this->setSerialized($serialized);
	}
	/**
	 * @return string datatype
	 */
	public function getType() {
		$parsed = $this->getParsed();
		return $parsed[0];
	}
	public function getSerialized() {
		return $this->data;
	}
	public function setSerialized($serialized) {
		$this->data = (string) $serialized;
	}
	private function typeByChar($char) {
		$map = array_flip($this->typeChars);
		if (!isset($map[$char])) {
			// @codeCoverageIgnoreStart
			throw new \InvalidArgumentException(sprintf('Unknown char "%s" to identify a vartype.', $char));
			// @codeCoverageIgnoreEnd
		}
		return $map[$char];
	}
	private function typeByName($name) {
		$map = array_flip($this->typeNames);
		if (!isset($map[$name])) {
			// @codeCoverageIgnoreStart
			throw new \InvalidArgumentException(sprintf('Unknown name "%s" to identify a vartype.', $name));
			// @codeCoverageIgnoreEnd
		}
		return $map[$name];
	}
	protected function typeNameByType($type) {
		if (array_key_exists($type, $this->typeNames)) {
			return $this->typeNames[$type];
		}
		// @codeCoverageIgnoreStart
		throw new \InvalidArgumentException(sprintf('Unknown vartype %s.', $type));
		// @codeCoverageIgnoreEnd
	}
	/**
	 * @return array(int type, int byte length)
	 */
	private function lookupVartype($offset) {
		$serialized = $this->data;
		$len = strlen($serialized) - $offset;
		$error = array(self::TYPE_INVALID, 0);
		if ($len < 2) return $error;
		# NULL; fixed length: 2
		$token = $serialized[$offset];
		$test = $serialized[$offset+1];
		if ('N' === $token && ';' === $test)
			return array(self::TYPE_NULL, 0);
		if (':' !== $test)
			return $error;
		if (false === strpos('abdiOrRs', $token))
			return $error;
		return array($this->typeByChar($token), 2);
	}
	/**
	 * @param string $regex
	 * @param int $offset
	 * @return int length in chars of match
	 */
	protected function matchRegex($regex, $offset) {
		$return = 0;
		$subject = $this->data;
		if (!isset($subject[$offset])) {
			throw new ParseException(sprintf('Illegal offset "%s" for pattern, length is #%d.', $offset, strlen($subject)));
		}
		$found = preg_match($regex, $subject, $matches, PREG_OFFSET_CAPTURE, $offset);
		if (false === $found) {
			// @codeCoverageIgnoreStart
			$error = preg_last_error();
			throw new \UnexpectedValueException(sprintf('Regular expression ("%s") failed (Error-Code: %d).', $regex, $error));
			// @codeCoverageIgnoreEnd
		}
		$found
			&& isset($matches[0][1])
			&& $matches[0][1] === $offset
			&& $return = strlen($matches[0][0])
		;
		return $return;
	}
	protected function expectChar($charExpected, $offset) {
		if (!isset($this->data[$offset])) {
			throw new ParseException(sprintf('Unexpected EOF at offset %d. Expected "%s".', $offset, $charExpected));
		}
		$char = $this->data[$offset];
		if ($charExpected !== $char) {
			throw new ParseException(sprintf('Unexpected char "%s" at offset %d. Expected "%s".', $char, $offset, $charExpected));
		}
	}
	protected function expectEof($offset) {
		$len = strlen($this->data);
		$end = ($offset + 1) === $len;
		if (!$end) {
			throw new ParseException(sprintf('Not EOF after offset %d. Length is %d.', $offset, $len));
		}
	}
	private function parseRecursionValue($offset) {
		$len = $this->matchRegex('([1-9]+[0-9]*)', $offset);
		if (!$len) {
			throw new ParseException(sprintf('Invalid character sequence for recursion index at offset %d.', $offset));
		}
		$this->expectChar(';', $offset+$len);
		$valueString = substr($this->data, $offset, $len);
		$value = (int) $valueString;
		return array($value, $len+1);
	}
	private function parseRecursionrefValue($offset) {
		return $this->parseRecursionValue($offset);
	}
	private function parseStringValue($offset, $terminator = ';') {
		$lenLength = $this->matchRegex('([0-9]+(?=:))', $offset);
		if (!$lenLength) {
			throw new ParseException(sprintf('Invalid character sequence for string vartype at offset %d.', $offset));
		}
		$this->expectChar(':', $offset+$lenLength);
		$this->expectChar('"', $offset+$lenLength+1);
		$lenString = substr($this->data, $offset, $lenLength);
		$lenInt = (int) $lenString;
		$this->expectChar('"', $offset+$lenLength+$lenInt+2);
		$this->expectChar($terminator, $offset+$lenLength+$lenInt+3);
		$value = substr($this->data, $offset+$lenLength+2, $lenInt);
		return array($value, $lenLength+2+$lenInt+2);
	}
	private function parseIntValue($offset) {
		$len = $this->matchRegex('([-+]?[0-9]+)', $offset);
		if (!$len) {
			throw new ParseException(sprintf('Invalid character sequence for integer value at offset %d.', $offset));
		}
		$this->expectChar(';', $offset+$len);
		$valueString = substr($this->data, $offset, $len);
		$value = (int) $valueString;
		return array($value, $len+1);
	}
	private function extract($offset) {
		$delta = 10;
		$start = max(0, $offset-$delta);
		$before = $offset - $start;
		$end = min(strlen($this->data), $offset + $delta + 1);
		$after = $end - $offset;
		$end = $end - $after + 1;
		$build = '';
		$build .= ($before === $delta ? '...' : '');
		$build .= substr($this->data, $start, $before);
		$build .= sprintf('[%s]', $this->data[$offset]);
		$build .= substr($this->data, $end, $after);
		$build .= ($after === $delta ? '...' : '');

		return $build;
	}
	private function parseInvalidValue($offset) {
		throw new ParseException(sprintf('Invalid ("%s") at offset %d.', $this->extract($offset), $offset));
	}
	private function parseFloatValue($offset) {
		$pattern = '((?:[-]?INF|[+-]?(?:(?:[0-9]+|(?:[0-9]*[\.][0-9]+)|(?:[0-9]+[\.][0-9]*))|(?:[0-9]+|(?:([0-9]*[\.][0-9]+)|(?:[0-9]+[\.][0-9]*)))[eE][+-]?[0-9]+));)';
		$len = $this->matchRegex($pattern, $offset);
		if (!$len) {
			throw new ParseException(sprintf('Invalid character sequence for float vartype at offset %d.', $offset));
		}
		$valueString = substr($this->data, $offset, $len-1);
		$value = unserialize("d:{$valueString};"); // using unserialize for INF and -INF.
		return array($value, $len);
	}
	private function parseNullValue($offset) {
		$this->expectChar('N', $offset);
		$this->expectChar(';', $offset+1);
		return array(null, 2);
	}
	private function parseBoolValue($offset) {
		$char = $this->data[$offset];
		if ('0' !== $char && '1' !== $char) {
			throw new ParseException(sprintf('Unexpected char "%s" at offset %d. Expected "0" or "1".', $char, $offset));
		}
		$this->expectChar(';',$offset+1);
		$valueInt = (int) $char;
		$value = (bool) $valueInt;
		return array($value, 2);
	}
	private function infoOf($hinted) {
		list($typeName) = $hinted;
		$type = $this->typeByName($typeName);
		return array($typeName, $type);
	}
	private function invalidArrayKeyType($type) {
		return (bool) !in_array($type, array(self::TYPE_INT, self::TYPE_STRING));
	}
	private function parseArrayValue($offset) {
		$offsetStart = $offset;
		$lenMatch = $this->matchRegex('([0-9]+:)', $offset);
		if (!$lenMatch) {
			throw new ParseException(sprintf('Invalid character sequence for array length at offset %d.', $offset));
		}
		$lenString = substr($this->data, $offset, $lenMatch-1);
		$lenLen = (int) $lenString;
		$offset += $lenMatch;
		$this->expectChar('{', $offset++);
		$value = array();
		for($elementNumber=0; $elementNumber<$lenLen; $elementNumber++) {
			list($keyHinted, $keyLength) = $this->parseValue($offset);
			list($keyTypeName, $keyType) = $this->infoOf($keyHinted);
			if ($this->invalidArrayKeyType($keyType)) {
				throw new ParseException(sprintf('Invalid vartype %s (%d) for array key at offset %d.', $keyTypeName, $keyType, $offset));
			}
			list($valueHinted, $valueLength) = $this->parseValue($offset+=$keyLength);
			$offset+=$valueLength;
			$element = array(
				$keyHinted,
				$valueHinted,
			);
			$value[] = $element;
		}
		$this->expectChar('}', $offset);
		$len = $offset-$offsetStart+1;
		return array($value, $len);
	}
	private function parseObjectValue($offset) {
		$totalLen = 0;
		list($className, $len) = $this->parseStringValue($offset, ':');
		$totalLen += $len;
		list($classMembers, $len) = $this->parseArrayValue($offset+$len);
		foreach($classMembers as $index => $member) {
			list(list($typeSpec)) = $member;
			if ('string' !== $typeSpec)
				throw new ParseException(sprintf('Unexpected type %s, expected string on offset %d.', $typeSpec, $offset));
			$classMembers[$index][0][0] = $this->typeNameByType(self::TYPE_MEMBER);
		}
		$totalLen += $len;

		$count = count($classMembers);
		$value = array(array($this->typeNameByType(self::TYPE_CLASSNAME), $className), array($this->typeNameByType(self::TYPE_MEMBERS), $classMembers));
		return array($value, $totalLen);
	}
	public function parseValue($offset) {
		list($type, $consume) = $this->lookupVartype($offset);
		$typeName = $this->typeNameByType($type);
		$function = sprintf('parse%sValue', ucfirst($typeName));
		if (!is_callable(array($this, $function))) {
			// @codeCoverageIgnoreStart
			throw new ParseException(sprintf('Unable to parse vartype %s (%d) at offset %s. Parsing function %s is not callable', $typeName, $type, $offset, $function));
			// @codeCoverageIgnoreEnd
		}
		list($value, $len) = $this->$function($offset+$consume);
		$hinted = array($typeName, $value);
		return array($hinted, $len+$consume);
	}
	public static function parse($serialized) {
		$parser = new self($serialized);
		try {
			$result = $parser->getParsed();
		} catch (ParseException $e) {
			trigger_error(sprintf('Error parsing serialized string: %s', $e->getMessage()), E_USER_WARNING);
			$result = false;
		}
		return $result;
	}
	public function getParsed() {
		list($value, $len) = $this->parseValue(0);
		$this->expectEof($len-1);
		return $value;
	}
	/**
	 * print serialized array notation
	 *
	 * @param array $parsed (optional) serialized array notation data or empty to use this objects data.
	 */
	public function dump(array $parsed = null, array $options = array()) {
		(null === $parsed) && $parsed = $this->getParsed();
		$options = array_merge(array('type'=>'text', 'config'=>array()), $options);
		$dumper = Dumper::factory($options['type'], $options['config']);
		$dumper->dump($parsed);
	}
}