<?php

namespace Tests\Helpers\Matches;

use Elephant\Helpers\Matchers\Glob;
use Elephant\Helpers\Exceptions\MatcherException;
use Tests\TestCase;

class GlobTest extends TestCase
{
    const PATTERN = "a[abc][[cde]]f?g*\[\][!g0-9][[!h0-9]]";
    const PASS_AGAINST = 'abcccdef1g23[]hklmnop';
    const FAIL_AGAINST = 'ade';

    /** @test */
    function it_generates_the_correct_regex()
    {
        $glob = new Glob(self::PATTERN);

        $this->assertSame(';a[abc][cde]+f.g.*\[\][^g0-9][^h0-9]+;i', (string) $glob);
    }

    /** @test */
    function it_matches_string_using_glob_pattern()
    {
        $glob = new Glob(self::PATTERN);

        $this->assertTrue($glob->compare(self::PASS_AGAINST));
        $this->assertFalse($glob->compare(self::FAIL_AGAINST));
    }

    /** @test */
    function it_matches_array_using_glob_pattern()
    {
        $glob = new Glob(self::PATTERN);

        $this->assertTrue($glob->in([self::PASS_AGAINST]));
        $this->assertTrue($glob->in([self::PASS_AGAINST, self::FAIL_AGAINST]));
        $this->assertTrue($glob->in([[self::PASS_AGAINST, self::FAIL_AGAINST], self::FAIL_AGAINST]));
        $this->assertFalse($glob->in([self::FAIL_AGAINST]));
    }

    /** @test */
    function it_returns_matches_using_glob_pattern()
    {
        $glob = new Glob(self::PATTERN);

        $this->assertSame([self::PASS_AGAINST], $glob->matches(self::PASS_AGAINST));
    }

    /** @test */
    function it_throws_exception_when_matches_fails_using_glob_pattern()
    {
        $glob = new Glob(self::PATTERN);

        $this->expectException(MatcherException::class);
        $glob->matches(self::FAIL_AGAINST);
    }

    /** @test */
    function it_returns_matches_from_array_using_glob_pattern()
    {
        $glob = new Glob(self::PATTERN);

        $this->assertSame([self::PASS_AGAINST], $glob->matchesIn([self::PASS_AGAINST]));
        $this->assertSame([self::PASS_AGAINST], $glob->matchesIn([[self::PASS_AGAINST], 'asdasfasd']));
    }

    /** @test */
    function it_throws_exception_from_invalid_array_using_glob_pattern()
    {
        $glob = new Glob(self::PATTERN);

        $this->expectException(MatcherException::class);
        $glob->matchesIn([self::FAIL_AGAINST]);
    }
}
