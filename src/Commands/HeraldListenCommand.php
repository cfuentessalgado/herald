<?php

namespace Assetplan\Herald\Commands;

use Illuminate\Console\Command;

class HeraldListenCommand extends Command
{
    protected $signature = 'herald:listen 
                            {--connection= : The connection to use}';

    protected $description = 'Listen to all Herald events (development)';

    public function handle(): int
    {
        $this->info('👂 Listening to all Herald events...');

        return $this->call('herald:work', [
            'topic' => '#',
            '--connection' => $this->option('connection'),
        ]);
    }
}
