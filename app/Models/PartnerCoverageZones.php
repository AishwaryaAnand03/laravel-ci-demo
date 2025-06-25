<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerCoverageZones extends Model
{
    protected $table = 'partner_coverage_zones';
    public $timestamps = false;

    protected $fillable = [
        'id','partner_id',' suburb ','postcode','state' ,'country','coverage_type','polygon_geojson','radius_km','center_lat','center_lng'
    ];

}