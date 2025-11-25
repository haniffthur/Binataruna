<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
class SchoolClass extends Model {
    use HasFactory;
    
    protected $table = 'classes'; // Penting: Menentukan nama tabel secara eksplisit
    protected $fillable = ['name', 'description', 'price','access_rule_id'];

    public function accessRule()
{
    return $this->belongsTo(AccessRule::class);
}
   
}