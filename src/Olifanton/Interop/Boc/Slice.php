<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Boc\Exceptions\BitStringException;
use Olifanton\Interop\Boc\Exceptions\HashmapException;
use Olifanton\Interop\Boc\Exceptions\SliceException;
use Olifanton\TypedArrays\Uint8Array;
use Olifanton\Interop\Address;
use Olifanton\Interop\Bytes;

/**
 * Slice of Cell
 *
 * @phpstan-type BitLike 0|1|bool
 */
class Slice
{
    private int $readCursor = 0;

    private int $refCursor = 0;

    private int $usedBits;

    /**
     * @param int $length Length in bits
     * @param Cell[] $refs
     */
    public function __construct(
        private readonly Uint8Array $array,
        private readonly int $length,
        private readonly array $refs,
        ?int $usedBits = null,
    )
    {
        $this->usedBits = $usedBits ?? Cell::SIZE;
    }

    public function getFreeBits(): int
    {
        return $this->length - $this->readCursor;
    }

    public function getUsedBits(): int
    {
        return $this->usedBits - $this->readCursor;
    }

    /**
     * @return Cell[]
     */
    public function getRefs(): array
    {
        return $this->refs;
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
        // @codeCoverageIgnoreStart
        } catch (BitStringException $e) {
            throw new SliceException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd

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
            throw new SliceException("Incorrect bitLength: $bitLength");
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
            throw new SliceException("Incorrect bitLength: $bitLength");
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
            // @codeCoverageIgnoreStart
            throw new SliceException("Unsupported address type");
            // @codeCoverageIgnoreEnd
        }

        if ($this->loadBit()) {
            // @codeCoverageIgnoreStart
            throw new SliceException("Unsupported address type");
            // @codeCoverageIgnoreEnd
        }

        $wc = $this->loadInt(8)->toInt();
        $hashPart = $this->loadBits(256);

        return new Address($wc . ':' . Bytes::bytesToHexString($hashPart));
    }

    /**
     * @throws SliceException
     */
    public function loadRef(): Cell
    {
        if ($this->refCursor >= 4) {
            // @codeCoverageIgnoreStart
            throw new SliceException("Refs overflow");
            // @codeCoverageIgnoreEnd
        }

        $result = $this->refs[$this->refCursor];
        $this->refCursor++;

        return $result;
    }

    /**
     * @throws SliceException
     */
    public function loadString(?int $bytes = null): string
    {
        $bytes = $bytes === null
            ? $this->loadBits($this->length)
            : $this->loadBits($bytes);

        return trim(Bytes::arrayToBytes($bytes), "\0");
    }

    /**
     * @throws BitStringException
     * @throws HashmapException
     * @throws SliceException
     */
    public function loadDict(int $keySize, ?DictSerializers $serializers = null): HashmapE
    {
        $dictConstructor = $this->loadBit();
        $isEmpty = !$dictConstructor;

        return !$isEmpty
            ? HashmapE::parse(
                $keySize,
                (new Builder())->writeBit($dictConstructor)->writeRef($this->loadRef())->cell(),
            )
            : new HashmapE($keySize, $serializers);
    }

    public function skipRefs(): self
    {
        $this->refCursor++;

        return $this;
    }

    public function skipBits(int $skipBits): self
    {
        $this->readCursor += $skipBits;

        return $this;
    }

    public function skipDict(): self
    {
        $isEmpty = !$this->loadBit();

        return !$isEmpty ? $this->skipRefs(1) : $this;
    }

    public function getRefsCount(): int
    {
        return count($this->refs);
    }

    /**
     * @return BitLike[]
     * @throws SliceException
     */
    public function remainingBits(): array
    {
        $result = [];
        $i = $this->readCursor;

        while ($this->inRange($i)) {
            $result[] = $this->get($i);
            $i++;
        }

        return $result;
    }

    /**
     * @throws SliceException
     */
    private function checkRange(int $n): void
    {
        if (!$this->inRange($n)) {
            // @codeCoverageIgnoreStart
            throw new SliceException("BitString overflow");
            // @codeCoverageIgnoreEnd
        }
    }

    private function inRange(int $n): bool
    {
        return !($n >= $this->length);
    }
}
