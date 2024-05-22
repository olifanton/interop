<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Boc\Exceptions\BitStringException;
use Olifanton\Interop\Address;
use Olifanton\Interop\Bytes;
use Olifanton\Interop\Units;
use Olifanton\TypedArrays\Uint8Array;

/**
 * Bit String.
 *
 * `BitString` is a class that allows you to manipulate binary data. `BitString` is at the heart of the PHP representation of TVM Cells. `BitString` is memory optimized for storing binary data.
 * Internally, BitString uses implementation of `Uint8Array` provided by `olifanton/typed-arrays` package and is used as the base type for transferring binary data between parts of the Olifanton libraries.
 *
 * The BitString instance is created with a strictly fixed length. `write%` (writeBit, writeUint, ...) methods move the internal cursor. If you try to write a value that exceeds the length of the free bits, `BitStringException` exception will be thrown.
 *
 * @phpstan-type BitLike 0|1|bool
 */
class BitString implements \Stringable
{
    private int $length;

    private int $cursor = 0;

    private Uint8Array $array;

    protected ?Cell $_cell = null;

    private ?\Closure $cellProxyInvalidator = null;

    /**
     * @param int $length length of Uint8Array. Default value for TVM Cell: _1023_
     */
    public function __construct(int $length)
    {
        $this->length = $length;
        $this->array = new Uint8Array(array_fill(
            0,
            self::getUint8ArrayLength($length),
            0
        ));
    }

    public static function empty(): self
    {
        return new self(Cell::SIZE);
    }

    /**
     * Returns unused bits length of BitString.
     */
    public function getFreeBits(): int
    {
        return $this->length - $this->cursor;
    }

    /**
     * Returns used bits length of BitString.
     */
    public function getUsedBits(): int
    {
        return $this->cursor;
    }

    /**
     * Returns used bytes length of BitString.
     */
    public function getUsedBytes(): int
    {
        return (int)ceil($this->cursor / 8);
    }

    /**
     * Returns a bit value at `$n` position.
     *
     * @throws BitStringException
     */
    public function get(int $n): bool
    {
        $this->checkRange($n);

        return ($this->array[(int)($n / 8) | 0] & (1 << (7 - ($n % 8)))) > 0;
    }

    /**
     * Sets a bit value to 1 at position `$n`.
     *
     * @throws BitStringException
     */
    public function on(int $n): void
    {
        $this->checkRange($n);
        $this->array[(int)($n / 8) | 0] |= 1 << (7 - ($n % 8));
        $this->invalidateCell();
    }

    /**
     * Sets a bit value to 0 at position `$n`.
     *
     * @throws BitStringException
     */
    public function off(int $n): void
    {
        $this->checkRange($n);
        $this->array[(int)($n / 8) | 0] &= ~(1 << (7 - ($n % 8)));
        $this->invalidateCell();
    }

    /**
     * Toggle (inverse) bit value at position `$n`.
     *
     * @throws BitStringException
     */
    public function toggle(int $n): void
    {
        $this->checkRange($n);
        $this->array[(int)($n / 8) | 0] ^= 1 << (7 - ($n % 8));
        $this->invalidateCell();
    }

    /**
     * Returns Generator of used bits.
     *
     * @throws BitStringException
     */
    public function iterate(): \Generator
    {
        $max = $this->cursor;

        for ($i = 0; $i < $max; $i++) {
            yield $this->get($i);
        }
    }

    /**
     * @return BitLike[]
     * @throws BitStringException
     */
    public function toBitsA(): array
    {
        return iterator_to_array($this->iterate());
    }

    /**
     * Writes bit and increase BitString internal cursor.
     *
     * @param BitLike|int|bool $b
     * @throws BitStringException
     */
    public function writeBit(int | bool $b): self
    {
        if ($this->cursor === $this->length) {
            throw new BitStringException("BitString overflow");
        }

        if ($b && $b > 0) {
            $this->on($this->cursor);
        } else {
            $this->off($this->cursor);
        }

        $this->cursor++;

        return $this;
    }

    /**
     * Writes array of bits.
     *
     * @param BitLike[]|array<0|1|bool> $ba
     * @throws BitStringException
     */
    public function writeBitArray(array $ba): self
    {
        foreach ($ba as $b) {
            $this->writeBit($b);
        }

        return $this;
    }

