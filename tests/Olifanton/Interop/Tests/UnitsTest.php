<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests;

use Brick\Math\Exception\RoundingNecessaryException;
use Olifanton\Interop\Units;
use PHPUnit\Framework\TestCase;

class UnitsTest extends TestCase
{
    public function testToNano(): void
    {
        $this->assertEquals("500000000", Units::toNano("0.5")->toBase(10));
        $this->assertEquals("1000000000", Units::toNano(1)->toBase(10));
        $this->assertEquals("500000000", Units::toNano(0.5)->toBase(10));
        $this->assertEquals("10000000000", Units::toNano(10)->toBase(10));
        $this->assertEquals("10100000000", Units::toNano("10.1")->toBase(10));
        $this->assertEquals("123012345678", Units::toNano("123.012345678")->toBase(10));
        $this->assertEquals("123000012345678", Units::toNano("123000.012345678")->toBase(10));
    }

    public function testToNanoTooManyPrecision(): void
    {
        $this->expectException(RoundingNecessaryException::class);
        Units::toNano("123000.0123456789");
    }

    public function testFromNano(): void
    {
        $this->assertEquals("1", (string)Units::fromNano("1000000000"));
        $this->assertEquals("0.5", (string)Units::fromNano("500000000"));
        $this->assertEquals("0.000000001", (string)Units::fromNano("1"));
        $this->assertEquals("0.000000001", (string)Units::fromNano(1));
        $this->assertEquals("0.00000002", (string)Units::fromNano(20));
    }

    public function testFromNanoDecimal(): void
    {
        $this->expectException(RoundingNecessaryException::class);
        Units::fromNano("20.1");
    }
}
