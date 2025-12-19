<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Report;
use App\Models\Credential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GlideTransactionService;

class UserController extends Controller
{
    protected $service;

    public function __construct(GlideTransactionService $service)
    {
        $this->service = $service;
    }
    
public function getMerchants(Request $request)
{
    // Base query for users
    $query = User::where('role_type', 'user');

    // Apply filters if provided
    if ($request->has('account_status') && $request->account_status !== '') {
        $query->where('account_status', (bool)$request->account_status);
    }

    if ($request->has('payin_status') && $request->payin_status !== '') {
        $query->where('payin_status', (bool)$request->payin_status);
    }

    if ($request->has('payout_status') && $request->payout_status !== '') {
        $query->where('payout_status', (bool)$request->payout_status);
    }

    // Fetch users
    $merchants = $query->orderBy('id', 'desc')->get();

    // Fetch total charges grouped by user_id and product
    $charges = DB::table('reports')
        ->select('user_id', 'product', DB::raw('SUM(charge) as total_charge'), DB::raw('SUM(amount) as total_amount'))
        ->whereIn('product', ['UPI', 'payout', 'CRYPTO'])
        ->where('status', 'success')
        ->groupBy('user_id', 'product')
        ->get();

    // Map charges to each merchant
    $merchants = $merchants->map(function($merchant) use ($charges) {
        $userCharges = $charges->where('user_id', $merchant->id);

        $upiCharge = $userCharges->where('product', 'UPI')->first()->total_charge ?? 0;
        $payoutCharge = $userCharges->where('product', 'payout')->first()->total_charge ?? 0;
        $cryptoCharge = $userCharges->where('product', 'CRYPTO')->first()->total_charge ?? 0;

        $upiAmount = $userCharges->where('product', 'UPI')->first()->total_amount ?? 0;
        $payoutAmount = $userCharges->where('product', 'payout')->first()->total_amount ?? 0;
        $cryptoAmount = $userCharges->where('product', 'CRYPTO')->first()->total_amount ?? 0;

        $merchant->total_charge = [
            'UPI'    => $upiCharge,
            'payout' => $payoutCharge,
            'CRYPTO' => $cryptoCharge,
        ];

        $merchant->total_amount = [
            'UPI'    => $upiAmount,
            'payout' => $payoutAmount,
            'CRYPTO' => $cryptoAmount,
        ];

        // Add total payout (sum of all charges or amounts as needed)
        $merchant->total_payout = $upiAmount + $payoutAmount + $cryptoAmount; // or sum amounts if needed: $upiAmount + $payoutAmount + $cryptoAmount

        return $merchant;
    });

    return response()->json([
        'status' => true,
        'message' => 'Merchant list with charges fetched successfully',
        'data' => $merchants,
    ]);
}

    
//     public function onboardMerchant(Request $request)
//     {
//     try {
//         $validatedData = $request->validate([
//             'scheme_id'              => 'nullable|integer',
//             'name'                   => 'required|string|max:255',
//             'credentials_id'         => 'nullable|integer',
//             'email'                  => 'required|email|unique:users,email',
//             'mobile_no'              => 'required|string|max:15|unique:users,mobile_no',
//             'password'               => 'nullable|string|min:6',
//             'business_mcc'           => 'nullable|string|max:50',
//             'company_type'           => 'nullable|in:proprietary,partnership,private,public,llp,society,trust,govt,huf,boi,aop,ajp',
//             'company_pan_no'         => 'nullable|string|max:20',
//             'company_pan_no_doc'     => 'nullable|string|max:2048', // validate file
//             'company_gst_no'         => 'nullable|string|max:20',
//             'company_gst_no_doc'     => 'nullable|string|max:255',
//             'cin_llpin'              => 'nullable|string|max:50',
//             'date_of_incorporation'  => 'nullable|date',
//             'account_holder_name'    => 'nullable|string|max:255',
//             'bank_account_no'        => 'nullable|string|max:50',
//             'ifsc_code'              => 'nullable|string|max:20',
//             'cancel_cheque_doc'      => 'nullable|string|max:255',
//             'address'                => 'nullable|string|max:255',
//             'city'                   => 'nullable|string|max:100',
//             'district'               => 'nullable|string|max:100',
//             'state'                  => 'nullable|string|max:100',
//             'pin_code'               => 'nullable|string|max:10',
//             'director_info'          => 'nullable|array',
//             'payin_at_onboard'       => 'nullable|in:pay_u,bulk_pe,Airpay',
//             'payout_at_onboard'      => 'nullable|in:pay_u,bulk_pe',
//             'website_url'            => 'nullable|url|max:255',
//             'account_status'         => 'nullable|boolean',
//             'role_type'              => 'nullable|in:admin,user',
//             'payin_callback'         => 'nullable|url|max:255',
//             'payout_callback'        => 'nullable|url|max:255',
//             'payin_status'           => 'nullable|boolean',
//             'payout_status'          => 'nullable|boolean',
//             'mid'                    => 'nullable|string|max:100',
//             'key'                    => 'nullable|string|max:255',
//             'option_1'               => 'nullable|string|max:255',
//             'option_2'               => 'nullable|string|max:255',
//             'payin_wallet'           => 'nullable|string|max:255',
//             'payout_wallet'          => 'nullable|string|max:255',
//             'total_charges'          => 'nullable|string|max:50',
//             'rolling_amount'         => 'nullable|string|max:50',
//             'remark'                 => 'nullable|string|max:500',
//             'qr_code'                => 'nullable|string|max:255',
//             'description'            => 'nullable|string|max:500',
//             'kyc'                    => 'nullable|boolean',
//             'email_verified_at'      => 'nullable|date',
//         ]);

//         // If password is not provided, use mobile_no as password
//         if (empty($validatedData['password'])) {
//             $validatedData['password'] = bcrypt($validatedData['mobile_no']);
//         } else {
//             $validatedData['password'] = bcrypt($validatedData['password']);
//         }
        
//         // if ($request->hasFile('company_pan_no_doc')) {
//         //     $path = $request->file('company_pan_no_doc')->store('company_docs', 'public');
//         //     $fullPath = asset('storage/' . $path);
//         //     $validatedData['company_pan_no_doc'] = $fullPath;
//         // }
//   if($request->payin_at_onboard === 'Airpay'){
//       if(empty($request->credentials_id)){
//           return response()->json([
//               'error_code'=> 422,
//               'errors'=> [
//                 'credentials_id' =>['credentials id required for airpay']
//                 ]
//               ],422);
//       }
//       $validatedData['credentials_id'] = $request->credentials_id;
      
//   }else{
//       $validatedData['credentials_id'] = null;
      
//   }
//         $merchant = User::create($validatedData);

//         return response()->json([
//             'message'  => 'Merchant onboarded successfully',
//             'merchant' => $merchant,
//         ], 201);

//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return response()->json([
//             'error_code' => 422,
//             'message'    => 'Validation failed',
//             'errors'     => $e->errors()
//         ], 422);
//     }
// }

public function onboardMerchant(Request $request)
{
    try {
        // Validate main merchant fields
        $validatedData = $request->validate([
            'scheme_id'             => 'nullable|integer',
            'name'                  => 'required|string|max:255',
            'credentials_id'        => 'nullable|integer',
            'email'                 => 'required|email|unique:users,email',
            'mobile_no'             => 'required|string|max:15|unique:users,mobile_no',
            'password'              => 'nullable|string|min:6',
            'business_mcc'          => 'nullable|string|max:50',
            'company_type'          => 'nullable|string',
            'company_pan_no'        => 'nullable|string|max:20',
            'company_pan_no_doc'    => 'nullable|file',
            'company_gst_no_doc'    => 'nullable|file',
            'cancel_cheque_doc'     => 'nullable|file',
            'company_gst_no'        => 'nullable|string|max:20',
            'cin_llpin'             => 'nullable|string|max:50',
            'date_of_incorporation' => 'nullable|date',
            'account_holder_name'   => 'nullable|string|max:255',
            'bank_account_no'       => 'nullable|string|max:50',
            'ifsc_code'             => 'nullable|string|max:20',
            'address'               => 'nullable|string|max:255',
            'city'                  => 'nullable|string|max:100',
            'district'              => 'nullable|string|max:100',
            'state'                 => 'nullable|string|max:100',
            'pin_code'              => 'nullable|string|max:10',
            'payin_at_onboard'      => 'nullable|string',
            'payout_at_onboard'     => 'nullable|string',
            'website_url'           => 'nullable|url|max:255',
            'director_info'         => 'nullable|array',
            'director_info.*.director_name'     => 'required_with:director_info|string|max:255',
            'director_info.*.director_pan_no'   => 'required_with:director_info|string|max:20',
            'director_info.*.director_aadhar_no'=> 'required_with:director_info|string|max:20',
            'director_info.*.director_gender'   => 'required_with:director_info|string|in:male,female,other',
            'director_info.*.director_dob'      => 'required_with:director_info|date',
            'director_info.*.user_pan_doc'      => 'nullable|file',
            'director_info.*.user_addhar_doc'   => 'nullable|file',
        ]);

        // Encrypt password if not provided
        $validatedData['password'] = bcrypt($validatedData['password'] ?? $validatedData['mobile_no']);

        // Handle main merchant file uploads
        $fileFields = [
            'company_pan_no_doc' => 'company_pan_docs',
            'company_gst_no_doc' => 'company_gst_docs',
            'cancel_cheque_doc'  => 'cancel_cheque_docs',
        ];

        foreach ($fileFields as $field => $folder) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store($folder, 'public');
                $validatedData[$field] = storage_path('app/public/' . $path);
            } else {
                $validatedData[$field] = null;
            }
        }

