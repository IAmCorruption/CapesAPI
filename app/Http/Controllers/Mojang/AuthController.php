<?php

namespace CapesAPI\Http\Controllers\Mojang;

use ActiveCapes;
use Capes;
use CapesAPI\Http\Controllers\Controller;
use CapesAPI\MojangLoginCode;
use Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Validator;

class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if ($request->session()->has('mojangAccessCode')) {
            return redirect()->route('mojang::getUserCP');
        } else {
            return view('mojang.login');
        }
    }

    public function showUserCP(Request $request)
    {
        if (!$request->session()->has('mojangAccessCode')) {
            return redirect()->route('mojang::getLogin');
        }

        $uuid = $request->session()->get('mojangUUID');
        $capes = ActiveCapes::where([
            'uuid'   => $uuid,
            'active' => false,
        ])->paginate();

        return view('mojang.dashboard', ['capes' => $capes]);
    }

    public function createSession(Request $request)
    {
        $rules = [
            'mcAuthCode'    => 'required',
        ];

        $validation = Validator::make($request->all(), $rules);

        if ($validation->fails()) {
            return redirect()->back()->withInput()->withErrors($validation);
        }

        try {
            $codeEntry = MojangLoginCode::where('code', $request->get('mcAuthCode'))->firstOrFail();

            if ($codeEntry->used) {
                return redirect()->back()->withErrors([
                    'mcError' => 'The login code used has already been used.',
                ]);
            }

            // This is currently broken on the MC auth server
            /*if (Carbon::parse($codeEntry->created_at)->diffInMinutes(Carbon::now()) > 10) {
                return redirect()->back()->withErrors([
                    'mcError' => 'The login code used is no longer valid (code expiration).',
                ]);
            }*/

            $request->session()->put('mojangAccessCode', $codeEntry->code);
            $request->session()->put('mojangUsername', $codeEntry->username);
            $request->session()->put('mojangUUID', $codeEntry->uuid);

            $codeEntry->used = true;
            $codeEntry->save();

            return redirect()->route('mojang::getUserCP');
        } catch (ModelNotFoundException $e) {
            return redirect()->back()->withErrors([
                'mcError' => 'The login code used does not exist.',
            ]);
        }

        return redirect()->back();

        /*$rules = [
            'email'    => 'required',
            'password' => 'required',
        ];

        $validation = Validator::make($request->all(), $rules);

        if ($validation->fails()) {
            return redirect()->back()->withInput()->withErrors($validation);
        }

        try {
            $mcAPI = new MinecraftAPI($request->get('email'), $request->get('password'));

            $request->session()->put('mojangAccessToken', $mcAPI->accessToken);
            $request->session()->put('mojangUsername', $mcAPI->username);
            $request->session()->put('mojangUUID', $mcAPI->minecraftID);

            return redirect()->route('mojang::getUserCP');
        } catch (BadLoginException $e) {
            return redirect()->back()->withErrors([
                'mcError' => 'The login credentials were invalid.',
            ]);
        } catch (MigrationException $e) {
            return redirect()->back()->withErrors([
                'mcError' => 'The account you requested has been migrated, please use the correct login.',
            ]);
        } catch (BasicException $e) {
            return redirect()->back()->withErrors([
                'mcError' => 'The login credentials were correct, but the account does not own Minecraft.',
            ]);
        }

        return redirect()->back();*/
    }

    public function destroySession(Request $request)
    {
        $request->session()->flush();

        return redirect()->route('mojang::getLogin');
    }

    public function makeCapeActive(Request $request)
    {
        $uuid = $request->session()->get('mojangUUID');
        $capeId = $request->get('capeId');

        $capes = ActiveCapes::where([
            'uuid'   => $uuid,
            'active' => true,
        ])->get();

        foreach ($capes as $cape) {
            $cape->active = false;
            $cape->save();
        }

        $cape = Capes::find($capeId);

        if ($cape == null) {
            return redirect()->back();
        }

        $newCape = ActiveCapes::where([
            'uuid'      => $uuid,
            'cape_hash' => $cape->hash,
        ])->first();

        if ($newCape != null) {
            $newCape->active = true;
            $newCape->save();
        }

        return redirect()->back();
    }

    public function disableAllCapes(Request $request)
    {
        $capes = ActiveCapes::where([
            'uuid'   => $request->session()->get('mojangUUID'),
            'active' => true,
        ])->get();

        foreach ($capes as $cape) {
            $cape->active = false;
            $cape->save();
        }

        return redirect()->back();
    }
}
