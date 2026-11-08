<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $enquiries = Enquiry::with('product')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('device', 'like', "%{$term}%")))
            ->latest()
            ->paginate(25)->withQueryString();

        return view('admin.enquiries.index', compact('enquiries'));
    }

    public function show(Enquiry $enquiry): View
    {
        return view('admin.enquiries.show', ['enquiry' => $enquiry->load('product')]);
    }

    public function update(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $enquiry->update($request->validate([
            'status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return back()->with('status', 'Enquiry updated.');
    }
}