        // Handle director files
        $directors = [];
        if (!empty($validatedData['director_info'])) {
            foreach ($validatedData['director_info'] as $index => $director) {
                // For each director, store files if uploaded
                if ($request->hasFile("director_info.$index.user_pan_doc")) {
                    $director['user_pan_doc'] = storage_path(
                        'app/public/' . $request->file("director_info.$index.user_pan_doc")->store('director_pan_docs', 'public')
                    );
                }

                if ($request->hasFile("director_info.$index.user_addhar_doc")) {
                    $director['user_addhar_doc'] = storage_path(
                        'app/public/' . $request->file("director_info.$index.user_addhar_doc")->store('director_aadhar_docs', 'public')
                    );
                }

                $directors[] = $director;
            }
        }

        $validatedData['director_info'] = $directors;

        // Create merchant
        $merchant = User::create($validatedData);

        return response()->json([
            'message'  => 'Merchant onboarded successfully',
            'merchant' => $merchant,
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error_code' => 422,
            'message'    => 'Validation failed',
            'errors'     => $e->errors()
        ], 422);
    }
}




    
   public function showMerchant($id = null)
{
    $authUser = auth()->user();

    // ✅ Admins can view any merchant by ID
    if ($authUser->role_type === 'admin') {
        if (!$id) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant ID is required for admin access',
            ], 400);
        }

        $merchant = User::where('role_type', 'user')->find($id);
    }
    // ✅ Crypto users can view only their own crypto data
    // else if ($authUser->role_type === 'crypto') {
    //     $merchant = User::where('role_type', 'crypto')->find($authUser->id);
    // } 
    // ✅ Normal users can view only their own profile
    else {
        $merchant = User::where('role_type', 'user')->find($authUser->id);
    }

    if (!$merchant) {
        return response()->json([
            'status' => false,
            'message' => 'Merchant not found or unauthorized access',
        ], 404);
    }

    return response()->json([
        'status' => true,
        'message' => 'Merchant details fetched successfully',
        'data' => $merchant,
    ]);
}

