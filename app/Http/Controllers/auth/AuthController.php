<?php
namespace App\Http\Controllers\auth;

use App\Http\Controllers\Controller;
use App\Jobs\PasswordReset;
use App\Jobs\verifyUserJob;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login', 'register','accountVerify','forgotPassword','updatePassword']]);
    }
    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request){
    	$validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (! $token = auth()->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->createNewToken($token);
    }
    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = User::create(array_merge(
                    $validator->validated(),
                    ['password' => bcrypt($request->password),'slug'=>Str::random(15),'token'=>Str::random(20)]
                ));
        if($user){
            $details=['name'=>$user->name, 'email'=>$user->email,'hashEmail'=>Crypt::encryptString($user->email),'token'=>$user->token];
            dispatch(new verifyUserJob($details));
        }
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        auth()->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    public function accountVerify($token,$email) {
        $user=User::where([['email',Crypt::decryptString($email)],['token',$token]])->first();
        if($user->token ==$token){
            $user->update([
                'verify'=>true,
                'token'=>null
            ]);
            return redirect()->to('http://127.0.0.1:8000/verify/success');
        }
        return redirect()->to('http://127.0.0.1:8000/verify/invalid_token');
    }
    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh() {
        return $this->createNewToken(JWTAuth::refresh());
    }
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile() {
        return response()->json(auth()->user());
    }
    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token){
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
    }

    public function forgotPassword(Request $request){
        $user = User::where('email',$request->email)->first();
        if($user){
            $token=Str::random(15);
            $details=['name'=>$user->name,'token'=>$token,'email'=>$user->email,'hashEmail'=>Crypt::encryptString($user->email)];
            if(dispatch(new PasswordReset($details))){
                    DB::table('password_resets')->insert([
                        'email'=>$user->email,
                        'token'=>$token,
                        'created_at'=>now(),
                    ]);
                    return Response()->json(['status'=>true,'message'=>'Password reset link has been sent to your email address']);
            }
        }else{
                return Response()->json(['status'=>false,'message'=>'Incvalid email address']);
        }
    }


    public function updatePassword(Request $request){

        $validator=validator::make($request->all(),[
            'email'=>'required',
            'password'=>'required|string|min:6',
            'token'=>'required'
        ]);
        if($validator->fails()){
            return response()->json($validator->errors(),422);
        }
        $email=Crypt::decryptString($request->email);
        $user=DB::table('password_resets')->where([['email',$email],['token',$request->token]])->first();
        if(!$user){
            return response()->json(['status'=>false,'message'=>'Invalid email address or token']);
        }else{
            $data=User::where('email',$email)->first();
            $data->update([
                'password'=>Hash::make($request->password)
            ]);
            DB::table('password_resets')->where([['email',$email]])->delete();
            return response()->json(['status'=>true,'message'=>'Password Updated']);
        }
    }

}
