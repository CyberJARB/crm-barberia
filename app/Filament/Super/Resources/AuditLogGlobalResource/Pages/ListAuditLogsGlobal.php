<?php

namespace App\Filament\Super\Resources\AuditLogGlobalResource\Pages;

use App\Filament\Super\Resources\AuditLogGlobalResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogsGlobal extends ListRecords
{
    protected static string $resource = AuditLogGlobalResource::class;
}
