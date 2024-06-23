<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

use Olifanton\Interop\Bytes;
use Olifanton\TypedArrays\Uint8Array;

final class BocMagicPrefix
{
    private static ?Uint8Array $reachBocMagicPrefix = null;

    private static ?Uint8Array $leanBocMagicPrefix = null;

    private static ?Uint8Array $leanBocMagicPrefixCRC = null;

    public const REACH_BOC_MAGIC_PREFIX = "b5ee9c72";
    public const LEAN_BOC_MAGIC_PREFIX = "68ff65f3";
    public const LEAN_BOC_MAGIC_PREFIX_CRC = "acc3a728";

    public static function reachBocMagicPrefix(): Uint8Array
    {
        if (!self::$reachBocMagicPrefix) {
            self::$reachBocMagicPrefix = Bytes::hexStringToBytes(self::REACH_BOC_MAGIC_PREFIX);
        }

        return self::$reachBocMagicPrefix;
    }

    public static function leanBocMagicPrefix(): Uint8Array
    {
        if (!self::$leanBocMagicPrefix) {
            self::$leanBocMagicPrefix = Bytes::hexStringToBytes(self::LEAN_BOC_MAGIC_PREFIX);
        }

        return self::$leanBocMagicPrefix;
    }

    public static function leanBocMagicPrefixCRC(): Uint8Array
    {
        if (!self::$leanBocMagicPrefixCRC) {
            self::$leanBocMagicPrefixCRC = Bytes::hexStringToBytes(self::LEAN_BOC_MAGIC_PREFIX_CRC);
        }

        return self::$leanBocMagicPrefixCRC;
    }
}
