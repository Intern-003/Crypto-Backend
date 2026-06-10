<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGlideWebhookRequest extends FormRequest
{
    public function rules()
    {
        return [
            'webhookId' => 'required|string',
            'entityId' => 'required|string',
            'payload' => 'required|array'
        ];
    }
}
