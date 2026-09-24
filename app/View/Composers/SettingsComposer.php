<?php

namespace App\View\Composers;

use App\Services\Settings\SettingService;
use App\Support\Settings\SiteSettings;
use Illuminate\View\View;

/**
 * Puts $site into every view.
 *
 * Global rather than scoped: the brand name and the logo are in the header,
 * the contact details are in the footer, the share image is in the head, and
 * the panel's own screens print the brand too. A composer per template would
 * be a list to keep in step with no upside.
 *
 * Memoised per request - the header, the footer and the SEO tags all ask for
 * the same bag, and the values are the same each time.
 */
class SettingsComposer
{
    private ?SiteSettings $resolved = null;

    public function __construct(private readonly SettingService $settings) {}

    public function compose(View $view): void
    {
        $view->with('site', $this->resolved ??= $this->settings->bag());
    }
}
