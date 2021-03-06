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
 * @author  Tom Klingenberg <http://lastflood.com/>
 * @version 0.2.5
 * @package Serialized
 */

namespace Serialized\Dumper;

use Serialized\ArrayNotation\ArrayValue;
use Serialized\ArrayNotation\BoolValue;
use Serialized\ArrayNotation\CustomValue;
use Serialized\ArrayNotation\FloatValue;
use Serialized\ArrayNotation\IntValue;
use Serialized\ArrayNotation\NullValue;
use Serialized\ArrayNotation\ObjectValue;
use Serialized\ArrayNotation\Recursion;
use Serialized\ArrayNotation\Reference;
use Serialized\ArrayNotation\StringValue;
use Serialized\ArrayNotation\VariableName;
use Serialized\ArrayNotation\Variables;
use Serialized\Value;

/**
 * Serialize Dumper
 *
 * Default dumper, works in the shell.
 */
class ObjectArrayNotation
    extends ObjectNotation
{

    // constants

    public const MAP
        = [
            ArrayValue::TYPE_CHAR    => ArrayValue::class,
            BoolValue::TYPE_CHAR     => BoolValue::class,
            CustomValue::TYPE_CHAR   => CustomValue::class,
            FloatValue::TYPE_CHAR    => FloatValue::class,
            IntValue::TYPE_CHAR      => IntValue::class,
            NullValue::TYPE_CHAR     => NullValue::class,
            ObjectValue::TYPE_CHAR   => ObjectValue::class,
            Recursion::TYPE_CHAR     => Recursion::class,
            Reference::TYPE_CHAR     => Reference::class,
            self::TYPE_STRINGENCODED => 'S',
            StringValue::TYPE_CHAR   => StringValue::class,
            VariableName::TYPE_NAME  => VariableName::class,
            Variables::TYPE_NAME     => Variables::class,
        ];


    /**
     * print serialized array notation as serialized data (PHP)
     *
     * @param  array  $parsed
     */
    protected function dumpConcrete($parsed)
    {

        if ($parsed instanceof Value)
        {
            return $parsed;
        }

        throw new \InvalidArgumentException("Invalid");
    }

}