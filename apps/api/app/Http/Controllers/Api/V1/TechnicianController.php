<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TechnicianController extends Controller
{
    public function __invoke(Request $request)
    {
        Gate::authorize('viewTechnicians');

        return UserResource::collection(User::query()
            ->where('role', UserRole::Technician->value)
            ->orderBy('name')
            ->get());
    }
}
