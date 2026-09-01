<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class ResultSubmissionRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['club_id' => ['required', 'integer', 'exists:clubs,id'], 'home_goals' => ['required', 'integer', 'min:0', 'max:255'], 'away_goals' => ['required', 'integer', 'min:0', 'max:255'], 'comment' => ['nullable', 'string', 'max:500']]; } }
