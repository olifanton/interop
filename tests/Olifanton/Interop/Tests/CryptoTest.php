<?php

namespace Olifanton\Interop\Tests;

use Olifanton\Interop\Bytes;
use Olifanton\Interop\Crypto;
use Olifanton\Interop\CryptoProviders\DefaultProvider;
use Olifanton\Interop\Tests\Stubs\CryptoProviderStub;
use Olifanton\TypedArrays\Uint8Array;
use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase
{
    protected function tearDown(): void
    {
        $defaultProvider = new DefaultProvider();

        Crypto::setKeyPairProvider($defaultProvider);
        Crypto::setDigestProvider($defaultProvider);
        Crypto::setSignatureProvider($defaultProvider);
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testSha256(): void
    {
        $stub = new Uint8Array(3);
        $stub[0] = 0x01;
        $stub[1] = 0x02;
        $stub[2] = 0x03;

        $this->assertEquals(
            "039058c6f2c0cb492c533b0a4d14ef77cc0f78abccced5287d84a1a2011cfb81",
            Bytes::bytesToHexString(Crypto::sha256($stub)),
        );
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testSha256EmptyArray(): void
    {
        $stub = new Uint8Array([]);
        $this->assertEquals(
            "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
            Bytes::bytesToHexString(Crypto::sha256($stub)),
        );
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testKeyPairFromSeedZeroFill(): void
    {
        $seed = new Uint8Array(array_fill(0, 32, 0));
        $keyPair = Crypto::keyPairFromSeed($seed);
        $this->assertEquals(
            "3b6a27bcceb6a42d62a3a8d02a6f0d73653215771de243a63ac048a18b59da29",
            Bytes::bytesToHexString($keyPair->publicKey),
        );
        $this->assertEquals(
            "00000000000000000000000000000000000000000000000000000000000000003b6a27bcceb6a42d62a3a8d02a6f0d73653215771de243a63ac048a18b59da29",
            Bytes::bytesToHexString($keyPair->secretKey),
        );
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testKeyPairFromSeedConstant(): void
    {
        $seed = new Uint8Array([0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31]);
        $keyPair = Crypto::keyPairFromSeed($seed);
        $this->assertEquals(
            "03a107bff3ce10be1d70dd18e74bc09967e4d6309ba50d5f1ddc8664125531b8",
            Bytes::bytesToHexString($keyPair->publicKey),
        );
        $this->assertEquals(
            "000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f03a107bff3ce10be1d70dd18e74bc09967e4d6309ba50d5f1ddc8664125531b8",
            Bytes::bytesToHexString($keyPair->secretKey),
        );
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testNewKeyPair(): void
    {
        $keyPair = Crypto::newKeyPair();

        $this->assertEquals(32, strlen(Bytes::arrayToBytes($keyPair->publicKey)));
        $this->assertEquals(64, strlen(Bytes::bytesToHexString($keyPair->publicKey)));

        $this->assertEquals(64, strlen(Bytes::arrayToBytes($keyPair->secretKey)));
        $this->assertEquals(128, strlen(Bytes::bytesToHexString($keyPair->secretKey)));
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testNewSeed(): void
    {
        $seed = Crypto::newSeed();

        $this->assertEquals(32, strlen(Bytes::arrayToBytes($seed)));
        $this->assertEquals(64, strlen(Bytes::bytesToHexString($seed)));
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testSetProviders(): void
    {
        $stub = new CryptoProviderStub();
        $stubEmpty32 = new Uint8Array(array_fill(0, 32, 0));
        $stubEmpty64 = Bytes::concatBytes($stubEmpty32, $stubEmpty32);

        Crypto::setDigestProvider($stub);
        Crypto::setKeyPairProvider($stub);

        $this->assertTrue(Bytes::compareBytes($stubEmpty32, Crypto::sha256(new Uint8Array([]))));
        $this->assertTrue(Bytes::compareBytes($stubEmpty32, Crypto::newSeed()));
        $this->assertTrue(Bytes::compareBytes($stubEmpty32, Crypto::newKeyPair()->publicKey));
        $this->assertTrue(Bytes::compareBytes($stubEmpty64, Crypto::newKeyPair()->secretKey));
        $this->assertTrue(Bytes::compareBytes($stubEmpty32, Crypto::keyPairFromSeed($stubEmpty32)->publicKey));
        $this->assertTrue(Bytes::compareBytes($stubEmpty64, Crypto::keyPairFromSeed($stubEmpty32)->secretKey));
    }

    /**
     * @throws \Olifanton\Interop\Exceptions\CryptoException
     */
    public function testSign(): void
    {
        $seed = new Uint8Array(array_fill(0, 32, 1));
        $keyPair = Crypto::keyPairFromSeed($seed);
        $message = new Uint8Array([1, 2, 3]);
        $signature = Crypto::sign($message, $keyPair->secretKey);

        $this->assertEquals(
            "2bb7f19541c9d70dceb448fabcec7776b327a5edae516f42fdf6b05232cca1ebadadf1dff4355eb5d4be12193c7a033c4d2c76ebd0e78cffaa0f5b268f07d908",
            Bytes::bytesToHexString($signature),
        );
    }
}
