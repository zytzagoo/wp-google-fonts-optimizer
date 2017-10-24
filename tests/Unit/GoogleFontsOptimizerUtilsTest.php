<?php

namespace ZWF\GoogleFontsOptimizer\Tests\Unit;

use ZWF\GoogleFontsOptimizer\Tests\TestCase;
use ZWF\GoogleFontsOptimizerUtils as Utils;

/**
 * Test cases for the GoogleFontsOptimizerUtils class.
 *
 * @package ZWF\GoogleFontsOptimizer\Tests\Unit
 */
class GoogleFontsOptimizerUtilsTest extends TestCase
{
    public function testDedupValues()
    {
        // Test with comma-separated string of values
        $a = [
            'key' => '400,700italic,400italic'
        ];
        // Sorted
        $this->assertSame(
            ['key' => ['400', '400italic', '700italic']],
            Utils::dedupValues($a, SORT_REGULAR)
        );
        // Unsorted
        $this->assertSame(
            ['key' => ['400', '700italic', '400italic']],
            Utils::dedupValues($a, false)
        );

        // Test with array of values
        $b = [
            'key' => ['400', '700italic', '400italic']
        ];
        // Sorted
        $this->assertSame(
            ['key' => ['400', '400italic', '700italic']],
            Utils::dedupValues($a, SORT_REGULAR)
        );
        // Unsorted
        $this->assertSame(
            ['key' => ['400', '700italic', '400italic']],
            Utils::dedupValues($a, false)
        );
    }

    /**
     * @dataProvider providerFontUrls
     */
    public function testIsGoogleWebFontUrl($url, $expected)
    {
        $actual = Utils::isGoogleWebFontUrl($url);
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
        $actual = Utils::buildGoogleFontsUrl($fonts, $subsets);
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
                'https://fonts.googleapis.com/css?family=Open+Sans%3A400%2C400italic%2C700%2C700italic%7CUbuntu%3A400%2C400italic%2C700%2C700italic&subset=latin%2Clatin-ext'
            ],
            [
                [
                    'Open Sans' => '300,400,700,700italic',
                    'Ubuntu'    => ['400','400italic','700','700italic'],
                ],
                ['latin', 'latin-ext'],
                'https://fonts.googleapis.com/css?family=Open+Sans%3A300%2C400%2C700%2C700italic%7CUbuntu%3A400%2C400italic%2C700%2C700italic&subset=latin%2Clatin-ext'
            ],
            [
                [
                    'Open Sans' => '',
                    'Ubuntu'    => [],
                ],
                ['latin', 'latin-ext'],
                'https://fonts.googleapis.com/css?family=Open+Sans%7CUbuntu&subset=latin%2Clatin-ext'
            ]
        ];
    }

    /**
     * @dataProvider providerAmpersands
     */
    public function testEncodingUnencodedAmpersands($in, $amp, $expected)
    {
        $actual = Utils::encodeUnencodedAmpersands($in, $amp);
        $this->assertSame($expected, $actual);
    }

    public function providerAmpersands()
    {
        return [
            [
                'It&rsquo;s 30 &#176; outside & very hot. T-shirt &amp; shorts needed!',
                '',
                'It&rsquo;s 30 &#176; outside &amp; very hot. T-shirt &amp; shorts needed!'
            ],
            [
                'It&rsquo;s 30 &#176; outside & very hot. T-shirt &amp; shorts needed!',
                ' ',
                'It&rsquo;s 30 &#176; outside &amp; very hot. T-shirt &amp; shorts needed!'
            ],
            [
                'It&rsquo;s 30 &#176; outside & very hot. T-shirt &amp; shorts needed!',
                null,
                'It&rsquo;s 30 &#176; outside &amp; very hot. T-shirt &amp; shorts needed!'
            ],
            [
                'It&rsquo;s 30 &#176; outside & very hot. T-shirt &amp; shorts needed!',
                '&#38;',
                'It&rsquo;s 30 &#176; outside &#38; very hot. T-shirt &amp; shorts needed!'
            ],
            [
                'It&rsquo;s 30 &#176; outside & very hot. T-shirt &amp; shorts needed!',
                'foo',
                'It&rsquo;s 30 &#176; outside foo very hot. T-shirt &amp; shorts needed!'
            ]
        ];
    }

    /**
     * @dataProvider providerLinks
     */
    public function testHttpsify($link, $expected)
    {
        $actual = Utils::httpsify($link);
        $this->assertSame($expected, $actual);
    }

    public function providerLinks()
    {
        return [
            [
                'http://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot',
                'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot'
            ],
            [
                'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot',
                'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot'
            ],
            [
                '//fonts.googleapis.com/css?family=Ubuntu',
                'https://fonts.googleapis.com/css?family=Ubuntu'
            ]
        ];
    }
}
