<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\RoutePublicIds;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('public-ids:backfill', function (RoutePublicIds $ids) {
    foreach ($ids->fillMissing() as $table => $count) {
        $this->info($table.': '.$count.' missing public IDs assigned.');
    }
})->purpose('Assign missing public IDs without changing existing identifiers or record data');
