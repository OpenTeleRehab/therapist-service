<?php

namespace App\Http\Controllers;

use App\Http\Resources\DownloadTrackerResource;
use App\Models\DownloadTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DownloadTrackerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        $userId = Auth::id();

        $query = DownloadTracker::where('author_id', $userId)
            ->orderBy('created_at', 'desc');

        return [
            'success' => true,
            'data' => DownloadTrackerResource::collection($query->get()),
        ];
    }

    /**
    * @param \Illuminate\Http\Request $request
    *
    * @return array
    */
    public function updateProgress(Request $request)
    {
        DownloadTracker::where('job_id', $request->get('job_id'))
            ->update([
                'status' => $request->get('status'),
                'file_path' => $request->get('file_path'),
            ]);

        return [
            'success' => true,
            'message' => 'success_message.download_history_update',
        ];
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return array
     */
    public function destroy()
    {
        $userId = Auth::id();
        DownloadTracker::where('author_id', $userId)
            ->delete();

        return [
            'success' => true,
            'message' => 'success_message.download_history_delete',
        ];
    }
}
