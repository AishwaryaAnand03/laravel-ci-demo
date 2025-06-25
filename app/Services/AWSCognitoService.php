<?php

namespace App\Services;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Illuminate\Http\Request;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class AWSCognitoService
{
    protected $client;

    public function __construct()
    {

        $this->region = env('AWS_DEFAULT_REGION');
        $this->accessKeyId = env('AWS_ACCESS_KEY_ID');
        $this->secretAccessKey = env('AWS_SECRET_ACCESS_KEY');
        $this->cognitoClientId = env('AWS_COGNITO_CLIENT_ID');
        $this->userPoolId = env('AWS_COGNITO_USER_POOL_ID');
        $this->cognitoDomain = env('AWS_COGNITO_DOMAIN');
        $this->redirectURI = env('AWS_LOGIN_REDIRECT_URI');


        $this->client = new CognitoIdentityProviderClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKeyId,
                'secret' => $this->secretAccessKey,
            ],
        ]);
    }

    public function userExistsInPool($email)
    {
        try {
            $result = $this->client->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $email,
            ]);
            // User found in the user pool
            return true;
        } catch (\Aws\CognitoIdentityProvider\Exception\UserNotFoundException $ex) {
            // User not found in the user pool
             if ($ex->getAwsErrorCode() === self::RESET_REQUIRED ||
                $ex->getAwsErrorCode() === self::USER_NOT_FOUND) {

                return false;

            }
            return false;
        } catch (\Exception $ex) {
            // Handle other exceptions if needed
            // Log or return false for unexpected errors
            return false;
        }
    }
    
    public function registerUser($email, $password, $name, $is_google_user = "false") {
        try {
            // Check if the user already exists in the user pool
            $userExists = $this->userExistsInPool($email);
    
            if ($userExists) {
                // Get user details including confirmation status
                $userData = $this->client->adminGetUser([
                    'UserPoolId' => $this->userPoolId,
                    'Username' => $email,
                ]);
    
                // Check if the user is confirmed
                if ($userData['UserStatus'] === 'CONFIRMED') {
                    return [
                        'status' => 209,
                        'message' => 'User already signed up. Please login.',
                    ];
                } else {
                    // User exists but is not confirmed, resend OTP
                    $this->client->resendConfirmationCode([
                        'ClientId' => $this->cognitoClientId,
                        'Username' => $email,
                    ]);
    
                    return [
                        'status' => 205,
                        'message' => 'User already signed up. OTP resent for confirmation.',
                    ];
                }
            } else {
                // Proceed with user registration
                $result = $this->client->signUp([
                    'ClientId' => $this->cognitoClientId,
                    'Username' => $email,
                    'Password' => $password,
                    'UserAttributes' => [
                        [
                            'Name' => 'email',
                            'Value' => $email,
                        ],
                        [
                            'Name' => 'given_name', // First name attribute
                            'Value' => $name,
                        ],
                        [
                            'Name'=> 'custom:is_google_user', //To check whether the account is registered via Google sign up
                            'Value' => $is_google_user,
                        ]
                    ],
                ]);
    
                // Get user details after registration using the updated getUserDetails method
                $userDetails = $this->getUserDetails($result['UserSub']);
    
                return [
                    'status' => 200,
                    'message' => 'User registered successfully',
                    'userDetails' => $userDetails,
                    'email'=>$email,
                ];
            }
        } catch (\Aws\CognitoIdentityProvider\Exception\UnrecognizedClientException $ex) {
            return [
                'status' => 500,
                'message' => 'User registration failed. The security token included in the request is invalid.',
                'error' => $ex
            ];
        } catch (\Aws\CognitoIdentityProvider\Exception\UsernameExistsException $ex) {
            // Handle UsernameExistsException if needed
            // Your existing logic for handling this exception goes here
            return [
                'status' => 205,
                'message' => 'An error occurred. Username already exists.',
                'error' => $ex
            ];
        } catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex) {
            // Handle other CognitoIdentityProviderException if needed
            // Your existing logic for handling other exceptions goes here
            return [
                'status' => 206,
                'message' => 'An unexpected error occurred during registration.',
                'error' => $ex
            ];
        } catch (\Exception $ex) {
            // Handle other exceptions if needed
            // Your existing logic for handling other exceptions goes here
            return [
                'status' => 500,
                'message' => 'User registration failed. An unexpected error occurred.',
                'error' => $ex
            ];
        }
    } 

    // To validate the User's Google account

    private function validateGoogleToken($googleAccessToken)
    {
        $httpClient = new \GuzzleHttp\Client();
        try {
            $response = $httpClient->get('https://www.googleapis.com/oauth2/v3/tokeninfo', [
                'query' => [
                    'id_token' => $googleAccessToken,
                ],
            ]);

            $responseData = json_decode($response->getBody(), true);

            if (isset($responseData['email'])) {
                return [
                    'email' => $responseData['email'],
                    'first_name' => $responseData['given_name'],
                    'last_name' => $responseData['family_name']
                ];
            } else {
                return null;
            }
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getUserDetails($userSub)
    {
        try {
                                  
            $result = $this->client->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $userSub,
            ]);

            // Extract the required attributes from the result
            $userDetails = [
                'cognitoId' => $result['Username'], // UserSub is used as Cognito ID
            ];
            // Loop through the retrieved attributes and extract their values
            foreach ($result['UserAttributes'] as $attribute) {
                if ($attribute['Name'] === 'email') {
                    $userDetails['email'] = $attribute['Value'];
                } elseif ($attribute['Name'] === 'given_name') {
                    $userDetails['firstName'] = $attribute['Value']; // Assign given_name to firstName
                } elseif ($attribute['Name'] === 'family_name') {
                    $userDetails['lastName'] = $attribute['Value']; // Assign family_name to lastName
                } elseif ($attribute['Name'] == 'phone_number') {
                     $userDetails['mobile_number'] = $attribute['Value']; //
                    
                } elseif ($attribute['Name'] == 'custom:is_google_user') {
                    $userDetails['is_google_user'] = $attribute['Value']; // Assign is_google_user to is_google_user
                }
            }
            return [
                'status' => 200,
                'message' => 'User details retrieved successfully',
                'userDetails' => $userDetails,
            ];
        } catch (\Aws\CognitoIdentityProvider\Exception\UserNotFoundException $ex) {
            return [
                'status' => 404,
                'message' => 'User not found in the user pool.',
            ];
        } catch (\Aws\CognitoIdentityProvider\Exception\InternalErrorException  $ex) {
            return [
                'status' => 501,
                'message' => 'An unexpected internal error occurred while retrieving user details.',
            ];
        }  catch (\Exception $ex) {
            return [
                'status' => 500,
                'message' => 'An unexpected error occurred while retrieving user details.',
            ];
        }
    }
    
    public function confirmUser($email, $confirmationCode)
    {
        try {
            $result = $this->client->confirmSignUp([
                'ClientId' => $this->cognitoClientId,
                'Username' => $email,
                'ConfirmationCode' => $confirmationCode,
            ]);

            // If the confirmation response doesn't throw an exception, return success
            return true; // Confirmation successful
        } catch (\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $e) {
            // Handling specific CognitoIdentityProviderException for different error types
            $errorBody = $e->getResponse()->getBody()->getContents();
            $errorJson = json_decode($errorBody, true);
            if ($errorJson && isset($errorJson['message'])) {
                return $errorJson['message']; // Return the error message
            } else {
                return 'Unknown error'; // Fallback message if unable to extract error
            }
        } catch (\Exception $e) {
            // Handle other exceptions
            return $e->getMessage();
        }
    }

    // New method to confirm a user by setting a new permanent password
    public function confirmInviteUser($email, $newPassword, $name)
    {
        try {
            // Check if the user already exists in the user pool
            $userExists = $this->userExistsInPool($email);

            if ($userExists) {
                // User already exists, return an appropriate message and status code.
                return [
                    "success" => false,
                    "status"  => 209,
                    "message" => "User already signed up. Please login."
                ];
            } else {
                // Proceed with user registration without email verification
                $params = [
                    'UserPoolId' => $this->userPoolId,
                    'Username' => $email,
                    'TemporaryPassword' => $newPassword,
                    'UserAttributes' => [
                        ['Name' => 'email', 'Value' => $email],
                        ['Name' => 'given_name', 'Value' => $name],
                        ['Name' => 'email_verified', 'Value' => 'true']
                    ],
                    'DesiredDeliveryMediums' => ['EMAIL']
                ];

                // Call Amazon Cognito's AdminCreateUser method
                $result = $this->client->adminCreateUser($params);

                // Set the user's password permanently
                $params = [
                    'UserPoolId' => $this->userPoolId,
                    'Username' => $email,
                    'Password' => $newPassword,
                    'Permanent' => true
                ];
                $this->client->adminSetUserPassword($params);

                // Confirm the user's email address
                $this->confirmUserEmail($email);

                return ['success' => true];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function confirmUserEmail($email)
    {
        try {
            // Change the user status to "CONFIRMED"
            $params = [
                'UserPoolId' => $this->userPoolId,
                'Username' => $email,
                'UserStatus' => 'CONFIRMED'
            ];
            $this->client->adminUpdateUserAttributes($params);
            return true; // Return true if update is successful
        } catch (\Exception $ex) {
            // Handle any exceptions
            return false; // Return false if update fails
        }
    }

    public function loginUser($email, $password, $isSocialLogin = false)
    {
        try
        {
            $result = $this
                ->client
                ->initiateAuth(["AuthFlow" => "USER_PASSWORD_AUTH", "ClientId" => $this->cognitoClientId, "AuthParameters" => ["USERNAME" => $email, "PASSWORD" => $password, ], ]);
            // Extract the necessary tokens from the result
            $accessToken = $result["AuthenticationResult"]["AccessToken"];
            $idToken = $result["AuthenticationResult"]["IdToken"];
            $expiresIn = $result["AuthenticationResult"]["ExpiresIn"]; // Get expiration time
            return ["access_token" => $accessToken, "id_token" => $idToken, "expires_in" => $expiresIn, "status" => 200, "email"=> $email, "status_code" => 200 ];
        }
        catch(\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $e)
        {
            $userExists = $this->userExistsInPool($email);
            if($userExists){
            $result = $this->client->adminGetUser([
                'UserPoolId' => $this->userPoolId,
                'Username' => $email,
            ]);
            $isGoogleUser = "false";
            $isAppleUser = "false";
            $statusCode = $e->getStatusCode();
            if(!is_null($result) && $result["UserStatus"] == "CONFIRMED"){
            foreach ($result['UserAttributes'] as $attribute) {
                if ($attribute['Name'] === 'custom:is_google_user') {
                    $isGoogleUser = $attribute['Value'];
                    break;
                }
            }
            if($isGoogleUser == "true"){
                $statusCode = 500;
                $errorMessage = "This mail ID is associated with the Google login";
            }
            else if($isAppleUser == "true"){
                $statusCode = 500;
                $errorMessage = "This mail ID is associated with the Apple login";
            }else if($isGoogleUser == "false" && $isAppleUser == "false"){
                $statusCode = 500;
                if($isSocialLogin){
                 $errorMessage = "This mail ID is associated with standard login";
                }else{
                 $errorMessage = $e->getAwsErrorMessage();
                }
            }else{
                $errorMessage = $e->getAwsErrorMessage();
            }
            }else{
                $errorMessage = $e->getAwsErrorMessage();
            }
            } else {
                $statusCode = $e->getStatusCode();
                $errorMessage = $e->getAwsErrorMessage();
            }
            return ["status_code" => $statusCode, "message" => $errorMessage, "email" => $email];
        }
        catch(\Exception $e)
        {
            // Handle other exceptions
            return ["status_code" => 500, "message" => $e->getMessage() , ];
        }
    }

    // Login user using the Google account

    public function loginUserWithGoogleOld($googleAccessToken)
    {
        // Validate Google token and retrieve user information
        $googleUserInfo = $this->validateGoogleToken($googleAccessToken);

        if ($googleUserInfo) {
            // Login the user in Cognito using Google information

            $userExists = $this->userExistsInPool($googleUserInfo['email']);
            $first_name = $googleUserInfo['first_name'];
            $last_name = $googleUserInfo['last_name'];
            // $password = "39Rd6W%ZgH@E";
            $name = $first_name." ".$last_name;

            if($userExists){
                return $this->loginUser($googleUserInfo['email'], $password, true);
            }else{
                return $this->registerUser($googleUserInfo['email'], $password, $name, "true", "false");
            }
        } else {
            return [
                'status_code' => 400,
                'message' => 'Failed to validate Google token or retrieve user information.',
            ];
        }
    }

    public function loginUserWithGoogle($code)
    {

        try{
     
            if (!$code) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authorization code not found'], 400);
            }
    
            $clientId = $this->cognitoClientId; // e.g. 2fj4dajk0j0ahaime2kv7td0pj
            $clientSecret = $this->secretAccessKey; // Optional if set
          
            $cognitoDomain = $this->cognitoDomain; // e.g. ap-southeast-2qv3ttelxf.auth.ap-southeast-2.amazoncognito.com

            $redirect_uri = $this->redirectURI;
       
            // Exchange the code for tokens
            $response = Http::asForm()->post("https://{$cognitoDomain}/oauth2/token", [
                'grant_type' => 'authorization_code',
                'client_id' => $clientId,
                'redirect_uri' => $redirect_uri,
                'code' => $code,
                // 'client_secret' => $clientSecret, // include only if needed
            ]);
    
            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to exchange code for tokens',
                    'details' => $response->json()
                ], 500);
            }
    
            $tokens = $response->json();
            $idToken = $tokens['id_token'];
    
            // Decode ID Token
            $region = 'ap-southeast-2';
            $userPoolId = env('AWS_COGNITO_USER_POOL_ID'); // e.g. ap-southeast-2_XXXXXXXXX
            $jwkUrl = "https://cognito-idp.{$region}.amazonaws.com/{$userPoolId}/.well-known/jwks.json";

            
            JWT::$leeway = 60; // Allow a minute of clock skew
    
            $jwk = json_decode(file_get_contents($jwkUrl), true);
            $decoded = JWT::decode($idToken, JWK::parseKeySet($jwk));

            $loginDetailArray = [
                'email' => $decoded->email ?? null,
                'sub' => $decoded->sub ?? null,
                'name' => $decoded->name ?? null,
                'tokens' => $tokens,
            ];
            $request = Request::create('/fake-route', 'POST', $loginDetailArray);
            $userController = app()->make(UserController::class);
       
         
            $login_response =  $userController->loginWithGoogle($request);
            
            return $login_response;
            return response()->json([
                'status' => 200,
                'login_response' => $login_response
            ], 200);

        }
        catch(\Exception $e)
        {
            // Handle other exceptions
            return ["status_code" => 500, "message" => $e->getMessage() , ];
        }

    }

     

    public function resendOTP($email)
    {
        try {
            // Check if the user exists in the user pool
            $userExists = $this->userExistsInPool($email);
    
            if ($userExists) {
                // Get user details including confirmation status
                $userData = $this->client->adminGetUser([
                    'UserPoolId' => $this->userPoolId,
                    'Username' => $email,
                    'UserAttributes' => ['email', 'given_name','family_name'], // Specify the attributes you want to retrieve
                ]);
    
                // Check if the user is confirmed
                if ($userData['UserStatus'] === 'CONFIRMED') {
                    return [
                        'status' => 201,
                        'message' => 'User is already confirmed. Resending OTP not required.',
                    ];
                } else {
                    // User exists but is not confirmed, resend OTP
                    $this->client->resendConfirmationCode([
                        'ClientId' => $this->cognitoClientId,
                        'Username' => $email,
                    ]);
    
                    return [
                        'status' => 200,
                        'message' => 'OTP resent successfully.',
                    ];
                }
            } else {
                // User does not exist in the user pool
                return [
                    'status' => 404,
                    'message' => 'User does not exist in the user pool.',
                ];
            }
        } catch (\Exception $ex) {
            // Handle exceptions if the OTP resend fails or other errors occur
            return [
                'status' => 500,
                'message' => 'Error resending OTP. Please try again later.',
            ];
        }
    }

    public function initiateForgotPassword($email)
    {
        try
        {
            // Check if the user exists in the user pool
            $userExists = $this->userExistsInPool($email);

            $userDetails = $this->getuserDetails($email);
            if (!$userExists)
            {
                return ["status" => "error", "message" => "User not found. Please check the email address or complete the registration process.", ];
            } else if($userDetails['userDetails']['is_google_user'] == "true"){
                return ["status"=> "error", "message"=> "Use your Gmail account to Sign in"];
            }

            // Check if the user is confirmed
            $userConfirmed = $this->isUserConfirmed($email);

            if (!$userConfirmed)
            {
                return ["status" => "error", "message" => "User is not confirmed. Please confirm your account before initiating password reset.", ];
            }

            // Initiate the forgot password flow
            $result = $this
                ->client
                ->forgotPassword(["ClientId" => $this->cognitoClientId, "Username" => $email, ]);

            // If the forgot password request was successful
            return ["status" => "success", "message" => "Password reset code sent successfully.", "codeDeliveryDetails" => $result["CodeDeliveryDetails"], // Information about code delivery (e.g., email)
            ];
        }
        catch(CognitoIdentityProviderException $ex)
        {
            return ["status" => "error", "message" => "An error occurred while initiating password reset. " . $ex->getMessage() , ];
        }
    }

    public function isUserConfirmed($email)
    {
        try
        {
            $result = $this
                ->client
                ->adminGetUser(["UserPoolId" => $this->userPoolId, "Username" => $email, ]);

            $userAttributes = $result["UserAttributes"];

            // Check if the user is confirmed (assuming 'email_verified' attribute exists)
            foreach ($userAttributes as $attribute)
            {
                if (($attribute["Name"] === "email_verified" && $attribute["Value"] === "true") || ($attribute["Name"] === "phone_number_verified" && $attribute["Value"] === "true"))
                {
                    return true;
                }
            }

            return false;
        }
        catch(CognitoIdentityProviderException $ex)
        {
            // Handle the exception (e.g., log, return false, etc.)
            return false;
        }
    }

    public function verifyForgotPasswordCode($email, $verificationCode, $newPassword)
    {
        try {
            // Confirm the user's entered verification code
            $verificationResult = $this->client->confirmForgotPassword([
                'ClientId' => $this->cognitoClientId,
                'Username' => $email,
                'ConfirmationCode' => $verificationCode,
                'Password' => $newPassword, 
            ]);

            // If the verification of the code was successful
            return [
                'status' => 'success',
                'message' => 'Verification code is valid.',
                'verificationResult' => $verificationResult, 
            ];
        } catch (CognitoIdentityProviderException $ex) {
            return [
                'status' => 'error',
                'message' => 'Verification code is invalid or expired. ' . $ex->getMessage(),
            ];
        }
    }

    public function changePassword($email, $oldpassword, $newpassword) {
        try {
            // Assuming $this->client is properly configured for AWS Cognito
    
            // Step 1: Initiate authentication to get the access token
            $authResult = $this->client->initiateAuth([
                'AuthFlow' => 'USER_PASSWORD_AUTH',
                'ClientId' => $this->cognitoClientId,
                'AuthParameters' => [
                    'USERNAME' => $email,
                    'PASSWORD' => $oldpassword,
                ],
            ]);
    
            // Extract the necessary tokens from the result
            $accessToken = $authResult['AuthenticationResult']['AccessToken'];
    
            // Step 2: Change the password using the access token
            $result = $this->client->changePassword([
                'AccessToken' => $accessToken,
                'PreviousPassword' => $oldpassword,
                'ProposedPassword' => $newpassword,
            ]);
    
            return [
                'status' => 'success',
                'message' => 'Password changed successfully',
                'resetpassword' => $result,
            ];
        } catch (CognitoIdentityProviderException $e) {
            return [
                'status' => 'error',
                'message' => 'Password change failed: ' . $e->getMessage(),
            ];
        }
    }
    
    public function resetPassword($email, $password)
    {
        try {
             // Check if the user already exists in the user pool
             $userExists = $this->userExistsInPool($email);

             if ($userExists) {
                // Set the user's password
                $params = [
                    'UserPoolId' => $this->userPoolId,
                    'Username' => $email,
                    'Password' => $password,
                    'Permanent' => true
                ];
                $this->client->adminSetUserPassword($params);
                return ["status" => 200, "message" => "User registered successfully", "userDetails" => null];

            } else {
                return ["status" => 209, "message" => "User is not signed up. Please Sign up."];
            }
        } catch(\Aws\CognitoIdentityProvider\Exception\UnrecognizedClientException $ex) {
            return ["status" => 500, "message" => $ex->getMessage(), "userDetails" => null];
        } catch(\Aws\CognitoIdentityProvider\Exception\UsernameExistsException $ex) {
            return ["status" => 205, "message" => "An error occurred. Username already exists.", "userDetails" => null];
        } catch(\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex) {
            return ["status" => 206, "message" => $ex->getMessage(), "userDetails" => null];
        } catch(\Exception $ex) {
            return ["status" => 500, "message" => $ex->getMessage(), "userDetails" => null];
        }
    }

    public function deleteAccount($email){
        try {

            // Check if the user already exists in the user pool
            $userExists = $this->userExistsInPool($email);

            if ($userExists) {
               // Set the user's password
               $params = [
                   'UserPoolId' => $this->userPoolId,
                   'Username' => $email,
               ];
               $this->client->adminDeleteUser($params);
               return ["status" => 200, "message" => "User deleted successfully", "userDetails" => null];

           } else {
               return ["status" => 209, "message" => "User is not signed up. Please Sign up."];
           }
       } catch(\Aws\CognitoIdentityProvider\Exception\UnrecognizedClientException $ex) {
           return ["status" => 500, "message" => $ex->getMessage(), "userDetails" => null];
       } catch(\Aws\CognitoIdentityProvider\Exception\UsernameExistsException $ex) {
           return ["status" => 205, "message" => "An error occurred. Username already exists.", "userDetails" => null];
       } catch(\Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException $ex) {
           return ["status" => 206, "message" => $ex->getMessage(), "userDetails" => null];
       } catch(\Exception $ex) {
           return ["status" => 500, "message" => $ex->getMessage(), "userDetails" => null];
       }
    }
}
