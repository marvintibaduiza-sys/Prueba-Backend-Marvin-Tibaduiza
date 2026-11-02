<?php

namespace App\Http\Requests\User;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Returns the full policy response so a denial keeps its explanatory message.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('user'));
    }

    /**
     * Partial updates are allowed: only the fields sent are validated.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['sometimes', 'required', 'string', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'required', 'string', 'exists:roles,name'],
        ];
    }
}
