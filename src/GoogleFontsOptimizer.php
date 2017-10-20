<?php

namespace ZWF;

class GoogleFontsOptimizer
{
    const FILTER_MARKUP_TYPE  = 'zwf_gfo_markup_type';
    const DEFAULT_MARKUP_TYPE = 'link'; // Anything else generates the WebFont script loader

    const FILTER_OPERATION_MODE  = 'zwf_gfo_mode';
    const DEFAULT_OPERATION_MODE = 'enqueued_styles_only';

    const FILTER_OB_CLEANER  = 'zwf_gfo_clean_ob';
    const DEFAULT_OB_CLEANER = false;

    protected $candidates = [];

    protected $enqueued = [];

    /**
     * Like the wind.
     * Main entry point when hooked/called from WP action.
     *
     * @return void
     */
    public function run()
    {
        $mode = apply_filters(self::FILTER_OPERATION_MODE, self::DEFAULT_OPERATION_MODE);

        switch ($mode) {
            case 'markup':
                /**
                 * Scan and optimize requests found in the markup (uses more
                 * memory but works on [almost] any theme)
                 */
                add_action('template_redirect', [$this, 'startBuffering'], 11);
                break;

            case self::DEFAULT_OPERATION_MODE:
            default:
                /**
                 * Scan only things added via wp_enqueue_style (uses slightly
                 * less memory usually, but requires a decently coded theme)
                 */
                add_filter('print_styles_array', [$this, 'processStylesHandles']);
                break;
        }
    }

    /**
     * Returns true when an array isn't empty and has enough elements.
     *
     * @param array $candidates
     *
     * @return bool
     */
    protected function hasEnoughElements(array $candidates = [])
    {
        $enough = true;

        if (empty($candidates) || count($candidates) < 2) {
            $enough = false;
        }

        return $enough;
    }

    /**
     * Callback to hook into `wp_print_styles`.
     * It processes enqueued styles and combines any multiple google fonts
     * requests into a single one (and removes the enqueued styles handles
     * and replaces them with a single combined enqueued style request/handle).
     *
     * TODO/FIXME: Investigate how this works out when named deps are used somewhere?
     *
     * @param array $handles
     *
     * @return array
     */
    public function processStylesHandles(array $handles)
    {
        $candidate_handles = $this->findCandidateHandles($handles);

        // Bail if we don't have anything that makes sense for us to continue
        if (! $this->hasEnoughElements($candidate_handles)) {
            return $handles;
        }

        // Set the list of found urls we matched
        $this->setCandidates(array_values($candidate_handles));

        // Get fonts array data from candidates
        $fonts_array = $this->getFontsArray($this->getCandidates());
        if (isset($fonts_array['complete'])) {
            $combined_font_url = $this->buildGoogleFontsUrlFromFontsArray($fonts_array);
            $handle_name       = 'zwf-gfo-combined';
            $this->enqueueStyle($handle_name, $combined_font_url);
            $handles[] = $handle_name;
        }

        if (isset($fonts_array['partial'])) {
            $cnt = 0;
            foreach ($fonts_array['partial']['url'] as $url) {
                $cnt++;
                $handle_name = 'zwf-gfo-combined-txt-' . $cnt;
                $this->enqueueStyle($handle_name, $url);
                $handles[] = $handle_name;
            }
        }

        // Remove/dequeue the ones we just combined above
        $this->dequeueStyleHandles($candidate_handles);

        // Removes processed handles from originally given $handles
        $handles = array_diff($handles, array_keys($candidate_handles));

        return $handles;
    }

    /**
     * Given a list of WP style handles return a new "named map" of handles
     * we care about along with their urls.
     *
     * TODO/FIXME: See if named deps will need to be taken care of...
     *
     * @codeCoverageIgnore
     *
     * @param array $handles
     *
     * @return array
     */
    public function findCandidateHandles(array $handles)
    {
        $handler           = /** @scrutinizer ignore-call */ \wp_styles();
        $candidate_handles = [];

        foreach ($handles as $handle) {
            $dep = $handler->query($handle, 'registered');
            if ($dep) {
                $url = $dep->src;
                if ($this->isGoogleWebFontUrl($url)) {
                    $candidate_handles[$handle] = $url;
                }
            }
        }

        return $candidate_handles;
    }

