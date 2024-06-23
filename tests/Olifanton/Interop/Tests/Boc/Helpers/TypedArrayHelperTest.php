<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc\Helpers;

use Olifanton\Interop\Boc\Builder;
use Olifanton\Interop\Boc\Helpers\TypedArrayHelper;
use Olifanton\Interop\Bytes;
use Olifanton\TypedArrays\Uint8Array;
use PHPUnit\Framework\TestCase;

class TypedArrayHelperTest extends TestCase
{
    /**
     * @throws \Throwable
     */
    public function testSlice(): void
    {
        $cell = (new Builder())->writeBit(1)->writeString("foobar")->cell();
        $sliced = TypedArrayHelper::sliceUint8Array($cell->bits->getImmutableArray(), 0);

        $this->assertEquals(
            Bytes::bytesToBase64($cell->bits->getImmutableArray()),
            Bytes::bytesToBase64($sliced),
        );
        $this->assertNotSame(
            $cell->bits->getImmutableArray(),
            $sliced,
        );
    }

    /**
     * @throws \Throwable
     */
    public function testSliceHalf(): void
    {
        $arr = new Uint8Array([1, 0, 1, 1]);

        $slice0 = TypedArrayHelper::sliceUint8Array($arr, 0, 2);
        $slice1 = TypedArrayHelper::sliceUint8Array($arr, 2);

        $this->assertEquals(2, $slice0->length);
        $this->assertEquals(2, $slice1->length);

        $this->assertEquals(1, $slice0[0]);
        $this->assertEquals(0, $slice0[1]);

        $this->assertEquals(1, $slice1[0]);
        $this->assertEquals(1, $slice1[1]);
    }

    public function testSliceNotSame(): void
    {
        $arr = new Uint8Array([1, 0, 1, 1]);
        $slice = TypedArrayHelper::sliceUint8Array($arr, 0);

        $this->assertEquals(4, $arr->length);
        $this->assertEquals(4, $slice->length);

        $this->assertEquals(
            Bytes::bytesToBase64($arr),
            Bytes::bytesToBase64($slice),
        );
        $this->assertNotSame($arr, $slice);
    }
}
