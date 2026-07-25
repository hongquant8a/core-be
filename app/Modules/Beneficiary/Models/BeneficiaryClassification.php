<?php

namespace App\Modules\Beneficiary\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class BeneficiaryClassification extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    /** File quyết định công nhận của loại đối tượng này (nhiều file). */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('decision_documents');
    }

    protected static function newFactory()
    {
        return \Database\Factories\Modules\Beneficiary\Models\BeneficiaryClassificationFactory::new();
    }

    protected $table = 'beneficiary_classifications';

    protected $fillable = ['beneficiary_id', 'type', 'decision_no', 'decision_date', 'issued_by', 'is_primary'];

    protected $casts = [
        'decision_date' => 'date',
        'is_primary' => 'boolean',
    ];

    public function beneficiary()
    {
        return $this->belongsTo(Beneficiary::class);
    }
}
