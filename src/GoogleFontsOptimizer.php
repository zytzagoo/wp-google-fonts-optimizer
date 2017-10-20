<?php

namespace ZWF;

use ZWF\GoogleFontsOptimizerUtils as Utils;

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
            $combined_font_url = Utils::buildGoogleFontsUrlFromFontsArray($fonts_array);
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
                if (Utils::isGoogleWebFontUrl($url)) {
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
        if (! empty($hrefs)) {
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
     * @param \DOMNodeList $nodes
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
     * @param \DOMNode $node
     *
     * @return bool
     */
    protected function isCandidateLink(\DOMNode $node)
    {
        $rel  = $node->getAttribute('rel');
        $href = $node->getAttribute('href');

        return ('stylesheet' === $rel && Utils::isGoogleWebFontUrl($href));
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
        $html  = Utils::hasHtmlTag($content);
        $html5 = Utils::hasHtml5Doctype($content);
        $xsl   = Utils::hasXslStylesheet($content);

        return (($html || $html5) && ! $xsl);
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
                $fonts['partial']['url'][]  = Utils::httpsify($candidate);
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
            if (! empty($font)) {
                $fonts[] = $font;
            }
        }

        return $fonts;
    }

    /**
     * @param string $family
     * @param array $params
     *
     * @return string
     */
    protected function parseFontStringFamilyParam($family, array $params)
    {
        $subset = false;
        $family = explode(':', $family);

        if (isset($params['subset'])) {
            // Use the found subset query parameter
            $subset = $params['subset'];
        } elseif (isset($family[2])) {
            // Use the subset in the family string if present
            $subset = $family[2];
        }

        // $family can have a lot of thing specified with separators etc.
        $parts = $this->buildFontStringParts($family, $subset);
        $font  = implode(':', $parts);

        return $font;
    }

    /**
     * Makes sure we only include what we needValidate and return needed font parts data.
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
            $markup = Utils::buildFontsMarkupLinks($fonts_array);
        } else {
            // Bulding WebFont script loader
            $markup = Utils::buildFontsMarkupScript($fonts_array);
        }

        return $markup;
    }
}
