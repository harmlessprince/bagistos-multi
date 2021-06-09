<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Spatie\Multitenancy\Models\Tenant;

class CreateTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'tenant:create {name} {domain} {database}';
    protected $signature = 'create:tenant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create tenant with provided name and email address';

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
        // $name = $this->argument('name');
        // $domain = $this->argument('domain');
        // $database = $this->argument('database');
        $name = $this->ask('What is your store name: ?');
        $this->info('Your url will be domain.localhost.test');
        $domain = $this->ask('What is your domain name: ?');
        $database = $domain;

        $this->info('Your store name is: ' . $name);
        $this->info('Your url is: ' . $domain . 'localhost.test');
        $this->info('Your database name is: ' . $database);

        if ($this->confirm('Do you wish to continue?')) {
            if ($this->tenantExists($name, $domain, $database)) {
                $this->error("A tenant with name '{$name}' and/or '{$domain}' '{$database}' already exist");
                return;
            }
            $tenant = $this->registerTenant($name, $domain, $database);
            if ($tenant) {
                $this->info('creating database......');
                $this->DBCreate($tenant->database);
                $this->info('database created successfully......');
            }
            $this->info('migrating and seeding database, this will take a few seconds, please wait.....');
            $this->migrateTenantDB($tenant->id);

            $this->info('migration and seeding of database completed successfully.');
            $this->info('Your store has been successfully generated');
        }
    }
    private function tenantExists($name, $domain, $database)
    {
        return Tenant::where('name', $name)->orWhere('domain', $domain)->orWhere('database', $database)->exists();
    }

    private function registerTenant($name, $domain, $database)
    {
        //create Tenant
        $tenant = new Tenant();
        $tenant->name = $name;
        $tenant->domain = $domain . 'localhost';
        $tenant->database = $database;
        $tenant->save();
        return $tenant;
    }

    private function DBCreate($database)
    {
        try {
            //setup database connection
            $connection = config('database.landlord');
            $hasDb = DB::connection($connection)->select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = " . "'" . $database . "'");
            if (empty($hasDb)) {
                DB::connection($connection)->select('CREATE DATABASE ' . $database);
                $this->info("Database '$database' created successfully");
            } else {
                $this->info("Database $database already exists connection");
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
    private function migrateTenantDB($tenantId)
    {
        try {
            Artisan::call("tenants:artisan 'migrate --seed' --tenant=$tenantId");
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }
}
