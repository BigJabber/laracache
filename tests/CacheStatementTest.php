<?php

use Laracache\Pdo\Cache\Statement;
use PHPUnit\Framework\TestCase;

class CacheStatementTest extends TestCase
{
    private function makeStatement(): StatementStub
    {
        return new StatementStub;
    }

    private function getParameters(Statement $stmt): array
    {
        $ref = new ReflectionProperty(Statement::class, 'parameters');
        $ref->setAccessible(true);

        return $ref->getValue($stmt);
    }

    public function test_bind_value_wraps_string_in_single_quotes(): void
    {
        $stmt = $this->makeStatement();
        $stmt->bindValue(1, 'OR4391266');

        $this->assertSame("'OR4391266'", $this->getParameters($stmt)[1]);
    }

    public function test_bind_value_escapes_embedded_single_quotes(): void
    {
        $stmt = $this->makeStatement();
        $stmt->bindValue(1, "O'Brien");

        $this->assertSame("'O''Brien'", $this->getParameters($stmt)[1]);
    }

    public function test_bind_value_does_not_quote_integers(): void
    {
        $stmt = $this->makeStatement();
        $stmt->bindValue(1, 42, PDO::PARAM_INT);

        $this->assertSame(42, $this->getParameters($stmt)[1]);
    }

    public function test_bind_value_does_not_quote_null(): void
    {
        $stmt = $this->makeStatement();
        $stmt->bindValue(1, null, PDO::PARAM_NULL);

        $this->assertNull($this->getParameters($stmt)[1]);
    }

    public function test_bind_value_does_not_quote_float(): void
    {
        $stmt = $this->makeStatement();
        $stmt->bindValue(1, 3.14);

        $this->assertSame(3.14, $this->getParameters($stmt)[1]);
    }
}

class StatementStub extends Statement
{
    public function __construct()
    {
        // Skip parent constructor to avoid requiring an ODBC connection in tests.
    }
}
