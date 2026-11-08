<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Payment methods, bank account details and delivery charges. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', ['settings' => Setting::allValues()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_instructions' => ['nullable', 'string', 'max:500'],
            'cod_max_order' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'free_delivery_over' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $enabled = [
            'payment_cod_enabled' => $request->boolean('payment_cod_enabled'),
            'payment_bank_transfer_enabled' => $request->boolean('payment_bank_transfer_enabled'),
            'payment_pay_at_store_enabled' => $request->boolean('payment_pay_at_store_enabled'),
        ];

        if (! in_array(true, $enabled, true)) {
            return back()->withInput()->withErrors(['payment' => 'Keep at least one payment method switched on, otherwise customers cannot order.']);
        }
        if ($enabled['payment_bank_transfer_enabled'] && blank($data['bank_account_number'] ?? null)) {
            return back()->withInput()->withErrors(['bank_account_number' => 'Enter the bank account number so customers know where to pay.']);
        }
        if (! $enabled['payment_cod_enabled'] && ! $enabled['payment_bank_transfer_enabled']) {
            session()->flash('error', 'Note: with only "Pay at the store" switched on, customers can choose store pickup only, not delivery.');
        }

        Setting::write($enabled + [
            'bank_name' => $data['bank_name'] ?? '',
            'bank_account_name' => $data['bank_account_name'] ?? '',
            'bank_account_number' => $data['bank_account_number'] ?? '',
            'bank_branch' => $data['bank_branch'] ?? '',
            'bank_instructions' => $data['bank_instructions'] ?? '',
            'cod_max_order' => (float) ($data['cod_max_order'] ?? 0),
            'delivery_fee' => (float) $data['delivery_fee'],
            'free_delivery_over' => (float) ($data['free_delivery_over'] ?? 0),
        ]);

        return back()->with('status', 'Settings saved.');
    }
}
