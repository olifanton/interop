<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests;

use Olifanton\Interop\Address;
use Olifanton\Interop\Bytes;
use PHPUnit\Framework\TestCase;

class AddressTest extends TestCase
{
    public function testIsValidSuccess(): void
    {
        $this->assertTrue(Address::isValid("EQD__________________________________________0vo"));
        $this->assertTrue(Address::isValid("EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL"));
        $this->assertTrue(Address::isValid(new Address("EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL")));
        $this->assertTrue(Address::isValid("-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260"));
        $this->assertTrue(Address::isValid("kf/8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYIny"));
        $this->assertTrue(Address::isValid("kf_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYIny"));
        $this->assertTrue(Address::isValid("EQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAM9c"));
    }

    public function testIsValidFail(): void
    {
        // Length
        $this->assertFalse(Address::isValid("EQD0vo"));

        // Checksum
        $this->assertFalse(Address::isValid("EQD__________________________________________0v0"));
        $this->assertFalse(Address::isValid("zQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL"));

        // Unknown workchain
        $this->assertFalse(Address::isValid("2:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260"));

        // Format
        $this->assertFalse(Address::isValid("-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260:"));

        // Byte length
        $this->assertFalse(Address::isValid("-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db2923226060"));
        $this->assertFalse(Address::isValid("kf_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSQMiYIny"));
    }

    public function testToString(): void
    {
        $address = new Address("-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260");

        // hex
        $this->assertEquals(
            "-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260",
            $address->toString(isUserFriendly: false)
        );

        // User-friendly
        $this->assertEquals(
            "Uf/8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15+KsQHFLbKSMiYG+9",
            $address->toString(isUserFriendly: true)
        );

        // User-friendly and URL safe
        $this->assertEquals(
            "Uf_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYG-9",
            $address->toString(isUserFriendly: true, isUrlSafe: true)
        );

        // Bounceable
        $this->assertEquals(
            "Ef_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYDJ4",
            $address->toString(isUserFriendly: true, isUrlSafe: true, isBounceable: true),
        );

        // User-friendly, URL safe, Bounceable and test only
        $this->assertEquals(
            "kf_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYIny",
            $address->toString(true, true, true, true),
        );
    }

    public function test0x0(): void
    {
        $address = new Address("EQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAM9c");
        $this->assertEquals("0:0000000000000000000000000000000000000000000000000000000000000000", $address->toString(false));
    }

    public function testStringCast(): void
    {
        $address = new Address("EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL");
        $this->assertEquals(
            "EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL",
            (string)$address,
        );
    }

    public function testGetters(): void
    {
        $address = new Address("kf_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYIny");

        $this->assertTrue($address->isUserFriendly());
        $this->assertTrue($address->isBounceable());
        $this->assertTrue($address->isTestOnly());
        $this->assertTrue($address->isUrlSafe());
        $this->assertEquals(-1, $address->getWorkchain());
        $this->assertEquals(
            "fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260",
            Bytes::bytesToHexString($address->getHashPart()),
        );
    }

    public function testAddressFromAddress(): void
    {
        $other = new Address("kf_8uRo6OBbQ97jCx2EIuKm8Wmt6Vb15-KsQHFLbKSMiYIny");

        $addr = new Address($other);
        $this->assertTrue($addr->isUserFriendly());
        $this->assertTrue($addr->isBounceable());
        $this->assertTrue($addr->isTestOnly());
        $this->assertTrue($addr->isUrlSafe());
        $this->assertEquals(-1, $addr->getWorkchain());
        $this->assertEquals(
            "fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260",
            Bytes::bytesToHexString($addr->getHashPart()),
        );
    }

    public function testAsWallet(): void
    {
        $this->assertEquals(
            "UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAJKZ",
            (new Address("0:0000000000000000000000000000000000000000000000000000000000000000"))->asWallet(),
        );
    }

    public function testAsWalletTestnet(): void
    {
        $this->assertEquals(
            "0QAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACkT",
            (new Address("0:0000000000000000000000000000000000000000000000000000000000000000"))->asWallet(isTestOnly: true),
        );
    }

    public function testAsContract(): void
    {
        $this->assertEquals(
            "EQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAM9c",
            (new Address("0:0000000000000000000000000000000000000000000000000000000000000000"))->asContract(),
        );
    }

    public function testAsContractTestnet(): void
    {
        $this->assertEquals(
            "kQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAHTW",
            (new Address("0:0000000000000000000000000000000000000000000000000000000000000000"))->asContract(isTestOnly: true),
        );
    }
}
