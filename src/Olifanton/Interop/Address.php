<?php declare(strict_types=1);

namespace Olifanton\Interop;

use InvalidArgumentException;
use JetBrains\PhpStorm\ArrayShape;
use Olifanton\TypedArrays\Uint8Array;

/**
 * Address
 *
 * `Address` is a class that allows you to work with smart contract addresses in the TON network. Read more about Addresses in official [documentation](https://ton.org/docs/learn/overviews/addresses).
 */
class Address implements \Stringable
{
    public const NONE = null;

    private const BOUNCEABLE_TAG = 0x11;
    private const NON_BOUNCEABLE_TAG = 0x51;
    private const TEST_FLAG = 0x80;

    private int $wc;

    private Uint8Array $hashPart;

    private bool $isTestOnly;

    private bool $isUserFriendly;

    private bool $isBounceable;

    private bool $isUrlSafe;

    /**
     * Address constructor.
     *
     * `$anyForm` -- Address in supported form. Supported values are:
     *      - Friendly format (base64 encoded, URL safe or not): `EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL`;
     *      - Raw form: `-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260`;
     *      - Other `Address` instance, in this case the new instance will be an immutable copy of the other address.
     *
     * Depending on the passed value, the Address instance will store information about the input address flags.
     *
     * If the input value is not a valid address, then `\InvalidArgumentException` will be thrown.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string | Address $anyForm)
    {
        if ($anyForm instanceof Address) {
            $this->wc = $anyForm->wc;
            $this->hashPart = $anyForm->getHashPart();
            $this->isTestOnly = $anyForm->isTestOnly;
            $this->isUserFriendly = $anyForm->isUserFriendly;
            $this->isBounceable = $anyForm->isBounceable;
            $this->isUrlSafe = $anyForm->isUrlSafe;
            return;
        }

        if (strpos($anyForm, "-") > 0 || strpos($anyForm, "_") > 0) {
            $this->isUrlSafe = true;
            $anyForm = str_replace(["-", "_"], ["+", '/'], $anyForm);
        } else {
            $this->isUrlSafe = false;
        }

        if (str_contains($anyForm, ":")) {
            $chunks = explode(":", $anyForm);

            if (count($chunks) !== 2) {
                throw new InvalidArgumentException("Invalid address: " . $anyForm);
            }

            $wc = (int)$chunks[0];

            if ($wc !== 0 && $wc !== -1) {
                throw new InvalidArgumentException('Invalid address wc: ' . $anyForm);
            }

            $hex = $chunks[1];

            if (strlen($hex) !== 64) {
                throw new InvalidArgumentException("Invalid address hex: " . $anyForm);
            }

            $this->isUserFriendly = false;
            $this->wc = $wc;
            $this->hashPart = Bytes::hexStringToBytes($hex);
            $this->isTestOnly = false;
            $this->isBounceable = false;
        } else {
            $parseResult = self::parseFriendlyAddress($anyForm);

            $this->isUserFriendly = true;
            $this->wc = $parseResult['workchain'];
            $this->hashPart = $parseResult['hashPart'];
            $this->isTestOnly = $parseResult['isTestOnly'];
            $this->isBounceable = $parseResult['isBounceable'];
        }
    }

    /**
     * Returns a string representation of Address.
     *
     * If all parameters are left as default, then the address will be formatted with the same flags whose value was recognized in the constructor.
     */
    public function toString(?bool $isUserFriendly = null,
                             ?bool $isUrlSafe = null,
                             ?bool $isBounceable = null,
                             ?bool $isTestOnly = null): string
    {
        $isUserFriendly = ($isUserFriendly === null) ? $this->isUserFriendly : $isUserFriendly;
        $isUrlSafe = ($isUrlSafe === null) ? $this->isUrlSafe : $isUrlSafe;
        $isBounceable = ($isBounceable === null) ? $this->isBounceable : $isBounceable;
        $isTestOnly = ($isTestOnly === null) ? $this->isTestOnly : $isTestOnly;

        if (!$isUserFriendly) {
            return $this->wc . ":" . Bytes::bytesToHexString($this->hashPart);
        }

        $tag = $isBounceable ? self::BOUNCEABLE_TAG : self::NON_BOUNCEABLE_TAG;

        if ($isTestOnly) {
            $tag |= self::TEST_FLAG;
        }

        $addr = new Uint8Array(34);
        $addr->fSet(0, $tag);
        $addr->fSet(1, $this->wc);

        for ($i = 2; $i < $addr->length; $i++) {
            $addr->fSet($i, $this->hashPart->fGet($i - 2));
        }

        $addressWithChecksum = new Uint8Array(36);

        for ($i = 0; $i < $addr->length; $i++) {
            $addressWithChecksum->fSet($i, $addr->fGet($i));
        }

        $crc16 = Checksum::crc16($addr);

        for ($i = 0; $i < $crc16->length; $i++) {
            $addressWithChecksum->fSet($i + 34, $crc16->fGet($i));
        }

        $addressBase64 = base64_encode(Bytes::arrayToBytes($addressWithChecksum));

        if ($isUrlSafe) {
            $addressBase64 = str_replace(['+', '/'], ["-", '_'], $addressBase64);
        }

        return $addressBase64;
    }

