<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SyncApiUsage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync API usage from Redis to DB';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        {
            Log::info("Sync API usage from Redis to DB");
            $date = now()->toDateString();

            $cursor = 0;
            do {
                [$cursor, $keys] = Redis::scan($cursor, [
                    'match' => "api_usage_admin:$date:*",
                    'count' => 500
                ]);

                foreach ($keys as $key) {
                    $total = Redis::get($key);

                    // api_usage:date:user:endpoint
                    $parts = explode(':', $key);

                    $userId = $parts[2];
                    $endpoint = $parts[3] ?? '';
                    $type = 2;
                    DB::table('api_usages')->updateOrInsert(
                        [
                            'user_id' => $userId,
                            'endpoint' => $endpoint,
                            'date' => $date
                        ],
                        [
                            'total' => DB::raw("total + $total"),
                            'type' =>  $type,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    // reset sau khi sync
                    Redis::del($key);
                }

            } while ($cursor != 0);

            $this->info('Done sync');
        }
    }
}