    /**
     * Writes $bitLength-bit unsigned integer.
     *
     * @throws BitStringException
     */
    public function writeUint(int | BigInteger $number, int $bitLength): self
    {
        if (!$number instanceof BigInteger) {
            $number = BigInteger::of($number);
        }

        if ($bitLength === 0 || strlen($number->toBase(2)) > $bitLength) {
            if ($number->toInt() === 0) {
                return $this;
            }

            throw new BitStringException("bitLength is too small for number, got number=" . $number . ", bitLength=" . $bitLength);
        }

        $s = $this->toBaseWithPadding($number, 2, $bitLength);

        foreach (str_split($s) as $char) {
            $this->writeBit($char === "1");
        }

        return $this;
    }

    /**
     * Writes $bitLength-bit signed integer.
     *
     * @throws BitStringException
     */
    public function writeInt(int | BigInteger $number, int $bitLength): self
    {
        if (!$number instanceof BigInteger) {
            $number = BigInteger::of($number);
        }

        if ($bitLength === 1) {
            if ($number->toInt() === -1) {
                $this->writeBit(true);

                return $this;
            }

            if ($number->toInt() === 0) {
                $this->writeBit(false);

                return $this;
            }

            throw new BitStringException("bitLength is too small for number");
        } else {
            if ($number->isNegative()) {
                $this->writeBit(true);
                $b = BigInteger::of(2);
                $nb = $b->power($bitLength - 1);
                $this->writeUint($nb->plus($number), $bitLength - 1);
            } else {
                $this->writeBit(false);
                $this->writeUint($number, $bitLength - 1);
            }
        }

        return $this;
    }

    /**
     * Alias of `writeUint()` method with predefined $bitLength parameter value.
     *
     * @throws BitStringException
     */
    public function writeUint8(int $ui8): self
    {
        return $this->writeUint($ui8, 8);
    }

    /**
     * Write array of unsigned 8-bit integers.
     *
     * @throws BitStringException
     */
    public function writeBytes(Uint8Array $ui8): self
    {
        for ($i = 0; $i < $ui8->length; $i++) {
            $this->writeUint8($ui8[$i]);
        }

        return $this;
    }

    /**
     * Writes UTF-8 string.
     *
     * @throws BitStringException
     */
    public function writeString(string $value): self
    {
        return $this->writeBytes(new Uint8Array(array_values(unpack('C*', $value))));
    }

    /**
     * Writes coins in nanotoncoins.
     *
     * 1 TON === 1000000000 (10^9) nanotoncoins.
     *
     * @see Units::toNano()
     * @param int|BigInteger $amount in nanotoncoins.
     * @throws BitStringException
     */
    public function writeCoins(int | BigInteger $amount): self
    {
        if (!$amount instanceof BigInteger) {
            $amount = BigInteger::of($amount);
        }

        if ($amount->toInt() === 0) {
            $this->writeUint(0, 4);
        } else {
            $l = (int)ceil((strlen($amount->toBase(16))) / 2);
            $this->writeUint($l, 4);
            $this->writeUint($amount, $l * 8);
        }

        return $this;
    }

    /**
     * Writes TON address.
     *
     * @throws BitStringException
     */
    public function writeAddress(?Address $address): self
    {
        if (!$address) {
            $this->writeUint(0, 2);
        } else {
            $this->writeUint(2, 2);
            $this->writeUint(0, 1);
            $this->writeInt($address->getWorkchain(), 8);
            $this->writeBytes($address->getHashPart());
        }

        return $this;
    }

    /**
     * Writes another BitString to this BitString.
     *
     * @throws BitStringException
     */
    public function writeBitString(BitString $anotherBitString): self
    {
        foreach ($anotherBitString->iterate() as $x) {
            $this->writeBit($x);
        }

        return $this;
    }

    /**
     * Clones this BitString and returns new BitString instance.
     */
    public function clone(): BitString
    {
        $result = new BitString(0);

        $result->array = $this->getImmutableArray();
        $result->length = $this->length;
        $result->cursor = $this->cursor;

        return $result;
    }

