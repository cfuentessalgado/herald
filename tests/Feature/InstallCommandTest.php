<?php

use Illuminate\Support\Facades\File;

it('can install herald service provider', function () {
    $providerPath = app_path('Providers/HeraldServiceProvider.php');

    // Clean up if it exists
    if (File::exists($providerPath)) {
        File::delete($providerPath);
    }

    // Run the install command
    $this->artisan('herald:install')
        ->expectsOutput('Installing Herald...')
        ->expectsConfirmation('Would you like to publish the config file? (optional - most users don\'t need this)', 'no')
        ->assertExitCode(0);

    // Check that the service provider was created
    expect(File::exists($providerPath))->toBeTrue();

    // Check the content has the expected structure
    $content = File::get($providerPath);
    expect($content)->toContain('namespace App\Providers');
    expect($content)->toContain('class HeraldServiceProvider extends ServiceProvider');
    expect($content)->toContain('Herald::on(');

    // Clean up
    File::delete($providerPath);
});

it('can publish config during install', function () {
    $providerPath = app_path('Providers/HeraldServiceProvider.php');
    $configPath = config_path('herald.php');

    // Clean up if they exist
    if (File::exists($providerPath)) {
        File::delete($providerPath);
    }
    if (File::exists($configPath)) {
        File::delete($configPath);
    }

    // Run the install command and accept config publishing
    $this->artisan('herald:install')
        ->expectsOutput('Installing Herald...')
        ->expectsConfirmation('Would you like to publish the config file? (optional - most users don\'t need this)', 'yes')
        ->assertExitCode(0);

    // Check that both files were created
    expect(File::exists($providerPath))->toBeTrue();
    expect(File::exists($configPath))->toBeTrue();

    // Check config content
    $configContent = File::get($configPath);
    expect($configContent)->toContain('connections');
    expect($configContent)->toContain('rabbitmq');

    // Clean up
    File::delete($providerPath);
    File::delete($configPath);
});

it('prompts when service provider already exists', function () {
    $providerPath = app_path('Providers/HeraldServiceProvider.php');

    // Create a dummy file
    File::ensureDirectoryExists(app_path('Providers'));
    File::put($providerPath, '<?php // existing file');

    // Run the install command and decline overwrite
    $this->artisan('herald:install')
        ->expectsConfirmation('HeraldServiceProvider already exists. Overwrite?', 'no')
        ->expectsConfirmation('Would you like to publish the config file? (optional - most users don\'t need this)', 'no')
        ->assertExitCode(0);

    // Check the file wasn't overwritten
    $content = File::get($providerPath);
    expect($content)->toBe('<?php // existing file');

    // Clean up
    File::delete($providerPath);
});
