<?php

namespace App\Http\Controllers\API;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use App\Models\User;
use App\Models\Partner;
use Illuminate\Support\Facades\Storage;
use App\Models\PartnerUser;
use App\Models\PartnerServices;
use App\Models\UserProfile;
use App\Models\PartnerProfile;
use App\Models\PartnerDocuments;
use App\Models\PartnerCoverageZones;
use App\Services\AWSCognitoService;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

class BusinesRegistrationController extends BaseController
{
    protected $cognitoService;

    public function __construct(AWSCognitoService $cognitoService)
    {
        $this->cognitoService = $cognitoService;
    }
    public function register(Request $request)
    {
        $data = $request->all();



        // Debugging: Remove or comment out in production
        // Password confirmation check
        if (($data['password'] ?? '') !== ($data['confirmPassword'] ?? '')) {
            return $this->sendError('Validation Error', ['message' => 'Password and confirm password do not match.'], 422);
        }

        try {
            // Register user with Cognito service
            $cognitoResponse = $this->cognitoService->registerUser(
                $data['email'],
                $data['password'],
                $data['businessName'] ?? 'Business User',
                "partner"
            );
            \Log::info('cong found:', $cognitoResponse);

            if (in_array($cognitoResponse['status'], [205, 209])) {
                return $this->sendResponse([], $cognitoResponse['message']);
            }

            $cognitoSub = $cognitoResponse['userDetails']['userDetails']['cognitoId'] ?? null;
            if (!$cognitoSub) {
                return $this->sendError('Registration Failed', ['message' => 'Cognito sub ID missing.'], 500);
            }

            // Create user record
            $user = new User();
            $user->uuid = (string) \Illuminate\Support\Str::uuid();
            $user->email = $data['email'];
            $user->cognito_sub = $cognitoSub;
            $user->user_type = 'partner';
            $user->status = 'active';
            $user->save();

            // Create partner record
            $partner = new Partner();
            $partner->uuid = (string) \Illuminate\Support\Str::uuid();
            $partner->business_name = $data['businessName'] ?? null;
            $partner->abn = $data['abn'] ?? null;
            $partner->email = $data['email'];
            $partner->phone = $data['phone'] ?? null;
            $partner->website_url = $data['website'] ?? null;
            $partner->status = 'active';
            $partner->save();

            // Link partner to user
            $partnerUser = new PartnerUser();
            $partnerUser->user_id = $user->id;
            $partnerUser->partner_id = $partner->id;
            $partnerUser->role = 'owner';
            $partnerUser->is_primary_contact = true;
            $partnerUser->save();


            // Create user profile
            $userProfile = new UserProfile();
            $userProfile->user_id = $user->id;
            $userProfile->phone_number = $data['phone'] ?? null;
            $userProfile->save();

            // Handle multiple insurance files upload (from selectedFiles array)
            if ($request->hasFile('selectedFiles')) {
                foreach ($request->file('selectedFiles') as $file) {
                    $filename = \Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('partner-insurance', $filename, 's3');

                    $document = new PartnerDocuments();
                    $document->partner_id = $partner->id;
                    $document->document_type = 'csv';
                    $document->document_name = $file->getClientOriginalName();
                    $document->document_url = env('AWS_URL') . '/' . $path;
                    $document->uploaded_at = now();
                    $document->expiry_date = now()->addYear();
                    $document->save();
                }
            }


            if ($request->hasFile('insurance_certificate')) {
                $insuranceFile = $request->file('insurance_certificate');
                $insuranceFilename = \Str::uuid() . '.' . $insuranceFile->getClientOriginalExtension();
                $insurancePath = $insuranceFile->storeAs('partner-insurance-certificates', $insuranceFilename, 's3');

                $insuranceDocument = new PartnerDocuments();
                $insuranceDocument->partner_id = $partner->id;
                $insuranceDocument->document_type = 'insurance_certificate';
                $insuranceDocument->document_type = 'insurance'; // Add this line
                $insuranceDocument->document_name = $insuranceFile->getClientOriginalName();
                $insuranceDocument->document_url = env('AWS_URL') . '/' . $insurancePath;
                $insuranceDocument->uploaded_at = now();
                $insuranceDocument->expiry_date = now()->addYear(); // Optional
                $insuranceDocument->save();
            }

            // Handle photo upload (assuming first element in photos array)
            $uploadedPhotoUrls = [];
$uploadedLogoUrls = [];  // <-- new array for logos

if ($request->hasFile('photos')) {
    foreach ($request->file('photos') as $photoFile) {
        $photoFilename = \Str::uuid() . '.' . $photoFile->getClientOriginalExtension();
        $photoPath = $photoFile->storeAs('partner-profiles', $photoFilename, 's3');
        $photoUrl = env('AWS_URL') . '/' . $photoPath;

        $photoDocument = new PartnerDocuments();
        $photoDocument->partner_id = $partner->id;
        $photoDocument->document_type = 'profile_photo';
        $photoDocument->document_name = $photoFile->getClientOriginalName();
        $photoDocument->document_url = $photoUrl;
        $photoDocument->uploaded_at = now();
        $photoDocument->save();

        $uploadedPhotoUrls[] = $photoUrl; // Collect photo URL
    }
}

if ($request->hasFile('logo')) {
    $logoFiles = $request->file('logo'); // array of files or a single file

    // Normalize to array if single file
    if (!is_array($logoFiles)) {
        $logoFiles = [$logoFiles];
    }

    foreach ($logoFiles as $logoFile) {
        $logoFilename = \Str::uuid() . '.' . $logoFile->getClientOriginalExtension();
        $logoPath = $logoFile->storeAs('partner-logos', $logoFilename, 's3');
        $logoUrl = env('AWS_URL') . '/' . $logoPath;

        $logoDocument = new PartnerDocuments();
        $logoDocument->partner_id = $partner->id;
        $logoDocument->document_type = 'logo';
        $logoDocument->document_name = $logoFile->getClientOriginalName();
        $logoDocument->document_url = $logoUrl;
        $logoDocument->uploaded_at = now();
        $logoDocument->save();

        $uploadedLogoUrls[] = $logoUrl;  // <-- collect logo URL here
    }
}

// Create partner profile
$partnerProfile = new PartnerProfile();
$partnerProfile->partner_id = $partner->id;
$partnerProfile->business_address = $data['business_address'] ?? null;
$partnerProfile->other_services_text =  $data['other_services'] ?? null;
$partnerProfile->about_us = $data['businessDescription'] ?? null;
$partnerProfile->years_in_business = $data['establishedYear'] ?? null;
$partnerProfile->photos_json = json_encode($uploadedPhotoUrls);
$partnerProfile->logo_url = json_encode($uploadedLogoUrls);  // <-- save all logos as JSON
$partnerProfile->save();




            if (!empty($data['servicetypes']) && is_array($data['servicetypes'])) {
                foreach ($data['servicetypes'] as $serviceType) {
                    $partnerService = new PartnerServices();
                    $partnerService->partner_id = $partner->id;
                    $partnerService->service_type = $serviceType;
                    $partnerService->is_active = 1;
                    $partnerService->save();
                }
            }

            $coverageType = $data['coverage_type'];
            // Save coverage zone info from main data (adjust field names as per your front end)
            if ($coverageType === 'state') {
                $states = $request->input('statesServed'); // Get the array of states

                if (is_array($states)) {
                    foreach ($states as $state) {
                        if (!empty($state)) {
                            $coverageZone = new PartnerCoverageZones();
                            $coverageZone->partner_id = $partner->id;
                            $coverageZone->coverage_type = 'state';
                            $coverageZone->state = $state;
                            $coverageZone->save();
                        }
                    }
                }




            } elseif ($coverageType === 'postcode') {
                // Check and save manual postcode/state/suburb if present
                if (!empty($data['manual_postcode'])) {
                    $coverageZone = new PartnerCoverageZones();
                    $coverageZone->partner_id = $partner->id;
                    $coverageZone->coverage_type = 'postcode';
                    $coverageZone->postcode = $data['manual_postcode'];
                    $coverageZone->save();
                }

                if (!empty($data['manual_state'])) {
                    $coverageZone = new PartnerCoverageZones();
                    $coverageZone->partner_id = $partner->id;
                    $coverageZone->coverage_type = 'state';
                    $coverageZone->state = $data['manual_state'];
                    $coverageZone->save();
                }

                if (!empty($data['manual_suburb'])) {
                    $coverageZone = new PartnerCoverageZones();
                    $coverageZone->partner_id = $partner->id;
                    $coverageZone->coverage_type = 'suburb';
                    $coverageZone->suburb = $data['manual_suburb'];
                    $coverageZone->save();
                }

                // Fallback: if manual fields are not present, save from coverage_values array
            } elseif ($coverageType === 'radius') {

                $centerLat = $data['mapCoords']['lat'] ?? null;
                $centerLng = $data['mapCoords']['lng'] ?? null;

                // Save a single row with radius details
                $coverageZone = new PartnerCoverageZones();
                $coverageZone->partner_id = $partner->id;
                $coverageZone->coverage_type = 'radius';
                $coverageZone->center_lat = $centerLat;
                $coverageZone->center_lng =  $centerLng;
                $coverageZone->radius_km = isset($data['radius']) ? (float)$data['radius'] : null;
                $coverageZone->save();
            } else {
                // Optional: handle unexpected cases or no coverage selected
            }


            // Optionally save service areas from location->serviceAreas array if exists
            // (depends on your database structure, maybe another table)
            // You can loop and save service areas if you have a related table

            // You can also save contact details if needed:
            // For example, save $data['contact']['email'], $data['contact']['phone'] somewhere.

            return $this->sendResponse(['user_id' => $user->id], $cognitoResponse['message']);
            //  return response()->json([
            //     'data' => [
            //         'user_id' => $user->id,
            //         'message' => $cognitoResponse['message']
            //     ]
            // ]);
        } catch (AwsException $e) {
            return $this->sendError('Cognito Registration Failed', ['message' => $e->getAwsErrorMessage()], 500);
        } catch (\Exception $e) {
            return $this->sendError('Registration Failed', ['message' => $e->getMessage()], 500);
        }
    }


public function getUserById(Request $request)
{

   

    $id = $request->user_id;
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not found',
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $user,
    ]);
}



    public function sendResponse($result, $message)
    {
        return response()->json([
            "data" => [
            'success' => true,
            'data' => $result,
            'message' => $message,
            ]
        ], 200);
    }


}
