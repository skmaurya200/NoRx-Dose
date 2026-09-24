<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingService;
use App\Support\Settings\SettingSchema;
use Illuminate\View\View;

/**
 * The Settings screen in the admin panel.
 *
 * HTML only - both forms post to App\Http\Controllers\APIs\SettingController,
 * so the rules and their messages have one implementation.
 */
class SettingController extends Controller
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * GET /manager/settings
     */
    public function index(): View
    {
        return view('manager.settings.index', [
            'groups' => SettingSchema::all(),
            // Named $content because the form reuses the Pages module's field
            // partials, and those partials read the bag under that name.
            'content' => $this->settings->bag(),
        ]);
    }
}
