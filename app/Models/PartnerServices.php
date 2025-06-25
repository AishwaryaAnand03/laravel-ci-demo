<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerServices extends Model
{
    protected $table = 'partner_services';
    public $timestamps = false;

    protected $fillable = [
        'id','partner_id','service_type','is_active'
    ];

}