    /**
     * Returns hex string representation of BitString.
     *
     * @throws BitStringException
     */
    public function toHex(bool $fiftStyle = true): string
    {
        if ($this->cursor % 4 === 0) {
            $s = Bytes::bytesToHexString(
                Bytes::arraySlice(
                    $this->array,
                    0,
                    (int)ceil($this->cursor / 8)
                )
            );

            if ($this->cursor % 8 === 0) {
                return $fiftStyle ? strtoupper($s) : $s;
            }

            $s = substr($s, 0, strlen($s) - 1);

            return $fiftStyle ? strtoupper($s) : $s;
        }

        $temp = $this->clone();

        if (!$temp->getFreeBits()) {
            $temp = self::incLength($temp, $this->length + 1);
        }

        $temp->writeBit(1);

        while ($temp->cursor % 4 !== 0) {
            if (!$temp->getFreeBits()) {
                $temp = self::incLength($temp, $this->length + 1);
            }

            $temp->writeBit(0);
        }

        $hex = $temp->toHex($fiftStyle);

        return $hex . '_';
    }

    /**
     * Returns immutable copy of internal Uint8Array.
     */
    public function getImmutableArray(): Uint8Array
    {
        return Bytes::arraySlice($this->array, 0, self::getUint8ArrayLength($this->length));
    }

    /**
     * Returns size of BitString in bits.
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * @throws BitStringException
     */
    public function __toString(): string
    {
        return $this->toHex();
    }

    /**
     * @throws BitStringException
     * @ignore
     * @private
     */
    public function getTopUppedArray(): Uint8Array
    {
        $ret = $this->clone();
        $tu = (int)ceil($ret->cursor / 8) * 8 - $ret->cursor;

        if ($tu > 0) {
            $tu--;

            if (!$ret->getFreeBits()) {
                $ret = self::incLength($ret, $ret->length + 1);
            }

            $ret->writeBit(true);

            while ($tu > 0) {
                $tu--;

                if (!$ret->getFreeBits()) {
                    $ret = self::incLength($ret, $ret->length + 1);
                }

                $ret->writeBit(false);
            }
        }

        return Bytes::arraySlice($ret->array, 0, (int)ceil($ret->cursor / 8));
    }

    /**
     * @throws BitStringException
     * @ignore
     * @private
     */
    public function setTopUppedArray(Uint8Array $array, bool $fulfilledBytes = true): void
    {
        $this->length = $array->length * 8;
        $this->array = $array;
        $this->cursor = $this->length;

        if ($fulfilledBytes || !$this->length) {
            return;
        }

        $foundEndBit = false;

        for ($c = 0; $c < 7; $c++) {
            $this->cursor--;

            if ($this->get($this->cursor)) {
                $foundEndBit = true;
                $this->off($this->cursor);
                break;
            }
        }

        if (!$foundEndBit) {
            throw new BitStringException("Incorrect TopUppedArray");
        }
    }

    /**
     * @throws BitStringException
     */
    private function checkRange(int $n): void
    {
        if ($n >= $this->length) {
            throw new BitStringException("BitString overflow");
        }
    }

    private function toBaseWithPadding(BigInteger $number, int $base, int $padding): string
    {
        $str = $number->toBase($base);
        $needPad = $padding - strlen($str);

        if ($needPad > 0) {
            return str_pad($str, $padding, "0", STR_PAD_LEFT);
        }

        return $str;
    }

    private function invalidateCell(): void
    {
        if ($this->_cell) {
            if (!$this->cellProxyInvalidator) {
                $this->cellProxyInvalidator = (function () {
                    /** @noinspection PhpDynamicFieldDeclarationInspection */
                    $this->_hash = null; // @phpstan-ignore-line
                })(...);
            }

            $this->cellProxyInvalidator->call($this->_cell);
        }
    }

    private static function getUint8ArrayLength(int $bitStringLength): int
    {
        return (int)ceil($bitStringLength / 8);
    }

    private static function incLength(BitString $bitString, int $newLength): BitString
    {
        if ($newLength < $bitString->length) {
            throw new \OutOfRangeException();
        }

        $bitString = $bitString->clone();
        $bitString->length = $newLength;
        $tmpArr = $bitString->array;
        $bitString->array = new Uint8Array(self::getUint8ArrayLength($newLength));
        $bitString->array->set($tmpArr);

        return $bitString;
    }
}
