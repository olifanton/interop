<?php declare(strict_types=1);

namespace Olifanton\Interop;

use InvalidArgumentException;
use Olifanton\Interop\Helpers\OlifantonByteReader;
use Olifanton\TypedArrays\ArrayBuffer;
use Olifanton\TypedArrays\Uint16Array;
use Olifanton\TypedArrays\Uint32Array;
use Olifanton\TypedArrays\Uint8Array;

/**
 * Bytes helper
 *
 * This is a helper class for working with Uint8Array.
 */
class Bytes
{
    /**
     * Returns $n bytes from Uint8Array.
     */
    public static final function readNBytesUIntFromArray(int $n, Uint8Array $uint8Array): int
    {
        $res = 0;

        for ($i = 0; $i < $n; $i++) {
            $res *= 256;
            $res += $uint8Array->fGet($i);
        }

        return $res;
    }

    /**
     * Returns true if Uint8Array's $a and $b are equals.
     */
    public static final function compareBytes(Uint8Array $a, Uint8Array $b): bool
    {
        return self::arrayToBytes($a) === self::arrayToBytes($b); // @TODO: RAM using optimization
    }

    /**
     * Returns an immutable fragment from the given Uint8Array.
     */
    public static final function arraySlice(Uint8Array $bytes, int $start, int $end): Uint8Array
    {
        return new Uint8Array($bytes->buffer->slice($start, $end));
    }

    /**
     * Returns a new Uint8Array from the given ones.
     */
    public static final function concatBytes(Uint8Array $a, Uint8Array $b): Uint8Array
    {
        $aLength = $a->length;
        $bLength = $b->length;
        $c = new Uint8Array($aLength + $bLength);
        $i = 0;

        for ($j = 0; $j < $aLength; $j++) {
            $c->fSet($i, $a->fGet($j));
            $i++;
        }

        for ($j = 0; $j < $bLength; $j++) {
            $c->fSet($i, $b->fGet($j));
            $i++;
        }

        return $c;
    }

    /**
     * Returns a Uint8Array from a PHP byte string.
     *
     * Example:
     * ```php
     * $a = Bytes::stringToBytes('a');
     * $a[0] === 97; // True, because the ASCII code of `a` is 97 in decimal
     * ```
     */
    public static final function stringToBytes(string $str, int $size = 1): Uint8Array
    {
        $buf = null;
        $bufView = null;

        if ($size === 1) {
            $buf = new ArrayBuffer(strlen($str));
            $bufView = new Uint8Array($buf);
        }

        if ($size === 2) {
            $buf = new ArrayBuffer(strlen($str) * 2);
            $bufView = new Uint16Array($buf);
        }

        if ($size === 4) {
            $buf = new ArrayBuffer(strlen($str) * 4);
            $bufView = new Uint32Array($buf);
        }

        if ($buf === null) {
            throw new InvalidArgumentException("Unsupported size: $size");
        }

        for ($i = 0, $strLen = strlen($str); $i < $strLen; $i++) {
            $bufView[$i] = ord($str[$i]);
        }

        return new Uint8Array($bufView);
    }

    /**
     * Returns a Uint8Array from a hexadecimal string.
     *
     * Example:
     * ```php
     * $a = Bytes::hexStringToBytes('0a');
     * $a[0] === 10; // True
     * ```
     */
    public static final function hexStringToBytes(string $str): Uint8Array
    {
        $str = mb_strtolower($str);
        $length2 = strlen($str);

        if ($length2 % 2 !== 0) {
            throw new InvalidArgumentException("Hex string must have length a multiple of 2");
        }

        $length = $length2 / 2;
        $result = new Uint8Array($length);

        for ($i = 0; $i < $length; $i++) {
            $result->fSet($i, hexdec(substr($str, $i * 2, 2)));
        }

        return $result;
    }

    /**
     * Returns a hexadecimal string representation of the Uint8Array.
     *
     * Example:
     * ```php
     * $s = Bytes::bytesToHexString(new Uint8Array([10]));
     * $s === '0a'; // True
     * ```
     */
    public static final function bytesToHexString(Uint8Array $bytes): string
    {
        $result = [];

        for ($i = 0; $i < $bytes->length; $i++) {
            $result[] = str_pad(dechex($bytes->fGet($i)), 2, "0", STR_PAD_LEFT);
        }

        return implode("", $result);
    }

    public static final function bytesToArray(string $bytes): Uint8Array
    {
        $arr = new Uint8Array(strlen($bytes));

        foreach (str_split($bytes) as $i => $byte) {
            $arr->fSet($i, unpack("C", $byte)[1]);
        }

        return $arr;
    }

    /**
     * Returns PHP byte string from Uint8Array.
     */
    public static final function arrayToBytes(Uint8Array $arr): string
    {
        return OlifantonByteReader::getBytes($arr->buffer);
    }

    /**
     * Returns Uint8Array in Base64-encoded representation.
     */
    public static function bytesToBase64(Uint8Array $bytes): string
    {
        return base64_encode(OlifantonByteReader::getBytes($bytes->buffer));
    }

    public static final function base64ToBytes(string $base64): Uint8Array
    {
        $binaryString = base64_decode($base64);
        $length = strlen($binaryString);
        $bytes = new Uint8Array($length);

        for ($i = 0; $i < $length; $i++) {
            $bytes->fSet($i, ord($binaryString[$i]));
        }

        return $bytes;
    }

    public static function copyArrayToArray(Uint8Array $target, Uint8Array $source, int $targetStart = 0): void
    {
        $end = $source->length + $targetStart;

        for ($pos = $targetStart, $i = 0; $pos < $end; $pos++) {
            $target->fSet($pos, $source->fGet($i));
            $i++;
        }
    }
}
