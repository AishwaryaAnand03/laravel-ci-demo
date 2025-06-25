<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerUser extends Model
{
    protected $table = 'partner_users';
  public $timestamps = false;
    protected $fillable = [
        'id','user_id','partner_id','role' ,'is_primary_contact'    
    ];

}