    public function asWallet(): string
    {
        return $this->toString(
            isUserFriendly: true,
            isUrlSafe: true,
            isBounceable: false,
        );
    }

    public function asContract(): string
    {
        return $this->toString(
            isUserFriendly: true,
            isUrlSafe: true,
            isBounceable: true,
        );
    }

    /**
     * Returns Workchain ID.
     *
     * Returns `-1` for Masterchain and `0` for basic workchain.
     */
    public function getWorkchain(): int
    {
        return $this->wc;
    }

    /**
     * Returns address Account ID.
     */
    public function getHashPart(): Uint8Array
    {
        return Bytes::arraySlice($this->hashPart, 0, 32);
    }

    /**
     * Returns true if the address has the `isTestnetOnly` flag.
     */
    public function isTestOnly(): bool
    {
        return $this->isTestOnly;
    }

    /**
     * Returns true if the address is user-friendly.
     */
    public function isUserFriendly(): bool
    {
        return $this->isUserFriendly;
    }

    /**
     * Returns true if the address has the `isBounceable` flag.
     */
    public function isBounceable(): bool
    {
        return $this->isBounceable;
    }

    /**
     * Returns true if the address was encoded with URL-safe characters only.
     */
    public function isUrlSafe(): bool
    {
        return $this->isUrlSafe;
    }

    public function isEqual(Address|string $other): bool
    {
        if (is_string($other)) {
            $other = new Address($other);
        }

        return Bytes::compareBytes($this->hashPart, $other->hashPart);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Checks if the passed value is a valid address in any form.
     */
    public static function isValid(string | Address $address): bool
    {
        try {
            new Address($address);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function zero(): self
    {
        return new self("UQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAJKZ");
    }

    #[ArrayShape([
        'isTestOnly' => "bool",
        'isBounceable' => "bool",
        'workchain' => "int",
        'hashPart' => "Olifanton\\TypedArrays\\Uint8Array",
    ])]
    private static function parseFriendlyAddress(string $addressString): array
    {
        if (strlen($addressString) !== 48) {
            throw new InvalidArgumentException("User-friendly address should contain strictly 48 characters");
        }

        $data = Bytes::stringToBytes(base64_decode($addressString));

        if ($data->length !== 36) {
            throw new InvalidArgumentException("Unknown address type: byte length is not equal to 36");
        }

        $addr = Bytes::arraySlice($data, 0, 34);
        $crc = Bytes::arraySlice($data, 34, 36);
        $checkCrc = Checksum::crc16($addr);

        if (!Bytes::compareBytes($crc, $checkCrc)) {
            throw new InvalidArgumentException("Address CRC16-checksum error");
        }

        $tag = $addr[0];
        $isTestOnly = false;

        if ($tag & self::TEST_FLAG) {
            $isTestOnly = true;
            $tag ^= self::TEST_FLAG;
        }

        if (($tag !== self::BOUNCEABLE_TAG) && ($tag !== self::NON_BOUNCEABLE_TAG)) {
            throw new InvalidArgumentException("Unknown address tag");
        }

        $isBounceable = $tag === self::BOUNCEABLE_TAG;

        if ($addr[1] === 0xff) {
            $workchain = -1;
        } else {
            $workchain = $addr[1];
        }

        if ($workchain !== 0 && $workchain !== -1) {
            throw new InvalidArgumentException("Invalid address workchain: " . $workchain);
        }

        $hashPart = Bytes::arraySlice($addr, 2, 34);

        return [
            'isTestOnly' => $isTestOnly,
            'isBounceable' => $isBounceable,
            'workchain' => $workchain,
            'hashPart' => $hashPart,
        ];
    }
}
