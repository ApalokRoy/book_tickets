<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable=['name', 'seat_no', 'parent_seat_no', 'cancelled'];
    public $timestamps = true;
}