public function updateMerchant(Request $request, $id = null)
{
    try {
        $authUser = auth()->user();

        // ADMIN CAN UPDATE ANY MERCHANT
        if ($authUser->role_type === 'admin') {
            if (!$id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant ID is required for admin access',
                ], 400);
            }

            $merchant = User::where('role_type', 'user')->find($id);
        } else {
            // NORMAL USER CAN ONLY UPDATE THEMSELVES
            $merchant = User::where('role_type', 'user')->find($authUser->id);
        }

        if (!$merchant) {
            return response()->json([
                'status' => false,
                'message' => 'Merchant not found or unauthorized access',
            ], 404);
        }

        // -----------------------------------------
        // VALIDATION (INCLUDES EXISTING DOC PATHS)
        // -----------------------------------------
        $validatedData = $request->validate([
            'scheme_id'              => 'nullable|integer',
            'name'                   => 'nullable|string|max:255',
            'credentials_id'         => 'nullable|integer|exists:credentials,id',
            'email'                  => 'nullable|email|unique:users,email,' . ($id ?? $merchant->id),
            'mobile_no'              => 'nullable|string|max:15|unique:users,mobile_no,' . ($id ?? $merchant->id),
            'password'               => 'nullable|string|min:6',
            'business_mcc'           => 'nullable|string|max:50',
            'company_type'           => 'nullable|string|max:100',
            'company_pan_no'         => 'nullable|string|max:20',
            'company_gst_no'         => 'nullable|string|max:20',
            'cin_llpin'              => 'nullable|string|max:50',
            'date_of_incorporation'  => 'nullable|date',

            'account_holder_name'    => 'nullable|string|max:255',
            'bank_account_no'        => 'nullable|string|max:50',
            'ifsc_code'              => 'nullable|string|max:20',

            'address'                => 'nullable|string|max:255',
            'city'                   => 'nullable|string|max:100',
            'district'               => 'nullable|string|max:100',
            'state'                  => 'nullable|string|max:100',
            'pin_code'               => 'nullable|string|max:10',

            // FILE VALIDATION
            'company_pan_no_doc'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'company_gst_no_doc'     => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'cancel_cheque_doc'      => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',

            // DIRECTOR INFO
            'director_info'                               => 'nullable|array',
            'director_info.*.director_name'               => 'nullable|string|max:255',
            'director_info.*.director_pan_no'             => 'nullable|string|max:20',
            'director_info.*.director_aadhar_no'          => 'nullable|string|max:20',
            'director_info.*.director_gender'             => 'nullable|string|in:male,female,other',
            'director_info.*.director_dob'                => 'nullable|date',

            // NEW FILES
            'director_info.*.user_pan_doc'                => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
            'director_info.*.user_addhar_doc'             => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',

              // Only validate if a new file is uploaded
    'director_info.*.user_pan_doc' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    'director_info.*.user_addhar_doc' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',

            'website_url'            => 'nullable|url|max:255',
            'description'            => 'nullable|string|max:500',
        ]);

        // -----------------------------------------
        // UPDATE BASIC FIELDS
        // -----------------------------------------
        $merchant->fill($validatedData);

        // -----------------------------------------
        // HANDLE COMPANY FILES
        // -----------------------------------------
        foreach (['company_pan_no_doc', 'company_gst_no_doc', 'cancel_cheque_doc'] as $fileKey) {
            if ($request->hasFile($fileKey)) {
                $path = $request->file($fileKey)->store('merchant_docs', 'public');
                $merchant->{$fileKey} = $path;
            }
        }

        // -----------------------------------------
        // HANDLE DIRECTORS
        // -----------------------------------------
