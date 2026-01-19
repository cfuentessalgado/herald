<?php

namespace Assetplan\Herald\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    protected $signature = 'herald:install';

    protected $description = 'Install Herald service provider for registering message handlers';

    public function handle(): int
    {
        $this->info('Installing Herald...');
        $this->newLine();

        // Publish the service provider
        $this->publishServiceProvider();

        // Ask about config publishing
        $this->newLine();
        if ($this->confirm('Would you like to publish the config file? (optional - most users don\'t need this)', false)) {
            $this->publishConfig();
        } else {
            $this->components->info('Skipped config publishing. You can publish it later with:');
            $this->line('  <fg=gray>php artisan vendor:publish --tag=herald-config</>');
        }

        // Provide next steps
        $this->newLine();
        $this->components->info('Herald installed successfully!');
        $this->newLine();

        $this->comment('Next steps:');
        $this->line('  1. Register handlers in <fg=yellow>app/Providers/HeraldServiceProvider.php</>');
        $this->line('  2. Add the provider to <fg=yellow>config/app.php</> (if not using auto-discovery):');
        $this->line('     <fg=gray>App\Providers\HeraldServiceProvider::class</>');
        $this->line('  3. Configure your connection in <fg=yellow>.env</>:');
        $this->line('     <fg=gray>HERALD_CONNECTION=rabbitmq</>');
        $this->line('     <fg=gray>RABBITMQ_HOST=localhost</>');
        $this->line('  4. Start the worker: <fg=yellow>php artisan herald:work</>');

        $this->newLine();

        return self::SUCCESS;
    }

    protected function publishServiceProvider(): void
    {
        $filesystem = new Filesystem;

        $stub = __DIR__.'/../../stubs/HeraldServiceProvider.stub';
        $target = app_path('Providers/HeraldServiceProvider.php');

        // Check if file already exists
        if ($filesystem->exists($target)) {
            if (! $this->confirm('HeraldServiceProvider already exists. Overwrite?', false)) {
                $this->components->info('Skipped publishing service provider.');

                return;
            }
        }

        // Ensure directory exists
        $filesystem->ensureDirectoryExists(app_path('Providers'));

        // Copy the stub
        $filesystem->copy($stub, $target);

        $this->components->info('Published HeraldServiceProvider to [app/Providers/HeraldServiceProvider.php]');
    }

    protected function publishConfig(): void
    {
        $filesystem = new Filesystem;

        $source = __DIR__.'/../../config/herald.php';
        $target = config_path('herald.php');

        // Check if file already exists
        if ($filesystem->exists($target)) {
            if (! $this->confirm('Config file already exists. Overwrite?', false)) {
                $this->components->info('Skipped publishing config.');

                return;
            }
        }

        // Copy the config
        $filesystem->copy($source, $target);

        $this->components->info('Published config to [config/herald.php]');
    }
}
