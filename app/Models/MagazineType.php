<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MagazineType extends Model {
	use SoftDeletes;
	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'magazines_type';
	protected $dates = ['deleted_at', 'created_at', 'updated_at'];

	public function magazine() {
		return $this->hasOne(Magazine::class);
	}
}
