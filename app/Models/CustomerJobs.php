<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerJobs extends Model
{
    protected $table = 'customer_jobs';
    
    // Use guarded to block mass assignment for specific fields (or use ['id'] to guard only the ID)
    protected $guarded = ['id'];

    public function customerJob()
    {
        return $this->belongsTo(CustomerJob::class, 'job_id');
    }
}
