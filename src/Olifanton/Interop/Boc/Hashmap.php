<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use IteratorAggregate;
use Olifanton\Interop\Boc\Exceptions\BitStringException;
use Olifanton\Interop\Boc\Exceptions\CellException;
use Olifanton\Interop\Boc\Exceptions\HashmapException;
use Olifanton\Interop\Boc\Exceptions\SliceException;
use Olifanton\Interop\Boc\Helpers\BitHelper;
use Olifanton\Interop\Helpers\ArrayHelper;
use Traversable;

/**
 * @phpstan-type BitLike 0|1|bool
 * @phpstan-type HashmapKV array{0: BitLike[], 1: Cell}
 */
class Hashmap implements IteratorAggregate
{
    /**
     * @var \ArrayObject<string, Cell>
     */
    protected \ArrayObject $hashmap;

    protected DictSerializers $serializers;

    public function __construct(
        protected readonly int $keySize,
        ?DictSerializers       $serializers = null,
    )
    {
        if ($this->keySize < 1) {
            throw new \InvalidArgumentException();
        }

        $this->hashmap = new \ArrayObject();
        $this->serializers = $serializers ?? new DictSerializers();
    }

    public function getIterator(): Traversable
    {
        return (function () {
            foreach ($this->hashmap as $k => $v) {
                $key = $this->deserializeKey((string)$k);
                $value = $this->deserializeValue($v);

                yield $key => $value;
            }
        })();
    }

    public function keys(): array
    {
        $result = [];

        foreach ($this->hashmap as $k => $_) {
            $result[] = self::deserializeKey((string)$k);
        }

        return $result;
    }

    public function get($key): mixed
    {
        $k = $this->serializeKey($key);

        if (!$this->hashmap->offsetExists($k)) {
            return null;
        }

        return $this->deserializeValue($this->hashmap[$k]);
    }

    public function has(array $key): bool
    {
        $k = $this->serializeKey($key);

        return $this->hashmap->offsetExists($k);
    }

    public function set($key, $value): self
    {
        $k = $this->serializeKey($key);
        $v = $this->serializeValue($value);

        $this->hashmap[$k] = $v;

        return $this;
    }

    public function add($key, $value): self
    {
        return !$this->has($key) ? $this->set($key, $value) : $this;
    }

    public function replace($key, $value): self
    {
        return $this->has($key) ? $this->set($key, $value) : $this;
    }

    public function delete($key): self
    {
        $k = $this->serializeKey($key);

        if ($this->hashmap->offsetExists($k)) {
            $this->hashmap->offsetUnset($k);
        }

        return $this;
    }

    public function isEmpty(): bool
    {
        return $this->hashmap->count() === 0;
    }

    /**
     * @throws HashmapException
     */
    public function cell(): Cell
    {
        return $this->serialize();
    }

    /**
     * @throws HashmapException
     */
    public static function parse(int $keySize, Cell $cell, ?DictSerializers $serializers = null): self
    {
        $dict = new self($keySize, $serializers);
        $nodes = self::deserializeEdge($cell, $keySize);

        for ($i = 0; $i < count($nodes); $i++) {
            [$key, $value] = $nodes[$i];
            $dict->setRaw($key, $value);
        }

        return $dict;
    }

    /**
     * @param BitLike[] $key
     */
    protected function setRaw(array $key, Cell $value): self
    {
        $this->hashmap[self::implodeBitArray($key)] = $value;

        return $this;
    }

    protected function serializeKey($key): string
    {
        $keyArray = call_user_func(
            $this->serializers->getKeySerializer(),
            $key,
            $this->keySize,
        );

        if (!is_array($keyArray)) {
            throw new \RuntimeException(
                "Key serializer error, expects array, " . gettype($keyArray) . " given",
            );
        }

        return self::implodeBitArray($keyArray);
    }

    protected function deserializeKey(string $k)
    {
        return call_user_func(
            $this->serializers->getKeyDeserializer(),
            array_map("intval", str_split($k)),
            $this->keySize,
        );
    }

    protected function serializeValue($value): Cell
    {
        $cellValue = call_user_func(
            $this->serializers->getValueSerializer(),
            $value,
        );

        if (!$cellValue instanceof Cell) {
            throw new \RuntimeException(
                "Value serializer error, expects Cell, " . gettype($cellValue) . " given",
            );
        }

        return $cellValue;
    }

    protected function deserializeValue(Cell $value)
    {
        return call_user_func(
            $this->serializers->getValueDeserializer(),
            $value,
        );
    }