if ($request->has('director_info')) {
    $directors = $request->input('director_info');

    foreach ($directors as $i => $dir) {
        // Load existing DB values
        $existingDirector = $merchant->director_info[$i] ?? [];

        // Only update PAN if new file uploaded
        if ($request->hasFile("director_info.$i.user_pan_doc")) {
            $dir["user_pan_doc"] = $request->file("director_info.$i.user_pan_doc")->store('director_docs', 'public');
        } else {
            $dir["user_pan_doc"] = $existingDirector["user_pan_doc"] ?? null;
        }

        // Only update Aadhaar if new file uploaded
        if ($request->hasFile("director_info.$i.user_addhar_doc")) {
            $dir["user_addhar_doc"] = $request->file("director_info.$i.user_addhar_doc")->store('director_docs', 'public');
        } else {
            $dir["user_addhar_doc"] = $existingDirector["user_addhar_doc"] ?? null;
        }

        // Merge other director fields
        foreach (['director_name','director_pan_no','director_aadhar_no','director_gender','director_dob'] as $field) {
            $dir[$field] = $dir[$field] ?? ($existingDirector[$field] ?? null);
        }

        $directors[$i] = $dir;
    }

    $merchant->director_info = $directors;
}





        $merchant->save();

        return response()->json([
            'status'  => true,
            'message' => 'Merchant updated successfully',
            'data'    => $merchant,
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'status'  => false,
            'message' => 'Validation failed',
            'errors'  => $e->errors(),
        ], 422);

    } catch (\Exception $e) {
        \Log::error("Error updating merchant ID {$id}: " . $e->getMessage());

        return response()->json([
            'status'  => false,
            'message' => 'Something went wrong while updating merchant',
            'error'   => $e->getMessage(),
        ], 500);
    }
}


    
    public function deleteMerchant($id)
    {
        try {
            // Find merchant with role_type = 'user'
            $merchant = User::where('role_type', 'user')->find($id);
    
            // Handle if not found
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not found',
                ], 404);
            }
    
            // Delete merchant
            $merchant->delete();
    
            return response()->json([
                'status' => true,
                'message' => 'Merchant deleted successfully',
            ], 200);
    
        } catch (\Exception $e) {
            // Catch any unexpected errors
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
}

    public function updateUserStatuses(Request $request)
    {
        // 1️⃣ Validate request data
        $validated = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'account_status' => 'nullable|boolean',
            'payin_status'   => 'nullable|boolean',
            'payout_status'  => 'nullable|boolean',
        ], [
            'user_id.required' => 'User ID is required.',
            'user_id.exists'   => 'Invalid user ID.',
        ]);

        // 2️⃣ Find user
        $user = User::find($validated['user_id']);

        // 3️⃣ Update statuses (only provided fields)
        if ($request->has('account_status')) {
            $user->account_status = $validated['account_status'];
        }

        if ($request->has('payin_status')) {
            $user->payin_status = $validated['payin_status'];
        }

        if ($request->has('payout_status')) {
            $user->payout_status = $validated['payout_status'];
        }

        $user->save();

        // 4️⃣ Return JSON response
        return response()->json([
            'message' => 'User statuses updated successfully.',
            'data'    => [
                'user_id'        => $user->id,
                'account_status' => $user->account_status,
                'payin_status'   => $user->payin_status,
                'payout_status'  => $user->payout_status,
            ]
        ]);
    }
    
    public function managePayinWallet(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'user_id'       => 'required|exists:users,id',
                'payin_wallet'  => 'required|numeric|min:0',
                'remark'        => 'nullable|string',
            ]);
    
            DB::beginTransaction();
    
            // Fetch user
            $user = User::lockForUpdate()->findOrFail($validatedData['user_id']);
    
            $currentBalance = (float) $user->payin_wallet;
            $deductAmount   = (float) $validatedData['payin_wallet'];
    
            // Check if user has enough balance
            if ($deductAmount > $currentBalance) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Insufficient wallet balance.',
                    'current_balance' => $currentBalance,
                ], 400);
            }
    
            // Deduct amount
            $newBalance = $currentBalance - $deductAmount;
            
            // Update user wallet
            $user->update([
                'payin_wallet' => $this->service->numberFormat($newBalance),
            ]);
    
            // Generate unique reference number
            $refno = 'FT' . now()->format('YmdHis') . rand(11111111, 99999999);
            $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);
            
            // Prepare report data
            $data = [
                'txnid'             => $orderId,
                'mobile'            => $user->mobile_no,
                'amount'            => $deductAmount,
                'user_id'           => $user->id,
                'ref_no'            => $refno,
                'payin_amount'      => $newBalance,
                'transaction_type'   => 'debit',
                'status'            => 'success',
                'remark'            => $validatedData['remark'] ?? null,
                'description'       => 'Wallet deducted: ' . $deductAmount . ' INR',
                'product'           => 'payin_settlement',
                'payment_platform'  => 'api',
                'payer_name'        => 'Admin',
            ];
    
            // Create report record
            Report::create($data);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Fund transferred successfully.',
                'user_id' => $user->id,
                'deducted' => $deductAmount,
                'new_balance' => $user->payin_wallet,
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // validation failed
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error managing payin wallet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json([
                'message' => 'An unexpected error occurred while processing the request.',
                'error' => $e->getMessage(), // you can hide this in production
            ], 500);
        }
    }
    
    public function managePayoutWallet(Request $request){
        
        try {
            $validatedData = $request->validate([
                'user_id'       => 'required|exists:users,id',
                'payout_wallet'  => 'required|numeric|min:0',
                'remark'        => 'nullable|string',
            ]);
    
            DB::beginTransaction();
    
            // Fetch user
            $user = User::lockForUpdate()->findOrFail($validatedData['user_id']);
    
            $currentBalance = (float) $user->payout_wallet;
            $addedAmount   = (float) $validatedData['payout_wallet'];
    
            // Deduct amount
            $newBalance = $currentBalance + $addedAmount;
            
            // Update user wallet
            $user->update([
                'payout_wallet' => $this->service->numberFormat($newBalance),
            ]);
    
            // Generate unique reference number
            $refno = 'LW' . now()->format('YmdHis') . rand(11111111, 99999999);
            $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);
            
            // Prepare report data
            $data = [
                'txnid'             => $orderId,
                'mobile'            => $user->mobile_no,
                'amount'            => $addedAmount,
                'user_id'           => $user->id,
                'ref_no'            => $refno,
                'payout_amount'     => $newBalance,
                'transaction_type'  => 'credit',
                'status'            => 'success',
                'remark'            => $validatedData['remark'] ?? null,
                'description'       => 'Wallet top up with: ' . $addedAmount . ' INR',
                'product'           => 'topup_payout',
                'payment_platform'  => 'api',
                'payer_name'        => 'Admin',
            ];
    
            // Create report record
            Report::create($data);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Fund transferred successfully.',
                'user_id' => $user->id,
                'added' => $addedAmount,
                'new_balance' => $user->payout_wallet,
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // validation failed
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
    
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error managing payout wallet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
    
            return response()->json([
                'message' => 'An unexpected error occurred while processing the request.',
                'error' => $e->getMessage(), // you can hide this in production
            ], 500);
        }

    }
    
    public function takeBackFromPayoutWallet(Request $request) {
        try {
            $validatedData = $request->validate([
                'user_id'       => 'required|exists:users,id',
                'payout_wallet' => 'required|numeric|min:0',
                'remark'        => 'nullable|string',
            ]);
    
            DB::beginTransaction();
    
            // Fetch user
            $user = User::lockForUpdate()->findOrFail($validatedData['user_id']);
    
            $currentBalance = (float) $user->payout_wallet;
            $deductedAmount = (float) $validatedData['payout_wallet'];
    
            // Deduct amount
            $newBalance = $currentBalance - $deductedAmount;
    
            // Update user wallet
            $user->update([
                'payout_wallet' => $this->service->numberFormat($newBalance),
            ]);
    
            // Generate unique reference number
            $refno = 'LW' . now()->format('YmdHis') . rand(11111111, 99999999);
            $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);
            // Prepare report data
            $data = [
                'txnid'             => $orderId,
                'mobile'            => $user->mobile_no,
                'amount'            => $deductedAmount,
                'user_id'           => $user->id,
                'ref_no'            => $refno,
                'payout_amount'     => $newBalance,
                'transaction_type'  => 'debit',
                'status'            => 'success',
                'remark'            => $validatedData['remark'] ?? null,
                'description'       => 'Deducted amount from wallet because of error: ' . $deductedAmount . ' INR',
                'product'           => 'take_back_from_wallet',
                'payment_platform'  => 'api',
                'payer_name'        => 'Admin',
            ];
    
            // Create report record
            Report::create($data);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Fund taken back successfully.',
                'user_id' => $user->id,
                'deducted' => $deductedAmount,
                'new_balance' => $user->payout_wallet,
            ]);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // validation failed
            return response()->json([
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
    
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Error managing payout wallet', [
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString(),
            // ]);
    
            return response()->json([
                'message' => 'An unexpected error occurred while processing the request.',
                'error' => $e->getMessage(), // you can hide this in production
            ], 500);
        }
    }
    
    public function payinPayoutStatuses(Request $request)
    {
        // 1️⃣ Validate incoming data
        $validated = $request->validate([
            'payin_status'  => 'nullable|in:0,1',
            'payout_status' => 'nullable|in:0,1',
        ]);
    
        // 2️⃣ Build the update data array dynamically
        $updateData = [];
    
        if ($request->has('payin_status')) {
            $updateData['payin_status'] = $validated['payin_status'];
        }
    
        if ($request->has('payout_status')) {
            $updateData['payout_status'] = $validated['payout_status'];
        }
    
        // 3️⃣ If no valid fields were provided, return an error
        if (empty($updateData)) {
            return response()->json([
                'message' => 'No status field (payin_status or payout_status) provided.',
            ], 400);
        }
    
        // 4️⃣ Prepare the query
        $query = User::query()->where('role_type', 'user');
    
        // If trying to set payin_status = 1, only update users whose account_status != 0
        if (isset($updateData['payin_status']) && $updateData['payin_status'] == 1) {
            $query->where('account_status', '!=', 0);
        }
    
        // If trying to set payout_status = 1, only update users whose account_status != 0
        if (isset($updateData['payout_status']) && $updateData['payout_status'] == 1) {
            $query->where('account_status', '!=', 0);
        }
    
        // 5️⃣ Update eligible users
        $query->update($updateData);
    
        // 6️⃣ Return response
        return response()->json([
            'message' => 'Statuses updated successfully for eligible users.',
            'updated_fields' => $updateData
        ], 200);
}

    public function showCredentials(Request $request){
        $data= credential::all();
        return response()->json([
            'status' => 'success',
            'message' => "credentials fetched",
            'data' => $data]);
        // dd($data);
    }
    
