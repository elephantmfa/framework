<?php

namespace Elephant\Helpers\Matchers;

use Elephant\Helpers\Exceptions\MatcherException;

class Regex
{
    protected $pattern = '';

    protected $flags = 0;

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
     * Compare a regex against $against.
     *
     * @param string $against
     * @return bool Whether the regular expression matches.
     */
    public function compare(string $against): bool
    {
        return (bool) preg_match($this->pattern, $against, $matches, $this->flags);
    }

    /**
     * Get the matches of a regex match against $against.
     *
     * @param string $against
     * @return array<string> First item in the array is the whole match.
     * @see https://www.php.net/manual/en/function.preg-match.php
     * @throws MatcherException If the regex doesn't match at all.
     */
    public function matches(string $against): array
    {
        if (preg_match($this->pattern, $against, $matches, $this->flags)) {
            return $matches;
        }

        throw new MatcherException($this->pattern, $against);
    }

    /**
     * Check to see if regex matches anything in array.
     *
     * @param iterable<string|iterable> $against Can contain strings and compare will
     *  be called, or more arrays and this function will be called recursively.
     * @return bool Whether or not the regex matched anything in the array.
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
     * Check to see if regex matches anything in array.
     *
     * @param iterable<string|iterable> $against Can contain strings and compare will
     *  be called, or more arrays and this function will be called recursively.
     * @return array<string> Array_merge of all the matches in the array.
     * @see https://www.php.net/manual/en/function.preg-match.php
     * @throws MatcherException If the regex doesn't match at all.
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
}
