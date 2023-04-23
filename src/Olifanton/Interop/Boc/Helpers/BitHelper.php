<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

final class BitHelper
{
    public static function alignBits(int $bitLength): int
    {
        return (int) ceil($bitLength / 8) * 8;
    }
}
