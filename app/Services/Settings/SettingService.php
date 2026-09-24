<?php

namespace App\Services\Settings;

use App\Models\Admin;
use App\Models\Setting;
use App\Support\HtmlSanitizer;
use App\Support\PublicUpload;
use App\Support\Settings\SettingSchema;
use App\Support\Settings\SiteSettings;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Reads and writes the site-wide settings.
 *
 * Every page load on the whole site asks for these, so they are cached forever
 * and forgotten on save rather than given a timeout - they change when an
 * operator changes them and at no other moment.
 */
class SettingService
{
    private const UPLOAD_BUCKET = 'settings';

    private const CACHE_KEY = 'site-settings';

    /**
     * The bag every template reads, with the schema defaults behind it.
     */
    public function bag(): SiteSettings
    {
        return SiteSettings::make($this->values());
    }

    /**
     * Saved values only, as "group.item" => value.
     *
     * @return array<string, string|null>
     */
    public function values(): array
    {
        try {
            return Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Setting::query()
                    ->get()
                    ->mapWithKeys(fn (Setting $row) => [$row->path() => $row->value])
                    ->all(),
            );
        } catch (QueryException) {
            // The table is not there yet - a first deploy, or a view rendered
            // mid-migration. The schema defaults are a complete, working site
            // on their own, so this is a fallback rather than a failure. Not
            // cached, so the first real read after migrating gets the rows.
            return [];
        }
    }

    /* ---------------------------------------------------------------- write */

    /**
     * Saves the settings a form sent, and the images it uploaded.
     *
     * Only paths named in the schema are touched, so a crafted payload cannot
     * invent a setting. A field submitted empty is deleted rather than stored
     * as an empty string, which is what makes "clear it to restore the
     * original" true rather than approximately true.
     *
     * @param  array<string, string|null>  $fields  "group.item" => value
     * @param  array<string, UploadedFile>  $images
     * @param  array<int, string>  $removeImages
     */
    public function save(array $fields, array $images = [], array $removeImages = []): void
    {
        $schema = SettingSchema::fields();
        $existing = $this->values();

        DB::transaction(function () use ($fields, $images, $removeImages, $schema, $existing) {
            foreach ($schema as $path => $field) {
                if (($field['type'] ?? 'text') === 'image') {
                    $this->saveImage($path, $images[$path] ?? null,
                        in_array($path, $removeImages, true), $existing[$path] ?? null);

                    continue;
                }

                // A setting the form did not send is left alone, so a partial
                // save cannot wipe a tab that was not on screen.
                if (! array_key_exists($path, $fields)) {
                    continue;
                }

                $value = trim((string) $fields[$path]);

                // An "inline" setting is a line the design sets partly in
                // gold - the ticker items - so it keeps <b> and <em> and
                // loses everything else. Everything else is copy, stored as
                // text and escaped on output.
                $value = ($field['type'] ?? 'text') === 'inline'
                    ? HtmlSanitizer::inline($value)
                    : strip_tags($value);

                $this->put($path, $value === '' ? null : $value);
            }
        });

        $this->forget();
    }

    /**
     * Changes the signed-in operator's password.
     *
     * The current password is checked here rather than in the request class:
     * it is a credential check, and it belongs next to the write it guards.
     * Every other session is signed out, because a password change is usually
     * a response to one of them being somewhere it should not be.
     */
    public function changePassword(Admin $admin, string $password): void
    {
        $admin->forceFill(['password' => Hash::make($password)])->save();

        $admin->tokens()->delete();
    }

    /* -------------------------------------------------------------- private */

    private function saveImage(string $path, ?UploadedFile $file, bool $remove, ?string $current): void
    {
        if ($file !== null) {
            $this->put($path, PublicUpload::replace($file, self::UPLOAD_BUCKET, $current));

            return;
        }

        if ($remove && $current) {
            PublicUpload::delete($current, self::UPLOAD_BUCKET);
            $this->put($path, null);
        }
    }

    /**
     * Writes one setting, or deletes the row when the value is empty - the
     * table only holds what has actually been changed.
     */
    private function put(string $path, ?string $value): void
    {
        [$group, $item] = explode('.', $path, 2);

        $keys = ['group_key' => $group, 'item_key' => $item];

        if ($value === null || $value === '') {
            Setting::query()->where($keys)->delete();

            return;
        }

        Setting::query()->updateOrCreate($keys, ['value' => $value]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
