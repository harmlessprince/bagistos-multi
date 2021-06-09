<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Models\Tenant;

class DeleteTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delete:tenant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes a tenant of the provided name, should be made available for only local environment';

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
        // because this is a destructive command, we'll only allow to run this command
        // if you are on the local environment
        if (!app()->isLocal()) {
            $this->error('This command is only available on the local environment.');
            return;
        }
        $name = $this->ask('What is your store name you would like to delete: ?');
        if ($this->confirm('Do you wish to continue, this process is irreversible ?')) {
            $this->deleteTenant($name);
            return 0;
        }
    }

    private function deleteTenant($tenantName)
    {
        if ($this->tenantExists($tenantName)) {
            $this->info("Store/tenant with name '{$tenantName}' found");
            $tenant = $this->getTenant($tenantName);
            $tenantName = $tenant->name;
            $tenantDB = $tenant->database;
            $dbIsDestroyed =  $this->DBDestroy($tenantDB);
            if ($dbIsDestroyed) {
                $tenant->delete();
                $this->info("Store/tenant with name '{$tenantName}' successfully deleted");
                return true;
            }
        }
        $this->error("Database with name tenant '{$tenantName}' does not exist");
        return false;
    }

    private function tenantExists($name)
    {
        return Tenant::where('name', $name)->exists();
    }

    private function getTenant($name)
    {
        return Tenant::where('name', $name)->first();
    }
    private function DBDestroy($database)
    {
        try {
            //setup database connection
            $connection = config('database.landlord');
            $hasDb = DB::connection($connection)->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . "'" . $database . "'");
            if (!empty($hasDb)) {
                DB::connection($connection)->select('DROP DATABASE ' . $database);
                $this->info("Database '$database' deleted successfully");
                return true;
            } else {
                $this->error("Database $database was not found");
                return false;
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
