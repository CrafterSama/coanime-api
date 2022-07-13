<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ratings extends Model {
	use SoftDeletes;
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'ratings';
	protected $dates = ['deleted_at', 'created_at', 'updated_at'];

	public function titles() {
		return $this->hasOne(Title::class);
	}
}
