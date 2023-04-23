<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc\Helpers;

use Olifanton\Interop\Boc\Builder;
use Olifanton\Interop\Boc\Helpers\TypedArrayHelper;
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
            $cell->bits->getImmutableArray(),
            $sliced,
        );
    }
}