    /**
     * Dequeue given `$handles`.
     *
     * @param array $handles
     *
     * @return void
     */
    public function dequeueStyleHandles(array $handles)
    {
        foreach ($handles as $handle => $url) {
            // @codeCoverageIgnoreStart
            if (function_exists('\wp_deregister_style')) {
                /** @scrutinizer ignore-call */
                \wp_deregister_style($handle);
            }
            if (function_exists('\wp_dequeue_style')) {
                /** @scrutinizer ignore-call */
                \wp_dequeue_style($handle);
            }
            // @codeCoverageIgnoreEnd
            unset($this->enqueued[$handle]);
        }
    }

    /**
     * Enqueues a given style using `\wp_enqueue_style()` and keeps it in
     * our own `$enqueued` list for reference.
     *
     * @param string $handle
     * @param string $url
     * @param array $deps
     * @param string|null $version
     *
     * @return void
     */
    public function enqueueStyle($handle, $url, $deps = [], $version = null)
    {
        // @codeCoverageIgnoreStart
        if (function_exists('\wp_enqueue_style')) {
            /** @scrutinizer ignore-call */
            \wp_enqueue_style($handle, $url, $deps, $version);
        }
        // @codeCoverageIgnoreEnd

        $this->enqueued[$handle] = $url;
    }

    /**
     * Get the entire list of enqueued styles or a specific one if $handle is specified.
     *
     * @param string|null $handle Style "slug"
     *
     * @return array|string
     */
    public function getEnqueued($handle = null)
    {
        $data = $this->enqueued;

        if (null !== $handle && isset($this->enqueued[$handle])) {
            $data = $this->enqueued[$handle];
        }

        return $data;
    }

    /**
     * Callback to invoke in oder to modify google fonts found in the HTML markup.
     * Returns modified markup in which multiple google fonts requests are
     * combined into a single one (if multiple requests are found).
     *
     * @param string $markup
     *
     * @return string
     */
    public function processMarkup($markup)
    {
        $hrefs = $this->parseMarkupForHrefs($markup);
        if (!empty($hrefs)) {
            $this->setCandidates($hrefs);
        }

        // See if we found anything
        $candidates = $this->getCandidates();

        // Bail and return original markup unmodified if we don't have things to do
        if (! $this->hasEnoughElements($candidates)) {
            return $markup;
        }

        // Process what we found and modify original markup with our replacement
        $fonts_array = $this->getFontsArray($candidates);
        $font_markup = $this->buildFontsMarkup($fonts_array);
        $markup      = $this->modifyMarkup($markup, $font_markup, $fonts_array['links']);

        return $markup;
    }

    /**
     * Given a string of $markup, returns an array of hrefs we're interested in.
     *
     * @param string $markup
     *
     * @return array
     */
    protected function parseMarkupForHrefs($markup)
    {
        $hrefs = [];

        $dom = new \DOMDocument();
        // @codingStandardsIgnoreLine
        /** @scrutinizer ignore-unhandled */ @$dom->loadHTML($markup);
        // Looking for all <link> elements
        $links = $dom->getElementsByTagName('link');
        $hrefs = $this->filterHrefsFromCandidateLinkNodes($links);

        return $hrefs;
    }

    /**
     * Returns the list of google web fonts stylesheet hrefs found.
     *
     * @param DOMNodeList $nodes
     *
     * @return array
     */
    protected function filterHrefsFromCandidateLinkNodes(\DOMNodeList $nodes)
    {
        $hrefs = [];

        foreach ($nodes as $node) {
            if ($this->isCandidateLink($node)) {
                $hrefs[] = $node->getAttribute('href');
            }
        }

        return $hrefs;
    }

