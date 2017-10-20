<?php

namespace ZWF;

class GoogleFontsOptimizerUtils
{
    /**
     * Replaces any occurences of un-encoded ampersands in the given string
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
     * Turns protocol-relative or non-https URLs into their https versions.
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
     * Returns true if a given url is a google font url.
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
    public static function dedupValues(array $data, $sort = false)
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
    public static function consolidateFontsArray(array $fonts_array)
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
        $fonts = self::dedupValues($fonts, SORT_REGULAR); // sorts values (sizes)

        // Sorts by font names alphabetically
        ksort($fonts);

        // Sanitize and de-dup $fonts_to_subs mapping
        $fonts_to_subs = self::dedupValues($fonts_to_subs, false); // no sort

        return [$fonts, $subsets, $fonts_to_subs];
    }

    /**
     * Given data from `getFontsArray()` builds `<link rel="stylesheet">` markup.
     *
     * @param array $fonts
     *
     * @return string
     */
    public static function buildFontsMarkupLinks(array $fonts)
    {
        $font_url = self::buildGoogleFontsUrlFromFontsArray($fonts);
        $href     = self::encodeUnencodedAmpersands($font_url);
        $markup   = '<link rel="stylesheet" type="text/css" href="' . $href . '">';

        if (isset($fonts['partial']) && is_array($fonts['partial']['url'])) {
            foreach ($fonts['partial']['url'] as $other) {
                $markup .= '<link rel="stylesheet" type="text/css"';
                $markup .= ' href="' . self::encodeUnencodedAmpersands($other) . '">';
            }
        }

        return $markup;
    }

    /**
     * Given data from `GoogleFontsOptimizer::getFontsArray()` builds
     * WebFont loader script markup.
     *
     * @param array $fonts
     *
     * @return string
     */
    public static function buildFontsMarkupScript(array $fonts)
    {
        $families_array = [];

        list($names, $subsets, $mapping) = self::consolidateFontsArray($fonts);
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
     * Creates a single google fonts url from data returned by `getFontsArray()`.
     *
     * @param array $fonts_array
     *
     * @return string
     */
    public static function buildGoogleFontsUrlFromFontsArray(array $fonts_array)
    {
        list($fonts, $subsets) = self::consolidateFontsArray($fonts_array);

        return self::buildGoogleFontsUrl($fonts, $subsets);
    }
}
