<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Boc\Builder;
use Olifanton\Interop\Boc\Cell;
use Olifanton\Interop\Boc\HashmapE;
use Olifanton\Interop\Boc\Slice;
use Olifanton\Interop\Bytes;
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

    /**
     * @throws \Throwable
     */
    public function testSkipBits(): void
    {
        $slice = $this->getInstance(CellFactory::getCellWithInt(-1, 1));
        $slice->skipBits(2);
        $this->assertFalse($slice->loadBit());
    }

    /**
     * @throws \Throwable
     */
    public function testGetRefsCount(): void
    {
        $cell = CellFactory::getCellWithInt(-1, 1);
        $cell->refs[] = CellFactory::getCellWithInt(-1, 1);

        $slice = $this->getInstance($cell);
        $this->assertEquals(1, $slice->getRefsCount());
    }

    /**
     * @throws \Throwable
     */
    public function testLoadString(): void
    {
        $str = "Lorem ipsum dolor sit amet consectetur adipisicing elit 😃😃😄😇🤪🤪🙁😤😨👏☝️👍👨‍👩";
        $slice = (new Builder())->writeString($str)->cell()->beginParse();

        $this
            ->assertEquals(
                $str,
                $slice->loadString(),
            );
    }

    /**
     * @throws \Throwable
     */
    public function testLoadStringSize(): void
    {
        $str = "Lorem ipsum dolor sit amet";
        $slice = (new Builder())->writeString($str)->writeBit(1)->cell()->beginParse();

        $this
            ->assertEquals(
                $str,
                $slice->loadString(26 * 8),
            );
        $this
            ->assertEquals(1, $slice->loadBit());
    }

    /**
     * @throws \Throwable
     */
    public function testSkipEmptyDict(): void
    {
        $dict = new HashmapE(16);
        $ref = new Cell();
        $builder = (new Builder())
            ->writeDict($dict)
            ->writeBitArray([1, 1])
            ->writeRef($ref);
        $slice = $builder->cell()->beginParse();

        $this->assertEquals(
            [false, true, true],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
                $slice->getRemainingBits()[2],
            ],
        );
        $this->assertEquals(1, $slice->getRemainingRefsCount());

        $slice->skipDict();

        $this->assertEquals(
            [1, 1],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
            ],
        );
        $this->assertEquals(1, $slice->getRemainingRefsCount());
    }

    /**
     * @throws \Throwable
     */
    public function testSkipFilledDict(): void
    {
        $dict = new HashmapE(1);
        $dict->set([0], new Cell());
        $dict->set([1], new Cell());

        $ref = new Cell();
        $builder = (new Builder())
            ->writeDict($dict)
            ->writeBitArray([1, 1])
            ->writeRef($ref);
        $slice = $builder->cell()->beginParse();

        $this->assertEquals(
            [true, true, true],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
                $slice->getRemainingBits()[2],
            ],
        );
        $this->assertEquals(2, $slice->getRemainingRefsCount());

        $slice->skipDict();

        $this->assertEquals(
            [1, 1],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
            ],
        );
        $this->assertEquals(1, $slice->getRemainingRefsCount());
    }

    /**
     * @throws \Throwable
     */
    public function testLoadEmptyDict(): void
    {
        $dict = new HashmapE(16);
        $ref = new Cell();
        $builder = (new Builder())
            ->writeDict($dict)
            ->writeBitArray([1, 1])
            ->writeRef($ref);
        $slice = $builder->cell()->beginParse();

        $this->assertEquals(
            [false, true, true],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
                $slice->getRemainingBits()[2],
            ],
        );
        $this->assertEquals(1, $slice->getRemainingRefsCount());

        $dict = $slice->loadDict(16);

        $this->assertEquals(
            [1, 1],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
            ],
        );
        $this->assertEquals(1, $slice->getRemainingRefsCount());

        $this->assertEquals([], $dict->keys());
    }

    /**
     * @throws \Throwable
     */
    public function testLoadFilledDict(): void
    {
        $dict = new HashmapE(2);
        $dict->set([0, 0], (new Builder())->writeInt(1, 32)->cell());
        $dict->set([0, 1], (new Builder())->writeInt(2, 32)->cell());

        $ref = new Cell();
        $builder = (new Builder())
            ->writeDict($dict)
            ->writeBitArray([1, 1])
            ->writeRef($ref);
        $slice = $builder->cell()->beginParse();

        $this->assertEquals(
            [true, true, true],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
                $slice->getRemainingBits()[2],
            ],
        );
        $this->assertEquals(2, $slice->getRemainingRefsCount());

        $dict = $slice->loadDict(2);

        $this->assertEquals(
            [true, true],
            [
                $slice->getRemainingBits()[0],
                $slice->getRemainingBits()[1],
            ],
        );
        $this->assertEquals(1, $slice->getRemainingRefsCount());

        $this->assertEquals(
            BigInteger::of(1),
            $dict->get([0, 0])->beginParse()->loadInt(32),
        );
        $this->assertEquals(
            BigInteger::of(2),
            $dict->get([0, 1])->beginParse()->loadInt(32),
        );
    }

    /**
     * @throws \Throwable
     */
    public function testPreloadBit(): void
    {
        $slice = (new Builder())
            ->writeBit(1)
            ->writeBit(0)
            ->writeBit(1)
            ->cell()
            ->beginParse();

        $this->assertTrue($slice->preloadBit());
        $this->assertTrue($slice->preloadBit());
        $this->assertTrue($slice->preloadBit());
        $this->assertTrue($slice->preloadBit());

        $b0 = $slice->loadBit();
        $this->assertTrue($b0);

        $this->assertFalse($slice->preloadBit());
        $this->assertFalse($slice->preloadBit());
        $this->assertFalse($slice->preloadBit());
        $this->assertFalse($slice->preloadBit());

        $b1 = $slice->loadBit();
        $this->assertFalse($b1);

        $this->assertTrue($slice->preloadBit());
        $this->assertTrue($slice->preloadBit());
        $this->assertTrue($slice->preloadBit());
        $this->assertTrue($slice->preloadBit());

        $b2 = $slice->loadBit();
        $this->assertTrue($b2);
    }

    /**
     * @throws \Throwable
     */
    public function testPreloadBits(): void
    {
        $slice = (new Builder())
            ->writeBit(1)
            ->writeBit(0)
            ->writeBit(1)
            ->writeBit(0)
            ->cell()
            ->beginParse();

        $bits = $slice->preloadBits(4);
        $this->assertEquals(
            "a0",
            Bytes::bytesToHexString($bits),
        );

        $this->assertTrue($slice->loadBit());
    }

    /**
     * @throws \Throwable
     */
    public function testPreloadUint(): void
    {
        $slice = (new Builder())
            ->writeUint(999, 32)
            ->cell()
            ->beginParse();

        $this->assertEquals(999, $slice->preloadUint(32)->toInt());
        $this->assertEquals(999, $slice->preloadUint(32)->toInt());
        $this->assertEquals(999, $slice->preloadUint(32)->toInt());
        $this->assertEquals(999, $slice->preloadUint(32)->toInt());
    }
}
