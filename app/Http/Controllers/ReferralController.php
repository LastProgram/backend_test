<?php

namespace App\Http\Controllers;

use App\Models\Master;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Services\Referral\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReferralController extends Controller
{
    public function __construct(private ReferralService $referrals)
    {
    }

    public function attach(Request $request): JsonResponse
    {
        $master = $request->attributes->get('current_master');

        if (!$master) {
            return response()->json(['error' => 'Master not found'], 401);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid input', 'details' => $validator->errors()], 422);
        }

        $code = $validator->validated()['code'];
        $referrer = Master::where('referral_code', $code)->first();

        if (!$referrer) {
            return response()->json(['error' => 'Invalid referral code'], 422);
        }

        if ($referrer->id === $master->id) {
            return response()->json(['error' => 'Self-referral is not allowed'], 422);
        }

        $existing = Referral::where('referred_master_id', $master->id)->first();

        if ($existing && $existing->referrer_master_id !== $referrer->id) {
            return response()->json(['error' => 'Referrer cannot be changed'], 409);
        }

        $referral = $this->referrals->registerReferral($master, $code);

        if (!$referral) {
            return response()->json(['error' => 'Invalid referral code'], 422);
        }

        if ($referral->referrer_master_id !== $referrer->id) {
            return response()->json(['error' => 'Referrer cannot be changed'], 409);
        }

        return response()->json([
            'data' => [
                'id' => $referral->id,
                'referrer_master_id' => $referral->referrer_master_id,
                'status' => $referral->status,
            ],
        ], $referral->wasRecentlyCreated ? 201 : 200);
    }

    public function my(Request $request): JsonResponse
    {
        $master = $request->attributes->get('current_master');

        if (!$master) {
            return response()->json(['error' => 'Master not found'], 401);
        }

        $referrals = $master->referrals()
            ->with('referredMaster:id,name')
            ->withSum('earnings', 'amount')
            ->get()
            ->map(fn (Referral $referral) => [
                'name' => $referral->referredMaster->name,
                'attached_at' => $referral->created_at->toIso8601String(),
                'credited' => $referral->status === Referral::STATUS_REWARDED,
                'earned_amount' => (int) ($referral->earnings_sum_amount ?? 0),
            ]);

        return response()->json(['data' => $referrals]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $master = $request->attributes->get('current_master');

        if (!$master) {
            return response()->json(['error' => 'Master not found'], 401);
        }

        $earnings = $master->referralEarnings()->get(['amount', 'status']);

        return response()->json(['data' => [
            'total' => (int) $earnings->sum('amount'),
            'pending' => (int) $earnings->where('status', ReferralEarning::STATUS_PENDING)->sum('amount'),
            'paid' => (int) $earnings->where('status', ReferralEarning::STATUS_PAID)->sum('amount'),
            'credited_referrals' => $master->referrals()->active()->count(),
        ]]);
    }
}
