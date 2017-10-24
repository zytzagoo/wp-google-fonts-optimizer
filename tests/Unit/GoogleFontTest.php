<?php

namespace ZWF\GoogleFontsOptimizer\Tests\Unit;

use ZWF\GoogleFontsOptimizer\Tests\TestCase;
use ZWF\GoogleFont;

/**
 * Test cases for the GoogleFont class.
 *
 * @package ZWF\GoogleFontsOptimizer\Tests\Unit
 */
class GoogleFontTest extends TestCase
{
    public function testFromStringHelper()
    {
        $test = 'Ubuntu:400,700italic,400italic:cyrillic';
        $font = GoogleFont::fromString($test);

        $this->assertSame('Ubuntu', $font->getName());
        $this->assertSame(['400','400italic','700italic'], $font->getSizes());
        $this->assertSame('400,400italic,700italic', $font->getSizesString());
        $this->assertSame(['cyrillic'], $font->getSubsets());
        $this->assertSame('cyrillic', $font->getSubsetsString());
        $this->assertSame('Ubuntu:400,400italic,700italic:cyrillic', (string) $font);

        $test = 'Roboto+Condensed:400';
        $font = GoogleFont::fromString($test);

        $this->assertSame('Roboto+Condensed', $font->getName());
        $this->assertSame(['400'], $font->getSizes());
        $this->assertSame('400', $font->getSizesString());
        $this->assertSame([], $font->getSubsets());
        $this->assertSame('', $font->getSubsetsString());
        $this->assertSame('Roboto+Condensed:400', (string) $font);

        $test = 'Roboto';
        $font = GoogleFont::fromString($test);

        $this->assertSame('Roboto', $font->getName());
        $this->assertSame([], $font->getSizes());
        $this->assertSame('', $font->getSizesString());
        $this->assertSame([], $font->getSubsets());
        $this->assertSame('', $font->getSubsetsString());
        $this->assertSame('Roboto', (string) $font);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testException()
    {
        $test = '';
        $font = GoogleFont::fromString($test);
    }
}
