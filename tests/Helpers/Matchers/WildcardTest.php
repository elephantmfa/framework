<?php

namespace Tests\Helpers\Matches;

use Elephant\Helpers\Matchers\Wildcard;
use Elephant\Helpers\Exceptions\MatcherException;
use Tests\TestCase;

class WildcardTest extends TestCase
{
    const PATTERN = "a[abc][[cde]]f?g*\[\][!g0-9][[!h0-9]]";
    const PASS_AGAINST = 'abcccdef1g23[]hklmnop';
    const FAIL_AGAINST = 'ade';

    /** @test */
    function it_generates_the_correct_regex()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->assertSame(';a[abc][cde]+f.g.*\[\][^g0-9][^h0-9]+;i', (string) $wildcard);
    }

    /** @test */
    function it_matches_string_using_wildcard_pattern()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->assertTrue($wildcard->compare(self::PASS_AGAINST));
        $this->assertFalse($wildcard->compare(self::FAIL_AGAINST));
    }

    /** @test */
    function it_matches_array_using_wildcard_pattern()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->assertTrue($wildcard->in([self::PASS_AGAINST]));
        $this->assertTrue($wildcard->in([self::PASS_AGAINST, self::FAIL_AGAINST]));
        $this->assertTrue($wildcard->in([[self::PASS_AGAINST, self::FAIL_AGAINST], self::FAIL_AGAINST]));
        $this->assertFalse($wildcard->in([self::FAIL_AGAINST]));
    }

    /** @test */
    function it_returns_matches_using_wildcard_pattern()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->assertSame([self::PASS_AGAINST], $wildcard->matches(self::PASS_AGAINST));
    }

    /** @test */
    function it_throws_exception_when_matches_fails_using_wildcard_pattern()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->expectException(MatcherException::class);
        $wildcard->matches(self::FAIL_AGAINST);
    }

    /** @test */
    function it_returns_matches_from_array_using_wildcard_pattern()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->assertSame([self::PASS_AGAINST], $wildcard->matchesIn([self::PASS_AGAINST]));
        $this->assertSame([self::PASS_AGAINST], $wildcard->matchesIn([[self::PASS_AGAINST], 'asdasfasd']));
    }

    /** @test */
    function it_throws_exception_from_invalid_array_using_wildcard_pattern()
    {
        $wildcard = new Wildcard(self::PATTERN);

        $this->expectException(MatcherException::class);
        $wildcard->matchesIn([self::FAIL_AGAINST]);
    }
}
