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

    /**
     * Test processMarkup() generating webfontloader
     */
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

    /**
     * Test processMarkup() generating webfontloader with extra params
     */
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
}
