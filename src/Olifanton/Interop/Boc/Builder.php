<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Address;
use Olifanton\Interop\Boc\Exceptions\BitStringException;
use Olifanton\Interop\Boc\Exceptions\CellException;
use Olifanton\Interop\Boc\Exceptions\HashmapException;
use Olifanton\Interop\Boc\Exceptions\SliceException;
use Olifanton\TypedArrays\Uint8Array;

/**
 * Cell builder
 *
 * @phpstan-type BitLike 0|1|bool
 */
class Builder
{
    private Cell $cell;

    public function __construct()
    {
        $this->cell = new Cell();
    }

    /**
     * Writes another Cell.
     *
     * @throws CellException
     */
    public function writeCell(Cell $otherCell): self
    {
        $this->cell->writeCell($otherCell);

        return $this;
    }

    /**
     * Writes bit.
     *
     * @throws BitStringException
     */
    public function writeBit(int | bool $b): self
    {
        $this->cell->bits->writeBit($b);

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
        $this->cell->bits->writeBitArray($ba);

        return $this;
    }

    /**
     * Writes $bitLength-bit unsigned integer.
     *
     * @throws BitStringException
     */
    public function writeUint(int | BigInteger $number, int $bitLength): self
    {
        $this->cell->bits->writeUint($number, $bitLength);

        return $this;
    }

    /**
     * @throws BitStringException
     */
    public function writeMaybeUint(int | BigInteger | null $number, int $bitLength): self
    {
        if (!is_null($number)) {
            $this->cell->bits->writeBit(1);
            $this->cell->bits->writeUint($number, $bitLength);
        } else {
            $this->cell->bits->writeBit(0);
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
        $this->cell->bits->writeInt($number, $bitLength);

        return $this;
    }

    /**
     * @throws BitStringException
     */
    public function writeMaybeInt(int | BigInteger | null $number, int $bitLength): self
    {
        if (!is_null($number)) {
            $this->writeBit(1);
            $this->cell->bits->writeInt($number, $bitLength);
        } else {
            $this->writeBit(0);
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
        $this->cell->bits->writeUint8($ui8);

        return $this;
    }

    /**
     * @throws BitStringException
     */
    public function writeMaybeUint8(?int $ui8): self
    {
        if (!is_null($ui8)) {
            $this->cell->bits->writeBit(1);
            $this->cell->bits->writeUint8($ui8);
        } else {
            $this->cell->bits->writeBit(0);
        }

        return $this;
    }

    /**
     * Write array of unsigned 8-bit integers.
     *
     * @throws BitStringException
     */
    public function writeBytes(Uint8Array $ui8): self
    {
        $this->cell->bits->writeBytes($ui8);

        return $this;
    }

    /**
     * Writes UTF-8 string.
     *
     * @throws BitStringException
     */
    public function writeString(string $value): self
    {
        $this->cell->bits->writeString($value);

        return $this;
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
        $this->cell->bits->writeCoins($amount);

        return $this;
    }

    /**
     * @throws BitStringException
     */
    public function writeMaybeCoins(int | BigInteger | null $amount): self
    {
        if (!is_null($amount)) {
            $this->cell->bits->writeBit(1);
            $this->cell->bits->writeCoins($amount);
        } else {
            $this->cell->bits->writeBit(0);
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
        $this->cell->bits->writeAddress($address);

        return $this;
    }

    /**
     * Writes BitString to current builder.
     *
     * @throws BitStringException
     */
    public function writeBitString(BitString $anotherBitString): self
    {
        $this->cell->bits->writeBitString($anotherBitString);

        return $this;
    }

    public function writeRef(Cell $cell): self
    {
        if (count($this->cell->refs) === 4) { // @phpstan-ignore-line
            throw new \RuntimeException("Refs overflow");
        }

        $this->cell->refs[] = $cell;

        return $this;
    }

    /**
     * @throws BitStringException
     */
    public function writeMaybeRef(?Cell $cell): self
    {
        if ($cell) {
            $this->writeBit(1);
            $this->writeRef($cell);
        } else {
            $this->writeBit(0);
        }

        return $this;
    }

    /**
     * @throws SliceException
     */
    public function writeSlice(Slice $slice): self
    {
        try {
            $this->writeBitArray(array_slice($slice->getRemainingBits(), 0, $slice->getUsedBits()));
        } catch (BitStringException $e) {
            throw new SliceException($e->getMessage(), $e->getCode(), $e);
        }

        $refCount = $slice->getRefsCount();

        if ($refCount > 0) {
            foreach ($slice->getRefs() as $ref) {
                $this->writeRef($ref);
            }
        }

        return $this;
    }

    /**
     * @throws CellException
     * @throws HashmapException
     * @throws SliceException
     */
    public function writeDict(HashmapE $dict): self
    {
        $this->writeSlice($dict->cell()->beginParse());

        return $this;
    }

    public function cell(): Cell
    {
        return $this->cell;
    }
}
