<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SalesTrialImage extends Model
{
    protected $table = 'sales_trial_images';

    protected $fillable = ['sales_trial_id', 'path', 'width', 'height', 'bytes'];

    public function trial(): BelongsTo
    {
        return $this->belongsTo(SalesTrial::class, 'sales_trial_id');
    }

    /** Public URL for the stored (compressed) image. */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
