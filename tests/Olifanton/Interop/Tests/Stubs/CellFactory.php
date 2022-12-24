<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Stubs;

use Brick\Math\BigInteger;
use Olifanton\Interop\Boc\Cell;

class CellFactory
{
    /**
     * @throws \Olifanton\Interop\Boc\Exceptions\BitStringException
     */
    public static function getCellWithUint(BigInteger | int $number, int $length): Cell
    {
        $cell = new Cell();
        $cell
            ->bits
            ->writeUint($number, $length);

        return $cell;
    }

    /**
     * @throws \Olifanton\Interop\Boc\Exceptions\BitStringException
     */
    public static function getCellWithInt(BigInteger | int $number, int $length): Cell
    {
        $cell = new Cell();
        $cell
            ->bits
            ->writeInt($number, $length);

        return $cell;
    }
}
