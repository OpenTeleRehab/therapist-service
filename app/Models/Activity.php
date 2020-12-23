<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var boolean
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['treatment_plan_id', 'week', 'day', 'exercises'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = ['exercises' => 'array'];
}
