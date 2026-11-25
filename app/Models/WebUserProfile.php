<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A person's details beside their sign-in (`web_user_profiles`, finance request of 2026-11-29).
 *
 * @property int $user_id
 * @property string|null $full_name
 * @property string|null $job_title
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $notes
 * @property bool $limit_projects the person sees and enters only their projects (finance request of 2026-11-30)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebUserProfile extends Model
{
    protected $table = 'web_user_profiles';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'full_name', 'job_title', 'email', 'phone', 'city', 'notes', 'limit_projects'];

    protected function casts(): array
    {
        return ['limit_projects' => 'boolean'];
    }
}