    /**
     * Returns true if given DOMNode is a stylesheet and points to a Google Web Fonts url.
     *
     * @param DOMNode $node
     *
     * @return bool
     */
    protected function isCandidateLink(\DOMNode $node)
    {
        $rel  = $node->getAttribute('rel');
        $href = $node->getAttribute('href');

        return ('stylesheet' === $rel && $this->isGoogleWebFontUrl($href));
    }

    /**
     * Modifies given $markup to include given $font_markup in the <head>.
     * Also removes any existing stylesheets containing the given $font_links (if found).
     *
     * @param string $markup
     * @param string $font_markup
     * @param array $font_links
     *
     * @return string
     */
    public function modifyMarkup($markup, $font_markup, array $font_links)
    {
        $new_markup = $markup;

        // Remove existing stylesheets
        foreach ($font_links as $font_link) {
            $font_link = preg_quote($font_link, '/');

            // Tweak back what DOMDocument replaces sometimes
            $font_link = str_replace('&#038;', '&', $font_link);
            // This adds an extra capturing group in the pattern actually
            $font_link = str_replace('&', '(&|&#038;|&amp;)', $font_link);
            // Match this url's link tag, including optional newlines at the end of the string
            $pattern = '/<link([^>]*?)href[\s]?=[\s]?[\'\"\\\]*' . $font_link . '([^>]*?)>([\s]+)?/is';
            // Now replace
            $new_markup = preg_replace($pattern, '', $new_markup);
        }

        // Adding the font markup to top of <head> for now
        // TODO/FIXME: This could easily break when someone uses `<head>` in HTML comments and such?
        $new_markup = str_ireplace('<head>', '<head>' . $font_markup, trim($new_markup));

        return $new_markup;
    }

    public function startBuffering()
    {
        $started = false;

        if ($this->shouldBuffer()) {
            /**
             * N.B.
             * In theory, others might've already started buffering before us,
             * which can prevent us from getting the markup.
             * If that becomes an issue, we can call shouldCleanOutputBuffers()
             * and cleanOutputBuffers() here before starting our buffering.
             */

            // Start our own buffering
            $started = ob_start([$this, 'endBuffering']);
        }

        return $started;
    }

    public function endBuffering($markup)
    {
        // Bail early on things we don't want to parse
        if (! $this->isMarkupDoable($markup)) {
            return $markup;
        }

        return $this->processMarkup($markup);
    }

    /**
     * Determines whether the current WP request should be buffered.
     *
     * @return bool
     * @codeCoverageIgnore
     */
    protected function shouldBuffer()
    {
        $buffer = true;

        if ($buffer && function_exists('\is_admin') && /** @scrutinizer ignore-call */ is_admin()) {
            $buffer = false;
        }

        if ($buffer && function_exists('\is_feed') && /** @scrutinizer ignore-call */ is_feed()) {
            $buffer = false;
        }

        if ($buffer && defined('\DOING_AJAX')) {
            $buffer = false;
        }

        if ($buffer && defined('\DOING_CRON')) {
            $buffer = false;
        }

        if ($buffer && defined('\WP_CLI')) {
            $buffer = false;
        }

        if ($buffer && defined('\APP_REQUEST')) {
            $buffer = false;
        }

        if ($buffer && defined('\XMLRPC_REQUEST')) {
            $buffer = false;
        }

        if ($buffer && defined('\SHORTINIT') && \SHORTINIT) {
            $buffer = false;
        }

        return $buffer;
    }

