<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceProviderResource;
use App\Http\Resources\TaskResource;
use App\Models\ServiceProvider;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceProviderController extends Controller
{
    /**
     * List all service providers.
     * 
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $providers = ServiceProvider::all();

        return ServiceProviderResource::collection($providers);
    }

    /**
     * List tasks for a specific service provider.
     * 
     * @param ServiceProvider $serviceProvider
     * 
     * @return AnonymousResourceCollection
     */
    public function tasks(ServiceProvider $serviceProvider): AnonymousResourceCollection
    {
        return TaskResource::collection($serviceProvider->tasks);
    }
}
