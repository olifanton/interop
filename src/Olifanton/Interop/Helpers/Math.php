<?php declare(strict_types=1);

namespace Olifanton\Interop\Helpers;

final class Math
{
    public static function clz32(int $num): int
    {
        if ($num < 0) {
            return 0;
        }

        if ($num === 0) {
            return 32;
        }

        return 31 - (int)floor(log($num + 0.5) * M_LOG2E);
    }

    public static function rrr(int $v, int $n): int
    {
        return ($v & 0xffffffff) >> ($n & 0x1f);
    }
}
