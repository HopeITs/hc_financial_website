<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An action a person's role allows, taken from this one person on the users page (`web_user_denials`, finance
 * request of 2026-11-29).
 *
 * @property int $user_id
 * @property string $screen
 * @property string $action
 */
class WebUserDenial extends Model
{
    public $timestamps = false;

    protected $table = 'web_user_denials';

    protected $guarded = [];
}
