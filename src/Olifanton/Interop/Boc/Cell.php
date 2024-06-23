<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use JetBrains\PhpStorm\ArrayShape;
use Olifanton\Interop\Boc\Exceptions\BitStringException;
use Olifanton\Interop\Boc\Exceptions\CellException;
use Olifanton\Interop\Boc\Exceptions\SliceException;
use Olifanton\Interop\Boc\Helpers\CellType;
use Olifanton\Interop\Boc\Helpers\CellTypeResolver;
use Olifanton\Interop\Boc\Helpers\LevelMask;
use Olifanton\Interop\Boc\Helpers\MaskResolver;
use Olifanton\Interop\Boc\Helpers\TypedArrayHelper;
use Olifanton\Interop\Boc\Helpers\BocMagicPrefix;
use Olifanton\Interop\Bytes;
use Olifanton\Interop\Checksum;
use Olifanton\Interop\Crypto;
use Olifanton\Interop\Exceptions\CryptoException;
use Olifanton\TypedArrays\Uint8Array;
use function DeepCopy\deep_copy;

/**
 * Cell
 *
 * `Cell` is a class that implements the concept of [TVM Cells](https://ton.org/docs/learn/overviews/Cells) in PHP. To create new and process received messages from the blockchain, you will work with instances of the Cell class.
 *
 * @property-read BitString $bits
 * @property \ArrayObject<Cell> & Cell[] $refs
 */
class Cell
{
    public const SIZE = 1023;

    private BitString $bits;

    /**
     * @var \ArrayObject
     */
    private \ArrayObject $_refs;

    /**
     * @var int[]
     */
    private array $refs_r = []; // @phpstan-ignore-line

    private bool $isExotic = false;

    private CellType $type = CellType::ORDINARY;

    protected ?Uint8Array $_hash = null;

    /**
     * Creates array of Cell's from byte array or hex string.
     *
     * @return Cell[]
     * @throws CellException
     */
    public static function fromBoc(string|Uint8Array $serializedBoc): array
    {
        return self::deserializeBoc($serializedBoc);
    }

    /**
     * Fetch one root Cell from byte array or hex string.
     *
     * @throws CellException
     */
    public static function oneFromBoc(string|Uint8Array $serializedBoc, bool $isBase64 = false): Cell
    {
        $cells = self::deserializeBoc($serializedBoc, $isBase64);
        $cellsCount = count($cells);

        if ($cellsCount !== 1) {
            throw new CellException("Expected 1 root cell but have " . $cellsCount . " cells");
        }

        return $cells[0];
    }

    public function __construct()
    {
        $this->bits = new BitString(self::SIZE);
        $this->_refs = new \ArrayObject();
        (function (Cell $self) {
            /** @noinspection PhpDynamicFieldDeclarationInspection */
            $this->_cell = $self; // @phpstan-ignore-line
        })(...)->call($this->bits, $this);
    }

    /**
     * Writes another Cell to this cell and returns this cell.
     *
     * @throws CellException
     */
    public function writeCell(Cell $anotherCell): self
    {
        try {
            $this->bits->writeBitString($anotherCell->bits);
        } catch (BitStringException $e) {
            throw new CellException("Cell writing error: " . $e->getMessage(), $e->getCode(), $e);
        }

        $this->_refs = new \ArrayObject(
            array_merge($this->_refs->getArrayCopy(), $anotherCell->_refs->getArrayCopy())
        );
        $this->_hash = null;

        return $this;
    }

    public function getMaxLevel(): int
    {
        $maxLevel = 0;

        foreach ($this->_refs as $ref) {
            $rMaxLevel = $ref->getMaxLevel();
            $maxLevel = ($rMaxLevel > $maxLevel) ? $rMaxLevel : $maxLevel;
        }

        return $maxLevel;
    }

    /**
     * Returns max depth of child cells.
     */
    public function getMaxDepth(): int
    {
        $maxDepth = 0;

        if ($this->_refs->count() > 0) {
            foreach ($this->_refs as $ref) {
                /** @var Cell $ref */
                $rMaxDepth = $ref->getMaxDepth();
                $maxDepth = ($rMaxDepth > $maxDepth) ? $rMaxDepth : $maxDepth;
            }

            $maxDepth++;
        }

        return $maxDepth;
    }

