<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasFactory;

    const STATUS_INVITED = 'invited';
    const STATUS_DECLINED = 'declined';
    const LEAD_THERAPIST = 'lead';
    const SUPPLEMENTARY_THERAPIST = 'supplementary';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'from_therapist_id',
        'to_therapist_id',
        'clinic_id',
        'therapist_type',
        'status'
    ];

    public function from_therapist()
    {
        return $this->belongsTo(User::class, 'from_therapist_id');
    }
}
