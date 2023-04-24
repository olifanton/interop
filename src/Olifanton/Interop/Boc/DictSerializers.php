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
    private $keySerializer;

    private bool $isDefKeySerializer = false;

    /**
     * @var KDeserializerCallback
     */
    private $keyDeserializer;

    private bool $isDefKeyDeserializer = false;

    /**
     * @var VSerializerCallback
     */
    private $valueSerializer;

    private bool $isDefValueSerializer = false;

    /**
     * @var VDeserializerCallback
     */
    private $valueDeserializer;

    private bool $isDefValueDeserializer = false;

    private bool $isCombined = false;

    /**
     * @param null|KSerializerCallback $keySerializer
     * @phpstan-param null|KSerializerCallback $keySerializer
     * @param null|KDeserializerCallback $keyDeserializer
     * @phpstan-param  null|KDeserializerCallback $keyDeserializer
     * @param null|VSerializerCallback $valueSerializer
     * @phpstan-param null|VSerializerCallback $valueSerializer
     * @param null|VDeserializerCallback $valueDeserializer
     * @phpstan-param null|VDeserializerCallback $valueDeserializer
     */
    public final function __construct(
        ?callable $keySerializer = null,
        ?callable $keyDeserializer = null,
        ?callable $valueSerializer = null,
        ?callable $valueDeserializer = null,
    ) {
        $this->keySerializer = $keySerializer ?? static fn ($key) => $key;
        $this->isDefKeySerializer = !$keySerializer;

        $this->keyDeserializer = $keyDeserializer ?? static fn($key) => $key;
        $this->isDefKeyDeserializer = !$keyDeserializer;

        $this->valueSerializer = $valueSerializer ?? static fn ($value) => $value;
        $this->isDefValueSerializer = !$valueSerializer;

        $this->valueDeserializer = $valueDeserializer ?? static fn($value) => $value;
        $this->isDefValueDeserializer = !$valueDeserializer;
    }

    public final function combine(DictSerializers $serializers): self
    {
        if ($this->isCombined) {
            throw new \RuntimeException("Already combined with other Serializer");
        }

        $this->isCombined = true;
        $callbacks = [
            [
                $this->isDefKeySerializer,
                $serializers->getKeySerializer(),
                'keySerializer',
            ],
            [
                $this->isDefKeyDeserializer,
                $serializers->getKeyDeserializer(),
                'keyDeserializer',
            ],
            [
                $this->isDefValueSerializer,
                $serializers->getValueSerializer(),
                'valueSerializer',
            ],
            [
                $this->isDefValueDeserializer,
                $serializers->getValueDeserializer(),
                'valueDeserializer',
            ],
        ];

        foreach ($callbacks as [$isDefault, $callback, $property]) {
            if ($isDefault) {
                $this->{$property} = $callback;
            }
        }

        return $this;
    }

    /**
     * @param null|VSerializerCallback $valueSerializer
     * @param null|VDeserializerCallback $valueDeserializer
     */
    public final static function uintKey(
        bool $isBigInt = true,
        ?callable $valueSerializer = null,
        ?callable $valueDeserializer = null,
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
    public final static function intKey(
        bool $isBigInt = true,
        ?callable $valueSerializer = null,
        ?callable $valueDeserializer = null,
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
    public final static function addressKey(
        ?callable $valueSerializer = null,
        ?callable $valueDeserializer = null,
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
     * @param VSerializerCallback|null $keySerializer
     * @param VDeserializerCallback|null $keyDeserializer
     */
    public final static function intValue(
        int $intSize,
        bool $isBigInt = true,
        ?callable $keySerializer = null,
        ?callable $keyDeserializer = null,
    ): self
    {
        return new self(
            $keySerializer,
            $keyDeserializer,
            static fn(int|BigInteger $v): Cell => (new Builder())->writeInt($v, $intSize)->cell(),
            static function (Cell $v) use($isBigInt, $intSize): int|BigInteger {
                $value = $v
                    ->beginParse()
                    ->loadInt($intSize);

                return $isBigInt ? $value : $value->toInt();
            },
        );
    }

    /**
     * @param VSerializerCallback|null $keySerializer
     * @param VDeserializerCallback|null $keyDeserializer
     */
    public final static function uintValue(
        int $uintSize,
        bool $isBigInt = true,
        ?callable $keySerializer = null,
        ?callable $keyDeserializer = null,
    ): self
    {
        return new self(
            $keySerializer,
            $keyDeserializer,
            static fn(int|BigInteger $v): Cell => (new Builder())->writeUint($v, $uintSize)->cell(),
            static function (Cell $v) use($isBigInt, $uintSize): int|BigInteger {
                $value = $v
                    ->beginParse()
                    ->loadUint($uintSize);

                return $isBigInt ? $value : $value->toInt();
            },
        );
    }

    /**
     * @return KSerializerCallback
     * @phpstan-return  KSerializerCallback
     */
    public final function getKeySerializer(): callable
    {
        return $this->keySerializer;
    }

    /**
     * @return KDeserializerCallback
     * @phpstan-return KDeserializerCallback
     */
    public final function getKeyDeserializer(): callable
    {
        return $this->keyDeserializer;
    }

    /**
     * @return VSerializerCallback
     * @phpstan-return VSerializerCallback
     */
    public final function getValueSerializer(): callable
    {
        return $this->valueSerializer;
    }

    /**
     * @return VDeserializerCallback
     * @phpstan-return VDeserializerCallback
     */
    public final function getValueDeserializer(): callable
    {
        return $this->valueDeserializer;
    }

    /**
     * @param KSerializerCallback $keySerializer
     * @phpstan-param KSerializerCallback $keySerializer
     */
    public final function setKeySerializer(callable $keySerializer): void
    {
        $this->keySerializer = $keySerializer;
        $this->isDefKeySerializer = false;
    }

    /**
     * @param KDeserializerCallback $keyDeserializer
     * @phpstan-param KDeserializerCallback $keyDeserializer
     */
    public final function setKeyDeserializer(callable $keyDeserializer): void
    {
        $this->keyDeserializer = $keyDeserializer;
        $this->isDefKeySerializer = false;
    }

    /**
     * @param VSerializerCallback $valueSerializer
     * @phpstan-param  VSerializerCallback $valueSerializer
     */
    public final function setValueSerializer(callable $valueSerializer): void
    {
        $this->valueSerializer = $valueSerializer;
        $this->isDefValueSerializer = false;
    }

    /**
     * @param KDeserializerCallback $valueDeserializer
     * @phpstan-param  KDeserializerCallback $valueDeserializer
     */
    public final function setValueDeserializer(callable $valueDeserializer): void
    {
        $this->valueDeserializer = $valueDeserializer;
        $this->isDefKeyDeserializer = false;
    }
}
