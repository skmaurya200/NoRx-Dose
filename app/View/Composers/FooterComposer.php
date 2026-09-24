<?php

namespace App\View\Composers;

use App\Services\Catalogue\StorefrontCatalogue;
use Illuminate\Contracts\View\View;

/**
 * The footer's category column appears on every storefront page, so it cannot
 * be fed from a controller without every action having to remember. A composer
 * binds it once, wherever the footer is included.
 */
class FooterComposer
{
    public function __construct(private readonly StorefrontCatalogue $catalogue) {}

    public function compose(View $view): void
    {
        $view->with('footerCategories', $this->catalogue->categories(7));
    }
}
