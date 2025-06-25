<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'partners';

    public $timestamps = false;

    protected $fillable = [
       'uuid', 'business_name', 'abn', 'email', 'phone','website_url','status'
    ];

    //customer quote listing

    public function profile()
    {
        return $this->hasOne(PartnerProfile::class);
    }

}
