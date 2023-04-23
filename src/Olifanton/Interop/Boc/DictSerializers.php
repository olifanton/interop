<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use Brick\Math\BigInteger;
use Olifanton\Interop\Address;

/**
 * @template K of int|BigInteger
 * @template V of int|bool|string|Cell
 * @phpstan-type BitLike 0|1|bool
 * @phpstan-type KSerializerCallback callable(K, int): BitLike[]
 * @phpstan-type KDeserializerCallback callable(BitLike[], int): K
 * @phpstan-type VSerializerCallback callable(V): Cell
 * @phpstan-type VDeserializerCallback callable(Cell): V
 */
class DictSerializers
{
    /**
     * @var KSerializerCallback
     */
    private mixed $keySerializer;

    /**
     * @var KDeserializerCallback
     */
    private mixed $keyDeserializer;

    /**
     * @var VSerializerCallback
     */
    private mixed $valueSerializer;

    /**
     * @var VDeserializerCallback
     */
    private mixed $valueDeserializer;

    /**
     * @param null|KSerializerCallback $keySerializer
     * @param null|KDeserializerCallback $keyDeserializer
     * @param null|VSerializerCallback $valueSerializer
     * @param null|VDeserializerCallback $valueDeserializer
     */
    public function __construct(
        mixed $keySerializer = null,
        mixed $keyDeserializer = null,
        mixed $valueSerializer = null,
        mixed $valueDeserializer = null,
    ) {
        $this->keySerializer = $keySerializer ?? static fn ($key) => $key;
        $this->keyDeserializer = $keyDeserializer ?? static fn($key) => $key;

        $this->valueSerializer = $valueSerializer ?? static fn ($value) => $value;
        $this->valueDeserializer = $valueDeserializer ?? static fn($value) => $value;
    }

    /**
     * @param null|VSerializerCallback $valueSerializer
     * @param null|VDeserializerCallback $valueDeserializer
     */
    public static function uintKey(
        bool $isBigInt = true,
        mixed $valueSerializer = null,
        mixed $valueDeserializer = null,
    ): self
    {
        return new self(
            static fn (int|BigInteger $k, int $keySize): array => BitString::empty()
                ->writeUint($k, $keySize)
                ->toBitsA(),
            static function (array $k, int $keySize) use ($isBigInt): BigInteger|int {
                $key = (new Builder())
                    ->writeBitArray($k)
                    ->cell()
                    ->beginParse()
                    ->loadUint($keySize);

                return $isBigInt ? $key : $key->toInt();
            },
            $valueSerializer,
            $valueDeserializer,
        );
    }

    /**
     * @param null|VSerializerCallback $valueSerializer
     * @param null|VDeserializerCallback $valueDeserializer
     */
    public static function intKey(
        bool $isBigInt = true,
        mixed $valueSerializer = null,
        mixed $valueDeserializer = null,
    ): self
    {
        return new self(
            static fn (int|BigInteger $k, int $keySize): array => BitString::empty()
                ->writeInt($k, $keySize)
                ->toBitsA(),
            static function (array $k, int $keySize) use ($isBigInt): BigInteger|int {
                $key = (new Builder())
                    ->writeBitArray($k)
                    ->cell()
                    ->beginParse()
                    ->loadInt($keySize);

                return $isBigInt ? $key : $key->toInt();
            },
            $valueSerializer,
            $valueDeserializer,
        );
    }

    /**
     * @param null|VSerializerCallback $valueSerializer
     * @param null|VDeserializerCallback $valueDeserializer
     */
    public static function addressKey(
        mixed $valueSerializer = null,
        mixed $valueDeserializer = null,
    ): self
    {
        return new self(
            static fn (?Address $k, int $keySize): array => $keySize !== 267 ? throw new \InvalidArgumentException() : BitString::empty()
                ->writeAddress($k)
                ->toBitsA(),
            static function (array $k, int $keySize): Address {
                return (new Builder())
                    ->writeBitArray($k)
                    ->cell()
                    ->beginParse()
                    ->loadAddress();
            },
            $valueSerializer,
            $valueDeserializer,
        );
    }

    /**
     * @return KSerializerCallback
     * @return KSerializerCallback
     */
    public function getKeySerializer(): callable
    {
        return $this->keySerializer;
    }

    /**
     * @return KDeserializerCallback
     * @phpstan-return KDeserializerCallback
     */
    public function getKeyDeserializer(): callable
    {
        return $this->keyDeserializer;
    }

    /**
     * @return VSerializerCallback
     * @phpstan-return VSerializerCallback
     */
    public function getValueSerializer(): callable
    {
        return $this->valueSerializer;
    }

    /**
     * @return VDeserializerCallback
     * @phpstan-return VDeserializerCallback
     */
    public function getValueDeserializer(): callable
    {
        return $this->valueDeserializer;
    }
}
