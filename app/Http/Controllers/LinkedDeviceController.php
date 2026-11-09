<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Devices\ForgetLinkedDevices;
use App\Data\LinkedDeviceData;
use App\Data\PhoneLinkData;
use App\Http\Middleware\LimitVisit;
use App\Models\LinkedDevice;
use App\Models\User;
use App\Services\PhoneLinks;
use App\Support\QrCode;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * The phones linked to the account of whoever signed in: the panel *Link the phone* on the computer (a code, its
 * QR and the list) and *Forget*. Each account sees and forgets only its own phones.
 */
final class LinkedDeviceController extends Controller
{
    /**
     * @param  PhoneLinks  $links  the linking of the phones
     */
    public function __construct(private readonly PhoneLinks $links) {}

    /**
     * What the panel needs. Opening it makes a new code; the reload every 2 seconds asks `?only=devices` and
     * gets just the phones, with no new code. During a visit of the owner no code is made: the account's own code
     * stays valid, and the owner cannot link a phone that would outlive the visit.
     *
     * @param  Request  $request  the request
     * @param  User  $user  whoever signed in
     */
    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $devices = self::devicesOf($user);

        if ($request->query('only') === 'devices') {
            return response()->json(['devices' => $devices]);
        }

        $code = LimitVisit::isOpen($request) ? null : $this->links->newCode($user);

        return response()->json(new PhoneLinkData(
            code: $code,
            qr: $code === null ? null : QrCode::svg(route('connect.show', ['code' => $code])),
            address: (string) preg_replace('#^https?://#', '', route('connect')),
            expires_at: $code === null ? null : now()->addMinutes(PhoneLinks::CODE_MINUTES)->toIso8601String(),
            person: $this->links->firstName($user),
            family: $user->family->name,
            devices: $devices,
        ));
    }

    /**
     * Forgets a phone: it and its session go. A phone of another person, or of another family (which the scope of
     * the family does not find), answers 404.
     *
     * @param  LinkedDevice  $device  the phone
     * @param  User  $user  whoever signed in
     * @param  ForgetLinkedDevices  $forget  the action
     */
    public function destroy(LinkedDevice $device, #[CurrentUser] User $user, ForgetLinkedDevices $forget): RedirectResponse
    {
        abort_unless($device->user_id === $user->id, 404);
        Gate::authorize('delete', $device);

        $forget->handle($user, $device);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Phone unlinked.')]);

        return back();
    }

    /**
     * The phones of an account, the oldest first.
     *
     * @param  User  $user  the account
     * @return list<LinkedDeviceData>
     */
    public static function devicesOf(User $user): array
    {
        $devices = LinkedDevice::query()
            ->where('user_id', $user->id)
            ->oldest()
            ->orderBy('id')
            ->get();

        return array_values($devices->map(LinkedDeviceData::fromDevice(...))->all());
    }
}