    /**
     * @codeCoverageIgnore
     */
    protected function shouldCleanOutputBuffers()
    {
        return apply_filters(self::FILTER_OB_CLEANER, self::DEFAULT_OB_CLEANER);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function cleanOutputBuffers()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Returns true if given markup should be processed.
     *
     * @param string $content
     *
     * @return bool
     */
    protected function isMarkupDoable($content)
    {
        $html  = $this->hasHtmlTag($content);
        $html5 = $this->hasHtml5Doctype($content);
        $xsl   = $this->hasXslStylesheet($content);

        return (($html || $html5) && ! $xsl);
    }

    /**
     * Returns true if given `$string` contains the HTML5 doctype.
     *
     * @param string $string
     *
     * @return bool
     */
    protected function hasHtml5Doctype($string)
    {
        return (preg_match('/^<!DOCTYPE.+html>/i', $string) > 0);
    }

    /**
     * Returns true when given `$string` contains an XSL stylesheet element.
     *
     * @param string $string
     *
     * @return bool
     */
    protected function hasXslStylesheet($string)
    {
        return (false !== stripos($string, '<xsl:stylesheet'));
    }

    /**
     * Returns true when given `$string` contains the beginnings of an `<html>` tag.
     *
     * @param string $string
     * @return bool
     */
    protected function hasHtmlTag($string)
    {
        return (false !== stripos($string, '<html'));
    }

    /**
     * Returns true if a given url is a google font url.
     *
     * @param string $url
     *
     * @return bool
     */
    public function isGoogleWebFontUrl($url)
    {
        return (substr_count($url, 'fonts.googleapis.com/css') > 0);
    }

    /**
     * @param array $urls
     */
    public function setCandidates(array $urls = [])
    {
        $this->candidates = $urls;
    }

    /**
     * @return array
     */
    public function getCandidates()
    {
        return $this->candidates;
    }

    /**
     * Builds a combined Google Font URL for multiple font families/subsets.
     *
     * Usage examples:
     * ```
     * $this->buildGoogleFontsUrl(
     *     [
     *         'Open Sans' => [ '400', '400italic', '700', '700italic' ],
     *         'Ubuntu'    => [ '400', '400italic', '700', '700italic' ],
     *     ),
     *     [ 'latin', 'latin-ext' ]
     * );
     * ```
     *
     * or
     *
     * ```
     * $this->buildGoogleFontsUrl(
     *     [
     *         'Open Sans' => '400,400italic,700,700italic',
     *         'Ubuntu'    => '400,400italic,700,700italic',
     *     ],
     *     'latin,latin-ext'
     * );
     * ```
     *
     * @param array $fonts
     * @param array|string $subsets
     *
     * @return null|string
     */
    public function buildGoogleFontsUrl(array $fonts, $subsets = [])
    {
        $base_url  = 'https://fonts.googleapis.com/css';
        $font_args = [];
        $family    = [];

        foreach ($fonts as $font_name => $font_weight) {
            if (is_array($font_weight)) {
                $font_weight = implode(',', $font_weight);
            }
            // Trimming end colon handles edge case of being given an empty $font_weight
            $family[] = trim(trim($font_name) . ':' . trim($font_weight), ':');
        }

        $font_args['family'] = implode('|', $family);

        if (! empty($subsets)) {
            if (is_array($subsets)) {
                $subsets = array_unique($subsets);
                $subsets = implode(',', $subsets);
            }
            $font_args['subset'] = trim($subsets);
        }

        $url = $base_url . '?' . http_build_query($font_args);

        return $url;
    }

    /**
     * Given a list of google fonts urls it returns another array of data
     * representing found families/sizes/subsets/urls.
     *
     * @param array $candidates
     *
     * @return array
     */
    protected function getFontsArray(array $candidates = [])
    {
        $fonts = [];

        foreach ($candidates as $candidate) {
            $fonts['links'][] = $candidate;

            $params = [];
            parse_str(parse_url($candidate, PHP_URL_QUERY), $params);

            if (isset($params['text'])) {
                // Fonts with character limitations are segregated into
                // under 'partial' (when `text` query param is used)
                $font_family                = explode(':', $params['family']);
                $fonts['partial']['name'][] = $font_family[0];
                $fonts['partial']['url'][]  = $this->httpsify($candidate);
            } else {
                $fontstrings = $this->buildFontStringsFromQueryParams($params);
                foreach ($fontstrings as $font) {
                    $fonts['complete'][] = $font;
                }
            }
        }

        return $fonts;
    }

    /**
     * Looks for and parses the `family` query string value into a string
     * that Google Fonts expects (family, weights and subsets separated by
     * a semicolon).
     *
     * @param array $params
     *
     * @return array
     */
    protected function buildFontStringsFromQueryParams(array $params)
    {
        $fonts = [];

        foreach (explode('|', $params['family']) as $family) {
            $font = $this->parseFontStringFamilyParam($family, $params);
            if (!empty($font)) {
                $fonts[] = $font;
            }
        }

        return $fonts;
    }

    protected function parseFontStringFamilyParam($family, array $params)
    {
        $font   = null;
        $subset = false;
        $family = explode(':', $family);

        if (isset($params['subset'])) {
            // Use the found subset query parameter
            $subset = $params['subset'];
        } elseif (isset($family[2])) {
            // Use the subset in the family string if present
            $subset = $family[2];
        }

        // We can have only the name specified in some cases
        $parts = $this->buildFontStringParts($family, $subset);
        $font  = implode(':', $parts);

        return $font;
    }

    /**
     * Validate and return needed font parts data.
     *
     * @param array $family
     * @param bool $subset
     *
     * @return array
     */
    protected function buildFontStringParts(array $family, $subset = false)
    {
        $parts = [];

        // First part is the font name, which should always be present
        $parts[] = $family[0];

        // Check if sizes are specified
        if (isset($family[1]) && strlen($family[1]) > 0) {
            $parts[] = $family[1];
        }

        // Add the subset if specified
        if ($subset) {
            $parts[] = $subset;
        }

        return $parts;
    }

    /**
     * Creates a single google fonts url from data returned by `getFontsArray()`.
     *
     * @param array $fonts_array
     *
     * @return string
     */
    protected function buildGoogleFontsUrlFromFontsArray(array $fonts_array)
    {
        list($fonts, $subsets) = $this->consolidateFontsArray($fonts_array);

        return $this->buildGoogleFontsUrl($fonts, $subsets);
    }

    /**
     * Given a "raw" getFontsArray(), deduplicate and sort the data
     * and return a new array with three keys:
     * - the first key contains sorted list of fonts/sizes
     * - the second key contains the global list of requested subsets
     * - the third key contains the map of requested font names and their subsets
     *
     * @param array $fonts_array
     *
     * @return array
     */
    protected function consolidateFontsArray(array $fonts_array)
    {
        $fonts         = [];
        $subsets       = [];
        $fonts_to_subs = [];

        foreach ($fonts_array['complete'] as $font_string) {
            $parts = explode(':', $font_string);
            $name  = $parts[0];
            $size  = isset($parts[1]) ? $parts[1] : '';

            if (isset($fonts[$name])) {
                // If a name already exists, append the new size
                $fonts[$name] .= ',' . $size;
            } else {
                // Create a new key for the name and size
                $fonts[$name] = $size;
            }

            // Check if subset is specified as the third element
            if (isset($parts[2])) {
                $subset = $parts[2];
                // Collect all the subsets defined into a single array
                $elements = explode(',', $subset);
                foreach ($elements as $sub) {
                    $subsets[] = $sub;
                }
                // Keeping a separate map of names => requested subsets for
                // webfontloader purposes
                if (isset($fonts_to_subs[$name])) {
                    $fonts_to_subs[$name] .= ',' . $subset;
                } else {
                    $fonts_to_subs[$name] = $subset;
                }
            }
        }

        // Remove duplicate subsets
        $subsets = array_unique($subsets);

        // Sanitize and de-dup name/sizes pairs
        $fonts = $this->dedupValues($fonts, SORT_REGULAR); // sorts values (sizes)

        // Sorts by font names alphabetically
        ksort($fonts);

        // Sanitize and de-dup $fonts_to_subs mapping
        $fonts_to_subs = $this->dedupValues($fonts_to_subs, false); // no sort

        return [$fonts, $subsets, $fonts_to_subs];
    }

    /**
     * Given a key => value map in which the value is a single string or
     * a list of comma-separeted strings, it returns a new array with the
     * given keys, but the values are transformed into an array and any
     * potential duplicate values are removed.
     * If the $sort parameter is given, the list of values is sorted using
     * `sort()` and the $sort param is treated as a sort flag.
     *
     * @param array $data
     * @param bool|int $sort If false, no sorting, otherwise an int representing
     *                       sort flags. See http://php.net/sort
     *
     * @return array
     */
    protected function dedupValues(array $data, $sort = false)
    {
        foreach ($data as $key => $values) {
            $parts = explode(',', $values);
            $parts = array_unique($parts);
            if (false !== $sort) {
                sort($parts, (int) $sort);
            }
            $data[$key] = $parts;
        }

        return $data;
    }

    /**
     * Replaces any occurences of un-encoded ampersands in the given string
     * with the value given in the `$amp` parameter (`&amp;` by default).
     *
     * @param string $url
     * @param string $amp
     *
     * @return string
     */
    public function encodeUnencodedAmpersands($url, $amp = '&amp;')
    {
        $amp = trim($amp);
        if (empty($amp)) {
            $amp = '&amp;';
        }

        return preg_replace('/&(?!#?\w+;)/', $amp, $url);
    }

    /**
     * Turns protocol-relative or non-https URLs into their https versions.
     *
     * @param string $link
     *
     * @return string
     */
    public function httpsify($link)
    {
        $is_protocol_relative = ('/' === $link{0} && '/' === $link{1});

        if ($is_protocol_relative) {
            $link = 'https:' . $link;
        } else {
            $link = preg_replace('/^(https?):\/\//mi', '', $link);
            $link = 'https://' . $link;
        }

        return $link;
    }

    /**
     * Given data from `getFontsArray()` builds HTML markup for found google fonts.
     *
     * @param array $fonts_array
     *
     * @return string
     */
    protected function buildFontsMarkup(array $fonts_array)
    {
        $markup_type = apply_filters(self::FILTER_MARKUP_TYPE, self::DEFAULT_MARKUP_TYPE);
        if ('link' === $markup_type) {
            // Build standard link markup
            $markup = $this->buildFontsMarkupLinks($fonts_array);
        } else {
            // Bulding WebFont script loader
            $markup = $this->buildFontsMarkupScript($fonts_array);
        }

        return $markup;
    }

    /**
     * Given data from `getFontsArray()` builds `<link rel="stylesheet">` markup.
     *
     * @param array $fonts
     *
     * @return string
     */
    protected function buildFontsMarkupLinks(array $fonts)
    {
        $font_url = $this->buildGoogleFontsUrlFromFontsArray($fonts);
        $href     = $this->encodeUnencodedAmpersands($font_url);
        $markup   = '<link rel="stylesheet" type="text/css" href="' . $href . '">';

        if (isset($fonts['partial'])) {
            if (is_array($fonts['partial']['url'])) {
                foreach ($fonts['partial']['url'] as $other) {
                    $markup .= '<link rel="stylesheet" type="text/css"';
                    $markup .= ' href="' . $this->encodeUnencodedAmpersands($other) . '">';
                }
            }
        }

        return $markup;
    }

    /**
     * Given data from `getFontsArray()` builds WebFont loader script markup.
     *
     * @param array $fonts
     *
     * @return string
     */
    protected function buildFontsMarkupScript(array $fonts)
    {
        $families_array = [];

        list($names, $subsets, $mapping) = $this->consolidateFontsArray($fonts);
        foreach ($names as $name => $sizes) {
            $family = $name . ':' . implode(',', $sizes);
            if (isset($mapping[$name])) {
                $family .= ':' . implode(',', $mapping[$name]);
            }
            $families_array[] = $family;
        }
        $families = "'" . implode("', '", $families_array) . "'";

        // Load 'text' requests with the "custom" module
        $custom = '';
        if (isset($fonts['partial'])) {
            $custom  = ",\n    custom: {\n";
            $custom .= "        families: [ '" . implode("', '", $fonts['partial']['name']) . "' ],\n";
            $custom .= "        urls: [ '" . implode("', '", $fonts['partial']['url']) . "' ]\n";
            $custom .= '    }';
        }

        $markup = <<<MARKUP
<script type="text/javascript">
WebFontConfig = {
    google: { families: [ {$families} ] }{$custom}
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
MARKUP;

        return $markup;
    }
}
