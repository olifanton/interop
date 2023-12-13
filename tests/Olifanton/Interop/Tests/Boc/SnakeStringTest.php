<?php declare(strict_types=1);

namespace Olifanton\Interop\Tests\Boc;

use Olifanton\Interop\Boc\SnakeString;
use PHPUnit\Framework\TestCase;

class SnakeStringTest extends TestCase
{
    private string $stub = "Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.";

    public function testFromStringEqData(): void
    {
        $ss = SnakeString::fromString($this->stub);
        $this->assertEquals($this->stub, $ss->getData());
    }

    /**
     * @throws \Throwable
     */
    public function testFromStringToCellWithPrefix(): void
    {
        $ss = SnakeString::fromString($this->stub);
        $cell = $ss->cell(true);
        $expected = <<<BOC
        x{000000004C6F72656D20697073756D20646F6C6F722073697420616D65742C20636F6E73656374657475722061646970697363696E6720656C69742C2073656420646F20656975736D6F642074656D706F7220696E6369646964756E74207574206C61626F726520657420646F6C6F7265206D61676E6120616C697175612E}
         x{20557420656E696D206164206D696E696D2076656E69616D2C2071756973206E6F737472756420657865726369746174696F6E20756C6C616D636F206C61626F726973206E69736920757420616C697175697020657820656120636F6D6D6F646F20636F6E7365717561742E2044756973206175746520697275726520646F}
          x{6C6F7220696E20726570726568656E646572697420696E20766F6C7570746174652076656C697420657373652063696C6C756D20646F6C6F726520657520667567696174206E756C6C612070617269617475722E204578636570746575722073696E74206F6363616563617420637570696461746174206E6F6E2070726F69}
           x{64656E742C2073756E7420696E2063756C706120717569206F666669636961206465736572756E74206D6F6C6C697420616E696D20696420657374206C61626F72756D2E}
        BOC;
        $this->assertEquals($expected, trim($cell->print()));
    }

    /**
     * @throws \Throwable
     */
    public function testFromStringToCellWithoutPrefix(): void
    {
        $ss = SnakeString::fromString($this->stub);
        $cell = $ss->cell(false);
        $expected = <<<BOC
        x{4C6F72656D20697073756D20646F6C6F722073697420616D65742C20636F6E73656374657475722061646970697363696E6720656C69742C2073656420646F20656975736D6F642074656D706F7220696E6369646964756E74207574206C61626F726520657420646F6C6F7265206D61676E6120616C697175612E20557420}
         x{656E696D206164206D696E696D2076656E69616D2C2071756973206E6F737472756420657865726369746174696F6E20756C6C616D636F206C61626F726973206E69736920757420616C697175697020657820656120636F6D6D6F646F20636F6E7365717561742E2044756973206175746520697275726520646F6C6F7220}
          x{696E20726570726568656E646572697420696E20766F6C7570746174652076656C697420657373652063696C6C756D20646F6C6F726520657520667567696174206E756C6C612070617269617475722E204578636570746575722073696E74206F6363616563617420637570696461746174206E6F6E2070726F6964656E74}
           x{2C2073756E7420696E2063756C706120717569206F666669636961206465736572756E74206D6F6C6C697420616E696D20696420657374206C61626F72756D2E}
        BOC;
        $this->assertEquals($expected, trim($cell->print()));
    }

    /**
     * @throws \Throwable
     */
    public function testParseWithoutPrefix(): void
    {
        $cell = SnakeString::fromString($this->stub)->cell();
        $this->assertEquals($this->stub, SnakeString::parse($cell)->getData());
    }

    /**
     * @throws \Throwable
     */
    public function testParseWithPrefix(): void
    {
        $cell = SnakeString::fromString($this->stub)->cell(true);
        $this->assertEquals($this->stub, SnakeString::parse($cell, true)->getData());
    }

    /**
     * @throws \Throwable
     */
    public function testParseBadPrefix(): void
    {
        $cell = SnakeString::fromString($this->stub)->cell();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Bad Snake string prefix");
        SnakeString::parse($cell, true)->getData();
    }
}
