<?php

namespace Tests\Feature;

use App\Support\DatabaseBusy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class DatabaseBusyTest extends TestCase
{
    private function failure(int $code): QueryException
    {
        $previous = new PDOException('SENSITIVE TEST database diagnostic');
        $previous->errorInfo = ['HY000', $code, 'database is locked'];

        return new QueryException('sqlite', 'SELECT SENSITIVE TEST', [], $previous);
    }

    public function test_sqlite_contention_returns_safe_retry_response_instead_of_exposing_database_details(): void
    {
        Route::get('/test-database-busy', fn () => throw $this->failure(5));
        $this->get('/test-database-busy')->assertStatus(503)->assertHeader('Retry-After', '5')
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Database temporarily busy')->assertDontSee('SENSITIVE TEST');
        $this->getJson('/test-database-busy')->assertStatus(503)->assertJsonStructure(['message'])->assertDontSee('SENSITIVE TEST');
        $this->assertTrue(DatabaseBusy::matches($this->failure(6)));
        $this->assertTrue(DatabaseBusy::matches($this->failure(517)));
        $this->assertFalse(DatabaseBusy::matches($this->failure(19)));
    }
}
