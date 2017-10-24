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
        if (empty($name)) {
            throw new \InvalidArgumentException('Font `$name` is required!');
        }

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
        $this->stringOrArraySetter('sizes', $sizes);

        $this->sizes = array_unique($this->sizes);
        sort($this->sizes, SORT_REGULAR);
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
        $this->stringOrArraySetter('subsets', $subsets);

        $this->subsets = array_unique($this->subsets, SORT_STRING);
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
        $parts   = [
            $this->getName()
        ];
        $sizes   = $this->getSizesString();
        $subsets = $this->getSubsetsString();

        if (! empty($sizes)) {
            $parts[] = $sizes;
        }
        if (! empty($subsets)) {
            $parts[] = $subsets;
        }

        return implode(':', $parts);
    }

    /**
     * Setter for given `$property` which makes sure that if a string `$value`
     * is given (in which multiple values can be separated by a comma) it ends
     * up being converted into an array.
     *
     * @param string $property
     * @param string|array $value
     *
     * @return void
     */
    private function stringOrArraySetter($property, $value)
    {
        if (is_array($value)) {
            $this->$property = $value;
        }
        if (is_string($value)) {
            $this->$property = array_map('trim', explode(',', $value));
        }
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
