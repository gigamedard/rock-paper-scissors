<?php
    namespace App\Models;

    use Illuminate\Database\Eloquent\Factories\HasFactory;
    use Illuminate\Database\Eloquent\Model;

    class Challenge extends Model
    {
        use HasFactory;

        protected $fillable = ['challenger_id', 'challengee_id', 'status'];

        public function challenger()
        {
            return $this->belongsTo(User::class, 'challenger_id');
        }

        public function challengee()
        {
            return $this->belongsTo(User::class, 'challengee_id');
        }
    }
