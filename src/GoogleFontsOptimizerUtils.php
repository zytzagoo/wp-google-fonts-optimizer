<?php

namespace ZWF;

use ZWF\GoogleFontsCollection as Collection;

class GoogleFontsOptimizerUtils
{
    /**
     * Replaces any occurrences of un-encoded ampersands in the given string
     * with the value given in the `$amp` parameter (`&amp;` by default).
     *
     * @param string $url
     * @param string $amp
     *
     * @return string
     */
    public static function encodeUnencodedAmpersands($url, $amp = '&amp;')
    {
        $amp = trim($amp);

        if (empty($amp)) {
            $amp = '&amp;';
        }

        return preg_replace('/&(?!#?\w+;)/', $amp, $url);
    }

    /**
     * Replaces protocol-relative or non-https URLs into https URLs.
     *
     * @param string $link
     *
     * @return string
     */
    public static function httpsify($link)
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
     * Returns true if a given `$url` is a Google Fonts URL.
     *
     * @param string $url
     *
     * @return bool
     */
    public static function isGoogleWebFontUrl($url)
    {
        return (substr_count($url, 'fonts.googleapis.com/css') > 0);
    }

    /**
     * Returns true if given `$string` contains the HTML5 doctype.
     *
     * @param string $string
     *
     * @return bool
     */
    public static function hasHtml5Doctype($string)
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
    public static function hasXslStylesheet($string)
    {
        return (false !== stripos($string, '<xsl:stylesheet'));
    }

    /**
     * Returns true when given `$string` contains the beginnings of an `<html>` tag.
     *
     * @param string $string
     * @return bool
     */
    public static function hasHtmlTag($string)
    {
        return (false !== stripos($string, '<html'));
    }

    /**
     * Given a `['key' => 'value1,value2,value3']` or a
     * `['key' => ['value1', 'value2', 'value3']]` map/array it returns a new
     * array with the same keys, but the values are now always an array of
     * values (and any potential duplicate values are removed).
     * If the `$sort` parameter is given, the list of values is sorted using
     * `sort()` and the `$sort` parameter is treated as a sort flag.
     *
     * @param array $data
     * @param bool|int $sort If false, no sorting, otherwise an integer representing
     *                       sort flags. See http://php.net/sort
     *
     * @return array
     */
    public static function dedupValues(array $data, $sort = false)
    {
        foreach ($data as $key => $values) {
            if (is_array($values)) {
                $parts = $values;
            } else {
                $parts = explode(',', $values);
            }

            $parts = array_unique($parts);

            // Perform sort if specified
            if (false !== $sort) {
                sort($parts, (int) $sort);
            }

            // Store back
            $data[$key] = $parts;
        }

        return $data;
    }

    /**
     * Given a `GoogleFontsCollection` it builds needed `<link rel="stylesheet">` markup.
     *
     * @param Collection $fonts
     *
     * @return string
     */
    public static function buildFontsMarkupLinks(Collection $fonts)
    {
        $font_url = $fonts->getCombinedUrl();
        $href     = self::encodeUnencodedAmpersands($font_url);
        $markup   = '<link rel="stylesheet" type="text/css" href="' . $href . '">';

        foreach ($fonts->getTextUrls() as $url) {
            $markup .= '<link rel="stylesheet" type="text/css"';
            $markup .= ' href="' . self::encodeUnencodedAmpersands($url) . '">';
        }

        return $markup;
    }

    /**
     * Given a `GoogleFontsCollection` it builds the WebFont loader script markup.
     *
     * @param Collection $collection
     *
     * @return string
     */
    public static function buildFontsMarkupScript(Collection $collection)
    {
        $families_array = [];

        list($names, $mapping) = $collection->getScriptData();

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
        if ($collection->hasText()) {
            $custom  = ",\n    custom: {\n";
            $custom .= "        families: [ '" . implode("', '", $collection->getTextNames()) . "' ],\n";
            $custom .= "        urls: [ '" . implode("', '", $collection->getTextUrls()) . "' ]\n";
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

    /**
     * Builds a combined Google Font URL for multiple font families/subsets.
     *
     * Usage examples:
     * ```
     * ZWF\GoogleFontsOptimizerUtils::buildGoogleFontsUrl(
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
     * ZWF\GoogleFontsOptimizerUtils::buildGoogleFontsUrl(
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
    public static function buildGoogleFontsUrl(array $fonts, $subsets = [])
    {
        $base_url  = 'https://fonts.googleapis.com/css';
        $font_args = [];
        $family    = [];

        $fonts = self::dedupValues($fonts, false); // no sort
        foreach ($fonts as $font_name => $font_weight) {
            // Trimming end colon handles edge case of being given an empty $font_weight
            $family[] = trim(trim($font_name) . ':' . implode(',', $font_weight), ':');
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
     * Flatten an array without recursion.
     *
     * @param array $arr
     *
     * @return array
     */
    public static function arrayFlattenIterative(array $arr)
    {
        $flat  = [];
        $stack = array_values($arr);

        while (! empty($stack)) {
            $value = array_shift($stack);
            if (is_array($value)) {
                $stack = array_merge(array_values($value), $stack);
            } else {
                $flat[] = $value;
            }
        }

        return $flat;
    }
}
