<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc;

use Olifanton\Interop\Boc\Cell;
use Olifanton\Interop\Boc\DictSerializers;
use Olifanton\Interop\Boc\HashmapE;
use Olifanton\Interop\Bytes;
use PHPUnit\Framework\TestCase;

class HashmapETest extends TestCase
{
    /**
     * @throws \Throwable
     */
    public function testParse(): void
    {
        $cell = Cell::oneFromBoc('b5ee9c72410106010020000101c0010202c8020302016204050007befdf2180007a68054c00007a08090c08d16037d');
        $serializers = DictSerializers::intKey(false)->combine(DictSerializers::uintValue(16, false));
        $dict = HashmapE::parse(16, $cell, $serializers);

        $expectedDict = (new HashmapE(16, $serializers))
            ->set(13, 169)
            ->set(17, 289)
            ->set(239, 57121);

        foreach ($expectedDict->keys() as $key) {
            $this->assertEquals(
                $expectedDict->get($key),
                $dict->get($key),
                sprintf("Key: %s", $key),
            );
        }
    }

    /**
     * @throws \Throwable
     */
    public function testSerialize(): void
    {
        $dict = (new HashmapE(
            16,
            DictSerializers::intKey(false)->combine(DictSerializers::uintValue(16, false))
        ))
            ->set(13, 169)
            ->set(17, 289)
            ->set(239, 57121);
        $cell = $dict->cell();

        $this->assertEquals(
            "36580c6ea4f3dd0dbce3693b76d6d7f236877cfd9fbc5bd8faa647761f2d1afd",
            Bytes::bytesToHexString($cell->hash()),
        );
        $this->assertEquals(
            <<<FIFT_PRINT
            x{C_}
             x{C8}
              x{62_}
               x{A68054C_}
               x{A08090C_}
              x{BEFDF21}
            FIFT_PRINT,
            trim($cell->print()),
        );
    }
}
