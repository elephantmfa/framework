<?php

namespace Elephant\Helpers\Matchers;

class Glob extends Regex
{
    /**
     * Construct a new Glob pattern
     *
     * @param string $pattern Has the following options:
     *  - `*`: 0 or more of any character.
     *  - `?`: Exactly one of any character.
     *  - `[abc]`: One of any character in `abc`.
     *  - `[[abc]]`: One or more of any character in `abc`.
     *  - `[!xyz]`: One of any character *not* in `xyz`.
     *  - `[[!xyz]]`: One or more of any character *not* in `xyz`.
     */
    public function __construct(string $pattern)
    {
        parent::__construct($this->regexify($pattern));
    }

    private function regexify(string $pattern): string
    {
        $pattern = preg_quote($pattern, ';');
        $pattern = str_replace('\\*', '.*', $pattern);
        $pattern = str_replace('\\?', '.', $pattern);
        $pattern = str_replace('\\[', '[', $pattern);
        $pattern = str_replace('\\]', ']', $pattern);
        $pattern = str_replace('\\\\[', '\[', $pattern);
        $pattern = str_replace('\\\\]', '\]', $pattern);
        $pattern = str_replace('[[', '[', $pattern);
        $pattern = str_replace(']]', ']+', $pattern);
        $pattern = str_replace('[\\!', '[^', $pattern);
        $pattern = str_replace('\\-', '-', $pattern);
        $pattern = ";{$pattern};i";

        return $pattern;
    }
}
