<?php

namespace ZWF\GoogleFontsOptimizer\Tests\Unit;

use Brain\Monkey;
use ZWF\GoogleFontsOptimizer as Testee;
use ZWF\GoogleFontsOptimizer\Tests\TestCase;

/**
 * Test case for the GoogleFontsOptimizer class.
 *
 * @package ZWF\GoogleFontsOptimizer\Tests\Unit
 */
class GoogleFontsOptimizerTest extends TestCase
{
    /**
     * @dataProvider providerFontUrls
     */
    public function testIsGoogleWebFontUrl($url, $expected)
    {
        $instance = new Testee();

        $actual = $instance->isGoogleWebFontUrl($url);
        $this->assertSame($expected, $actual);
    }

    public function providerFontUrls()
    {
        return [
            ['https://fonts.googleapis.com/css?family=Open+Sans', true],
            ['http://fonts.googleapis.com/css?family=Open+Sans', true],
            ['https://fonts.googleapis.com/css', true],
            ['http://fonts.googleapis.com/css', true],
            ['//fonts.googleapis.com/css', true],
            ['fonts.googleapis.com/css', true],
            ['https://font.googleapi.com/css?family=Open+Sans', false],
            ['https://fonts.google.com', false]
        ];
    }

    /**
     * @dataProvider providerBuildGoogleFontsUrl
     */
    public function testBuildGoogleFontsUrl(array $fonts, $subsets, $expected)
    {
        $instance = new Testee();

        $actual = $instance->buildGoogleFontsUrl($fonts, $subsets);

        $this->assertSame($expected, $actual);
    }

    public function providerBuildGoogleFontsUrl()
    {
        return [
            [
                [
                    'Open Sans' => '400,400italic,700,700italic',
                    'Ubuntu'    => ['400','400italic','700','700italic'],
                ],
                'latin,latin-ext',
                '//fonts.googleapis.com/css?family=Open+Sans%3A400%2C400italic%2C700%2C700italic%7CUbuntu%3A400%2C400italic%2C700%2C700italic&subset=latin%2Clatin-ext'
            ],
            [
                [
                    'Open Sans' => '300,400,700,700italic',
                    'Ubuntu'    => ['400','400italic','700','700italic'],
                ],
                ['latin', 'latin-ext'],
                '//fonts.googleapis.com/css?family=Open+Sans%3A300%2C400%2C700%2C700italic%7CUbuntu%3A400%2C400italic%2C700%2C700italic&subset=latin%2Clatin-ext'
            ]
        ];
    }
}
