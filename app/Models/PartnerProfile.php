<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerProfile extends Model
{
    protected $table = 'partner_profiles';
    
    // If your table doesn't use created_at or updated_at
    public $timestamps = false;

    protected $fillable = [
        'partner_id',
        'about_us',
        'years_in_business',
        'rating_average',
        'total_reviews',
        'photos_json',
        'trust_badges_json',
    ];

    //customer quote listing code

    protected $casts = [
        'trust_badges_json' => 'array',
        'photos_json' => 'array',
        'rating_average' => 'float',
        'total_reviews' => 'integer',
        'years_in_business' => 'integer',
    ];

    // Optional: If you have a relationship to Partner
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
}
