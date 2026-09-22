<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::get('/locale/{locale}', function (Request $request, string $locale) {
    $supported = array_keys(config('app.available_locales', ['en' => 'English']));

    if (in_array($locale, $supported, true)) {
        $request->session()->put('locale', $locale);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->save();
        }
    }

    return back();
})->name('locale.switch');
