<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityLogController extends Controller
{
    const VIEW_PERMISSION = "admin.logs.read";
    /**
     * List activity logs with search.
     *
     * @return Application|Factory|View|Response
     */
    public function index(Request $request)
    {
        $this->checkPermission(self::VIEW_PERMISSION);


        $cronLogs = Storage::disk('logs')->exists('cron.log') ? Storage::disk('logs')->get('cron.log') : null;

        if ($request->input('search')) {
            $searchTerm = $request->input('search');

            $logs = Activity::all()->filter(function ($log) use ($searchTerm) {
                $properties = json_decode($log->properties, true);

                $attributesMatch = isset($properties['attributes']) &&
                    collect($properties['attributes'])->contains(fn($value) => str_contains(strtolower($value), strtolower($searchTerm)));

                $oldMatch = isset($properties['old']) &&
                    collect($properties['old'])->contains(fn($value) => str_contains(strtolower($value), strtolower($searchTerm)));

                return str_contains(strtolower($log->description), strtolower($searchTerm)) ||
                    str_contains(strtolower(optional($log->causer)->name), strtolower($searchTerm)) ||
                    $attributesMatch || $oldMatch;
            });

            $perPage = 20;
            $currentPage = LengthAwarePaginator::resolveCurrentPage();
            $currentItems = $logs->slice(($currentPage - 1) * $perPage, $perPage);
            $query = new LengthAwarePaginator(
                $currentItems,
                $logs->count(),
                $perPage,
                $currentPage,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );
        } else {
            $query = Activity::orderBy('created_at', 'desc')->paginate(20);
        }

        return view('admin.activitylogs.index')->with([
            'logs' => $query,
            'cronlogs' => $cronLogs,
        ]);


    }

    public function create()
    {
        //
    }

    /**
     * Blocked, logs are read-only.
     *
     * @param  Request  $request
     * @return Response
     */
    public function store(Request $request)
    {
        abort(403, __('User does not have the right permissions.'));
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        //
    }

    /**
     * Blocked, logs are read-only.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        abort(403, __('User does not have the right permissions.'));
    }

    public function destroy($id)
    {
        //
    }
}
