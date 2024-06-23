<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc\Helpers;

enum CellType : int
{
    case ORDINARY = -1;
    case PRUNED_BRANCH = 1;
    case LIBRARY = 2;
    case MERKLE_PROOF = 3;
    case MERKLE_UPDATE = 4;
}
