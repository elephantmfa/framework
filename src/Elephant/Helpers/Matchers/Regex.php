<?php

namespace Elephant\Helpers\Matchers;

use Elephant\Helpers\Exceptions\MatcherException;

class Regex
{
    /** @var string $pattern */
    protected $pattern = '';

    /** @var int $flags */
    protected $flags = 0;

    /** @var array<array<string>> $matches */
    protected $matches = [];

    /**
     * Create a new regex matcher.
     *
     * @param string $pattern Regular Expression pattern as per preg_match.
     * @see https://www.php.net/manual/en/ref.pcre.php
     * @see https://www.php.net/manual/en/pcre.pattern.php
     */
    public function __construct(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * Set the PCRE flags.
     *
     * @param int $flags
     * @return self
     * @see https://www.php.net/manual/en/function.preg-match.php
     */
    public function setFlags(int $flags): self
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * Compare a pattern against $against.
     *
     * Additionally, it will cache the matches
     *  for this string, so that if you call self::$matches(), it will just
     *  return without having to re-run preg_match.
     *
     * @param string $against
     * @return bool Whether the pattern matches.
     */
    public function compare(string $against): bool
    {
        if (preg_match($this->pattern, $against, $matches, $this->flags)) {
            if (! isset($this->matches[$against])) {
                $this->matches[$against] = $matches;
            }

            return true;
        }

        return false;
    }

    /**
     * Get the matches of a match against $against.
     *
     * This caches the matches for a string, thus if you re-scan a string
     *  again, it no longer needs to do a preg_match against it.
     *
     * @param string $against
     * @return array<string> First item in the array is the whole match.
     * @see https://www.php.net/manual/en/function.preg-match.php
     * @throws MatcherException If the pattern doesn't match at all.
     */
    public function matches(string $against): array
    {
        if (isset($this->matches[$against])) {
            return $this->matches[$against];
        }

        if (preg_match($this->pattern, $against, $matches, $this->flags)) {
            $this->matches[$against] = $matches;

            return $matches;
        }

        throw new MatcherException($this->pattern, $against);
    }

    /**
     * Check to see if matches anything in array.
     *
     * @param iterable<string|iterable> $against Can contain strings and compare will
     *  be called, or more arrays and this function will be called recursively.
     * @return bool Whether or not the pattern matched anything in the array.
     */
    public function in(iterable $against): bool
    {
        foreach ($against as $key => $value) {
            if (is_string($value)) {
                if ($this->compare($value)) {
                    return true;
                }
            } elseif (is_iterable($value)) {
                if ($this->in($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check to see if matches anything in array, providing the matches.
     *
     * @param iterable<string|iterable> $against Can contain strings and compare will
     *  be called, or more arrays and this function will be called recursively.
     * @return array<string> Array_merge of all the matches in the array.
     * @see https://www.php.net/manual/en/function.preg-match.php
     * @throws MatcherException If the pattern doesn't match at all.
     */
    public function matchesIn(iterable $against): array
    {
        $matches = [];
        foreach ($against as $key => $value) {
            if (is_string($value)) {
                try {
                    $matches = array_merge($matches, $this->matches($value));
                } catch (MatcherException $e) {
                    // Ignore.
                }
            } elseif (is_iterable($value)) {
                try {
                    $matches = array_merge($matches, $this->matchesIn($value));
                } catch (MatcherException $e) {
                    // Ignore.
                }
            }
        }

        if (empty($matches)) {
            throw new MatcherException($this->pattern, $against);
        }

        return $matches;
    }

    /**
     * Returns the pattern.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->pattern;
    }


    /**
     * Compare a $pattern against $against.
     *
     * @param string $pattern
     * @param string $against
     * @return bool Whether the pattern matches.
     */
    public static function match(string $pattern, string $against): bool
    {
        return (new static($pattern))->compare($against);
    }

    /**
     * Compare a $pattern against all the strings in the iterable $against.
     *
     * @param string $pattern
     * @param iterable<string|iterable> $against
     * @return bool Whether the pattern matches.
     */
    public static function matchIn(string $pattern, iterable $against): bool
    {
        return (new static($pattern))->in($against);
    }
}
