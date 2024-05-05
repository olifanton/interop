<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigNumber;

/**
 * Cryptocurrency units helper
 */
class Units
{
    public const DEFAULT = 9;
    public const USDt = 6;

    /**
     * Returns $amount in nano.
     */
    public static final function toNano(BigNumber|string|int|float $amount, int $decimals = self::DEFAULT): BigInteger
    {
        return self::toWei(
            BigDecimal::of($amount), str_pad("1", $decimals + 1, "0"),
        )->toBigInteger();
    }

    /**
     * Returns wei value from $amount in nano.
     */
    public static final function fromNano(BigNumber|string|int $amount, int $decimals = self::DEFAULT): BigNumber
    {
        return self::fromWei(
            BigNumber::of($amount)->toScale($decimals), str_pad("1", $decimals + 1, "0"),
            $decimals,
        );
    }

    private static function toWei(BigDecimal $bn, string $decimals): BigNumber
    {
        return $bn->multipliedBy(BigNumber::of($decimals));
    }

    private static function fromWei(BigDecimal $bn, string $decimalsString, int $decimals): BigNumber
    {
        return self::fixScale($bn->dividedBy(BigNumber::of($decimalsString)), $decimals);
    }

    private static function fixScale(BigNumber $bn, int $decimals): BigNumber
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

        return $bn->toScale($decimals - $dropZeroCount);
    }
}
