<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc\Helpers;

use Olifanton\Interop\Boc\Helpers\BitHelper;
use PHPUnit\Framework\TestCase;

class BitHelperTest extends TestCase
{
    public function testAlignBits(): void
    {
        $this->assertEquals(8, BitHelper::alignBits(1));
        $this->assertEquals(8, BitHelper::alignBits(7));
        $this->assertEquals(16, BitHelper::alignBits(12));
        $this->assertEquals(24, BitHelper::alignBits(18));
        $this->assertEquals(32, BitHelper::alignBits(25));
    }
}
