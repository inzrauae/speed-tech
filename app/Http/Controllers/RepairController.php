<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Repair services: overview page and one detail page per service.
 * Content lives in config/speedtech.php (repair_services).
 */
class RepairController extends Controller
{
    public function index(): View
    {
        return view('repairs', ['services' => config('speedtech.repair_services')]);
    }

    public function show(string $service): View
    {
        $services = collect(config('speedtech.repair_services'));
        $current = $services->firstWhere('slug', $service);
        abort_unless($current, 404);

        return view('repair-service', [
            'service' => $current,
            'others' => $services->where('slug', '!=', $service)->values(),
        ]);
    }
}
