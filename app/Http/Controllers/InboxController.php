<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyAppWorker;
use App\Services\Push\Inbox;
use App\Services\Push\StaffNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/** Runda 91. Numărul de notificări necitite (clopoțelul din antet), reîmprospătat când aplicația revine în prim-plan. */
class InboxController extends Controller
{
    public function participant(): JsonResponse
    {
        return response()->json(['n' => Inbox::unread(Inbox::forParticipant((int) Auth::guard('participant')->id()))]);
    }

    public function staff(string $app): JsonResponse
    {
        abort_unless(in_array($app, StaffNotifier::APPS, true), 404);

        return response()->json(['n' => Inbox::unread(Inbox::forStaff((int) (ApplyAppWorker::loginId() ?? Auth::guard('admin')->id()), $app))]);
    }
}
