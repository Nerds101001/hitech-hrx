<?php

namespace Tests\Feature\Telephony;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Puts the telephony suite on an in-memory SQLite database.
 *
 * This repository's phpunit.xml deliberately leaves DB_CONNECTION and
 * DB_DATABASE commented out, so tests otherwise run against the real
 * development MySQL database. These tests provision their own schema with
 * Schema::create(), which against MySQL either collides with real tables or,
 * on a fresh database, writes into one. Overriding here rather than
 * re-enabling those lines keeps every other suite running exactly as it does
 * today.
 *
 * The switch is made through config rather than putenv(). putenv() is
 * process-wide and survives the test class that set it, so it silently forced
 * every later suite in the same PHPUnit run onto SQLite too -- ClickToCall
 * passed alone and failed when run after this one. Connections resolve their
 * config lazily, so changing it after boot is enough and affects nothing
 * outside this test's own application instance.
 *
 * Each test gets a fresh application, and ":memory:" is per-connection, so
 * every test also starts from an empty database without RefreshDatabase.
 */
abstract class TelephonyTestCase extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    config([
      'database.default' => 'sqlite',
      'database.connections.sqlite.database' => ':memory:',
    ]);

    // Drop any connection resolved during boot so the next query builds a new
    // one from the config above.
    DB::purge('sqlite');
    DB::setDefaultConnection('sqlite');
  }
}
