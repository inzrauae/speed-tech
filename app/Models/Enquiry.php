<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enquiry extends Model
{
    public const TYPES = [
        'repair' => 'Repair booking',
        'part' => 'Spare part request',
        'product' => 'Product enquiry',
        'general' => 'General question',
    ];

    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'closed' => 'Closed',
    ];

    protected $fillable = [
        'reference', 'type', 'name', 'phone', 'email', 'device', 'service',
        'product_id', 'details', 'message', 'status', 'admin_notes', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    public static function generateReference(string $type): string
    {
        $prefix = ['repair' => 'RP', 'part' => 'SP', 'product' => 'PE'][$type] ?? 'EQ';
        do {
            $reference = $prefix.now()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /** The message the customer can send on WhatsApp to follow up. */
    public function whatsappMessage(): string
    {
        $lines = ["Hi SpeedTech, my reference is {$this->reference}."];
        $lines[] = match ($this->type) {
            'repair' => "Repair: {$this->service} for {$this->device}.",
            'part' => 'Spare part: '.collect($this->details ?? [])->only(['brand', 'model', 'generation', 'part'])->filter()->implode(' / ').'.',
            'product' => 'Product: '.($this->product?->name ?? $this->device).'.',
            default => 'I have a question.',
        };
        if ($this->message) {
            $lines[] = $this->message;
        }

        return implode("\n", $lines);
    }
}
