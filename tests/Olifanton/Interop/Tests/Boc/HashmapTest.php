<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Address;
use Olifanton\Interop\Boc\Builder;
use Olifanton\Interop\Boc\Cell;
use Olifanton\Interop\Boc\Hashmap;
use Olifanton\Interop\Boc\DictSerializers;
use Olifanton\Interop\Bytes;
use PHPUnit\Framework\TestCase;

class HashmapTest extends TestCase
{
    /**
     * @throws \Throwable
     */
    public function testSimpleWith16bKey(): void
    {
        $dict = new Hashmap(
            16,
            DictSerializers::uintKey(
                valueSerializer: static fn (int $v): Cell => (new Builder())->writeUint($v, 16)->cell(),
                valueDeserializer: static fn (Cell $v): int => $v->beginParse()->loadUint(16)->toInt(),
            ),
        );

        $dict
            ->set(100, 2000)
            ->set(300, 40000)
            ->set(5, 6000);

        $this->assertEquals(
            2000,
            $dict->get(100),
        );
        $this->assertEquals(
            40000,
            $dict->get(300),
        );
        $this->assertEquals(
            6000,
            $dict->get(5),
        );

        $cell = $dict->cell();

        $this->assertEquals(
            "a72254ee2993546ed970201f8603be066f5481b3752ac247a0c37de4616a5731",
            Bytes::bytesToHexString($cell->hash()),
        );
        $this
            ->assertEquals(
                <<<FIFT_PRINT
                x{C7}
                 x{4}
                  x{B0A2EE1_}
                  x{B480FA1_}
                 x{A0B27102_}
                FIFT_PRINT,
                trim($cell->print()),
            );
    }

    /**
     * @throws \Throwable
     */
    public function testSimpleWith256bKey(): void
    {
        $dict = new Hashmap(
            256,
            DictSerializers::uintKey(
                valueSerializer: static fn (int $v): Cell => (new Builder())->writeUint($v, 16)->cell(),
                valueDeserializer: static fn (Cell $v): int => $v->beginParse()->loadUint(16)->toInt(),
            ),
        );

        $dict
            ->set(BigInteger::of("23570985008687907853269984665640564039457584007913129"), 2)
            ->set(BigInteger::of("11579208923731619542357098500868790785326998466564056"), 1)
            ->set(BigInteger::of("579208923731619542357098500868"), 3);

        $this->assertEquals(
            1,
            $dict->get(BigInteger::of("11579208923731619542357098500868790785326998466564056")),
        );
        $this->assertEquals(
            2,
            $dict->get(BigInteger::of("23570985008687907853269984665640564039457584007913129")),
        );
        $this->assertEquals(
            3,
            $dict->get(BigInteger::of("579208923731619542357098500868")),
        );

        $cell = $dict->cell();

        $this->assertEquals(
            "68901afd12fadef92bec21a6767f186ecc4a439ec619cc37f600a9cbbd1733fa",
            Bytes::bytesToHexString($cell->hash()),
        );
        $this
            ->assertEquals(
                <<<FIFT_PRINT
                x{C52}
                 x{2_}
                  x{AB0000000000000000001D3E1991BE900352E7CE584C10000E_}
                  x{AB3BCB43D769F762A89D41EEDEC1FA91024609C4DE3F600006_}
                 x{AB7DFFD846195B50CA81C2404C64C8741A4DF47F993D520005_}
                FIFT_PRINT,
                trim($cell->print()),
            );
    }

    public function testIterator(): void
    {
        $dict = new Hashmap(
            16,
            DictSerializers::uintKey(
                valueSerializer: static fn (int $v): Cell => (new Builder())->writeUint($v, 16)->cell(),
                valueDeserializer: static fn (Cell $v): int => $v->beginParse()->loadUint(16)->toInt(),
            ),
        );

        $cases = [
            100 => 2000,
            300 => 40000,
            5 => 6000,
        ];

        foreach ($cases as $k => $v) {
            $dict->set($k, $v);
        }

        foreach ($dict as $k => $v) {
            /** @var BigInteger $k */
            $this->assertEquals($cases[$k->toInt()], $dict->get($k));
        }
    }

