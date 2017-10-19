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
        $actual   = $instance->isGoogleWebFontUrl($url);

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
        $actual   = $instance->buildGoogleFontsUrl($fonts, $subsets);

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
        $instance = new Testee();
        $actual   = $instance->encodeUnencodedAmpersands($in, $amp);

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
        $instance = new Testee();
        $actual   = $instance->httpsify($link);

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

    public function testStartBufferingShouldBuffer()
    {
        $instance = new Testee();

        // Starts
        $this->assertTrue($instance->startBuffering());
        $contents = ob_get_clean(); // otherwise the test ends up being marked as risky

        // Doesn't start
        define('WP_CLI', true);
        $this->assertFalse($instance->startBuffering());
    }

    public function testEndBuffering()
    {
        $instance = new Testee();

        // No changes to markup
        $markup   = 'bla';
        $expected = 'bla';
        $actual   = $instance->endBuffering($markup);
        $this->assertSame($expected, $actual);

        // No changes when only one link in markup
        $markup   = <<<HTML
<html>
    <head>
    <link href="//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700" rel="stylesheet" type="text/css">
</head>
<body>
</body>
</html>
HTML;
        $expected = $markup;
        $actual   = $instance->endBuffering($markup);
        $this->assertSame($expected, $actual);

        // Actually replaces stuff when there is stuff to replace...
        $markup   = <<<HTML
<html>
    <head>
    <link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700' rel='stylesheet' type='text/css'>
    <link href='http://fonts.googleapis.com/css?family=Ubuntu:400,700,400italic,700italic&subset=latin,latin-ext,cyrillic' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Raleway:400,700&amp;subset=latin,latin-ext' rel='stylesheet' type='text/css'>
    <link href='http://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot' rel='stylesheet' type='text/css'>
</head>
<body>
</body>
</html>
HTML;
        $expected = <<<HTML
<html>
    <head><link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Open+Sans%3A400%2C400italic%2C700%2C700italic%7CRaleway%3A400%2C700%7CUbuntu%3A400%2C400italic%2C700%2C700italic&amp;subset=latin%2Clatin-ext%2Ccyrillic"><link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Roboto+Condensed&amp;text=Woot">
    </head>
<body>
</body>
</html>
HTML;

        $actual = $instance->endBuffering($markup);
        $this->assertSame($expected, $actual);
    }

    /**
     * Test processStylesHandles() works as expected
     */
    public function testProcessStylesHandlesDefaults()
    {
        $test_handles = [
            'my-fonts' => 'http://fonts.googleapis.com/css?family=Raleway:300,500,700&subset=latin,latin-ext',
            'my-font-text' => 'https://fonts.googleapis.com/css?family=Inconsolata&text=Hello',
            'my-font-text-spaces' => 'https://fonts.googleapis.com/css?family=Open+Sans&text=Hello',
            'my-font-text-spaces-2' => 'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot',
            'multi' => 'http://fonts.googleapis.com/css?family=Open+Sans:300|PT+Sans:300,400,700&subset=latin,cyrillic',
            'wfl-syntax' => 'http://fonts.googleapis.com/css?family=Ubuntu:300,700:latin,latin-ext|Roboto:400,700:cyrillic',
            'wfl-syntax-2' => 'http://fonts.googleapis.com/css?family=Ubuntu:600:latin|Roboto:500:cyrillic',
            'some-duplicates-in-the-mix' => 'http://fonts.googleapis.com/css?family=Ubuntu:300|Roboto:400&subset=latin,cyrillic'
        ];

        $expected_handles = [
            'zwf-gfo-combined' => 'https://fonts.googleapis.com/css?family=Open+Sans%3A300%7CPT+Sans%3A300%2C400%2C700%7CRaleway%3A300%2C500%2C700%7CRoboto%3A400%2C500%2C700%7CUbuntu%3A300%2C600%2C700&subset=latin%2Clatin-ext%2Ccyrillic',
            'zwf-gfo-combined-txt-1' => 'https://fonts.googleapis.com/css?family=Inconsolata&text=Hello',
            'zwf-gfo-combined-txt-2' => 'https://fonts.googleapis.com/css?family=Open+Sans&text=Hello',
            'zwf-gfo-combined-txt-3' => 'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot'
        ];

        // Mock methods relying on WP core functions
        // @codingStandardsIgnoreLine
        $stub = $this->getMockBuilder(Testee::class)
            ->setMethods(['findCandidateHandles'])
            ->getMock();

        // Needs to have its behavior changed since there's no WP core here
        $stub->method('findCandidateHandles')->willReturn($test_handles);
        // findCandidateHandles called with what we expect
        $stub->expects($this->once())
            ->method('findCandidateHandles')
            ->with($this->equalTo(array_keys($test_handles)));

        // We need to set candidates manually since findCandidateHandles is mocked
        $stub->setCandidates(array_values($test_handles));

        // Finally, call processStylesHandles with data that would match the
        // way it would be called when it's hooked in WP's `print_styles_array`
        $results = $stub->processStylesHandles(array_keys($test_handles));

        // Making sure we return handle names only, as would be done in WP-land
        $this->assertSame(array_keys($expected_handles), array_values($results));

        // Ensure the newly enqueued urls are what we expect them to be
        $list = $stub->getEnqueued();
        $this->assertSame(count($expected_handles), count($list));
        foreach ($expected_handles as $handle => $url) {
            $this->assertSame($url, $stub->getEnqueued($handle));
        }
    }

    /**
     * Test processStylesHandles() doesn't do anything when it shouldn't have to
     */
    public function testProcessStylesHandlesWithoutGoogleFonts()
    {
        $test_handles     = [
            'my-fonts' => 'http://www.example.org/fooo.css'
        ];
        $expected_handles = [
            'my-fonts' => 'http://www.example.org/fooo.css'
        ];

        // Mock methods relying on WP core functions
        // @codingStandardsIgnoreLine
        $stub = $this->getMockBuilder(Testee::class)
            ->setMethods(['findCandidateHandles', 'dequeueStyleHandles', 'enqueueStyle'])
            ->getMock();

        // Needs to have its behavior changed since there's no WP core here
        $stub->method('findCandidateHandles')->willReturn([]);

        // Ensure findCandidateHandles is called with what we expect
        $stub->expects($this->once())
            ->method('findCandidateHandles')
            ->with($this->equalTo(array_keys($test_handles)));
        // These methods shouldn't be called
        $stub->expects($this->never())
            ->method('dequeueStyleHandles');
        $stub->expects($this->never())
            ->method('enqueueStyle');

        // Finally, call processStylesHandles with data that would match the
        // way it would be called when it's hooked via WP's `print_styles_array`
        $results = $stub->processStylesHandles(array_keys($test_handles));
        $this->assertSame(array_keys($expected_handles), array_values($results));
    }
}
