<?php /** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Olifanton\Interop\CryptoProviders;

use Olifanton\Interop\Bytes;
use Olifanton\Interop\DigestProvider;
use Olifanton\Interop\Exceptions\CryptoException;
use Olifanton\Interop\KeyPair;
use Olifanton\Interop\KeyPairProvider;
use Olifanton\Interop\SignatureProvider;
use Olifanton\TypedArrays\Uint8Array;

class DefaultProvider implements DigestProvider, KeyPairProvider, SignatureProvider
{
    private array $ext = [];

    /**
     * @inheritDoc
     */
    public function digestSha256(Uint8Array $bytes): Uint8Array
    {
        self::checkExt("hash");

        try {
            $digest = hash('sha256', Bytes::arrayToBytes($bytes), true);
        // @codeCoverageIgnoreStart
        } catch (\Throwable $e) {
            throw new CryptoException("Hash error: " . $e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd

        return Bytes::bytesToArray($digest);
    }

    /**
     * @inheritDoc
     */
    public function keyPairFromSeed(Uint8Array $seed): KeyPair
    {
        self::checkExt("sodium");

        try {
            $keyPair = sodium_crypto_sign_seed_keypair(Bytes::arrayToBytes($seed));

            return $this->keyPairFromSodium($keyPair);
        // @codeCoverageIgnoreStart
        } catch (\SodiumException $e) {
            throw new CryptoException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @inheritDoc
     */
    public function newKeyPair(): KeyPair
    {
        self::checkExt("sodium");

        try {
            return $this->keyPairFromSodium(sodium_crypto_sign_keypair());
        // @codeCoverageIgnoreStart
        } catch (\SodiumException $e) {
            throw new CryptoException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @inheritDoc
     */
    public function newSeed(): Uint8Array
    {
        self::checkExt("sodium");

        try {
            $keyPair = sodium_crypto_sign_keypair();
            $secretKey = sodium_crypto_sign_secretkey($keyPair);

            return Bytes::bytesToArray(substr($secretKey, 0, 32));
        // @codeCoverageIgnoreStart
        } catch (\SodiumException $e) {
            throw new CryptoException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @inheritDoc
     */
    public function signDetached(Uint8Array $message, Uint8Array $secretKey): Uint8Array
    {
        self::checkExt("sodium");

        try {
            return Bytes::bytesToArray(
                sodium_crypto_sign_detached(
                    Bytes::arrayToBytes($message),
                    Bytes::arrayToBytes($secretKey),
                ),
            );
        // @codeCoverageIgnoreStart
        } catch (\SodiumException $e) {
            throw new CryptoException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @throws \SodiumException
     */
    private function keyPairFromSodium(string $sodiumKP): KeyPair
    {
        return new KeyPair(
            Bytes::bytesToArray(sodium_crypto_sign_publickey($sodiumKP)),
            Bytes::bytesToArray(sodium_crypto_sign_secretkey($sodiumKP)),
        );
    }

    /**
     * @throws CryptoException
     */
    private function checkExt(string $ext): void
    {
        if (in_array($ext, $this->ext)) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }

        if (!extension_loaded($ext)) {
            // @codeCoverageIgnoreStart
            throw new CryptoException("Missing `" . $ext . "` extension");
            // @codeCoverageIgnoreEnd
        }

        $this->ext[] = $ext;
    }
}
