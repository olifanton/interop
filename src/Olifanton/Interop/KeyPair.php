<?php declare(strict_types=1);

namespace Olifanton\Interop;

use Olifanton\Interop\Helpers\OlifantonByteReader;
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

    public static function fromSecretKey(Uint8Array $secretKey): self
    {
        $publicKey = substr(OlifantonByteReader::getBytes($secretKey->buffer), 32);

        return new KeyPair(
            Bytes::bytesToArray($publicKey),
            $secretKey,
        );
    }
}
