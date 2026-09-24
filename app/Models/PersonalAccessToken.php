<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum's token model, repointed at the project's tbl_ table name.
 * Registered in AppServiceProvider via Sanctum::usePersonalAccessTokenModel().
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $table = 'tbl_personal_access_tokens';
}
