<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Helpers;

use Olifanton\Interop\Helpers\Math;
use PHPUnit\Framework\TestCase;

class MathTest extends TestCase
{
    public function testClz32(): void
    {
        $cases = [
            0 => 32,
            1 => 31,
            2 => 30,
            10 => 28,
            -1 => 0,
            -100 => 0,
            256 => 23,
            10000 => 18,
        ];

        foreach ($cases as $num => $expected) {
            $this->assertEquals(
                $expected,
                Math::clz32($num),
                "Num: " . $num,
            );
        }
    }
}
