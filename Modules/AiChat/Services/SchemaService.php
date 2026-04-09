<?php

namespace Modules\AiChat\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SchemaService
{
  public function getSchema($summary = false)
  {
    return Cache::rememberForever($summary ? 'db_schema_summary' : 'db_schema', function () use ($summary) {
      return $this->generateSchema($summary);
    });
  }

  private function generateSchema($summary = false)
  {
    $tables = DB::select('SHOW TABLES');
    $schema = '';

    foreach ($tables as $table) {
      $tableName = array_values((array)$table)[0];
      $schema .= "Table: `$tableName`\n";

      if ($summary) {
        // Only include table names and primary/foreign keys
        $columns = DB::select("SHOW FULL COLUMNS FROM `$tableName`");
        foreach ($columns as $column) {
          if ($column->Key === 'PRI' || $column->Key === 'MUL') {
            $schema .= "- Column: `{$column->Field}` | Key: `{$column->Key}`\n";
          }
        }
      } else {
        // Full schema details
        $columns = DB::select("SHOW FULL COLUMNS FROM `$tableName`");
        foreach ($columns as $column) {
          $schema .= "- Column: `{$column->Field}` | Type: `{$column->Type}` | Null: `{$column->Null}` | Key: `{$column->Key}` | Default: `{$column->Default}` | Extra: `{$column->Extra}`\n";
        }
      }

      $schema .= "\n";
    }

    return $schema;
  }

  public function getTableSchema($tableName)
  {
    return Cache::rememberForever("db_table_schema_$tableName", function () use ($tableName) {
      $schema = "Table: `$tableName`\n";
      $columns = DB::select("SHOW FULL COLUMNS FROM `$tableName`");

      foreach ($columns as $column) {
        $schema .= "- Column: `{$column->Field}` | Type: `{$column->Type}` | Null: `{$column->Null}` | Key: `{$column->Key}` | Default: `{$column->Default}` | Extra: `{$column->Extra}`\n";
      }

      return $schema;
    });
  }

  private function getOptimizedTableSchema($tableName)
  {
    $columns = DB::select("SHOW FULL COLUMNS FROM `$tableName`");
    $schema = "Table: `$tableName`\n";

    foreach ($columns as $column) {
      $schema .= "- {$column->Field}: {$column->Type} ({$column->Key})\n";
    }

    // Add foreign key details
    $foreignKeys = DB::select("
        SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_NAME = '$tableName' AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    foreach ($foreignKeys as $fk) {
      $schema .= "- FK: {$fk->COLUMN_NAME} references {$fk->REFERENCED_TABLE_NAME}({$fk->REFERENCED_COLUMN_NAME})\n";
    }

    return $schema;
  }
}
