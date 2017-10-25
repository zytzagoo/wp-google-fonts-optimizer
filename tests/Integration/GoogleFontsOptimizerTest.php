<?php

namespace ZWF\GoogleFontsOptimizer\Tests\Integration;

use Mockery;
use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use ZWF\GoogleFontsOptimizer as Testee;
use ZWF\GoogleFontsOptimizer\Tests\TestCase;

/**
 * Integration test cases for the GoogleFontsOptimizer class.
 *
 * @package ZWF\GoogleFontsOptimizer\Tests\Unit
 */
class GoogleFontsOptimizerTest extends TestCase
{
    public function testMarkupModeAddsExpectedHooks()
    {
        Actions\expectAdded('template_redirect');
        Filters\expectApplied(Testee::FILTER_OPERATION_MODE)
            ->once()
            ->with(Testee::DEFAULT_OPERATION_MODE)
            ->andReturn('markup');

        $instance = new Testee();
        $instance->run();

        $this->assertTrue(has_action('template_redirect', [$instance, 'startBuffering']));
    }

    public function testStylesModeIsDefault()
    {
        $instance = new Testee();
        $instance->run();

        $this->assertTrue(has_filter('print_styles_array', [$instance, 'processStylesHandles']));
        $this->assertTrue(Filters\applied(Testee::FILTER_OPERATION_MODE) > 0);

        $this->assertFalse(has_action('template_redirect', [$instance, 'startBuffering']));
        $this->assertFalse(has_action('shutdown', [$instance, 'obEndFlush']));
    }

    public function testUnkownModeDefaultsToStylesMode()
    {
        Filters\expectApplied(Testee::FILTER_OPERATION_MODE)
            ->once()
            ->with(Testee::DEFAULT_OPERATION_MODE)
            ->andReturn('foobar');

        $instance = new Testee();
        $instance->run();

        $this->assertTrue(has_filter('print_styles_array', [$instance, 'processStylesHandles']));
        $this->assertTrue(Filters\applied(Testee::FILTER_OPERATION_MODE) > 0);
        $this->assertFalse(has_action('template_redirect', [$instance, 'startBuffering']));
        $this->assertFalse(has_action('shutdown', [$instance, 'obEndFlush']));
    }

    public function testProcessMarkupWebFontLoader()
    {
        $input    = <<<HTML
<html>
<head>
<link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700&amp;subset=latin,latin-ext' rel='stylesheet' type='text/css'>
<link href='//fonts.googleapis.com/css?family=Ubuntu:400,700,400italic,700italic&#038;subset=latin,latin-ext,cyrillic' rel='stylesheet' type='text/css'>
<link href='//fonts.googleapis.com/css?family=Raleway:400,700&subset=latin,latin-ext,cyrillic' rel='stylesheet' type='text/css'>
</head>
<body>
</body>
</html>
HTML;
        $expected = <<<HTML
<html>
<head><script type="text/javascript">
WebFontConfig = {
    google: { families: [ 'Open Sans:400,400italic,700,700italic:latin,latin-ext', 'Raleway:400,700:latin,latin-ext,cyrillic', 'Ubuntu:400,400italic,700,700italic:latin,latin-ext,cyrillic' ] }
};
(function() {
    var wf = document.createElement('script');
    wf.src = ('https:' == document.location.protocol ? 'https' : 'http') +
        '://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js';
    wf.type = 'text/javascript';
    wf.async = 'true';
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(wf, s);
})();
</script>
</head>
<body>
</body>
</html>
HTML;
        // Setup the filter to change markup type
        Filters\expectApplied(Testee::FILTER_MARKUP_TYPE)
            ->once()
            ->with(Testee::DEFAULT_MARKUP_TYPE)
            ->andReturn('script');

        $instance = new Testee();
        $actual   = $instance->processMarkup($input);

        $this->assertSame($expected, $actual);
    }

    public function testProcessMarkupWebFontLoaderCustomText()
    {
        $input    = <<<HTML
<html>
<head>
<link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700&amp;subset=latin,latin-ext' rel='stylesheet' type='text/css'>
<link href='https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot' rel='stylesheet' type='text/css'>
</head>
<body>
</body>
</html>
HTML;
        $expected = <<<HTML
<html>
<head><script type="text/javascript">
WebFontConfig = {
    google: { families: [ 'Open Sans:400,400italic,700,700italic:latin,latin-ext' ] },
    custom: {
        families: [ 'Roboto Condensed' ],
        urls: [ 'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot' ]
    }
};
(function() {
    var wf = document.createElement('script');
    wf.src = ('https:' == document.location.protocol ? 'https' : 'http') +
        '://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js';
    wf.type = 'text/javascript';
    wf.async = 'true';
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(wf, s);
})();
</script>
</head>
<body>
</body>
</html>
HTML;

        // Setup the filter to change markup type
        Filters\expectApplied(Testee::FILTER_MARKUP_TYPE)
            ->once()
            ->with(Testee::DEFAULT_MARKUP_TYPE)
            ->andReturn('script');

        $instance = new Testee();
        $actual   = $instance->processMarkup($input);

        $this->assertSame($expected, $actual);
    }

    public function testStartBuffering()
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

    public function testProcessMarkupWithAmp()
    {
        $input = <<<MARKUP
<!doctype html>
<html amp lang="en">
    <head>
    <meta charset="utf-8">
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <title>Hello, AMPs</title>
    <link href="https://fonts.googleapis.com/css?family=Tangerine" rel="stylesheet">
    <link href="http://fonts.googleapis.com/css?family=Bitstream+Vera+Serif" rel="stylesheet">
    <link rel="canonical" href="http://example.ampproject.org/article-metadata.html">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    </head>
    <body>
    <h1>Welcome to the mobile web</h1>
    </body>
</html>
MARKUP;

        $expected = <<<MARKUP
<!doctype html>
<html amp lang="en">
    <head><link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Bitstream+Vera+Serif%7CTangerine">
    <meta charset="utf-8">
    <script async src="https://cdn.ampproject.org/v0.js"></script>
    <title>Hello, AMPs</title>
    <link rel="canonical" href="http://example.ampproject.org/article-metadata.html">
    <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
    </head>
    <body>
    <h1>Welcome to the mobile web</h1>
    </body>
</html>
MARKUP;

        $instance = new Testee();
        $actual   = $instance->processMarkup($input);

        $this->assertSame($expected, $actual);
    }
}
