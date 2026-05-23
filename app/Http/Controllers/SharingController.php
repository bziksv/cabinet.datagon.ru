<?php

namespace App\Http\Controllers;

use App\ProjectRelevanceHistory;
use App\RelevanceAnalysisConfig;
use App\RelevanceSharing;
use App\RelevancePublicShare;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SharingController extends Controller
{
    /**
     * @return View
     */
    public function index(): View
    {
        $projects = ProjectRelevanceHistory::where('user_id', Auth::id())
            ->with([
                'relevanceTags:id,name,color',
                'sharing.user:id,email,name,last_name',
            ])
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'name']);
        $admin = User::isUserAdmin();

        return view('relevance-analysis.sharing.index', [
            'admin' => $admin,
            'projects' => $projects
        ]);
    }


    /**
     * @param ProjectRelevanceHistory $project
     * @return View
     */
    public function shareProjectConf(ProjectRelevanceHistory $project): View
    {
        if ($project->user_id != Auth::id()) {
            abort(403);
        }

        $access = RelevanceSharing::where('project_id', '=', $project->id)->get();
        $publicShare = RelevancePublicShare::where('project_id', $project->id)->active()->first();

        return view('relevance-analysis.sharing.sharing-config', [
            'project' => $project,
            'access' => $access,
            'publicShare' => $publicShare,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function setAccess(Request $request): JsonResponse
    {
        $authId = Auth::id();
        $user = User::where('email', '=', $request->email)
            ->where('id', '!=', $authId)
            ->first();

        if (!isset($user)) {
            return response()->json([
                'success' => false,
                'message' => "Пользователя с такой почтой не существует",
                'code' => 415
            ]);
        }

        $project = ProjectRelevanceHistory::where('id', '=', $request->project_id)
            ->where('user_id', '=', $authId)->first();

        if (!isset($project)) {
            return response()->json([
                'success' => false,
                'message' => "Проект не существует или пренадлежит не вам",
                'code' => 415
            ]);
        }

        $share = RelevanceSharing::where('user_id', '=', $user->id)
            ->where('project_id', '=', $request->project_id)
            ->where('owner_id', '=', $authId)
            ->first();

        if (isset($share)) {
            return response()->json([
                'success' => false,
                'message' => 'Пользователь уже получил доступ до этого проекта',
                'code' => 415
            ]);
        }

        $share = new  RelevanceSharing();
        $share->project_id = $request->project_id;
        $share->owner_id = $authId;
        $share->user_id = $user->id;
        $share->access = $request->access;
        $share->save();

        return response()->json([
            'success' => true,
            'message' => "$user->email получил доступ до вашего проекта",
            'code' => 201,
            'object' => $share,
            'user' => $user
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function setMultiplyAccess(Request $request): JsonResponse
    {
        $user = User::where('email', '=', $request->email)
            ->where('id', '!=', Auth::id())
            ->first();

        if (!isset($user)) {
            return response()->json([
                'success' => false,
                'message' => "Пользователя с такой почтой не существует",
                'code' => 415
            ]);
        }

        $objects = [];

        foreach ($request->ids as $key => $id) {
            $project = ProjectRelevanceHistory::where('id', '=', $id)
                ->where('user_id', '=', Auth::id())->first();

            if (!isset($project)) {
                return response()->json([
                    'success' => false,
                    'message' => "Проект не существует или пренадлежит не вам",
                    'code' => 415
                ]);
            }

            $project = RelevanceSharing::where('user_id', '=', $user->id)
                ->where('project_id', '=', $id)
                ->where('owner_id', '=', Auth::id())
                ->first();

            if (isset($project)) {
                continue;
            } else {
                $newAccess = new RelevanceSharing();
                $newAccess->user_id = $user->id;
                $newAccess->project_id = $id;
                $newAccess->owner_id = Auth::id();
                $newAccess->access = $request->access;

                $newAccess->save();
                $objects[] = $newAccess;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "$user->email получил доступы до ваших проектов",
            'code' => 201,
            'objects' => $objects,
            'user' => $user
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function changeAccess(Request $request): JsonResponse
    {
        $project = RelevanceSharing::where('id', '=', $request->id)
            ->where('owner_id', '=', Auth::id())
            ->first();

        if (!isset($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Проект не существует или пренадлежит не вам',
                'code' => 415
            ]);
        }

        $project->access = $request->access;
        $project->save();

        return response()->json([
            'success' => true,
            'message' => 'Доступы пользователя изменены',
            'code' => 201
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeAccess(Request $request): JsonResponse
    {
        $project = RelevanceSharing::where('id', '=', $request->id)
            ->where('owner_id', '=', Auth::id())
            ->first();

        if (!isset($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ не существует или был удалён ранее',
                'code' => 415
            ]);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Доступ успешно удалён',
            'code' => 201
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeGuestAccess(Request $request): JsonResponse
    {
        $project = RelevanceSharing::where('id', '=', $request->id)
            ->where('user_id', '=', Auth::id())
            ->first();

        if (!isset($project)) {
            return response()->json([
                'success' => false,
                'message' => 'Доступ не существует или был удалён ранее',
                'code' => 415
            ]);
        }

        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Доступ успешно удалён',
            'code' => 201
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function removeMultiplyAccess(Request $request): JsonResponse
    {
        $user = User::where('email', '=', $request->email)
            ->where('id', '!=', Auth::id())
            ->first();

        if (!isset($user)) {
            return response()->json([
                'success' => false,
                'message' => "Пользователя с такой почтой не существует",
                'code' => 415
            ]);
        }

        $objectsId = [];

        foreach ($request->ids as $id) {
            $sharing = RelevanceSharing::where('project_id', '=', $id)
                ->where('user_id', '=', $user->id)
                ->first();

            if (isset($sharing)) {
                $objectsId[] = $sharing->id;
                $sharing->delete();
            }
        }

        return response()->json([
            'success' => false,
            'message' => "Доступы пользователя убраны",
            'code' => 200,
            'objects' => $objectsId
        ]);
    }

    public function createPublicShare(Request $request): JsonResponse
    {
        $project = ProjectRelevanceHistory::where('id', '=', $request->project_id)
            ->where('user_id', '=', Auth::id())
            ->first();

        if ($project === null) {
            return response()->json([
                'success' => false,
                'message' => __('The project does not exist or does not belong to you'),
                'code' => 415,
            ]);
        }

        $share = RelevancePublicShare::issueForProject($project, Auth::id());

        return response()->json([
            'success' => true,
            'message' => __('Public link created'),
            'code' => 201,
            'url' => $share->publicUrl(),
            'expires_at' => $share->expires_at->format('d.m.Y H:i'),
        ]);
    }

    public function revokePublicShare(Request $request): JsonResponse
    {
        $project = ProjectRelevanceHistory::where('id', '=', $request->project_id)
            ->where('user_id', '=', Auth::id())
            ->first();

        if ($project === null) {
            return response()->json([
                'success' => false,
                'message' => __('The project does not exist or does not belong to you'),
                'code' => 415,
            ]);
        }

        RelevancePublicShare::revokeForProject($project->id, Auth::id());

        return response()->json([
            'success' => true,
            'message' => __('Public link revoked'),
            'code' => 201,
        ]);
    }

    /**
     * @return View
     */
    public function accessProject(): View
    {
        $projects = RelevanceSharing::where('user_id', Auth::id())
            ->with([
                'item:id,name,user_id',
                'owner:id,name,email,last_name',
            ])
            ->orderByDesc('id')
            ->get();
        $admin = User::isUserAdmin();
        $config = RelevanceAnalysisConfig::first();

        return view('relevance-analysis.sharing.access', [
            'projects' => $projects,
            'admin' => $admin,
            'config' => $config
        ]);
    }
}
