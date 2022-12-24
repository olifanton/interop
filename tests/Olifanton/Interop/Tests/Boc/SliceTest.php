<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Boc\Cell;
use Olifanton\Interop\Boc\Slice;
use Olifanton\Interop\Tests\Stubs\CellFactory;
use PHPUnit\Framework\TestCase;

class SliceTest extends TestCase
{
    private function getInstance(Cell $cell): Slice
    {
        return $cell->beginParse();
    }

    /**
     * @throws \Throwable
     */
    public function testGetFreeBits(): void
    {
        $slice = $this->getInstance(CellFactory::getCellWithUint(100500, 32));
        $this->assertEquals(1023, $slice->getFreeBits());

        $slice->loadBit();
        $this->assertEquals(1022, $slice->getFreeBits());
    }

    /**
     * @throws \Throwable
     */
    public function testLoadInt(): void
    {
        $slice = $this->getInstance(CellFactory::getCellWithInt(-1000, 32));
        $this->assertEquals(BigInteger::of(-1000), $slice->loadInt(32));
    }

    /**
     * @throws \Throwable
     */
    public function testLoadIntOneBitLength(): void
    {
        $slice = $this->getInstance(CellFactory::getCellWithInt(-1, 1));
        $this->assertEquals(BigInteger::of(-1), $slice->loadInt(1));
    }
}
