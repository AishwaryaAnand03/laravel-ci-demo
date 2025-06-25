<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserProfile extends Model
{
    protected $table = 'user_profiles';
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone_number',
        'profile_photo_url',
        'timezone',
    ];
}



