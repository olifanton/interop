<?php declare(strict_types=1);

namespace Olifanton\Interop\Helpers;

final class ArrayHelper
{
    /**
     * @template T of int|string
     * @template V
     * @param array<T, V> $target
     * @param callable(V, int): bool $uf
     * @return T|null
     */
    public static function arraySearch(array $target, callable $uf)
    {
        $i = 0;

        foreach ($target as $key => $value) {
            if (call_user_func($uf, $value, $i++)) {
                return $key;
            }
        }

        return null;
    }
}
