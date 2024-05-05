<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc;

use Olifanton\Interop\Address;
use Olifanton\Interop\Boc\BitString;
use Olifanton\Interop\Boc\Builder;
use Olifanton\Interop\Boc\Cell;
use Olifanton\Interop\Units;
use Olifanton\TypedArrays\Uint8Array;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    /**
     * @throws \Throwable
     */
    public function testComplex(): void
    {
        $cell = (new Builder())
            ->writeCell(
                (new Builder())->writeUint8(10)->cell(),
            )
            ->writeBit(1)
            ->writeBitArray([1, 0])
            ->writeUint(10, 32)
            ->writeString("foobar")
            ->writeInt(11, 16)
            ->writeBytes(new Uint8Array([1, 2, 3]))
            ->writeCoins(Units::toNano(1))
            ->writeAddress(Address::NONE)
            ->writeBitString(BitString::empty()->writeBit(1))
            ->cell();

        $this->assertInstanceOf(Cell::class, $cell);
    }

    /**
     * @throws \Throwable
     */
    public function testWriteMaybeRefNull(): void
    {
        $cell = (new Builder())
            ->writeMaybeRef(null)
            ->cell();

        $slice = $cell->beginParse();
        $this->assertFalse($slice->loadBit());
        $this->assertCount(0, $slice->getRefs());
    }

    /**
     * @throws \Throwable
     */
    public function testWriteMaybeRefNotNull(): void
    {
        $cell = (new Builder())
            ->writeMaybeRef(new Cell())
            ->cell();

        $slice = $cell->beginParse();
        $this->assertTrue($slice->loadBit());
        $this->assertCount(1, $slice->getRefs());
    }
}
