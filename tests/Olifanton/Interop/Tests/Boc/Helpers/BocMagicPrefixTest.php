<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc\Helpers;

use Olifanton\Interop\Boc\Helpers\BocMagicPrefix;
use Olifanton\Interop\Bytes;
use PHPUnit\Framework\TestCase;

class BocMagicPrefixTest extends TestCase
{
    public function testConstant(): void
    {
        $this->assertTrue(Bytes::compareBytes(Bytes::hexStringToBytes("b5ee9c72"), BocMagicPrefix::reachBocMagicPrefix()));
        $this->assertTrue(Bytes::compareBytes(Bytes::hexStringToBytes("68ff65f3"), BocMagicPrefix::leanBocMagicPrefix()));
        $this->assertTrue(Bytes::compareBytes(Bytes::hexStringToBytes("acc3a728"), BocMagicPrefix::leanBocMagicPrefixCRC()));
    }
}
