<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests;

use Olifanton\Interop\Bytes;
use Olifanton\Interop\KeyPair;
use PHPUnit\Framework\TestCase;

class KeyPairTest extends TestCase
{
    public function testFromSecretKey(): void
    {
        $kp = KeyPair::fromSecretKey(
            Bytes::hexStringToBytes(
                "15d906cf1eacd6103eb256208c96090fe9405926475fd7ccfa4362527814703475594fd9690e01ea5b712d66d960025cab237f7227bea51c6d56afedf76285c4"
            ),
        );

        $this->assertEquals(
            Bytes::bytesToHexString($kp->publicKey),
            "75594fd9690e01ea5b712d66d960025cab237f7227bea51c6d56afedf76285c4"
        );

        $this->assertEquals(
            Bytes::bytesToHexString($kp->secretKey),
            "15d906cf1eacd6103eb256208c96090fe9405926475fd7ccfa4362527814703475594fd9690e01ea5b712d66d960025cab237f7227bea51c6d56afedf76285c4"
        );
    }
}