public function updateMerchantCredential(Request $request)
{
    // dd("hello");
    $request->validate([
        'id' => 'required|integer|exists:users,id',
        'credentials_id' => 'required|integer|exists:credentials,id',
    ]);

    $merchant = User::find($request->id);

    $merchant->credentials_id = $request->credentials_id;
    $merchant->save();

    return response()->json([
        'status' => 'success',
        'message' => 'Credential updated successfully',
        'data' => $merchant
    ]);
}

    public function addCredential(Request $request){
        try{
              $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|array',
              ]);
             $validated['description'] = json_encode($validated['description']);
        
            // dd($validated);
            // Store data directly (raw text)
            $cred = Credential::create($validated);
            
            return response()->json([
                'message' => "credential added",
                'data' => $cred
            ]);
        }catch(\Exception $e){
            return response()->json([
                'message' =>"something went wrong",
                'error' => $e->getMessage()
                ],500);
            
        }
          
                
    }
    
    public function deleteCredential($id){
        try{
            $deleteCred = Credential::findOrFail($id);
            $deleteCred->delete();
            return response()->json([
                'status' => 'true',
                'message' => "creddential deleted successfully"
                ],200);
            
        }catch( \Exception $e){
            return response()->json([
                'message' =>"error to delete",
                'error' => $e->getMessage()
                ],500);
        }


        
    }

}





