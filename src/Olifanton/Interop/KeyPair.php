<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Olifanton\TypedArrays\Uint8Array;

/**
 * Public/Secret key pair
 */
final class KeyPair
{
    public function __construct(
        public readonly Uint8Array $publicKey,
        public readonly Uint8Array $secretKey,
    )
    {
    }
}
