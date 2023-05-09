Olifanton Interop library
---

![Code Coverage Badge](./.github/badges/coverage.svg)
![Tests](https://github.com/olifanton/interop/actions/workflows/tests.yml/badge.svg)
[![Latest Stable Version](https://poser.pugx.org/olifanton/interop/v/stable)](https://packagist.org/packages/olifanton/interop)
[![Total Downloads](https://poser.pugx.org/olifanton/interop/downloads)](https://packagist.org/packages/olifanton/interop)


## Installation

```bash
composer require olifanton/interop
```

## Documentation

### Getting started

Install [`olifanton/interop`](https://packagist.org/packages/olifanton/interop) package via Composer and include autoload script:

```php
<?php declare(strict_types=1);

require __DIR__ . "/vendor/autoload.php";

use Olifanton\Interop\Boc\BitString;
use Olifanton\Interop\Boc\Cell;

// Now you can use Interop classes

```

### Components

#### Address

`Olifanton\Interop\Address`

`Address` is a class that allows you to work with smart contract addresses in the TON network. Read more about Addresses in official [documentation](https://ton.org/docs/learn/overviews/addresses).

##### _Address_ constructor

```php
/**
 * @param string | \Olifanton\Interop\Address $anyForm
 */
public function __construct(string | Address $anyForm)
```

Parameters:

- `$anyForm` &mdash; Address in supported form. Supported values are:
    - Friendly format (base64 encoded, URL safe or not): `EQBvI0aFLnw2QbZgjMPCLRdtRHxhUyinQudg6sdiohIwg5jL`;
    - Raw form: `-1:fcb91a3a3816d0f7b8c2c76108b8a9bc5a6b7a55bd79f8ab101c52db29232260`;
    - Other `Address` instance, in this case the new instance will be an immutable copy of the other address.

Depending on the passed value, the Address instance will store information about the input address flags.

If the input value is not a valid address, then `\InvalidArgumentException` will be thrown.

##### _Address_ static methods

###### isValid(string | \Olifanton\Interop\Address $anyForm): bool
Checks if the passed value is a valid address in any form.

##### _Address_ methods

###### toString(): string
```php
/**
 * @param bool|null $isUserFriendly User-friendly flag
 * @param bool|null $isUrlSafe URL safe encoded flag
 * @param bool|null $isBounceable Bounceable address flag
 * @param bool|null $isTestOnly Testnet Only flag
 */
public function toString(?bool $isUserFriendly = null,
                         ?bool $isUrlSafe = null,
                         ?bool $isBounceable = null,
                         ?bool $isTestOnly = null): string
```
Returns a string representation of Address.

If all parameters are left as default, then the address will be formatted with the same flags whose value was recognized in the constructor.

###### getWorkchain(): int
Returns Workchain ID. Returns `-1` for Masterchain and `0` for basic workchain.

###### getHashPart(): Uint8Array
Returns address Account ID.

###### isTestOnly(): bool
Returns true if the address has the `isTestnetOnly` flag.

###### isBounceable(): bool
Returns true if the address has the `isBounceable` flag.

###### isUserFriendly(): bool
Returns true if the address is user-friendly.

###### isUrlSafe(): bool
Returns true if the address was encoded with URL-safe characters only.

#### BitString

`Olifanton\Interop\Boc\BitString`

`BitString` is a class that allows you to manipulate binary data. `BitString` is at the heart of the PHP representation of TVM Cells. `BitString` is memory optimized for storing binary data.
Internally, BitString uses implementation of `Uint8Array` provided by [`olifanton/typed-arrays`](https://packagist.org/packages/olifanton/typed-arrays) package and is used as the base type for transferring binary data between parts of the Olifanton libraries.

The BitString instance is created with a strictly fixed length. `write%` (writeBit, writeUint, ...) methods move the internal cursor. If you try to write a value that exceeds the length of the free bits, `BitStringException` exception will be thrown.

##### _BitString_ constructor

```php
/**
 * @param int $length
 */
public function __construct(int $length)
```

Parameters:

- `$length` &mdash; length of Uint8Array. Default value for TVM Cell: _1023_ ([Documentation](https://docs.ton.org/learn/overviews/cells))

##### _BitString_ methods

###### getFreeBits(): int
Returns unused bits length of BitString.


###### getUsedBits(): int
Returns used bits length of BitString.


###### getUsedBytes(): int
Returns used bytes length of BitString.


###### get(): bool

```php
/**
 * @param int $n Position
 */
public function get(int $n): bool
```
Returns a bit value at `$n` position.


###### on(): void
```php
/**
 * @param int $n Position
 */
public function on(int $n): void
```
Sets a bit value to 1 at position `$n`.


###### off(): void
```php
/**
 * @param int $n Position
 */
public function off(int $n): void
```
Sets a bit value to 0 at position `$n`.


###### toggle(): void
```php
/**
 * @param int $n Position
 */
public function toggle(int $n): void
```
Toggle (inverse) bit value at position `$n`.


###### iterate(): \Generator
Returns Generator of used bits.

Example:
```php
<?php declare(strict_types=1);

use Olifanton\Interop\Boc\BitString;

$bs = new BitString(4);
$bs->writeBit(1);
$bs->writeBit(0);
$bs->writeBit(1);
$bs->writeBit(1);

foreach ($bs->iterate() as $b) {
    echo (int)$b;
}
// Prints "1011"
```


###### writeBit(): void
```php
/**
 * @param int|bool $b
 */
public function writeBit(int | bool $b): void
```
Writes bit and increase BitString internal cursor.


###### writeBitArray(): void
```php
/**
 * @param array<int | bool> $ba Array of bits
 */
public function writeBitArray(array $ba): void
```
Writes array of bits.

Example:
```php
<?php declare(strict_types=1);

use Olifanton\Interop\Boc\BitString;

$bs = new BitString(4);
$bs->writeBitArray([1, false, 0, true]);

foreach ($bs->iterate() as $b) {
    echo (int)$b;
}
// Prints "1001"
```


###### writeUint(): void
```php
/**
 * @param int|\Brick\Math\BigInteger $number Unsigned integer
 * @param int $bitLength Integer size (8, 16, 32, ...)
 */
public function writeUint(int | BigInteger $number, int $bitLength): void
```
Writes $bitLength-bit unsigned integer.


###### writeInt(): void
```php
/**
 * @param int|\Brick\Math\BigInteger $number Signed integer
 * @param int $bitLength Integer size (8, 16, 32, ...)
 */
public function writeInt(int | BigInteger $number, int $bitLength): void
```
Writes $bitLength-bit signed integer.


###### writeUint8(): void
Alias of `writeUint()` method with predefined $bitLength parameter value.


###### writeBytes(): void
```php
/**
 * @param \Olifanton\TypedArrays\Uint8Array $ui8 Byte array
 */
public function writeBytes(Uint8Array $ui8): void
```
Write array of unsigned 8-bit integers.


###### writeString(): void
```php
/**
 * @param string $value
 */
public function writeString(string $value): void
```
Writes UTF-8 string.


###### writeCoins(): void
```php
/**
 * @param int|\Brick\Math\BigInteger $amount
 */
public function writeCoins(int | BigInteger $amount): void;
```
Writes coins in nanotoncoins. 1 TON === 1000000000 (10^9) nanotoncoins.


###### writeAddress(): void
```php
/**
 * @param \Olifanton\Interop\Address|null $address TON Address
 */
public function writeAddress(?Address $address): void
```
Writes TON address


###### writeBitString(): void
```php
/**
 * @param \Olifanton\Interop\Boc\BitString $anotherBitString BitString instance
 */
public function writeBitString(BitString $anotherBitString): void
```
Writes another BitString to this BitString.


###### clone(): BitString
Clones this BitString and returns new BitString instance.


###### toHex(): string
Returns hex string representation of BitString.


###### getImmutableArray(): Uint8Array
Returns immutable copy of internal Uint8Array.


###### getLength(): int
Returns size of BitString in bits.

#### Cell

`Olifanton\Interop\Boc\Cell`

`Cell` is a class that implements the concept of [TVM Cells](https://docs.ton.org/learn/overviews/cells) in PHP. To create new and process received messages from the blockchain, you will work with instances of the Cell class.

##### _Cell_ constructor
Without parameters.


##### _Cell_ methods


###### fromBoc(): Array\<Cell\>
```php
/**
 * @param string|Uint8Array $serializedBoc Serialized BoC
 * @return Cell[]
 */
public static function fromBoc(string|Uint8Array $serializedBoc): array
```
Creates array of Cell's from byte array or hex string.


###### oneFromBoc(): Cell
```php
/**
 * @param string|Uint8Array $serializedBoc Serialized BoC
 * @param bool $isBase64 Base64-serialized flag, default false
 */
public static function oneFromBoc(string|Uint8Array $serializedBoc, bool $isBase64 = false): Cell
```
Fetch one root Cell from byte array or hex string.


###### writeCell(): void
```php
/**
 * @param Cell $anotherCell Another cell
 * @return Cell This Cell
 */
public function writeCell(Cell $anotherCell): self
```
Writes another Cell to this cell and returns this cell. Mutable method.


###### getMaxDepth(): int
Returns max depth of child cells.


###### getBits(): BitString
Returns internal BitString instance for writing and reading.


###### getRefs(): ArrayObject\<Cell\>
Returns Array-like object of children cells.


###### hash(): Uint8Array
Returns SHA-256 hash of this Cell.


###### print(): string
Recursively prints cell's content like Fift.


###### toBoc(): Uint8Array
```php
/**
 * @param bool $has_idx Default _true_
 * @param bool $hash_crc32 Default _true_
 * @param bool $has_cache_bits Default _false_
 * @param int $flags Default _0_
 */
public function toBoc(bool $has_idx = true,
                      bool $hash_crc32 = true,
                      bool $has_cache_bits = false,
                      int  $flags = 0): Uint8Array
```
Creates BoC Byte array.

#### Slice

`Olifanton\Interop\Boc\Slice`

`Slice` is the type of cell slices. A cell can be transformed into a slice, and then the data bits and references to other cells from the cell can be obtained by loading them from the slice.

`load%` (loadBit, loadUint, ...) methods move the Slice internal cursor. If you try to read a value that exceeds the length of the free bits, `SliceException` exception will be thrown.

##### _Slice_ constructor

```php
/**
 * @param \Olifanton\TypedArrays\Uint8Array $array
 * @param int $length
 * @param \Olifanton\Interop\Boc\Slice[] $refs
 */
public function __construct(Uint8Array $array, int $length, array $refs)
```

Parameters:

- `$array` &mdash; Uint8Array from BitString representation of Cell
- `$length` &mdash; BitString length
- `$refs` &mdash; Children Cells slices

##### _Slice_ methods

###### getFreeBits(): int
Returns the unread bits according to the internal cursor.

###### get(): bool
```php
/**
 * @param int $n
 */
public function get(int $n): bool
```
Returns a bit value at position `$n`.

###### loadBit(): bool
Reads a bit and moves the cursor.

###### loadBits(): Uint8Array
```php
/**
 * @param int $bitLength
 */
public function loadBits(int $bitLength): Uint8Array
```
Reads bit array.

###### loadUint(): BigInteger
```php
/**
 * @param int $bitLength
 */
public function loadUint(int $bitLength): BigInteger
```
Reads unsigned integer.

###### loadInt(): BigInteger
```php
/**
 * @param int $bitLength
 */
public function loadInt(int $bitLength): BigInteger
```
Reads signed integer.

###### loadVarUint(): BigInteger
```php
/**
 * @param int $bitLength
 */
public function loadVarUint(int $bitLength): BigInteger
```


###### loadCoins(): BigInteger
Reads TON amount in nanotoncoins.

###### loadAddress(): ?Address
Reads Address.

###### loadRef(): Slice
Reads one of children Cell.

#### Builder

`Olifanton\Interop\Boc\Builder`

The Builder allows you to quickly create cells (operating inside a BitString instance). All instances of the Builder class are mutable and all methods return the same instance. The builder interface in most cases duplicates the interface of the BitString class.

#### Hashmap

@WIP

---

## Tests

```bash
composer run test
```

---

# License

MIT
