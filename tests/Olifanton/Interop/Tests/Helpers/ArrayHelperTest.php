<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Helpers;

use Olifanton\Interop\Helpers\ArrayHelper;
use PHPUnit\Framework\TestCase;

class ArrayHelperTest extends TestCase
{
    public function testArraySearchReturnsKey(): void
    {
        $target = [
            "one" => 1,
            "two" => 2,
            "three" => 3,
        ];

        $result = ArrayHelper::arraySearch($target, function ($v, $index) {
            return $v === 2;
        });

        $this->assertEquals("two", $result);
    }

    public function testArraySearchReturnsNull(): void
    {
        $target = [
            "one" => 1,
            "two" => 2,
            "three" => 3,
        ];

        $result = ArrayHelper::arraySearch($target, function ($v, $index) {
            return $v === 4;
        });

        $this->assertNull($result);
    }

    public function testArraySearchWithIndexReturnsKey(): void
    {
        $target = [
            "one" => 1,
            "two" => 2,
            "three" => 3,
        ];

        $result = ArrayHelper::arraySearch($target, function ($v, $index) {
            return $index === 1;
        });

        $this->assertEquals("two", $result);
    }

    public function testArraySearchWithListIndexReturnsIndex(): void
    {
        $target = [
            "one",
            "two",
            "three",
        ];

        $result = ArrayHelper::arraySearch($target, function ($v, $index) {
            return $index === 1;
        });

        $this->assertEquals(1, $result);
    }
}
