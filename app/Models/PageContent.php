<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One edited field on one storefront page.
 *
 * Rows are written only by App\Services\Content\PageContentService, and only
 * for fields an operator has actually changed - see the migration for why the
 * table is deliberately sparse.
 */
class PageContent extends Model
{
    protected $table = 'tbl_page_contents';

    protected $fillable = [
        'page_key',
        'section_key',
        'field_key',
        'value',
    ];

    /**
     * "section.field", the shape the schema and the templates use.
     */
    public function path(): string
    {
        return $this->section_key.'.'.$this->field_key;
    }
}
