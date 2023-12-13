<?php declare(strict_types=1);

namespace Olifanton\Interop\Boc;

use Olifanton\Interop\Bytes;

class SnakeString
{
    private const PREFIX = "\00\00\00\00";

    private function __construct(
        private readonly string $data,
    ) {}

    /**
     * @throws Exceptions\CellException
     * @throws Exceptions\SliceException
     */
    public static function parse(Cell $snakeCell, bool $skipOxPrefix = false): self
    {
        $data = "";
        $c = $snakeCell;
        $isFirst = true;

        while ($c) {
            $cs = $c->beginParse();

            if ($isFirst && $skipOxPrefix) {
                $prefix = Bytes::arrayToBytes($cs->loadBits(32));

                if ($prefix !== self::PREFIX) {
                    throw new \InvalidArgumentException("Bad Snake string prefix");
                }
            }

            $rb = count($cs->getRemainingBits());

            if (!$rb) {
                break;
            }

            $d = $cs->loadBits($rb);
            $data .= trim(Bytes::arrayToBytes($d), "\0");
            $c = $cs->getRefsCount() === 1 ? $cs->loadRef() : null;
            $isFirst = false;
        }

        return new self($data);
    }

    public static function fromString(string $snakeData): self
    {
        return new self($snakeData);
    }

    public function getData(): string
    {
        return $this->data;
    }

    /**
     * @throws Exceptions\BitStringException
     */
    public function cell(bool $oxPrefix = false): Cell
    {
        if (strlen($this->data) === 0) {
            return new Cell();
        }

        $data = $oxPrefix ? (self::PREFIX . $this->data) : $this->data;
        $chunks = str_split($data, 127);
        $chunksCount = count($chunks);

        if ($chunksCount === 1) {
            return (new Builder())->writeString($chunks[0])->cell();
        }

        $currentCell = (new Builder());

        for ($i = $chunksCount - 1; $i >= 0; $i--) {
            $chunk = $chunks[$i];
            $currentCell->writeString($chunk);

            if ($i - 1 >= 0) {
                $nextCell = (new Builder());
                $nextCell->writeRef($currentCell->cell());
                $currentCell = $nextCell;
            }
        }

        return $currentCell->cell();
    }
}
