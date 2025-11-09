<?php



// These are simplified feature tests that test the logic without needing real connections
// Full integration tests would require actual RabbitMQ instances

it('command exists and is registered', function () {
    expect(
        $this->app->make('Illuminate\Contracts\Console\Kernel')
            ->all()
    )->toHaveKey('herald:work');
});

it('displays error when no handlers are registered', function () {
    \Assetplan\Herald\Facades\Herald::clearHandlers();

    $this->artisan('herald:work', ['topic' => 'nonexistent'])
        ->expectsOutput('No handlers registered for topic: nonexistent')
        ->assertExitCode(1);
});

it('config has correct structure', function () {
    $config = config('herald');

    expect($config)->toHaveKey('default')
        ->and($config)->toHaveKey('connections')
        ->and($config['connections'])->toHaveKey('rabbitmq');
});
