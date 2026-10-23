<?php

namespace App\Http\Controllers\Admissions;

use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\Admissions\Queries\AdmissionOptionsQuery;
use App\Domain\Admissions\Queries\DocumentSettingsQuery;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Actions\UpdateAdmissionDocumentRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\UpdateDocumentRulesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionSettingsController extends Controller
{
    /**
     * Which documents admissions require. Anyone who can see admissions can
     * read the rules; changing them needs `settings.manage`.
     */
    public function show(Request $request, DocumentSettingsQuery $settings, AdmissionOptionsQuery $options): Response
    {
        Gate::authorize('admissions.view');

        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return Inertia::render('admissions/settings', [
            'documents' => $settings->get(),
            'abilities' => $options->abilities($identity),
        ]);
    }

    public function update(UpdateDocumentRulesRequest $request, UpdateAdmissionDocumentRules $update): RedirectResponse
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        $rules = [];

        foreach (AdmissionDocumentKind::cases() as $kind) {
            $row = $request->input("rules.{$kind->value}");

            if (! is_array($row) || ! filter_var($row['required'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $rules[$kind->value] = filter_var($row['all_classes'] ?? false, FILTER_VALIDATE_BOOLEAN)
                ? null
                : array_values(array_map('strval', (array) ($row['classes'] ?? [])));
        }

        $update->handle($identity, $rules);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Required documents saved.')]);

        return back();
    }
}
