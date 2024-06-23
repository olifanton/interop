<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

use Olifanton\Interop\Boc\BitString;
use Olifanton\Interop\Boc\Slice;

final class CellTypeResolver
{
    /**
     * @throws \Olifanton\Interop\Boc\Exceptions\SliceException
     */
    public static function get(BitString $bytes): CellType
    {
        $reader = new Slice(
            $bytes->getImmutableArray(),
            $bytes->getLength(),
            [],
        );

        $typeId = $reader->preloadUint(8)->toInt();
        $type = CellType::tryFrom($typeId);

        if (!$type) {
            throw new \InvalidArgumentException("Unknown exotic cell type with id: " . $typeId);
        }

        return $type;
    }
}
