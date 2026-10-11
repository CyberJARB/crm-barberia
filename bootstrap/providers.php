<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\SuperPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SuperPanelProvider::class,
];
