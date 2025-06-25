<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerDocuments extends Model
{
    protected $table = 'partner_documents';
    public $timestamps = false;
    protected $fillable = [
        'id','partner_id','document_type','document_name','document_url' ,'expiry_date','uploaded_at'   
    ];

}