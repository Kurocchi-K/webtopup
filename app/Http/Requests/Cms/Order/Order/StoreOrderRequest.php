<?php

namespace App\Http\Requests\Cms\Order\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ubah jadi true agar bisa dipakai
    }

    public function rules(): array
    {
        // Sesuaikan dengan data yang Abang butuhkan di form
        return [
            'product_id' => 'required',
            'user_id' => 'required',
            // tambahkan rules lain sesuai kebutuhan
        ];
    }
}
