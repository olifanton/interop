<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Olifanton\Interop\Exceptions\CryptoException;
use Olifanton\TypedArrays\Uint8Array;

interface DigestProvider
{
    /**
     * @throws CryptoException
     */
    public function digestSha256(Uint8Array $bytes): Uint8Array;
}
