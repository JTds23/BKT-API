<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\ServiceProvider;
use App\Models\Task;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create 2 customers
        Customer::create(['name' => 'John Smith']);
        Customer::create(['name' => 'Jane Doe']);

        // Create service provider 1 with tasks
        $provider1 = ServiceProvider::create(['business_name' => 'CleanPro Services']);
        Task::create(['service_provider_id' => $provider1->id, 'name' => 'Oven Cleaning', 'price' => 55.00]);
        Task::create(['service_provider_id' => $provider1->id, 'name' => 'Carpet Cleaning', 'price' => 80.00]);
        Task::create(['service_provider_id' => $provider1->id, 'name' => 'Window Cleaning', 'price' => 45.00]);
        Task::create(['service_provider_id' => $provider1->id, 'name' => 'Deep House Clean', 'price' => 150.00]);

        // Create service provider 2 with tasks
        $provider2 = ServiceProvider::create(['business_name' => 'GreenThumb Gardening']);
        Task::create(['service_provider_id' => $provider2->id, 'name' => 'Lawn Mowing', 'price' => 20.00]);
        Task::create(['service_provider_id' => $provider2->id, 'name' => 'Tree Trimming', 'price' => 75.00]);
        Task::create(['service_provider_id' => $provider2->id, 'name' => 'Hedge Trimming', 'price' => 35.00]);
        Task::create(['service_provider_id' => $provider2->id, 'name' => 'Garden Maintenance', 'price' => 60.00]);
        Task::create(['service_provider_id' => $provider2->id, 'name' => 'Pressure Washing', 'price' => 90.00]);

        // Create service provider 3 with tasks
        $provider3 = ServiceProvider::create(['business_name' => 'HandyFix Home Repairs']);
        Task::create(['service_provider_id' => $provider3->id, 'name' => 'Plumbing Repair', 'price' => 85.00]);
        Task::create(['service_provider_id' => $provider3->id, 'name' => 'Electrical Repair', 'price' => 95.00]);
        Task::create(['service_provider_id' => $provider3->id, 'name' => 'Door/Window Repair', 'price' => 65.00]);
        Task::create(['service_provider_id' => $provider3->id, 'name' => 'Furniture Assembly', 'price' => 50.00]);
        Task::create(['service_provider_id' => $provider3->id, 'name' => 'TV Wall Mounting', 'price' => 40.00]);
        Task::create(['service_provider_id' => $provider3->id, 'name' => 'General Handyman (hourly)', 'price' => 45.00]);

        // Create service provider 4 with tasks
        $provider4 = ServiceProvider::create(['business_name' => 'SparkleShine Car Care']);
        Task::create(['service_provider_id' => $provider4->id, 'name' => 'Car Wash (Exterior)', 'price' => 15.00]);
        Task::create(['service_provider_id' => $provider4->id, 'name' => 'Car Wash (Full)', 'price' => 25.00]);
        Task::create(['service_provider_id' => $provider4->id, 'name' => 'Interior Detailing', 'price' => 70.00]);
        Task::create(['service_provider_id' => $provider4->id, 'name' => 'Full Detailing', 'price' => 120.00]);
        Task::create(['service_provider_id' => $provider4->id, 'name' => 'Wax & Polish', 'price' => 55.00]);

        // Create service provider 5 with tasks
        $provider5 = ServiceProvider::create(['business_name' => 'PetPal Services']);
        Task::create(['service_provider_id' => $provider5->id, 'name' => 'Dog Walking (30 min)', 'price' => 15.00]);
        Task::create(['service_provider_id' => $provider5->id, 'name' => 'Dog Walking (1 hour)', 'price' => 25.00]);
        Task::create(['service_provider_id' => $provider5->id, 'name' => 'Pet Sitting (per day)', 'price' => 45.00]);
        Task::create(['service_provider_id' => $provider5->id, 'name' => 'Dog Grooming', 'price' => 60.00]);
        Task::create(['service_provider_id' => $provider5->id, 'name' => 'Cat Grooming', 'price' => 40.00]);
    }
}
