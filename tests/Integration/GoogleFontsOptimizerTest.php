<?php

namespace ZWF\GoogleFontsOptimizer\Tests\Integration;

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

    /**
     * test processMarkup() works as expected
     */
    public function testProcessMarkupDefaultModeCombinesLinksInMarkup()
    {
        $input = <<<HTML
<html>
    <head>
    <link href='//fonts.googleapis.com/css?family=Open+Sans:400italic,700italic,400,700' rel='stylesheet' type='text/css'>
    <link href='//fonts.googleapis.com/css?family=Ubuntu:400,700,400italic,700italic&subset=latin,latin-ext,cyrillic' rel='stylesheet' type='text/css'>
    <link href='//fonts.googleapis.com/css?family=Raleway:400,700&amp;subset=latin,latin-ext' rel='stylesheet' type='text/css'>
</head>
<body>
</body>
</html>
HTML;
        $expected = <<<HTML
<html>
    <head><link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Open+Sans%3A400italic%2C700italic%2C400%2C700%7CUbuntu%3A400%2C700%2C400italic%2C700italic%7CRaleway%3A400%2C700&#38;subset=latin%2Clatin-ext%2Ccyrillic">
    </head>
<body>
</body>
</html>
HTML;

        $instance = new Testee();
        $actual = $instance->processMarkup($input);

        $this->assertSame($expected, $actual);
    }

    /**
     * test processMarkup() works as expected when generating WebFont loader
     */
    public function testProcessMarkupWithNonLinkMode()
    {
        $input = <<<HTML
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
    google: { families: [ 'Open Sans:400italic,700italic,400,700:latin,latin-ext', 'Ubuntu:400,700,400italic,700italic:latin,latin-ext,cyrillic', 'Raleway:400,700:latin,latin-ext,cyrillic' ] }
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
        $actual = $instance->processMarkup($input);

        $this->assertSame($expected, $actual);
    }

    /**
     * Test processStylesHandles() works as expected
     */
    public function testEnqueuedFontStylesAreCombined()
    {
        $test_handles = [
            'my-fonts' => 'http://fonts.googleapis.com/css?family=Raleway:300,500,700&subsets=latin,latin-ext',
            'my-font-text' => 'https://fonts.googleapis.com/css?family=Inconsolata&text=Hello',
            'my-font-text-spaces' => 'https://fonts.googleapis.com/css?family=Open+Sans&text=Hello',
            'my-font-text-spaces-2' => 'https://fonts.googleapis.com/css?family=Roboto+Condensed&text=Woot',
            'multi' => 'http://fonts.googleapis.com/css?family=Open+Sans:700,300|PT+Sans:400,700&subset=latin,cyrillic'
        ];

        $expected_handles = [
            'zwf-gfo-combined' => '//fonts.googleapis.com/css?family=Raleway%3A300%2C500%2C700%7COpen+Sans%3A700%2C300&#038;subset=latin%2Clatin-ext%2Ccyrillic',
            'zwf-gfo-combined-txt-1' => 'https://fonts.googleapis.com/css?family=Inconsolata&#038;text=Hello',
            'zwf-gfo-combined-txt-2' => 'https://fonts.googleapis.com/css?family=Open+Sans&#038;text=Hello',
            'zwf-gfo-combined-txt-3' => 'https://fonts.googleapis.com/css?family=Roboto+Condensed&#038;text=Woot'
        ];

        // Mock methods relying on WP core functions
        // @codingStandardsIgnoreLine
        $stub = $this->getMockBuilder(Testee::class)
            ->setMethods(['findCandidateHandles', 'dequeueStyleHandles', 'enqueueStyle'])
            ->getMock();

        // These 3 need to have their behavior changed since there's no WP core here
        $stub->method('findCandidateHandles')->willReturn($test_handles);
        $stub->method('dequeueStyleHandles')->willReturn(true);
        $stub->method('enqueueStyle')->willReturn(true);

        // findCandidateHandles called with what we expect
        $stub->expects($this->once())
            ->method('findCandidateHandles')
            ->with($this->equalTo(array_keys($test_handles)));
        // dequeueStyleHandles called once with a list of found candidates to remove
        $stub->expects($this->once())
            ->method('dequeueStyleHandles')
            ->with($this->equalTo($test_handles));
        // enqueueStyle called matching number of times
        $stub->expects($this->exactly(count($expected_handles)))
            ->method('enqueueStyle');

        // We need to set candidates manually since findCandidateHandles is mocked
        $stub->setCandidates(array_values($test_handles));

        // Finally, call processStylesHandles with data that would match the
        // way it would be called when it's hooked in WP's `print_styles_array`
        $results = $stub->processStylesHandles(array_keys($test_handles));
        $this->assertSame(array_keys($expected_handles), array_values($results));
    }

    /**
     * Test processStylesHandles() doesn't do anything when it shouldn't have to
     */
    public function testEnqueuedRandomStylesAreUntouched()
    {
        $test_handles = [
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

        // These 3 need to have their behavior changed since there's no WP core here
        $stub->method('findCandidateHandles')->willReturn([]);

        // findCandidateHandles called with what we expect
        $stub->expects($this->once())
            ->method('findCandidateHandles')
            ->with($this->equalTo(array_keys($test_handles)));
        // these shouldn't be called
        $stub->expects($this->never())
            ->method('dequeueStyleHandles');
        $stub->expects($this->never())
            ->method('enqueueStyle');

        // Finally, call processStylesHandles with data that would match the
        // way it would be called when it's hooked in WP's `print_styles_array`
        $results = $stub->processStylesHandles(array_keys($test_handles));
        $this->assertSame(array_keys($expected_handles), array_values($results));
    }
}
