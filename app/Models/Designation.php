<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    use HasFactory;

    protected $primaryKey = 'designation_id';

    protected $fillable = [
        'designation_name',
    ];

    public function designationDetails(): HasMany
    {
        return $this->hasMany(DesignationDetail::class, 'designation_id', 'designation_id');
    }
}
