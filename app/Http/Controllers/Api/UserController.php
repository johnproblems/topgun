<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function search(Request $request)
    {
        $request->validate([
            'email' => 'required|string|min:3',
            'exclude_organization' => 'nullable|exists:organizations,id',
        ]);

        $query = User::where('email', 'like', '%'.$request->email.'%')
            ->limit(10);

        // Exclude users already in the specified organization
        if ($request->exclude_organization) {
            $query->whereDoesntHave('organizations', function ($q) use ($request) {
                $q->where('organization_id', $request->exclude_organization);
            });
        }

        $users = $query->get(['id', 'name', 'email']);

        return response()->json([
            'users' => $users,
        ]);
    }
}
