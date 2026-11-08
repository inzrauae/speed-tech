<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Product;
use App\Notifications\ShopNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Repair bookings, spare-part requests and product price enquiries.
 */
class EnquiryController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Enquiry::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s\-]{9,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'device' => ['required_if:type,repair', 'nullable', 'string', 'max:160'],
            'service' => ['nullable', Rule::in(config('speedtech.services'))],
            'product_id' => ['nullable', 'integer'],
            'message' => ['required_if:type,repair,general', 'nullable', 'string', 'max:2000'],
            'details' => ['nullable', 'array'],
            'details.brand' => ['nullable', 'string', 'max:100'],
            'details.model' => ['nullable', 'string', 'max:150'],
            'details.generation' => ['nullable', 'string', 'max:100'],
            'details.part' => ['nullable', 'string', 'max:100'],
            'details.part_number' => ['nullable', 'string', 'max:100'],
            'website' => ['prohibited'], // honeypot
        ], [
            'phone.regex' => 'Please enter a valid phone number, e.g. 077 123 4567.',
            'device.required_if' => 'Please tell us your device or model.',
            'message.required_if' => 'Please tell us how we can help.',
        ]);

        $product = isset($data['product_id']) ? Product::active()->find($data['product_id']) : null;

        $enquiry = Enquiry::create([
            'reference' => Enquiry::generateReference($data['type']),
            'type' => $data['type'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'device' => $data['device'] ?? null,
            'service' => $data['type'] === 'repair' ? ($data['service'] ?? null) : null,
            'product_id' => $product?->id,
            'details' => array_filter($data['details'] ?? []) ?: null,
            'message' => $data['message'] ?? null,
            'ip_address' => $request->ip(),
        ]);
        $enquiry->setRelation('product', $product);

        ShopNotifier::enquiryReceived($enquiry);

        $message = "Thank you, {$enquiry->name}. Your reference is {$enquiry->reference}. Our team will contact you shortly.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'reference' => $enquiry->reference,
                'message' => $message,
                'whatsapp' => whatsapp_url($enquiry->whatsappMessage()),
            ], 201);
        }

        return back()->with('status', $message)->with('whatsapp', whatsapp_url($enquiry->whatsappMessage()));
    }
}
