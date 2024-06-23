<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

use Olifanton\Interop\Boc\BitString;
use Olifanton\Interop\Boc\Cell;
use Olifanton\Interop\Boc\Exceptions\CellException;
use Olifanton\Interop\Boc\Exceptions\SliceException;
use Olifanton\Interop\Boc\Slice;

final class MaskResolver
{
    /**
     * @param \ArrayObject<Cell> $refs
     * @throws SliceException|CellException
     */
    public static function get(BitString $bits, CellType $type, \ArrayObject $refs): LevelMask
    {
        if ($type === CellType::ORDINARY) {
            $mask = 0;

            foreach ($refs as $ref) {
                /** @var Cell $ref */
                $mask = $mask | $ref->getLevelMask()->getValue();
            }

            return new LevelMask($mask);
        }

        if ($type === CellType::PRUNED_BRANCH) {
            $reader = new Slice(
                $bits->getImmutableArray(),
                $bits->getLength(),
                [],
            );
            $reader->skipBits(8); // type

            if ($bits->getLength() === 280) {
                return new LevelMask(1);
            }

            return new LevelMask($reader->loadUint(8)->toInt());
        }

        if ($type === CellType::LIBRARY) {
            return new LevelMask(0);
        }

        if ($type === CellType::MERKLE_PROOF) {
            return new LevelMask($refs[0]->getLevelMask()->getValue() >> 1);
        }

        if ($type === CellType::MERKLE_UPDATE) {
            /** @var Cell $r0 */
            $r0 = $refs[0];
            /** @var Cell $r1 */
            $r1 = $refs[1];

            return new LevelMask(
                $r0->getLevelMask()->getValue() | $r1->getLevelMask()->getValue() >> 1,
            );
        }

        throw new \RuntimeException("Unsupported cell type: " . $type->name); // @phpstan-ignore-line
    }
}
