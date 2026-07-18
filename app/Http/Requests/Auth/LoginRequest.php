<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'login' => [
                'required_without:email',
                'string',
                'max:255',
            ],

            'email' => [
                'required_without:login',
                'email',
                'max:255',
            ],

            'password' => [
                'required',
                'string',
            ],

            'device_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
