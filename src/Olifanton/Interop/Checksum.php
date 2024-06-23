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
        $intCrc = self::crc32cInternal($bytes);
        $result = new Uint8Array(4);
        $result->fSet(0, $intCrc);
        $result->fSet(1, $intCrc >> 8);
        $result->fSet(2, $intCrc >> 16);
        $result->fSet(3, $intCrc >> 24);

        return $result;
    }

    /**
     * Calculates CRC16 checksum of given Uint8Array.
     */
    public static final function crc16(Uint8Array $bytes): Uint8Array
    {
        $reg = 0;
        $message = new Uint8Array($bytes->length + 2);

        for ($i = 0; $i < $bytes->length; $i++) {
            $message->fSet($i, $bytes->fGet($i));
        }

        for ($i = 0; $i < $message->length; $i++) {
            $byte = $message->fGet($i);
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

    private static function crc32cInternal(Uint8Array $bytes): int
    {
        $crc = 0 ^ 0xffffffff;
        $length = $bytes->length;

        for ($n = 0; $n < $length; $n++) {
            $crc ^= $bytes->fGet($n);

            for ($i = 0; $i < 8; $i++) {
                $rrr = ($crc & 0xffffffff) >> 1;
                $crc = $crc & 1
                    ? $rrr ^ self::POLY_32
                    : $rrr;
            }
        }

        return $crc ^ 0xffffffff;
    }
}
