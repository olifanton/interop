<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Boc\Exceptions\BitStringException;
use Olifanton\Interop\Boc\Exceptions\SliceException;
use Olifanton\TypedArrays\Uint8Array;
use Olifanton\Interop\Address;
use Olifanton\Interop\Bytes;

/**
 * Slice of Cell
 */
class Slice
{
    private int $readCursor = 0;

    private int $refCursor = 0;

    /**
     * @param Slice[] $refs
     */
    public function __construct(private readonly Uint8Array $array, private readonly int $length, private readonly array $refs)
    {
    }

    public function getFreeBits(): int
    {
        return $this->length - $this->readCursor;
    }

    /**
     * @return bool A bit value at position `$n`
     * @throws SliceException
     */
    public function get(int $n): bool
    {
        $this->checkRange($n);

        return ($this->array[($n / 8) | 0] & (1 << (7 - ($n % 8)))) > 0;
    }

    /**
     * Reads a bit and moves the cursor
     *
     * @return bool
     * @throws SliceException
     */
    public function loadBit(): bool
    {
        $result = $this->get($this->readCursor);
        $this->readCursor++;

        return $result;
    }

    /**
     * Reads bit array
     *
     * @throws SliceException
     */
    public function loadBits(int $bitLength): Uint8Array
    {
        $result = new BitString($bitLength);

        try {
            for ($i = 0; $i < $bitLength; $i++) {
                $result->writeBit($this->loadBit());
            }
        } catch (BitStringException $e) {
            throw new SliceException($e->getMessage(), $e->getCode(), $e);
        }

        return $result->getImmutableArray();
    }

    /**
     * Reads unsigned integer
     *
     * @throws SliceException
     */
    public function loadUint(int $bitLength): BigInteger
    {
        if ($bitLength < 1) {
            throw new SliceException("Incorrect bitLength");
        }

        $s = "";

        for ($i = 0; $i < $bitLength; $i++) {
            $s .= ($this->loadBit() ? "1" : "0");
        }

        return BigInteger::fromBase($s, 2);
    }

    /**
     * Reads signed integer
     *
     * @throws SliceException
     */
    public function loadInt(int $bitLength): BigInteger
    {
        if ($bitLength < 1) {
            throw new SliceException("Incorrect bitLength");
        }

        $sign = $this->loadBit();

        if ($bitLength === 1) {
            return $sign ? BigInteger::one()->negated() : BigInteger::zero();
        }

        $number = $this->loadUint($bitLength - 1);

        if ($sign) {
            $b = BigInteger::of(2);
            $nb = $b->power($bitLength - 1);
            $number = $number->minus($nb);
        }

        return $number;
    }

    /**
     * @throws SliceException
     */
    public function loadVarUint(int $bitLength): BigInteger
    {
        $len = $this->loadUint(strlen(BigInteger::of($bitLength)->toBase(2)) - 1);

        if ($len->isZero()) {
            return BigInteger::zero();
        }

        return $this->loadUint($len->toInt() * 8);
    }

    /**
     * @throws SliceException
     */
    public function loadCoins(): BigInteger
    {
        return $this->loadVarUint(16);
    }

    /**
     * @throws SliceException
     */
    public function loadAddress(): ?Address
    {
        $b = $this->loadUint(2);
        if ($b->isZero()) {
            return Address::NONE;
        }

        if ($b->toInt() !== 2) {
            throw new SliceException("Unsupported address type");
        }

        if ($this->loadBit()) {
            throw new SliceException("Unsupported address type");
        }

        $wc = $this->loadInt(8)->toInt();
        $hashPart = $this->loadBits(256);

        return new Address($wc . ':' . Bytes::bytesToHexString($hashPart));
    }

    /**
     * @throws SliceException
     */
    public function loadRef(): Slice
    {
        if ($this->refCursor >= 4) {
            throw new SliceException("Refs overflow");
        }

        $result = $this->refs[$this->refCursor];
        $this->refCursor++;

        return $result;
    }

    /**
     * @throws SliceException
     */
    private function checkRange(int $n): void
    {
        if ($n > $this->length) {
            throw new SliceException("BitString overflow");
        }
    }
}
