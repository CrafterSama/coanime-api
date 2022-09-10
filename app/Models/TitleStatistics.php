<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TitleStatistics extends Model
{
    use HasFactory;
    use SoftDeletes;
		/**
		 * The table associated with the model.
		 *
		 * @var string
		 */
		protected $table = 'title_statistics';
		protected $fillable = ['user_id', 'statistics_id', 'title_id'];
		protected $dates = ['deleted_at', 'created_at', 'updated_at'];

		public function users() {
			return $this->belongsTo(User::class, 'user_id', 'id');
		}

		public function titles() {
			return $this->belongsTo(Title::class, 'title_id', 'id');
		}

		public function statistics() {
			return $this->belongsTo(Statistic::class, 'statistics_id', 'id');
		}

	}
