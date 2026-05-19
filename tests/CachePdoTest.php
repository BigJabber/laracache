<?php

use Laracache\Pdo\Cache;
use PHPUnit\Framework\TestCase;

class CachePdoTest extends TestCase
{
    private function makeCache(): CacheQuoteStub
    {
        return new CacheQuoteStub;
    }

    public function test_quote_wraps_string_in_single_quotes(): void
    {
        $cache = $this->makeCache();

        $this->assertSame("'OR4391266'", $cache->quote('OR4391266'));
    }

    public function test_quote_escapes_embedded_single_quotes(): void
    {
        $cache = $this->makeCache();

        $this->assertSame("'O''Brien'", $cache->quote("O'Brien"));
    }

    public function test_quote_returns_raw_string_for_param_int(): void
    {
        $cache = $this->makeCache();

        $this->assertSame('42', $cache->quote('42', PDO::PARAM_INT));
    }
}

class CacheQuoteStub extends Cache
{
    public function __construct()
    {
        // Skip parent constructor to avoid requiring an ODBC connection in tests.
    }
}
