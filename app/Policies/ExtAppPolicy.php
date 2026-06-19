<?php

namespace App\Policies;

use App\Models\ExtApp;
use App\Models\User;
use App\Policies\Traits\CommonPolicyChecksTrait;
use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Illuminate\Http\Request;

class ExtAppPolicy
{
    use HandlesAuthorization;
    use ServiceLocatorTrait;
    use CommonPolicyChecksTrait;

    /**
     * Determine whether the user can view the external app.
     * External apps are an internal construct, but are required to be shown to normal users
     * when they want to connect an external app to their account.
     * Only authenticated users can view external apps, and only admins can view external apps by numeric ID (which is used in the admin interface),
     * all other users must present their "connectRequest" (base64-encoded value) to access the app.
     */
    public function view(?User $user, ExtApp $extApp): Response|bool
    {
        if (!$user) {
            return $this->deny('Only authenticated users can view external apps.');
        }

        return $this->isAdminOrResponse(
            $user,
            additionalCheck: function () {
                $isNumericIdRequest = is_numeric($this->getService(Request::class)->route()->originalParameter('ext_app'));
                if ($isNumericIdRequest) {
                    return 'Only admins can view external apps by numeric ID.';
                }
                return true;
            }
        );
    }

    /**
     * Determine whether the user can establish a connection to the external app.
     * Only authenticated users can establish a connection to an external app.
     */
    public function establishConnection(?User $user): Response
    {
        return $this->isUserResponse($user, 'Only authenticated users can establish a connection to an external app.');
    }

    /**
     * Determine whether the user can view the logo of the external app.
     * Only authenticated users can view the logo of an external app.
     */
    public function viewLogo(?User $user): Response
    {
        return $this->isUserResponse($user, 'Only authenticated users can view external app logos.');
    }
}
