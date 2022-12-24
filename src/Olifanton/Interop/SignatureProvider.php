<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Olifanton\TypedArrays\Uint8Array;
use Olifanton\Interop\Exceptions\CryptoException;

interface SignatureProvider
{
    /**
     * @throws CryptoException
     */
    public function signDetached(Uint8Array $message, Uint8Array $secretKey): Uint8Array;
}
