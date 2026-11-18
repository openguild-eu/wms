<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('does not link to the demo signup on the central domain landing page without the demo module', function () {
    expect(Route::has('demo.signup.show'))->toBeFalse();

    $this->get('http://'.config('app.central_domain').'/')
        ->assertOk()
        ->assertSee('Continue to Login')
        ->assertDontSee('Start a free demo')
        ->assertDontSee('/demo/signup');
});
