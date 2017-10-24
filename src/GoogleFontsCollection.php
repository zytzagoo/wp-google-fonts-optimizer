<?php

namespace ZWF;

use ZWF\GoogleFontsOptimizerUtils as Utils;

class GoogleFontsCollection
{

    protected $links = [];

    protected $texts = [];

    /**
     * @var array|ZWF\GoogleFont[]
     */
    protected $fonts = [];

    protected $subsetsMap = [];

    protected $namedSizes = [];

    public function __construct(array $urls = [])
    {
        if (! empty($urls)) {
            foreach ($urls as $url) {
                $this->add($url);
            }
        }
    }

    public function add($url)
    {
        $this->addLink($url);

        $params = [];
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $bucket = isset($params['text']) ? 'text' : 'complete';

        $fontstrings = $this->buildFontStringsFromQueryParams($params);
        foreach ($fontstrings as $font) {
            $this->addFontFromString($font, $bucket);
            if ('text' === $bucket) {
                $font_family = explode(':', $font);
                $this->addTextFont($font_family[0], Utils::httpsify($url));
            }
        }
    }

    protected function addLink($url)
    {
        $this->links[] = $url;
    }

    protected function addFontFromString($fontstring, $bucket = 'complete')
    {
        $font    = GoogleFont::fromString($fontstring);
        $name    = $font->getName();
        $subsets = $font->getSubsetsString();

        // Add to bucket list
        $this->fonts[$bucket][] = $font;

        // Keeping a separate map of names => subsets for webfontloader purposes
        if (! array_key_exists($name, $this->subsetsMap)) {
            // Nothing found yet, create a new key
            $this->subsetsMap[$name] = $subsets;
        } else {
            // Something is in there already, merge
            $this->subsetsMap[$name] = array_merge($this->subsetsMap[$name], (array) $subsets);
        }

        // Deduplicate values and don't sort the subsets map
        $this->subsetsMap = Utils::dedupValues($this->subsetsMap, false); // no sort

        // Maintain the list/hash of name => sizes when adding a new one too
        $this->namedSizes = $this->buildNamedsizesMap();
    }

    protected function addTextFont($name, $url)
    {
        $this->texts['name'][] = $name;
        $this->texts['url'][]  = $url;
    }

    /**
     * Returns an array of data needed to generated webfontloader script markup.
     *
     * @return array
     */
    public function getScriptData()
    {
        return [
            $this->getNamedSizesMap(),
            $this->getSubsetsMap()
        ];
    }

    public function getNamedSizesMap()
    {
        return $this->namedSizes;
    }

    /**
     * @return array
     */
    protected function getSubsetsMap()
    {
        return $this->subsetsMap;
    }

    public function getOriginalLinks()
    {
        return $this->links;
    }

    public function hasText()
    {
        return (! empty($this->texts));
    }

    /**
     * @return array
     */
    public function getTextUrls()
    {
        $urls = [];

        if ($this->hasText()) {
            $urls = $this->texts['url'];
        }

        return $urls;
    }

    /**
     * @return array
     */
    public function getTextNames()
    {
        return $this->texts['name'];
    }

    /**
     * @return ZWF\GoogleFont[]
     */
    public function getFonts()
    {
        return $this->fonts['complete'];
    }

    /**
     * @return string
     */
    public function getCombinedUrl()
    {
        return Utils::buildGoogleFontsUrl(
            $this->getNamedSizesMap(),
            $this->getSubsets()
        );
    }

    /**
     * @return array
     */
    public function getSubsets()
    {
        $subsets = [];

        $fonts = $this->getFonts();
        foreach ($fonts as $font) {
            $subsets[] = $font->getSubsets();
        }

        $subsets = Utils::arrayFlattenIterative($subsets);

        return array_unique($subsets);
    }

    /**
     * @return array
     */
    protected function buildNamedsizesMap()
    {
        $fonts = [];

        foreach ($this->getFonts() as $font) {
            $name = $font->getName();
            if (! array_key_exists($name, $fonts)) {
                $fonts[$name] = $font->getSizes();
            } else {
                $fonts[$name] = array_merge($fonts[$name], $font->getSizes());
            }
        }

        // Sanitize and de-dup
        $fonts = Utils::dedupValues($fonts, SORT_REGULAR); // sorts values (sizes)

        // Sorts by font names alphabetically
        ksort($fonts);

        return $fonts;
    }

    /**
     * Looks for and parses the `family` key in `$params` array to get back
     * the list of fonts/sizes/subsets requested via a GoogleFonts url.
     *
     * @param array $params
     *
     * @return array
     */
    protected function buildFontStringsFromQueryParams(array $params)
    {
        $fonts = [];

        foreach (explode('|', $params['family']) as $family) {
            $font = $this->parseFontStringName($family, $params);
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
    protected function parseFontStringName($family, array $params)
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
        $parts = $this->validateFontStringParts($family, $subset);
        $font  = implode(':', $parts);

        return $font;
    }

    /**
     * Validate and return desired font name/size/subset parts.
     *
     * @param array $family
     * @param bool $subset
     *
     * @return array
     */
    protected function validateFontStringParts(array $family, $subset = false)
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
}
