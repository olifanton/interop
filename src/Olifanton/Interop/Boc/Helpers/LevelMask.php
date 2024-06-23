<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

use Olifanton\Interop\Helpers\Math;

final class LevelMask
{
    private readonly int $hashIndex;
    private readonly int $hashCount;

    public function __construct(
        private readonly int $mask,
    )
    {
        $this->hashIndex = self::countSetBits($this->mask);
        $this->hashCount = $this->hashIndex + 1;
    }

    public function getValue(): int
    {
        return $this->mask;
    }

    public function getLevel(): int
    {
        return 32 - Math::clz32($this->mask);
    }

    public function getHashIndex()
    {
        return $this->hashIndex;
    }

    public function getHashCount()
    {
        return $this->hashCount;
    }

    public function apply(int $level): self
    {
        return new self(
            $this->mask & ((1 << $level) - 1),
        );
    }

    public function isSignificant(int $level): bool
    {
        return $level === 0 || ($this->mask >> ($level - 1)) % 2 !== 0;
    }

    private static function countSetBits(int $n): int
    {
        $n = $n - (($n >> 1) & 0x55555555);
        $n = ($n & 0x33333333) + (($n >> 2) & 0x33333333);

        return (($n + ($n >> 4) & 0xF0F0F0F) * 0x1010101) >> 24;
    }
}
