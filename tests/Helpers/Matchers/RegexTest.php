<?php

namespace Tests\Helpers\Matches;

use Tests\TestCase;
use Elephant\Helpers\Matchers\Regex;
use Elephant\Helpers\Exceptions\MatcherException;

class RegexTest extends TestCase
{
    const PATTERN = "/a.*c/";
    const PASS_AGAINST = 'abcccdef1g23hklmnop';
    const FAIL_AGAINST = 'ade';

    /** @test */
    function it_matches_string_using_regex_pattern()
    {
        $regex = new Regex(self::PATTERN);

        $this->assertTrue($regex->compare(self::PASS_AGAINST));
        $this->assertFalse($regex->compare(self::FAIL_AGAINST));
    }

    /** @test */
    function it_matches_array_using_regex_pattern()
    {
        $regex = new Regex(self::PATTERN);

        $this->assertTrue($regex->in([self::PASS_AGAINST]));
        $this->assertTrue($regex->in([self::PASS_AGAINST, self::FAIL_AGAINST]));
        $this->assertTrue($regex->in([[self::PASS_AGAINST, self::FAIL_AGAINST], self::FAIL_AGAINST]));
        $this->assertFalse($regex->in([self::FAIL_AGAINST]));
    }

    /** @test */
    function it_returns_matches_using_regex_pattern()
    {
        $regex = new Regex(self::PATTERN);

        $this->assertSame(['abccc'], $regex->matches(self::PASS_AGAINST));
    }

    /** @test */
    function it_throws_exception_when_matches_fails_using_regex_pattern()
    {
        $regex = new Regex(self::PATTERN);

        $this->expectException(MatcherException::class);
        $regex->matches(self::FAIL_AGAINST);
    }

    /** @test */
    function it_returns_matches_from_array_using_regex_pattern()
    {
        $regex = new Regex(self::PATTERN);

        $this->assertSame(['abccc'], $regex->matchesIn([self::PASS_AGAINST]));
        $this->assertSame(['abccc'], $regex->matchesIn([[self::PASS_AGAINST], 'asdasfasd']));
    }

    /** @test */
    function it_throws_exception_from_invalid_array_using_regex_pattern()
    {
        $regex = new Regex(self::PATTERN);

        $this->expectException(MatcherException::class);
        $regex->matchesIn([self::FAIL_AGAINST]);
    }
}
