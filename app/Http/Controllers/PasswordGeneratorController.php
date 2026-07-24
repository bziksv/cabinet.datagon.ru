<?php

namespace App\Http\Controllers;

use App\PasswordsGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class PasswordGeneratorController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:Password generator']);
    }

    public function index(): View
    {
        return view('pages.password', [
            'user' => Auth::user(),
            'passwords' => session('generated_passwords'),
        ]);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     */
    public function createPassword(Request $request): RedirectResponse
    {
        if (PasswordsGenerator::isErrors($request->all())) {
            flash()->overlay(__('Password generator options required'), ' ')->error();
            return Redirect::back();
        }
        if (isset($request->savePassword)) {
            $userPassword = new PasswordsGenerator();
            $userPassword->password = PasswordsGenerator::generatePassword($request->all());
            $userPassword->user_id = Auth::id();
            $userPassword->save();

            return Redirect::route('pages.password');
        }

        $passwords = [];
        for ($i = 0; $i < 5; $i++) {
            $passwords[] = PasswordsGenerator::generatePassword($request->all());
        }

        // PRG: иначе после POST Ctrl+R / Reload просят повтор формы и «не работают».
        return Redirect::route('pages.password')->with('generated_passwords', $passwords);
    }

    public function saveGenerated(Request $request): \Illuminate\Http\JsonResponse
    {
        $password = (string) $request->input('password', '');
        $password = trim($password);

        if ($password === '' || mb_strlen($password) > 100) {
            return response()->json([
                'success' => false,
                'message' => __('Error'),
            ], 422);
        }

        $row = new PasswordsGenerator();
        $row->password = $password;
        $row->user_id = Auth::id();
        $row->comment = '';
        $row->save();

        return response()->json([
            'success' => true,
            'id' => $row->id,
            'password' => $row->password,
            'created_at' => $row->created_at ? $row->created_at->format('d.m.Y H:i') : '—',
            'comment_url' => route('edit.password.comment'),
            'comment_placeholder' => __('Password generator comment placeholder'),
            'comment_success' => __('Comment successfully changed'),
            'comment_error' => __('Error'),
            'copy_msg' => __('Successfully copied'),
            'copy_title' => __('Copy to Clipboard'),
            'remove_title' => __('Remove'),
            'saved_msg' => __('Password generator saved ok'),
        ]);
    }

    public function editComment(Request $request): \Illuminate\Http\JsonResponse
    {
        PasswordsGenerator::where('id', '=', $request->input('id'))
            ->where('user_id', '=', Auth::id())
            ->update(['comment' => $request->input('comment')]);

        return response()->json([
            'success' => true
        ]);
    }

    public function remove(Request $request): \Illuminate\Http\JsonResponse
    {
        PasswordsGenerator::where('id', '=', $request->input('id'))
            ->where('user_id', '=', Auth::id())
            ->delete();

        return response()->json([
            'success' => true
        ]);
    }

}
