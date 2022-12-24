<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Olifanton\TypedArrays\Uint8Array;

/**
 * Checksums helper
 */
class Checksum
{
    private const POLY_32 = 0x82f63b78;
    private const POLY_16 = 0x1021;

    /**
     * Calculates CRC32C checksum of given Uint8Array.
     */
    public static final function crc32c(Uint8Array $bytes): Uint8Array
    {
        $intCrc = self::crc32cInternal(0, $bytes);
        $arr = new Uint8Array(4);
        $arr[0] = $intCrc >> 24;
        $arr[1] = $intCrc >> 16;
        $arr[2] = $intCrc >> 8;
        $arr[3] = $intCrc;

        $tmpArray = [];

        for ($i = 0; $i < 4; $i++) {
            $tmpArray[] = $arr[$i];
        }

        return new Uint8Array(array_reverse($tmpArray));
    }

    /**
     * Calculates CRC16 checksum of given Uint8Array.
     */
    public static final function crc16(Uint8Array $bytes): Uint8Array
    {
        $reg = 0;
        $message = new Uint8Array($bytes->length + 2);
        $message->set($bytes);

        for ($i = 0; $i < $message->length; $i++) {
            $byte = $message[$i];
            $mask = 0x80;

            while ($mask > 0) {
                $reg <<= 1;

                if ($byte & $mask) {
                    $reg += 1;
                }

                $mask >>= 1;

                if ($reg > 0xffff) {
                    $reg &= 0xffff;
                    $reg ^= self::POLY_16;
                }
            }
        }

        return new Uint8Array([(int)floor($reg / 256), $reg % 256]);
    }

    private static function crc32cInternal(int $crc, Uint8Array $bytes): int
    {
        $crc ^= 0xffffffff;

        for ($n = 0; $n < $bytes->length; $n++) {
            $crc ^= $bytes[$n];

            for ($i = 0; $i < 8; $i++) {
                $crc = $crc & 1 ? (self::rrr($crc, 1)) ^ self::POLY_32 : self::rrr($crc, 1);
            }
        }

        return $crc ^ 0xffffffff;
    }

    private static function rrr(int $v, int $n): int
    {
        return ($v & 0xffffffff) >> ($n & 0x1f);
    }
}
