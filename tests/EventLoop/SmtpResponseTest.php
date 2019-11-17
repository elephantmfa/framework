<?php

namespace Tests\EventLoop;

use Elephant\Testing\Concerns\InteractsWithEventLoop;
use Tests\TestCase;

class SmtpResponseTest extends TestCase
{
    use InteractsWithEventLoop;

    /** @test */
    public function it_has_valid_helo_response()
    {
        $this->speak('HELO test.com')
            ->assert2XXResponse();
    }

    /** @test */
    function it_has_valid_ehlo_response()
    {
        $this->speak('EHLO test.com')
            ->assert2XXResponse();
    }

    /** @test */
    function it_has_valid_mail_from_response()
    {
        $this->speak('HELO test.com')
            ->speak('MAIL FROM: <test@test.com>')
            ->assert2XXResponse();
    }

    /** @test */
    function it_returns_error_response_when_helo_not_sent_and_mail_from_is_sent()
    {
        $this->speak('MAIL FROM: <test@test.com>')
            ->assert5XXResponse();
    }

    /** @test */
    function it_has_valid_rcpt_to_response()
    {
        $this->speak('HELO test.com')
            ->speak('MAIL FROM: <test@test.com>')
            ->speak('RCPT TO: <test@test.com>')
            ->assert2XXResponse();
    }

    /** @test */
    function it_returns_error_response_when_mail_from_not_sent_and_rcpt_to_is_sent()
    {
        $this->speak('HELO test.com')
            ->speak('RCPT TO: <test@test.com>')
            ->assert5XXResponse();
    }

    /** @test */
    function it_has_valid_data_response()
    {
        $this->speak('HELO test.com')
            ->speak('MAIL FROM: <test@test.com>')
            ->speak('RCPT TO: <test@test.com>')
            ->speak('DATA')
            ->assert3XXResponse()
            ->speak('From: test <test@test.com>')
            ->speak('To: test <test@test.com>')
            ->speak('Subject: Test')
            ->speak('')
            ->speak('Test')
            ->speak('')
            ->speak('.')
            ->speak('')
            ->assert2XXResponse();
    }

    /** @test */
    function it_returns_error_response_with_invalid_command()
    {
        $this->speak('INVALID')
            ->assert5XXResponse();
    }

    /** @test */
    function it_returns_error_response_when_vrfy_sent()
    {
        $this->speak('VRFY')
            ->assert5XXResponse();
    }

    /** @test */
    function it_has_valid_response_when_rset_sent()
    {
        $this->speak('RSET')
            ->assert2XXResponse();
    }

    /** @test */
    function it_has_valid_response_when_noop_sent()
    {
        $this->speak('NOOP')
            ->assert2XXResponse();
    }

    /** @test */
    function it_has_valid_response_when_quit_sent()
    {
        $this->speak('QUIT')
            ->assert2XXResponse();
    }
}
