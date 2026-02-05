<?php

namespace App\Http\Controllers\Internal;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserController extends Controller
{

    /**
     * Get users by ids.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByIds(Request $request)
    {
        $ids = $request->get('ids', []);
        $users = User::whereIn('id', $ids)->select('id', 'first_name', 'last_name', 'type')->get();

        return ['success' => true, 'data' => $users];
    }

    /**
     * Get users by name.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByName(Request $request)
    {
        $name = $request->get('name');
        $users = $users = User::where('first_name', 'like', '%' . $name . '%')
            ->orWhere('last_name', 'like', '%' . $name . '%')
            ->get();

        return ['success' => true, 'data' => $users];
    }

    /**
     * Get users by type.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return array
     */
    public function getByType(Request $request)
    {
        $type = $request->get('type');
        $users = User::where('type', $type)->get();

        return ['success' => true, 'data' => $users];
    }
}
