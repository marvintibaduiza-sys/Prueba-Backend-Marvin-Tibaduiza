<?php

namespace App\Http\Requests\Role;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Keep the policy message in the 403 response.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->role());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:50', 'regex:'.StoreRoleRequest::NAME_FORMAT,
                Rule::unique('roles', 'name')->ignore($this->role()),
            ],
            'permissions' => ['sometimes', 'required', 'array', 'min:1'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreRoleRequest)->messages();
    }

    private function role(): Role
    {
        return $this->route('role');
    }
}
