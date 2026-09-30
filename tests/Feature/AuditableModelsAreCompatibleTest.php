<?php

namespace Tests\Feature;

use OwenIt\Auditing\Contracts\Auditable;
use Tests\TestCase;

/**
 * `audits.auditable_id` is BIGINT UNSIGNED, so a model keyed by anything other
 * than an auto-incrementing integer cannot be audited into it. MySQL rejects
 * the insert with
 *
 *   1366 Incorrect integer value: '<uuid>' for column 'auditable_id'
 *
 * and because the audit write happens inside the model's own save, the whole
 * operation fails -- the user gets a 500 on an ordinary upload. That is exactly
 * what happened when LibraryFile (HasUuids) was given the trait: five tests
 * went red and the vault upload screen broke.
 *
 * This walks every model in the codebase rather than a maintained list, so a
 * model that gains Auditable later is covered automatically.
 */
class AuditableModelsAreCompatibleTest extends TestCase
{
    public function test_auditable_id_can_hold_every_audited_models_key(): void
    {
        $nonInteger = [];

        foreach ($this->modelClasses() as $class) {
            if (!is_subclass_of($class, Auditable::class)) {
                continue;
            }

            try {
                $model = new $class();
            } catch (\Throwable) {
                // Models that cannot be constructed at all are a separate
                // problem and not what this test is about.
                continue;
            }

            if ($model->getKeyType() !== 'int' || $model->getIncrementing() === false) {
                $nonInteger[] = sprintf(
                    '%s (keyType=%s, incrementing=%s)',
                    class_basename($class),
                    $model->getKeyType(),
                    var_export($model->getIncrementing(), true)
                );
            }
        }

        if ($nonInteger === []) {
            $this->addToAssertionCount(1);

            return;
        }

        // At least one audited model is keyed by something other than an
        // auto-incrementing integer, so the column has to be able to store it.
        // A numeric auditable_id would reject the insert outright, and because
        // the audit write happens inside the model's own save, the record
        // itself would fail to save too -- an ordinary upload returning a 500.
        $type = $this->auditableIdType();

        $this->assertTrue(
            str_contains($type, 'char') || str_contains($type, 'text'),
            "These audited models are not keyed by an integer:\n  "
            . implode("\n  ", $nonInteger)
            . "\n\nbut audits.auditable_id is `{$type}`, which cannot store their keys."
            . "\nEither widen the column to VARCHAR, or drop the Auditable trait from them."
        );
    }

    private function auditableIdType(): string
    {
        $column = collect(\Illuminate\Support\Facades\DB::select('SHOW COLUMNS FROM `audits`'))
            ->firstWhere('Field', 'auditable_id');

        return strtolower($column->Type ?? 'unknown');
    }

    /** Every App\Models class, discovered from the filesystem. */
    private function modelClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\' . basename($file, '.php');

            try {
                // class_exists() autoloads, and a model whose parent class is
                // not installed (Tenant extends a Stancl\Tenancy base that is
                // not a dependency of this project) throws here rather than
                // returning false. That is a separate, pre-existing problem --
                // skip it rather than let it fail this test.
                if (!class_exists($class)) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