    public function getRefsDescriptor(): Uint8Array
    {
        $result = new Uint8Array(1);
        $result->fSet(0, count($this->_refs) + (int)$this->isExotic * 8 + $this->getMaxLevel() * 32,);

        return $result;
    }

    public function getBitsDescriptor(): Uint8Array
    {
        $usedBits = $this->bits->getUsedBits();
        $result = new Uint8Array(1);
        $result->fSet(0, (int)ceil($usedBits / 8) + (int)floor($usedBits / 8));

        return $result;
    }

    /**
     * @throws CellException
     */
    public function getDataWithDescriptors(): Uint8Array
    {
        $d1 = $this->getRefsDescriptor();
        $d2 = $this->getBitsDescriptor();

        try {
            $tuBits = $this->bits->getTopUppedArray();
        // @codeCoverageIgnoreStart
        } catch (BitStringException $e) {
            throw new CellException(
                "Getting data with descriptors error: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        // @codeCoverageIgnoreEnd

        return Bytes::concatBytes(Bytes::concatBytes($d1, $d2), $tuBits);
    }

    /**
     * @throws CellException
     */
    public function getRepr(): Uint8Array
    {
        if ($this->isExotic) {
            if ($this->type === CellType::MERKLE_PROOF || $this->type === CellType::MERKLE_UPDATE) {
                throw new \LogicException(
                    "Hash calculation for Merkle proof / Merkle update cells currently not supported",
                );
            }
        }

        $reprArray = [
            $this->getDataWithDescriptors(),
        ];

        foreach ($this->_refs as $ref) {
            /** @var Cell $ref */
            $reprArray[] = $ref->getMaxDepthAsArray();
        }

        foreach ($this->_refs as $ref) {
            /** @var Cell $ref */
            $reprArray[] = $ref->hash();
        }

        $x = $reprArray[0];

        foreach ($reprArray as $i => $repr) {
            if ($i > 0) {
                $x = Bytes::concatBytes($x, $repr);
            }
        }

        return $x;
    }

    /**
     * Returns internal BitString instance for writing and reading.
     */
    public function getBits(): BitString
    {
        return $this->bits;
    }

    /**
     * Returns Array-like object of children cells.
     *
     * @return \ArrayObject<Cell>
     */
    public function getRefs(): \ArrayObject
    {
        return $this->_refs;
    }

    /**
     * Returns SHA-256 hash of this Cell.
     *
     * @throws CellException
     */
    public function hash(): Uint8Array
    {
        if ($this->_hash) {
            return $this->_hash;
        }

        try {
            $this->_hash = Crypto::sha256($this->getRepr());

            return $this->_hash;
        // @codeCoverageIgnoreStart
        } catch (CryptoException $e) {
            throw new CellException("SHA256 digest error: " . $e->getMessage(), $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Recursively prints cell's content like Fift.
     *
     * @throws BitStringException
     */
    public function print(string $indent = ''): string
    {
        $s = $indent . 'x{' . $this->bits->toHex() . "}\n";

        foreach ($this->_refs as $ref) {
            $s .= $ref->print($indent . ' ');
        }

        return $s;
    }

    /**
     * Create BoC Byte array
     *
     * @throws CellException
     */
    public function toBoc(bool $has_idx = true,
                          bool $hash_crc32 = true,
                          bool $has_cache_bits = false,
                          int  $flags = 0): Uint8Array
    {
        //serialized_boc#b5ee9c72 has_idx:(## 1) has_crc32c:(## 1)
        //  has_cache_bits:(## 1) flags:(## 2) { flags = 0 }
        //  size:(## 3) { size <= 4 }
        //  off_bytes:(## 8) { off_bytes <= 8 }
        //  cells:(##(size * 8))
        //  roots:(##(size * 8)) { roots >= 1 }
        //  absent:(##(size * 8)) { roots + absent <= cells }
        //  tot_cells_size:(##(off_bytes * 8))
        //  root_list:(roots * ##(size * 8))
        //  index:has_idx?(cells * ##(off_bytes * 8))
        //  cell_data:(tot_cells_size * [ uint8 ])
        //  crc32c:has_crc32c?uint32
        // = BagOfCells;

        $root_cell = $this;

        $allCells = self::treeWalk($root_cell, [], []);
        $topologicalOrder = $allCells["topologicalOrderArray"];
        $cellsIndex = $allCells["indexHashmap"];

        $cells_num = count($topologicalOrder);
        $s = strlen(decbin($cells_num));
        $s_bytes = max((int)ceil($s / 8), 1);
        $full_size = 0;
        $sizeIndex = [];

        foreach ($topologicalOrder as $cell_info) {
            $sizeIndex[] = $full_size;
            /** @var Cell $cell_ */
            $cell_ = $cell_info[1];
            $full_size = $full_size + $cell_->bocSerializationSize($cellsIndex);
        }

        $offset_bits = strlen(decbin($full_size));
        $offset_bytes = max((int)ceil($offset_bits / 8), 1);

        $serialization = new BitString((self::SIZE + 32 * 4 + 32 * 3) * count($topologicalOrder));

        try {
            $serialization->writeBytes(BocMagicPrefix::reachBocMagicPrefix());
            $serialization->writeBitArray([$has_idx, $hash_crc32, $has_cache_bits]);
            $serialization->writeUint($flags, 2);
            $serialization->writeUint($s_bytes, 3);
            $serialization->writeUint8($offset_bytes);
            $serialization->writeUint($cells_num, $s_bytes * 8);
            $serialization->writeUint(1, $s_bytes * 8); // One root for now
            $serialization->writeUint(0, $s_bytes * 8); // Complete BOCs only
            $serialization->writeUint($full_size, $offset_bytes * 8);
            $serialization->writeUint(0, $s_bytes * 8); // Root should have zero index

            if ($has_idx) {
                foreach ($topologicalOrder as $index => $cell_data) {
                    $serialization->writeUint($sizeIndex[$index], $offset_bytes * 8);
                }
            }

            foreach ($topologicalOrder as $cell_info) {
                /** @var Cell $cell_ */
                $cell_ = $cell_info[1];
                $refcell_ser = $cell_->serializeForBoc($cellsIndex);
                $serialization->writeBytes($refcell_ser);
            }

            $ser_arr = $serialization->getTopUppedArray();

            if ($hash_crc32) {
                $ser_arr = Bytes::concatBytes($ser_arr, Checksum::crc32c($ser_arr));
            }

            return $ser_arr;
        } catch (BitStringException $e) {
            throw new CellException("BoC serialization error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * @throws CellException
     */
    public function beginParse(): Slice
    {
        return new Slice(
            $this->bits->getImmutableArray(),
            $this->bits->getLength(),
            array_map(fn (Cell $ref) => (new Builder())->writeCell($ref)->cell(), $this->refs->getArrayCopy()),
            $this->bits->getUsedBits(),
        );
    }

    /**
     * @throws CellException
     */
    public function getLevelMask(): LevelMask
    {
        try {
            return MaskResolver::get($this->bits, $this->type, $this->refs);
        } catch (SliceException $e) {
            throw new CellException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function __get(string $name)
    {
        if ($name === "bits") {
            return $this->getBits();
        }

        if ($name === "refs") {
            return $this->getRefs();
        }

        throw new \InvalidArgumentException("Unknown property \"$name\"");
    }

    private function getMaxDepthAsArray(): Uint8Array
    {
        $maxDepth = $this->getMaxDepth();
        $d = new Uint8Array(2);
        $d->fSet(0, (int)floor($maxDepth / 256));
        $d->fSet(1, $maxDepth % 256);

        return $d;
    }

    /**
     * @param array<string, int> $cellsIndex
     * @throws CellException
     */
    private function serializeForBoc(array $cellsIndex): Uint8Array
    {
        $reprArray = [
            $this->getDataWithDescriptors(),
        ];

        foreach ($this->_refs as $ref) {
            $refHash = $ref->hash();
            $refIndexInt = $cellsIndex[Bytes::arrayToBytes($refHash)];
            $refIndexHex = dechex($refIndexInt);

            if (strlen($refIndexHex) % 2) {
                $refIndexHex = "0" . $refIndexHex;
            }

            $reference = Bytes::hexStringToBytes($refIndexHex);
            $reprArray[] = $reference;
        }

        $x = $reprArray[0];

        foreach ($reprArray as $i => $repr) {
            if ($i > 0) {
                $x = Bytes::concatBytes($x, $repr);
            }
        }

        return $x;
    }

    /**
     * @throws CellException
     */
    private function bocSerializationSize(array $cellsIndex): int
    {
        return $this->serializeForBoc($cellsIndex)->length;
    }

    /**
     * @return Cell[]
     * @throws CellException
     */
    private static function deserializeBoc(string|Uint8Array $serializedBoc, bool $isBase64 = false): array
    {
        if (!$serializedBoc instanceof Uint8Array) {
            try {
                if ($isBase64) {
                    if (!self::isBase64String($serializedBoc)) {
                        throw new CellException("\$serializedBoc must to be valid base64 string");
                    }

                    $serializedBoc = Bytes::base64ToBytes($serializedBoc);
                } else {
                    if (!self::isHexString($serializedBoc)) {
                        throw new CellException("\$serializedBoc must to be valid hex string");
                    }

                    $serializedBoc = Bytes::hexStringToBytes($serializedBoc);
                }
            } catch (\Throwable $e) {
                throw new CellException($e->getMessage(), $e->getCode(), $e);
            }
        }

        $header = self::parseBocHeader($serializedBoc);
        $cells_data = $header["cells_data"];

        /** @var Cell[] $cells_array */
        $cells_array = [];

        for ($ci = 0; $ci < $header["cells_num"]; $ci++) {
            try {
                $dd = self::deserializeCellData($cells_data, $header["size_bytes"]);
            } catch (BitStringException|SliceException $e) {
                throw new CellException(
                    "Cell data deserialization error: " . $e->getMessage() . "; cell_num idx: " . $ci,
                    $e->getCode(),
                    $e
                );
            }

            $cells_data = $dd["residue"];
            $cells_array[] = $dd["cell"];
        }

        for ($ci = $header["cells_num"] - 1; $ci >= 0; $ci--) {
            $c = $cells_array[$ci];

            for ($ri = 0; $ri < count($c->refs_r); $ri++) {
                $r = $c->refs_r[$ri];

                if ($r < $ci) {
                    throw new CellException("Topological order is broken");
                }

                $c->_refs[$ri] = $cells_array[$r];
            }

            $c->refs_r = [];
        }

        $root_cells = [];

        foreach ($header["root_list"] as $ri) {
            $root_cells[] = $cells_array[$ri];
        }

        return $root_cells;
    }

    /**
     * @throws CellException
     * @noinspection PhpConditionAlreadyCheckedInspection
     */
    #[ArrayShape([
        "has_idx" => "int",
        "hash_crc32" => "int",
        "has_cache_bits" => "int",
        "flags" => "int",
        "size_bytes" => "int",
        "off_bytes" => "int",
        "cells_num" => "int",
        "roots_num" => "int",
        "absent_num" => "int",
        "tot_cells_size" => "int",
        "root_list" => "int[]",
        "index" => "int[]|false",
        "cells_data" => "Olifanton\\TypedArrays\\Uint8Array",
    ])]
    private static function parseBocHeader(Uint8Array $serializedBoc): array
    {
        if ($serializedBoc->length < 5) {
            throw new CellException("Not enough bytes for magic prefix");
        }

        /** @var Uint8Array $inputData */
        $inputData = deep_copy($serializedBoc);
        $serializedBocReader = new Slice(
            $serializedBoc,
            $serializedBoc->length * 8,
            [],
            usedBits: $serializedBoc->length * 8,
        );

        $prefix = $serializedBocReader->loadUint(4 * 8)->toBase(16);
        $size_bytes = $has_idx = $has_crc32 = $has_cache_bits = $flags = 0;

        if ($prefix === BocMagicPrefix::REACH_BOC_MAGIC_PREFIX) {
            $flags_byte = $serializedBocReader->loadUint(8)->toInt();
            $has_idx = $flags_byte & 128;
            $has_crc32 = $flags_byte & 64;
            $has_cache_bits = $flags_byte & 32;
            $flags = ($flags_byte & 16) * 2 + ($flags_byte & 8);
            $size_bytes = $flags_byte % 8;
        } elseif ($prefix === BocMagicPrefix::LEAN_BOC_MAGIC_PREFIX) {
            $has_idx = 1;
            $has_crc32 = 0;
            $has_cache_bits = 0;
            $flags = 0;
            $size_bytes = $serializedBocReader->loadUint(8)->toInt();
        } elseif ($prefix === BocMagicPrefix::LEAN_BOC_MAGIC_PREFIX_CRC) {
            $has_idx = 1;
            $has_crc32 = 1;
            $has_cache_bits = 0;
            $flags = 0;
            $size_bytes = $serializedBocReader->loadUint(8)->toInt();
        }

        if ($serializedBocReader->getFreeBytes() < 1 + 5 * $size_bytes) {
            throw new CellException("Not enough bytes for encoding cells counters");
        }

        $offset_bytes = $serializedBocReader->loadUint(8)->toInt();
        $cells_num = $serializedBocReader->loadUint($size_bytes * 8)->toInt();
        $roots_num = $serializedBocReader->loadUint($size_bytes * 8)->toInt();
        $absent_num = $serializedBocReader->loadUint($size_bytes * 8)->toInt();
        $tot_cells_size = $serializedBocReader->loadUint($offset_bytes * 8)->toInt();

        if ($serializedBocReader->getFreeBytes() < $roots_num * $size_bytes) {
            throw new CellException("Not enough bytes for encoding root cells hashes");
        }

        $root_list = [];

        for ($c = 0; $c < $roots_num; $c++) {
            $root_list[] = $serializedBocReader->loadUint($size_bytes * 8)->toInt();
        }

        $index = false;

        if ($has_idx) {
            $index = [];

            if ($serializedBocReader->getFreeBytes() < $offset_bytes * $cells_num) {
                throw new CellException("Not enough bytes for index encoding");
            }

            for ($c = 0; $c < $cells_num; $c++) {
                $index[] = $serializedBocReader->loadUint($offset_bytes * 8)->toInt();
            }
        }

        if ($serializedBocReader->getFreeBytes() < $tot_cells_size) {
            throw new CellException("Not enough bytes for cells data");
        }

        $cells_data = self::slice($inputData, $serializedBocReader->getReadBytes(), /*$tot_cells_size*/);
        $serializedBocReader->skipBits($tot_cells_size * 8);

        if ($has_crc32) {
            if ($serializedBocReader->getFreeBytes() < 4) {
                throw new CellException("Not enough bytes for crc32c checksum");
            }

            $length = $inputData->length;

            if (!Bytes::compareBytes(Checksum::crc32c(self::slice($inputData, 0, $length - 4)), $serializedBocReader->loadBits(32))) {
                throw new CellException("Crc32c checksum mismatch");
            }
        }

        if ($serializedBocReader->getFreeBits() > 0) {
            throw new CellException("Too much bytes in BoC serialization");
        }

        return [
            "has_idx" => $has_idx,
            "hash_crc32" => $has_crc32,
            "has_cache_bits" => $has_cache_bits,
            "flags" => $flags,
            "size_bytes" => $size_bytes,
            "off_bytes" => $offset_bytes,
            "cells_num" => $cells_num,
            "roots_num" => $roots_num,
            "absent_num" => $absent_num,
            "tot_cells_size" => $tot_cells_size,
            "root_list" => $root_list,
            "index" => $index,
            "cells_data" => $cells_data,
        ];
    }

    /**
     * @throws CellException|BitStringException|SliceException
     */
    #[ArrayShape([
        "cell" => "Olifanton\\Boc\\Cell",
        "residue" => "Olifanton\\TypedArrays\\Uint8Array",
    ])]
    private static function deserializeCellData(Uint8Array $cellData, int $referenceIndexSize): array
    {
        if ($cellData->length < 2) {
            throw new CellException("Not enough bytes to encode cell descriptors");
        }

        $d1 = $cellData[0];
        $d2 = $cellData[1];

        $isExotic = $d1 & 8;
        $refNum = $d1 % 8;
        $dataByteSize = (int)ceil($d2 / 2);
        $fulfilledBytes = !($d2 % 2);
        $levelMask = $d1 >> 5;
        $hasHashes = ($d1 & 16) !== 0;

        $cell = new Cell();
        $cell->isExotic = (bool)$isExotic;
        $offset = 2;

        if ($hasHashes) {
            $hashesCount = self::getHashesCountFromMask($levelMask & 7);
            $offset += $hashesCount * 32 + $hashesCount * 2;
        }

        $cellData = self::slice($cellData, $offset);

        if ($cellData->length < $dataByteSize + $referenceIndexSize * $refNum) {
            throw new CellException(sprintf(
                "Not enough bytes to encode cell data, needed: %d, cell data bytes: %d",
                $dataByteSize + $referenceIndexSize * $refNum,
                $cellData->length,
            ));
        }

        $cell
            ->bits
            ->setTopUppedArray(
                self::slice($cellData, 0, $dataByteSize),
                $fulfilledBytes,
            );

        if ($isExotic) {
            $cell->type = CellTypeResolver::get($cell->bits);
        }

        $cellData = self::slice($cellData, $dataByteSize);

        for ($r = 0; $r < $refNum; $r++) {
            $cell->refs_r[] = Bytes::readNBytesUIntFromArray($referenceIndexSize, $cellData);
            $cellData = self::slice($cellData, $referenceIndexSize);
        }

        return [
            "cell" => $cell,
            "residue" => $cellData,
        ];
    }

    /**
     * @throws CellException
     */
    #[ArrayShape([
        "topologicalOrderArray" => "array[]", // [0 => <string> cellHash, 1 => <Cell>]
        "indexHashmap" => "array<string, int>"
    ])]
    private static function treeWalk(Cell    $cell,
                                     array   $topologicalOrderArray,
                                     array   $indexHashmap,
                                     ?string $parentHash = null): array
    {
        $cellHash = Bytes::arrayToBytes($cell->hash());

        if (isset($indexHashmap[$cellHash])) {
            if ($parentHash) {
                if ($indexHashmap[$parentHash] > $indexHashmap[$cellHash]) {
                    self::moveToEnd($indexHashmap, $topologicalOrderArray, $cellHash);
                }
            }

            return [
                "topologicalOrderArray" => $topologicalOrderArray,
                "indexHashmap" => $indexHashmap,
            ];
        }

        $indexHashmap[$cellHash] = count($topologicalOrderArray);
        $topologicalOrderArray[] = [$cellHash, $cell];

        foreach ($cell->_refs as $subCell) {
            $res = self::treeWalk($subCell, $topologicalOrderArray, $indexHashmap, $cellHash);
            $topologicalOrderArray = $res["topologicalOrderArray"];
            $indexHashmap = $res["indexHashmap"];
        }

        return [
            "topologicalOrderArray" => $topologicalOrderArray,
            "indexHashmap" => $indexHashmap,
        ];
    }

    /**
     * @throws CellException
     */
    private static function moveToEnd(array  &$indexHashmap,
                                      array  &$topologicalOrderArray,
                                      string $target): void
    {
        $targetIndex = $indexHashmap[$target];

        foreach ($indexHashmap as $h => $index) {
            if ($index > $targetIndex) {
                $indexHashmap[$h] = $index - 1;
            }
        }

        $indexHashmap[$target] = count($topologicalOrderArray) - 1;
        $data = array_splice($topologicalOrderArray, $targetIndex, 1)[0];
        $topologicalOrderArray[] = $data;

        foreach ($data[1]->refs as $subCell) {
            /** @var Cell $subCell */
            self::moveToEnd(
                $indexHashmap,
                $topologicalOrderArray,
                Bytes::arrayToBytes($subCell->hash()),
            );
        }
    }

    private static function slice(Uint8Array $array, int $start, ?int $end = null): Uint8Array
    {
        return TypedArrayHelper::sliceUint8Array($array, $start, $end);
    }

    private static function isHexString(string $hexString): bool
    {
        return preg_match("/^[a-f0-9]{2,}$/i", $hexString) && !(strlen($hexString) & 1);
    }

    private static function isBase64String(string $base64String): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $base64String);
    }

    private static function getHashesCountFromMask(int $mask): int
    {
        $n = 0;

        for ($i = 0; $i < 3; $i++) {
            $n += ($mask & 1);
            $mask = $mask >> 1;
        }

        return $n + 1;
    }
}
