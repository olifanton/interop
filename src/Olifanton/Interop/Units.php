<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;
use InvalidArgumentException;

/**
 * Cryptocurrency units helper
 */
class Units
{
    private const UNITS = [
        'noether' => '0',
        'wei' => '1',
        'kwei' => '1000',
        'Kwei' => '1000',
        'babbage' => '1000',
        'femtoether' => '1000',
        'mwei' => '1000000',
        'Mwei' => '1000000',
        'lovelace' => '1000000',
        'picoether' => '1000000',
        'gwei' => '1000000000',
        'Gwei' => '1000000000',
        'shannon' => '1000000000',
        'nanoether' => '1000000000',
        'nano' => '1000000000',
        'szabo' => '1000000000000',
        'microether' => '1000000000000',
        'micro' => '1000000000000',
        'finney' => '1000000000000000',
        'milliether' => '1000000000000000',
        'milli' => '1000000000000000',
        'ether' => '1000000000000000000',
        'kether' => '1000000000000000000000',
        'grand' => '1000000000000000000000',
        'mether' => '1000000000000000000000000',
        'gether' => '1000000000000000000000000000',
        'tether' => '1000000000000000000000000000000',
    ];

    /**
     * Returns $amount in nano.
     */
    public static final function toNano(BigNumber|string|int|float $amount): BigInteger
    {
        return self::toWei(BigDecimal::of($amount), 'gwei')->toBigInteger();
    }

    /**
     * Returns wei value from $amount in nano.
     */
    public static final function fromNano(BigNumber|string|int $amount): BigNumber
    {
        return self::fromWei(BigNumber::of($amount)->toScale(9), 'gwei');
    }

    private static function toWei(BigDecimal $bn, string $unit): BigNumber
    {
        if (!isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException('toWei doesn\'t support ' . $unit . ' unit.');
        }

        return $bn->multipliedBy(BigNumber::of(self::UNITS[$unit]));
    }

    private static function fromWei(BigDecimal $bn, string $unit): BigNumber
    {
        if (!isset(self::UNITS[$unit])) {
            throw new InvalidArgumentException('fromWei doesn\'t support ' . $unit . ' unit.');
        }

        $bnt = BigNumber::of(self::UNITS[$unit]);

        return self::fixScale($bn->dividedBy($bnt));
    }

    private static function fixScale(BigNumber $bn): BigNumber
    {
        $strValue = (string)$bn;
        $isDrop = true;
        $dropZeroCount = array_reduce(
            array_reverse(str_split($strValue)),
            static function (int $curry, string $n) use (&$isDrop) {
                if ($isDrop) {
                    if ($n !== "0") {
                        $isDrop = false;

                        return $curry;
                    }

                    return $curry + 1;
                }

                return $curry;
            },
            0
        );

        return $bn->toScale(9 - $dropZeroCount);
    }
}
