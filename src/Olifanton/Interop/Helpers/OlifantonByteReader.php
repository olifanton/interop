<?php declare(strict_types=1);

namespace Olifanton\Interop\Helpers;

use Olifanton\TypedArrays\ArrayBuffer;

final class OlifantonByteReader
{
    public static final function getBytes(ArrayBuffer $buffer): string
    {
        return ArrayBuffer::__WARNING__UNSAFE__ACCESS_VIOLATION__UNSAFE__($buffer);
    }
}