    /**
     * @throws HashmapException
     */
    protected function serialize(): Cell
    {
        $nodes = $this->getSortedHashmap();

        if (empty($nodes)) {
            throw new \RuntimeException("HashMap cannot be empty. It must contain at least one key-value pair.");
        }

        return self::serializeEdge($nodes);
    }

    /**
     * @return HashmapKV[]
     */
    protected function getSortedHashmap(): array
    {
        $copy = $this->hashmap->getArrayCopy();
        $sorted = array_reduce(
            array_keys($copy),
            function (array $acc, string $bitString) use ($copy) {
                /** @var array{order: int, key: BitLike[], value: Cell}[] $acc */
                $key = array_map(static fn (string $c) => (int)$c, str_split($bitString));
                $order = intval($bitString, 2);
                $lt = ArrayHelper::arraySearch($acc, function ($a) use ($order) {
                    return $order > $a["order"];
                });
                $index = $lt !== null ? $lt : count($acc);

                array_splice($acc, $index, 0, [
                    [
                        "order" => $order,
                        "key" => $key,
                        "value" => $copy[$bitString],
                    ]
                ]);

                return $acc;
            },
            [],
        );

        return array_map(static fn (array $s) => [$s["key"], $s["value"]], $sorted);
    }

    /**
     * @param HashmapKV[] &$nodes
     * @throws HashmapException
     */
    protected static function serializeEdge(array $nodes): Cell
    {
        try {
            if (empty($nodes)) {
                return (new Builder())
                    ->writeBitArray(self::serializeLabelShort([]))
                    ->cell();
            }

            $edge = new Cell();
            $label = self::serializeLabel($nodes);

            $edge->bits->writeBitArray($label);
            $nodesLength = count($nodes);

            if ($nodesLength === 1) {
                $leaf = self::serializeLeaf($nodes[0]);
                $edge->writeCell($leaf);
            }

            if ($nodesLength > 1) {
                [$leftNodes, $rightNodes] = self::serializeFork($nodes);
                $leftEdge = self::serializeEdge($leftNodes);
                $edge->refs[] = $leftEdge;

                if (!empty($rightNodes)) {
                    $rightEdge = self::serializeEdge($rightNodes);
                    $edge->refs[] = $rightEdge;
                }
            }

            return $edge;
        } catch (BitStringException|CellException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @param HashmapKV[] $nodes
     * @return array{0: HashmapKV[], 1: HashmapKV[]}
     */
    protected static function serializeFork(array $nodes): array
    {
        return array_reduce(array_keys($nodes), static function (array $acc, int $nk) use ($nodes) {
            /** @var BitLike[] $key */
            /** @var Cell $value */
            [$key, $value] = $nodes[$nk];

            $acc[array_shift($key)][] = [
                $key,
                $value,
            ];

            return $acc;
        }, [[], []]);
    }

    /**
     * @param HashmapKV & array $node
     * @return Cell
     */
    protected static function serializeLeaf(array $node): Cell
    {
        return $node[1];
    }

    /**
     * @param HashmapKV[] $nodes
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function serializeLabel(array &$nodes): array
    {
        /** @var BitLike[] $first */
        $first = $nodes[0][0];
        /** @var BitLike[] $last */
        $last = $nodes[count($nodes) - 1][0];

        $m = count($first);
        $sameBitsIndex = ArrayHelper::arraySearch(
            $first,
            static fn(int|bool $bit, int $i) => isset($last[$i]) && (int)$bit !== (int)$last[$i],
        );
        $sameBitsLength = $sameBitsIndex === null ? count($first) : $sameBitsIndex;

        if (($first[0] ?? null) !== ($last[0] ?? null) || !$m) {
            return self::serializeLabelShort([]);
        }

        $label = array_slice($first, 0, $sameBitsLength);
        $matches = [];
        preg_match(
            '/(^0+)|(^1+)/',
            self::implodeBitArray($label),
            $matches,
        );
        /** @var BitLike[] $repeated */
        $repeated = array_map(
            static fn(string $s) => (int)$s,
            str_split($matches[0]),
        );
        $labelShort = self::serializeLabelShort($label);
        $labelLong = self::serializeLabelLong($label, $m);
        $labelSame = count($nodes) > 1 && count($repeated) > 1
            ? self::serializeLabelSame($repeated, $m)
            : null;
        $labels = array_filter([
            [
                "bits" => count($label),
                "label" => $labelShort,
            ],
            [
                "bits" => count($label),
                "label" => $labelLong,
            ],
            [
                "bits" => count($repeated),
                "label" => $labelSame,
            ],
        ], static fn(array $el) => $el['label'] !== null);

        usort($labels, static fn(array $a, array $b) => count($a['label']) <=> count($b['label']));
        $chosen = $labels[0];

        foreach ($nodes as &$node) {
            array_splice($node[0], 0, $chosen["bits"]);
        }
        unset($node);

        return $chosen["label"];
    }

    /**
     * @param BitLike[] $bits
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function serializeLabelShort(array $bits): array
    {
        try {
            return BitString::empty()
                ->writeBit(0)
                ->writeBitArray(array_fill(0, count($bits), 1))
                ->writeBit(0)
                ->writeBitArray($bits)
                ->toBitsA();
        // @codeCoverageIgnoreStart
        } catch (BitStringException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param BitLike[] $bits
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function serializeLabelLong(array $bits, int $m): array
    {
        try {
            return BitString::empty()
                ->writeBitArray([1, 0])
                ->writeUint(
                    count($bits),
                    (int)ceil(log($m + 1, 2)),
                )
                ->writeBitArray($bits)
                ->toBitsA();
        // @codeCoverageIgnoreStart
        } catch (BitStringException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param BitLike[] $bits
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function serializeLabelSame(array $bits, int $m): array
    {
        try {
            /** @noinspection PhpStrictTypeCheckingInspection */
            return BitString::empty()
                ->writeBitArray([1, 1])
                ->writeBit($bits[0])
                ->writeUint(
                    count($bits),
                    (int)ceil(log($m + 1, 2)),
                )
                ->toBitsA();
        // @codeCoverageIgnoreStart
        } catch (BitStringException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param BitLike[] $key
     * @return HashmapKV[]
     * @throws HashmapException
     */
    protected static function deserializeEdge(Cell $edge, int $keySize, array $key = []): array
    {
        try {
            $edgeSlice = $edge->beginParse();

            foreach (self::deserializeLabel($edgeSlice, $keySize - count($key)) as $bit) {
                $key[] = $bit;
            }

            if (count($key) === $keySize) {
                $value = (new Builder())->writeSlice($edgeSlice)->cell();

                return [[$key, $value]];
            }

            return array_reduce(
                $edgeSlice->getRefsCount() > 0 ? range(0, $edgeSlice->getRefsCount() - 1) : [],
                static function (array $acc, int $i) use ($edgeSlice, $key, $keySize) {
                    $forkEdge = $edgeSlice->loadRef();
                    $forkKey = array_merge($key, [$i]);

                    return array_merge(
                        $acc,
                        self::deserializeEdge($forkEdge, $keySize, $forkKey),
                    );
                },
                [],
            );
        // @codeCoverageIgnoreStart
        } catch (CellException|SliceException $e) {
            throw new HashmapException(
                $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function deserializeLabel(Slice $edge, int $m): array
    {
        try {
            if (!$edge->loadBit()) {
                return self::deserializeLabelShort($edge);
            }

            if (!$edge->loadBit()) {
                return self::deserializeLabelLong($edge, $m);
            }

            return self::deserializeLabelSame($edge, $m);
        // @codeCoverageIgnoreStart
        } catch (SliceException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function deserializeLabelShort(Slice $edge): array
    {
        try {
            $length = ArrayHelper::arraySearch(
                $edge->getRemainingBits(),
                static function (bool $bit) {
                    return !$bit;
                }
            ) ?? -1;

            $edge->skipBits($length + 1);
            $result = [];

            for ($i = 0; $i < $length; $i++) {
                $result[] = (int)$edge->loadBit();
            }

            return $result;
        // @codeCoverageIgnoreStart
        } catch (SliceException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function deserializeLabelLong(Slice $edge, int $m): array
    {
        try {
            $length = $edge->loadUint(
                (int)ceil(log($m + 1, 2)),
            )->toInt();

            return array_chunk((new BitString(BitHelper::alignBits($length)))
                ->writeBytes($edge->loadBits($length))
                ->toBitsA(), $length)[0];
        // @codeCoverageIgnoreStart
        } catch (SliceException|BitStringException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @return BitLike[]
     * @throws HashmapException
     */
    protected static function deserializeLabelSame(Slice $edge, int $m): array
    {
        try {
            $repeated = $edge->loadBit();
            $length = $edge->loadUint((int)ceil(log($m + 1, 2)))->toInt();

            return array_fill(0, $length, $repeated);
        // @codeCoverageIgnoreStart
        } catch (SliceException $e) {
            throw new HashmapException($e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * @param BitLike[] $ba
     * @return string
     */
    private static function implodeBitArray(array $ba): string
    {
        return implode('', array_map("intval", $ba));
    }
}
