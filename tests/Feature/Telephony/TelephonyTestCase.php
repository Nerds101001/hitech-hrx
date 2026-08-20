<?php

namespace Tests\Feature\Telephony;

use Illuminate\Foundation\Application;
use Tests\TestCase;

/**
 * Forces the telephony suite onto an in-memory SQLite database.
 *
 * This repository's phpunit.xml deliberately has DB_CONNECTION/DB_DATABASE
 * commented out, so tests otherwise run against the real development MySQL
 * database. These tests provision their own schema with Schema::create() —
 * pointed at MySQL that either collides with real tables or, worse on a fresh
 * database, writes into one. Overriding the connection here rather than
 * re-enabling those lines in phpunit.xml keeps every other suite running
 * exactly as it does today.
 *
 * Each test gets a brand-new application, and ":memory:" is per-connection, so
 * every test also gets an empty database without needing RefreshDatabase.
 */
abstract class TelephonyTestCase extends TestCase
{
  public function createApplication(): Application
  {
    // Set before the framework boots: config/database.php reads these through
    // env() during bootstrap, so changing them afterwards would be too late.
    foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:'] as $key => $value) {
      putenv("{$key}={$value}");
      $_ENV[$key] = $value;
      $_SERVER[$key] = $value;
    }

    return parent::createApplication();
  }
}
