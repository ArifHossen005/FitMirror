<?php

namespace App\Http\Controllers\Mission;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;

/**
 * Base for every Mission Control (super-admin) controller. Reuses
 * BaseApiController's response envelope helpers rather than duplicating
 * them (see PROGRESS.md Decision D-09) — the JSON shape is identical for
 * tenant and Mission Control responses, only the auth guard differs.
 */
abstract class BaseMissionController extends BaseApiController
{
    /**
     * The authenticated SuperAdmin for the current request. Only valid
     * behind the `auth:super_admin` + `super_admin` middleware pair — never
     * call this from an unauthenticated route.
     */
    protected function superAdmin(Request $request): SuperAdmin
    {
        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin');

        return $superAdmin;
    }
}
