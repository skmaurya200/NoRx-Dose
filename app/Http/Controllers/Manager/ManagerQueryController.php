<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Support\ManagerQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ManagerQueryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $target = $request->string('target')->toString();

        abort_unless(ManagerQuery::supports($target), Response::HTTP_NOT_FOUND);

        $allowed = ManagerQuery::allowedParameters($target);
        $submitted = array_keys($request->except(['_token', 'target']));

        abort_if(array_diff($submitted, $allowed) !== [], Response::HTTP_NOT_FOUND);

        try {
            $url = ManagerQuery::url($target, $request->only($allowed));
        } catch (InvalidArgumentException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return redirect()->to($url, Response::HTTP_SEE_OTHER);
    }
}
