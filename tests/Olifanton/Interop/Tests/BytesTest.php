<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests;

use Olifanton\Interop\Bytes;
use Olifanton\TypedArrays\Uint8Array;
use PHPUnit\Framework\TestCase;

class BytesTest extends TestCase
{
    public function testReadNBytesUIntFromArray(): void
    {
        $stub = new Uint8Array([1, 2, 3, 4, 5, 6, 7, 8, 9, 0]);

        $this->assertEquals(0, Bytes::readNBytesUIntFromArray(0, $stub));
        $this->assertEquals(1, Bytes::readNBytesUIntFromArray(1, $stub));
        $this->assertEquals(258, Bytes::readNBytesUIntFromArray(2, $stub));
        $this->assertEquals(66051, Bytes::readNBytesUIntFromArray(3, $stub));
        $this->assertEquals(16909060, Bytes::readNBytesUIntFromArray(4, $stub));
        $this->assertEquals(4328719365, Bytes::readNBytesUIntFromArray(5, $stub));
    }

    public function testConcatBytes(): void
    {
        $a0 = new Uint8Array([0, 1]);
        $a1 = new Uint8Array([2, 3]);

        $result = Bytes::concatBytes($a0, $a1);
        $this->assertEquals("00010203", Bytes::bytesToHexString($result));
    }

    public function testBytesToBase64(): void
    {
        $stub = new Uint8Array([0, 1, 2]);
        $this->assertEquals("AAEC", Bytes::bytesToBase64($stub));
    }

    public function testBase64ToBytes(): void
    {
        $stub = new Uint8Array([0, 1, 2]);
        $this->assertTrue(Bytes::compareBytes($stub, Bytes::base64ToBytes("AAEC")));
    }
}
