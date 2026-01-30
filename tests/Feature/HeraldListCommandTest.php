<?php

it('command exists and is registered', function () {
    expect(
        $this->app->make('Illuminate\\Contracts\\Console\\Kernel')
            ->all()
    )->toHaveKey('herald:list');
});

it('displays message when no handlers are registered', function () {
    \Assetplan\Herald\Facades\Herald::clearHandlers();

    $this->artisan('herald:list')
        ->expectsOutput('No handlers registered. Use Herald::on() to register handlers.')
        ->assertExitCode(0);
});
