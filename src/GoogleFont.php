<?php

namespace ZWF;

class GoogleFont
{

    protected $name = '';

    protected $sizes = [];

    protected $subsets = [];

    public function __construct($name, array $sizes = [], array $subsets = [])
    {
        $this->setName($name);
        $this->setSizes($sizes);
        $this->setSubsets($subsets);
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string|array $sizes
     */
    public function setSizes($sizes)
    {
        if (is_array($sizes)) {
            $this->sizes = $sizes;
        }
        if (is_string($sizes)) {
            $this->sizes = array_map('trim', explode(',', $sizes));
        }
    }

    public function getSizes()
    {
        return $this->sizes;
    }

    public function getSizesString()
    {
        return implode(',', $this->getSizes());
    }

    /**
     * @param string|array $subsets
     */
    public function setSubsets($subsets)
    {
        if (is_array($subsets)) {
            $this->subsets = $subsets;
        }
        if (is_string($subsets)) {
            $this->subsets = array_map('trim', explode(',', $subsets));
        }
    }

    public function getSubsets()
    {
        return $this->subsets;
    }

    public function getSubsetsString()
    {
        return implode(',', $this->subsets);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return implode(
            ':',
            [
                $this->getName(),
                $this->getSizesString(),
                $this->getSubsetsString()
            ]
        );
    }

    /**
     * Creates a new instance from a given $fontstring.
     *
     * @param string $fontstring
     *
     * @return GoogleFont
     */
    public static function fromString($fontstring)
    {
        $parts = explode(':', $fontstring);
        $font  = new self($parts[0]);

        if (isset($parts[1])) {
            $font->setSizes($parts[1]);
        }

        if (isset($parts[2])) {
            $font->setSubsets($parts[2]);
        }

        return $font;
    }
}