    public function testConstructIllegalKeySize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Hashmap(0);
    }

    public function testGetNotFoundKey(): void
    {
        $dict = new Hashmap(2);
        $this->assertNull($dict->get([1, 0]));
    }

    public function testHasNotFoundKey(): void
    {
        $dict = new Hashmap(2);
        $this->assertFalse($dict->has([1, 0]));
    }

    /**
     * @throws \Throwable
     */
    public function testSetAndGetAndHas(): void
    {
        $dict = new Hashmap(2);
        $dict->set([0, 1], (new Builder())->writeBit(1)->cell());

        $this->assertTrue($dict->has([0, 1]));
        $this->assertFalse($dict->has([1, 0]));

        /** @var Cell $v */
        $v = $dict->get([0, 1]);
        $this->assertEquals(
            true,
            $v->beginParse()->loadBit()
        );
    }

    /**
     * @throws \Throwable
     */
    public function testReplace(): void
    {
        $dict = new Hashmap(2);

        $dict->replace([0, 1], (new Builder())->writeBit(1)->cell());
        $this->assertFalse($dict->has([0, 1]));

        $dict->set([0, 1], (new Builder())->writeBit(1)->cell());
        $dict->replace([0, 1], (new Builder())->writeBit(0)->cell());

        $this->assertTrue($dict->has([0, 1]));

        /** @var Cell $v */
        $v = $dict->get([0, 1]);
        $this->assertEquals(false, $v->beginParse()->loadBit());
    }

    /**
     * @throws \Throwable
     */
    public function testAdd(): void
    {
        $dict = new Hashmap(2);
        $dict->add([0, 1], (new Builder())->writeBit(1)->cell());
        $dict->add([0, 1], (new Builder())->writeBit(0)->cell());

        /** @var Cell $v */
        $v = $dict->get([0, 1]);
        $this->assertEquals(true, $v->beginParse()->loadBit());
    }

    /**
     * @throws \Throwable
     */
    public function testDelete(): void
    {
        $dict = new Hashmap(2);
        $dict->add([0, 1], (new Builder())->writeBit(1)->cell());

        $this->assertTrue($dict->has([0, 1]));
        $dict->delete([0, 1]);
        $this->assertFalse($dict->has([0, 1]));
    }

    /**
     * @throws \Throwable
     */
    public function testIsEmpty(): void
    {
        $dict = new Hashmap(2);
        $this->assertTrue($dict->isEmpty());

        $dict->add([0, 1], (new Builder())->writeBit(1)->cell());
        $this->assertFalse($dict->isEmpty());
    }

    /**
     * @throws \Throwable
     */
    public function testParseKeys(): void
    {
        $expectedKeys = [0, 1, 9, 10, 12, 14, 15, 16, 17, 32, 34, 36, -1001, -1000];

        $dict = Hashmap::parse(
            32,
            Cell::oneFromBoc(
                'te6cckEBEwEAVwACASABAgIC2QMEAgm3///wYBESAgEgBQYCAWIODwIBIAcIAgHODQ0CAdQNDQIBIAkKAgEgCxACASAQDAABWAIBIA0NAAEgAgEgEBAAAdQAAUgAAfwAAdwXk+eF',
                isBase64: true,
            ),
            DictSerializers::intKey(isBigInt: false),
        );

        $this->assertEquals(
            $expectedKeys,
            $dict->keys(),
        );
    }

    /**
     * @throws \Throwable
     */
    public function testComplexCreateAndParse(): void
    {
        $dict = new Hashmap(2);
        $dict
            ->set([0, 0], (new Builder())->writeString("a")->cell())
            ->set([0, 1], (new Builder())->writeString("b")->cell())
            ->set([1, 0], (new Builder())->writeString("c")->cell())
            ->set(
                [1, 1],
                (new Builder())
                    ->writeString("d")
                    ->writeRef((new Builder())->writeString("e")->cell())
                    ->cell(),
            );

        $this->assertEquals(
            [
                [0, 0],
                [0, 1],
                [1, 0],
                [1, 1],
            ],
            $dict->keys(),
        );

        $cell = $dict->cell();
        $this->assertEquals(
            "3ae983f34854e2cc5af1b42595ebbee78d419f8da7f02430906cdaa8d90cbf9c",
            Bytes::bytesToHexString($cell->hash()),
        );
        $this->assertCount(2, $cell->refs);
        $this->assertEquals(
            <<<FIFT_PRINT
            x{2_}
             x{2_}
              x{186_}
              x{18A_}
             x{2_}
              x{18E_}
              x{192_}
               x{65}
            FIFT_PRINT,
            trim($cell->print()),
        );

        $result = Hashmap::parse(2, $cell);

        foreach ($dict as $k => $_) {
            /** @noinspection PhpUnpackedArgumentTypeMismatchInspection */
            $this->assertEquals(
                $dict->get($k),
                $result->get($k),
                sprintf("Key: [ %d, %d ]", ...$k),
            );
        }
    }

    /**
     * @throws \Throwable
     */
    public function testAddressKey(): void
    {
        $cases = [
            [new Address("EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL"), BigInteger::of(100)],
            [new Address("EQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAM9c"), BigInteger::of(200)],
            [new Address("EQD__________________________________________0vo"), BigInteger::of(9000)],
        ];

        $serializers = DictSerializers::addressKey(
            valueSerializer: static fn (BigInteger $v): Cell => (new Builder())->writeInt($v, 32)->cell(),
            valueDeserializer: static fn(Cell $v): BigInteger => $v->beginParse()->loadInt(32),
        );

        $dict = new Hashmap(
            267,
            $serializers,
        );

        foreach ($cases as [$k, $v]) {
            $dict->set($k, $v);
        }

        foreach ($cases as [$caseK, $caseV]) {
            $this->assertEquals(
                $dict->get($caseK),
                $caseV,
                (string)$caseK,
            );
        }

        $cell = $dict->cell();

        $this->assertEquals(
            "b00e6667e1ade55d4f69df7b8221eb1d44c3a32b7f1a6e4eb10e74dcb99a56f6",
            Bytes::bytesToHexString($cell->hash()),
        );
        $this->assertEquals(
            <<<FIFT_PRINT
            x{817002_}
             x{2_}
              x{BF8000000000000000000000000000000000000000000000000000000000000000000000C8}
              x{BFAF2346852E7C3641B6608CC3C22D176D447C615328A742E760EAC762A212308300000064}
             x{BFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF800011944_}
            FIFT_PRINT,
            trim($cell->print()),
        );

        $resultDict = Hashmap::parse(267, $cell, $serializers);

        foreach ($cases as [$caseK, $caseV]) {
            $this->assertEquals(
                $resultDict->get($caseK),
                $caseV,
                (string)$caseK,
            );
        }
    }
}
