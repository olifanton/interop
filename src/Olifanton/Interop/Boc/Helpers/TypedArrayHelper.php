<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

use Olifanton\Interop\Bytes;
use Olifanton\TypedArrays\Uint8Array;

final class TypedArrayHelper
{
    /**
     * Returns slice (new instance of Uint8Array) of $arr.
     *
     * The original $arr will not be changed.
     */
    public static function sliceUint8Array(Uint8Array $arr, int $start, ?int $end = null): Uint8Array
    {
        if ($end === null) {
            $end = $arr->length;
        }

        return Bytes::arraySlice($arr, $start, $end);
    }
}
