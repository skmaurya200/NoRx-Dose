<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One changed site-wide setting.
 *
 * Written only by App\Services\Settings\SettingService, and only for settings
 * an operator has actually changed - see the migration for why the table is
 * deliberately sparse.
 */
class Setting extends Model
{
    protected $table = 'tbl_settings';

    protected $fillable = [
        'group_key',
        'item_key',
        'value',
    ];

    /**
     * "group.item", the shape the schema and the templates use.
     */
    public function path(): string
    {
        return $this->group_key.'.'.$this->item_key;
    }
}
